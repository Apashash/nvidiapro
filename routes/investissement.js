const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { requireAuth } = require('../middleware/auth');
const {
  parseStrategy,
  rateForAmount,
  lowestRate,
  highestRate,
} = require('../services/investmentStrategy');

const MAX_PURCHASES_PER_PLAN = 3;

router.get('/investissement', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  try {
    const [[soldeRow]] = await db.query('SELECT solde FROM soldes WHERE user_id = ?', [user_id]);
    const solde = soldeRow ? soldeRow.solde : 0;

    const menu_actif = ['vip', 'commande'].includes(req.query.menu) ? req.query.menu : 'vip';

    const [plans] = await db.query(
      'SELECT *, COALESCE(bloque, false) as bloque FROM planinvestissement WHERE prix_action IS NOT NULL ORDER BY id ASC'
    );
    const plans_corriges = [];
    const seenIds = new Set();
    for (const plan of plans) {
      if (!seenIds.has(plan.id)) {
        seenIds.add(plan.id);
        plan.prix_action = parseFloat(plan.prix_action);
        plan.actions_minimum = Math.max(1, parseInt(plan.actions_minimum, 10) || 1);
        plan.prix = plan.prix_action * plan.actions_minimum;
        plan.strategie = parseStrategy(plan.strategie_json);
        plan.taux_minimum = lowestRate(plan.strategie);
        plan.taux_maximum = highestRate(plan.strategie);
        plan.revenu_journalier = (plan.prix * rateForAmount(plan.prix, plan.strategie)) / 100;
        plan.revenu_total = plan.revenu_journalier * plan.duree_jours;
        plan.minimum_montant = plan.prix;
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

    const [purchaseCountsRows] = await db.query(
      'SELECT plan_id, COUNT(*)::int as total FROM commandes WHERE user_id = ? GROUP BY plan_id',
      [user_id]
    );
    const achatsParPlan = Object.fromEntries(
      purchaseCountsRows.map((row) => [row.plan_id, Number(row.total) || 0])
    );

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

    res.render('investissement', {
      solde,
      menu_actif,
      plans: plans_corriges,
      commandes,
      achatsParPlan,
      maxAchatsParPlan: MAX_PURCHASES_PER_PLAN,
    });
  } catch (e) {
    console.error(e);
    res.redirect('/');
  }
});

router.get('/investissement/action/:id', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  const plan_id = parseInt(req.params.id, 10);

  try {
    const [[soldeRow]] = await db.query('SELECT solde FROM soldes WHERE user_id = ?', [user_id]);
    const [[plan]] = await db.query(
      'SELECT * FROM planinvestissement WHERE id = ? AND prix_action IS NOT NULL',
      [plan_id]
    );

    if (!plan || plan.bloque) return res.redirect('/investissement');

    const [[purchaseCount]] = await db.query(
      'SELECT COUNT(*)::int as total FROM commandes WHERE user_id = ? AND plan_id = ?',
      [user_id, plan_id]
    );
    const achatsEffectues = Number(purchaseCount?.total) || 0;
    const actionsMinimum = Math.max(1, parseInt(plan.actions_minimum, 10) || 1);
    const prixAction = parseFloat(plan.prix_action);

    plan.prix_action = prixAction;
    plan.actions_minimum = actionsMinimum;
    plan.prix = Math.round(prixAction * actionsMinimum * 100) / 100;
    plan.strategie = parseStrategy(plan.strategie_json);
    plan.taux_minimum = lowestRate(plan.strategie);
    plan.taux_maximum = highestRate(plan.strategie);

    res.render('investissement-action', {
      plan,
      solde: soldeRow ? parseFloat(soldeRow.solde) : 0,
      achatsEffectues,
      achatsRestants: Math.max(0, MAX_PURCHASES_PER_PLAN - achatsEffectues),
      maxAchatsParPlan: MAX_PURCHASES_PER_PLAN,
    });
  } catch (e) {
    console.error(e);
    res.redirect('/investissement');
  }
});

router.post('/acheter-action', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  const plan_id = parseInt(req.body.plan_id);

  try {
    const [[plan]] = await db.query('SELECT * FROM planinvestissement WHERE id = ?', [plan_id]);
    if (!plan) return res.json({ success: false, message: 'Plan introuvable' });
    if (plan.bloque) return res.json({ success: false, message: "Ce plan n'est pas encore disponible, il sera bientôt disponible dans le marché ! Profitez des plans actifs actuellement." });
    const isVariableSharePlan = plan.prix_action != null;
    let nombreActions = 1;
    let montant;
    let rendementJournalier;

    if (isVariableSharePlan) {
      const rawActions = String(req.body.actions ?? '').trim().replace(',', '.');
      const requestedActions = Number(rawActions);
      const actionsMinimum = Math.max(1, parseInt(plan.actions_minimum, 10) || 1);
      const prixAction = parseFloat(plan.prix_action);

      if (!/^\d+(?:\.\d{1,4})?$/.test(rawActions)
          || !Number.isFinite(requestedActions)
          || requestedActions < actionsMinimum) {
        return res.json({
          success: false,
          message: `Saisissez au moins ${actionsMinimum} actions, avec au maximum 4 décimales.`,
        });
      }

      nombreActions = Math.round(requestedActions * 10000) / 10000;
      montant = Math.round(prixAction * nombreActions * 100) / 100;
      rendementJournalier = rateForAmount(montant, plan.strategie_json);
    } else {
      montant = parseFloat(plan.prix);
      rendementJournalier = parseFloat(plan.rendement_journalier);
    }

    const [[soldeRow]] = await db.query('SELECT solde FROM soldes WHERE user_id = ?', [user_id]);
    const solde = soldeRow ? parseFloat(soldeRow.solde) : 0;
    if (solde < montant) return res.json({ success: false, message: 'Solde insuffisant' });

    const gain_journalier = Math.round(montant * (rendementJournalier / 100) * 100) / 100;
    const duree = parseInt(plan.duree_jours);

    const conn = await db.getConnection();
    try {
      await conn.beginTransaction();

      // Lock the balance row so two simultaneous purchases cannot both pass
      // the balance and three-purchases-per-plan checks.
      const [[lockedBalance]] = await conn.query(
        'SELECT solde FROM soldes WHERE user_id = ? FOR UPDATE',
        [user_id]
      );
      const soldeVerifie = lockedBalance ? parseFloat(lockedBalance.solde) : 0;
      if (soldeVerifie < montant) {
        await conn.rollback();
        return res.json({ success: false, message: 'Solde insuffisant' });
      }

      const [[purchaseCount]] = await conn.query(
        'SELECT COUNT(*)::int as total FROM commandes WHERE user_id = ? AND plan_id = ?',
        [user_id, plan_id]
      );
      if (Number(purchaseCount.total) >= MAX_PURCHASES_PER_PLAN) {
        await conn.rollback();
        return res.json({
          success: false,
          limit_reached: true,
          message: `Ce plan peut être acheté au maximum ${MAX_PURCHASES_PER_PLAN} fois.`,
        });
      }

      await conn.query(
        "INSERT INTO commandes (user_id, plan_id, montant, gain_journalier, nombre_actions, date_debut, date_fin) VALUES (?, ?, ?, ?, ?, NOW() + INTERVAL '7 hours', NOW() + INTERVAL '7 hours' + (? || ' days')::INTERVAL)",
        [user_id, plan_id, montant, gain_journalier, nombreActions, duree]
      );
      await conn.query('UPDATE soldes SET solde = solde - ? WHERE user_id = ?', [montant, user_id]);

      await conn.commit();
      res.json({
        success: true,
        plan_name: plan.nom,
        montant,
        nombre_actions: nombreActions,
        rendement_journalier: rendementJournalier,
        gain_journalier,
        duree,
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
