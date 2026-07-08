const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { requireAuth } = require('../middleware/auth');

router.get('/compte', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  try {
    const [[user]] = await db.query('SELECT * FROM utilisateurs WHERE id = ?', [user_id]);
    const [[soldeRow]] = await db.query('SELECT solde FROM soldes WHERE user_id = ?', [user_id]);
    const [[piecesRow]] = await db.query('SELECT solde FROM pieces WHERE user_id = ?', [user_id]);
    const [[rev]] = await db.query('SELECT SUM(montant) as total FROM historique_revenus WHERE user_id = ?', [user_id]);
    const [[vip]] = await db.query('SELECT * FROM vip WHERE user_id = ?', [user_id]);

    const [transactions] = await db.query(`
      (SELECT 'depot' as type, montant, date_depot as date, statut FROM depots WHERE user_id = ?)
      UNION ALL
      (SELECT 'retrait' as type, montant, date_demande as date, statut FROM retraits WHERE user_id = ?)
      UNION ALL
      (SELECT 'revenu' as type, montant, date_paiement as date, 'valide' as statut FROM historique_revenus WHERE user_id = ?)
      ORDER BY date DESC LIMIT 20
    `, [user_id, user_id, user_id]);

    const [commandes] = await db.query("SELECT * FROM commandes WHERE user_id = ? AND date_fin >= CURRENT_DATE", [user_id]);
    const has_active_command = commandes.length > 0;

    let can_collect = false;
    let next_payment_info = 'Aucun paiement prévu';
    let earliest_payment_time = null;
    const now = Date.now();

    if (has_active_command) {
      for (const cmd of commandes) {
        const created = new Date(cmd.date_creation).getTime();
        const hoursSince = (now - created) / 3600000;
        if (hoursSince < 24) {
          const next = created + 24 * 3600000;
          if (!earliest_payment_time || next < earliest_payment_time) {
            earliest_payment_time = next;
            const d = new Date(next);
            next_payment_info = `Prochain paiement: ${d.getDate().toString().padStart(2,'0')}/${(d.getMonth()+1).toString().padStart(2,'0')} ${d.getHours().toString().padStart(2,'0')}:${d.getMinutes().toString().padStart(2,'0')}`;
          }
          continue;
        }
        const [[lp]] = await db.query(
          "SELECT MAX(date_paiement) as last_payment FROM historique_revenus WHERE user_id = ? AND commande_id = ? AND type = 'paiement_journalier'",
          [user_id, cmd.id]
        );
        if (lp.last_payment) {
          const lpTime = new Date(lp.last_payment).getTime();
          const diffH = (now - lpTime) / 3600000;
          if (diffH >= 24) { can_collect = true; break; }
          else {
            const next = lpTime + 24 * 3600000;
            if (!earliest_payment_time || next < earliest_payment_time) {
              earliest_payment_time = next;
              const d = new Date(next);
              next_payment_info = `Prochain paiement: ${d.getDate().toString().padStart(2,'0')}/${(d.getMonth()+1).toString().padStart(2,'0')} ${d.getHours().toString().padStart(2,'0')}:${d.getMinutes().toString().padStart(2,'0')}`;
            }
          }
        } else { can_collect = true; break; }
      }
    }

    const custom_id = user.code_parrainage;
    const solde = soldeRow ? soldeRow.solde : 0;
    const pieces = piecesRow ? piecesRow.solde : 0;

    res.render('compte', {
      user, solde, pieces, revenus: rev, vip, transactions,
      has_active_command, can_collect, next_payment_info, custom_id,
      success: req.session.success_compte || null,
      error: req.session.error_compte || null,
    });
    delete req.session.success_compte;
    delete req.session.error_compte;
  } catch (e) {
    console.error(e);
    res.redirect('/');
  }
});

router.get('/historique', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  try {
    const [[user]] = await db.query('SELECT * FROM utilisateurs WHERE id = ?', [user_id]);

    const [transactions] = await db.query(`
      (SELECT 'depot' as type, montant, date_depot as date, statut FROM depots WHERE user_id = ?)
      UNION ALL
      (SELECT 'retrait' as type, montant, date_demande as date, statut FROM retraits WHERE user_id = ?)
      UNION ALL
      (SELECT 'revenu' as type, montant, date_paiement as date, 'valide' as statut FROM historique_revenus WHERE user_id = ?)
      ORDER BY date DESC
    `, [user_id, user_id, user_id]);

    res.render('historique', { user, transactions });
  } catch (e) {
    console.error(e);
    res.redirect('/compte');
  }
});

// Collect daily salary (salaire journalier)
router.post('/compte/collecter', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  try {
    const [commandes] = await db.query(
      "SELECT * FROM commandes WHERE user_id = ? AND date_fin >= CURRENT_DATE AND statut = 'actif'",
      [user_id]
    );
    const now = Date.now();
    let total_gain = 0;
    for (const cmd of commandes) {
      const created = new Date(cmd.date_creation).getTime();
      const hoursSince = (now - created) / 3600000;
      if (hoursSince < 24) continue;

      const [[lp]] = await db.query(
        "SELECT MAX(date_paiement) as last_payment FROM historique_revenus WHERE user_id = ? AND commande_id = ? AND type = 'paiement_journalier'",
        [user_id, cmd.id]
      );
      let canCollect = false;
      if (lp.last_payment) {
        const diff = (now - new Date(lp.last_payment).getTime()) / 3600000;
        if (diff >= 24) canCollect = true;
      } else canCollect = true;

      if (canCollect) {
        total_gain += parseFloat(cmd.gain_journalier);
        await db.query(
          "INSERT INTO historique_revenus (user_id, commande_id, montant, type) VALUES (?, ?, ?, 'paiement_journalier')",
          [user_id, cmd.id, cmd.gain_journalier]
        );
      }
    }
    if (total_gain > 0) {
      await db.query('UPDATE soldes SET solde = solde + ? WHERE user_id = ?', [total_gain, user_id]);
      return res.json({ success: true, message: `Vous avez collecté ${total_gain.toFixed(2)} FCFA !` });
    } else {
      return res.json({ success: false, message: 'Aucun paiement disponible pour le moment.' });
    }
  } catch (e) {
    console.error(e);
    return res.json({ success: false, message: 'Erreur lors de la collecte.' });
  }
});

module.exports = router;
