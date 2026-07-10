const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { requireAuth } = require('../middleware/auth');
const { getParams } = require('../services/params');

router.get('/investissement', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  try {
    const [[soldeRow]] = await db.query('SELECT solde FROM soldes WHERE user_id = ?', [user_id]);
    const solde = soldeRow ? soldeRow.solde : 0;

    const menu_actif = ['vip', 'commande'].includes(req.query.menu) ? req.query.menu : 'vip';

    const [plans] = await db.query('SELECT *, COALESCE(bloque, false) as bloque FROM planinvestissement ORDER BY id ASC');
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

    const [commandesRows] = await db.query(`
      SELECT c.*, p.nom as plan_nom, p.image_url as plan_image
      FROM commandes c
      JOIN planinvestissement p ON p.id = c.plan_id
      WHERE c.user_id = ? AND c.date_fin >= CURRENT_DATE AND c.statut = 'actif'
      ORDER BY c.date_creation DESC
    `, [user_id]);

    const commandes = [];
    for (const cmd of commandesRows) {
      const [[lp]] = await db.query(
        "SELECT MAX(date_paiement) as last_payment FROM historique_revenus WHERE user_id = ? AND commande_id = ? AND type = 'paiement_journalier'",
        [user_id, cmd.id]
      );
      const cycleStart = lp.last_payment ? new Date(lp.last_payment) : new Date(cmd.date_creation);
      const cycleEnd = new Date(cycleStart.getTime() + 24 * 3600000);
      commandes.push({
        ...cmd,
        cycle_start_ms: cycleStart.getTime(),
        cycle_end_ms: cycleEnd.getTime(),
      });
    }

    res.render('investissement', { solde, menu_actif, plans: plans_corriges, commandes });
  } catch (e) {
    console.error(e);
    res.redirect('/');
  }
});

router.post('/acheter-action', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  const plan_id = parseInt(req.body.plan_id);

  try {
    const params = await getParams();
    const comm1 = parseFloat(params.commission_niveau1 ?? 20) / 100;
    const comm2 = parseFloat(params.commission_niveau2 ?? 10) / 100;
    const comm3 = parseFloat(params.commission_niveau3 ?? 5)  / 100;

    const [[plan]] = await db.query('SELECT * FROM planinvestissement WHERE id = ?', [plan_id]);
    if (!plan) return res.json({ success: false, message: 'Plan introuvable' });
    if (plan.bloque) return res.json({ success: false, message: "Ce plan n'est pas encore disponible, il sera bientôt disponible dans le marché ! Profitez des plans actifs actuellement." });
    const montant = parseFloat(plan.prix);

    const [[soldeRow]] = await db.query('SELECT solde FROM soldes WHERE user_id = ?', [user_id]);
    const solde = soldeRow ? parseFloat(soldeRow.solde) : 0;
    if (solde < montant) return res.json({ success: false, message: 'Solde insuffisant' });

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

      // ── Commissions parrainage (niveaux 1, 2, 3) ────────────────────────────
      async function creditParrain(parrainId, taux, niveau) {
        if (!parrainId || taux <= 0) return;
        const bonus = Math.round(montant * taux * 100) / 100;
        if (bonus <= 0) return;
        const [[ps]] = await conn.query('SELECT id FROM soldes WHERE user_id = ?', [parrainId]);
        if (ps) {
          await conn.query('UPDATE soldes SET solde = solde + ? WHERE user_id = ?', [bonus, parrainId]);
        } else {
          await conn.query('INSERT INTO soldes (user_id, solde) VALUES (?, ?)', [parrainId, bonus]);
        }
        await conn.query(
          "INSERT INTO historique_revenus (user_id, montant, type) VALUES (?, ?, 'parrainage')",
          [parrainId, bonus]
        ).catch(() => {});
      }

      const [[u1]] = await conn.query('SELECT parrain_id FROM utilisateurs WHERE id = ?', [user_id]);
      const parrain1 = u1?.parrain_id;
      await creditParrain(parrain1, comm1, 1);

      if (parrain1) {
        const [[u2]] = await conn.query('SELECT parrain_id FROM utilisateurs WHERE id = ?', [parrain1]);
        const parrain2 = u2?.parrain_id;
        await creditParrain(parrain2, comm2, 2);

        if (parrain2) {
          const [[u3]] = await conn.query('SELECT parrain_id FROM utilisateurs WHERE id = ?', [parrain2]);
          await creditParrain(u3?.parrain_id, comm3, 3);
        }
      }

      await conn.commit();
      res.json({ success: true, plan_name: plan.nom, montant, gain_journalier, duree });
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
