const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { requireAuth } = require('../middleware/auth');
const { DEFAULT_TIERS } = require('../services/investmentStrategy');

const MAX_PURCHASES_PER_PLAN = 3;

router.get('/faq', requireAuth, async (req, res) => {
  try {
    const [plans] = await db.query(
      `SELECT nom, prix, prix_action, actions_minimum, rendement_journalier,
              duree_jours, bloque
       FROM planinvestissement
       WHERE COALESCE(bloque, false) = false AND prix_action IS NOT NULL
       ORDER BY id ASC`
    );
    res.render('faq', {
      plans,
      strategyTiers: DEFAULT_TIERS,
      maxAchatsParPlan: MAX_PURCHASES_PER_PLAN,
    });
  } catch (e) {
    console.error(e);
    res.render('faq', {
      plans: [],
      strategyTiers: DEFAULT_TIERS,
      maxAchatsParPlan: MAX_PURCHASES_PER_PLAN,
    });
  }
});

router.get('/tuto', requireAuth, async (req, res) => {
  try {
    const [plans] = await db.query(
      `SELECT nom, prix, prix_action, actions_minimum, rendement_journalier,
              duree_jours
       FROM planinvestissement
       WHERE COALESCE(bloque, false) = false AND prix_action IS NOT NULL
       ORDER BY id ASC`
    );
    res.render('tuto', {
      plans,
      strategyTiers: DEFAULT_TIERS,
      maxAchatsParPlan: MAX_PURCHASES_PER_PLAN,
    });
  } catch (e) {
    console.error(e);
    res.render('tuto', {
      plans: [],
      strategyTiers: DEFAULT_TIERS,
      maxAchatsParPlan: MAX_PURCHASES_PER_PLAN,
    });
  }
});

module.exports = router;
