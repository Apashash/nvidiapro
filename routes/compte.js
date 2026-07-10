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
    // Filleuls directs ayant au moins une commande active (date_fin >= aujourd'hui)
    const [[filleulRow]] = await db.query(`
      SELECT COUNT(DISTINCT u.id) AS cnt
      FROM utilisateurs u
      WHERE u.parrain_id = ?
        AND EXISTS (
          SELECT 1 FROM commandes c
          WHERE c.user_id = u.id AND c.date_fin >= CURRENT_DATE
        )
    `, [user_id]);
    const filleuls_actifs = parseInt(filleulRow ? filleulRow.cnt : 0) || 0;

    // 3 filleuls actifs = 1 niveau VIP
    const MAX_VIP = 10;
    const niveau_vip = Math.min(MAX_VIP, Math.floor(filleuls_actifs / 3));
    const inv_actuelles = filleuls_actifs % 3;
    const inv_requises  = niveau_vip >= MAX_VIP ? 0 : (3 - inv_actuelles);
    const pourcentage   = niveau_vip >= MAX_VIP ? 100 : Math.round(inv_actuelles / 3 * 100);

    // Upsert dans la table vip
    const [[existVip]] = await db.query('SELECT id FROM vip WHERE user_id = ?', [user_id]);
    if (existVip) {
      await db.query(
        'UPDATE vip SET niveau=?, pourcentage=?, invitations_actuelles=?, invitations_requises=? WHERE user_id=?',
        [niveau_vip, pourcentage, inv_actuelles, inv_requises, user_id]
      );
    } else {
      await db.query(
        'INSERT INTO vip (user_id, niveau, pourcentage, invitations_actuelles, invitations_requises) VALUES (?,?,?,?,?)',
        [user_id, niveau_vip, pourcentage, inv_actuelles, inv_requises]
      );
    }
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

    const PAGE_SIZE = 30;
    const page = Math.max(1, parseInt(req.query.page) || 1);
    const offset = (page - 1) * PAGE_SIZE;

    const unionQuery = `
      (SELECT 'depot' as type, montant, date_depot as date, statut FROM depots WHERE user_id = ?)
      UNION ALL
      (SELECT 'retrait' as type, montant, date_demande as date, statut FROM retraits WHERE user_id = ?)
      UNION ALL
      (SELECT 'revenu' as type, montant, date_paiement as date, 'valide' as statut FROM historique_revenus WHERE user_id = ?)
    `;

    const [[countRow]] = await db.query(
      `SELECT COUNT(*)::int as total FROM (${unionQuery}) as t`,
      [user_id, user_id, user_id]
    );
    const total = countRow ? countRow.total : 0;
    const total_pages = Math.max(1, Math.ceil(total / PAGE_SIZE));

    const [transactions] = await db.query(
      `${unionQuery} ORDER BY date DESC LIMIT ? OFFSET ?`,
      [user_id, user_id, user_id, PAGE_SIZE, offset]
    );

    res.render('historique', { user, transactions, page, total_pages, total });
  } catch (e) {
    console.error(e);
    res.redirect('/compte');
  }
});

// Collect daily salary (salaire journalier). Gains are also credited
// automatically every 24h by services/autoPayout.js; this endpoint shares
// the same row-locked payout function so the two paths can never double-credit.
router.post('/compte/collecter', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  try {
    const { payDueCommandesForUser } = require('../services/autoPayout');
    const total_gain = await payDueCommandesForUser(user_id);
    if (total_gain > 0) {
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
