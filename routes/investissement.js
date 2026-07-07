const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { requireAuth } = require('../middleware/auth');

router.get('/investissement', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  try {
    const [[soldeRow]] = await db.query('SELECT solde FROM soldes WHERE user_id = ?', [user_id]);
    const solde = soldeRow ? soldeRow.solde : 0;

    const menu_actif = ['vip', 'commande'].includes(req.query.menu) ? req.query.menu : 'vip';

    const [plans] = await db.query('SELECT * FROM planinvestissement ORDER BY prix ASC');
    const plans_corriges = [];
    const seenIds = new Set();
    for (const plan of plans) {
      if (!seenIds.has(plan.id)) {
        seenIds.add(plan.id);
        plan.revenu_journalier = (plan.prix * plan.rendement_journalier) / 100;
        plan.revenu_total = plan.revenu_journalier * plan.duree_jours;
        plans_corriges.push(plan);
      }
    }

    res.render('investissement', { solde, menu_actif, plans: plans_corriges });
  } catch (e) {
    console.error(e);
    res.redirect('/');
  }
});

router.post('/acheter-action', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  const plan_id = parseInt(req.body.plan_id);
  const montant = parseFloat(req.body.prix);

  try {
    const [[plan]] = await db.query('SELECT * FROM planinvestissement WHERE id = ?', [plan_id]);
    if (!plan) return res.redirect('/investissement');

    const [[soldeRow]] = await db.query('SELECT solde FROM soldes WHERE user_id = ?', [user_id]);
    const solde = soldeRow ? parseFloat(soldeRow.solde) : 0;
    if (solde < montant) return res.redirect('/depot');

    const gain_journalier = plan.prix * (plan.rendement_journalier / 100);
    const duree = parseInt(plan.duree_jours);

    const conn = await db.getConnection();
    try {
      await conn.beginTransaction();
      await conn.query(
        "INSERT INTO commandes (user_id, plan_id, montant, gain_journalier, date_debut, date_fin) VALUES (?, ?, ?, ?, NOW() + INTERVAL '7 hours', NOW() + INTERVAL '7 hours' + (? || ' days')::INTERVAL)",
        [user_id, plan_id, montant, gain_journalier, duree]
      );
      await conn.query('UPDATE soldes SET solde = solde - ? WHERE user_id = ?', [montant, user_id]);

      // Parrain bonus
      const [[parrain]] = await conn.query('SELECT parrain_id FROM utilisateurs WHERE id = ?', [user_id]);
      if (parrain && parrain.parrain_id) {
        const bonus = Math.round(montant * 0.20 * 100) / 100;
        const [[pSolde]] = await conn.query('SELECT solde FROM soldes WHERE user_id = ?', [parrain.parrain_id]);
        if (!pSolde) {
          await conn.query('INSERT INTO soldes (user_id, solde) VALUES (?, ?)', [parrain.parrain_id, bonus]);
        } else {
          await conn.query('UPDATE soldes SET solde = solde + ? WHERE user_id = ?', [bonus, parrain.parrain_id]);
        }
        // Log parrain bonus
        await conn.query(
          "INSERT INTO historique_revenus (user_id, montant, type) VALUES (?, ?, 'parrainage')",
          [parrain.parrain_id, bonus]
        ).catch(() => {});
      }
      await conn.commit();

      const [[dates]] = await conn.query(
        'SELECT date_debut, date_fin FROM commandes WHERE user_id = ? ORDER BY id DESC LIMIT 1',
        [user_id]
      );

      res.render('acheter_action', {
        plan_name: plan.nom,
        montant,
        plan_img: plan.image_url,
        gain_journalier,
        duree: parseInt(plan.duree_jours),
        date_debut: dates.date_debut,
        date_fin: dates.date_fin,
        plan_desc: plan.description,
        user_id,
      });
    } catch (e) {
      await conn.rollback();
      throw e;
    } finally {
      conn.release();
    }
  } catch (e) {
    console.error(e);
    res.redirect('/investissement');
  }
});

module.exports = router;
