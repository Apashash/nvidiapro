const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { requireAuth } = require('../middleware/auth');

router.get('/faq', requireAuth, async (req, res) => {
  try {
    const [plans] = await db.query(
      'SELECT nom, prix, rendement_journalier, duree_jours, bloque FROM planinvestissement ORDER BY id ASC'
    );
    res.render('faq', { plans });
  } catch (e) {
    console.error(e);
    res.render('faq', { plans: [] });
  }
});

router.get('/tuto', requireAuth, (req, res) => res.render('tuto'));

module.exports = router;
