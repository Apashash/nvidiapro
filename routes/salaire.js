const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { requireAuth } = require('../middleware/auth');

const salaires_par_niveau = { 0: 0, 1: 700, 2: 1200, 3: 4000, 4: 5000, 5: 7500 };

router.get('/salaire', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  try {
    const [[user]] = await db.query(`
      SELECT u.*, v.niveau, v.pourcentage, v.invitations_requises, v.invitations_actuelles
      FROM utilisateurs u LEFT JOIN vip v ON u.id = v.user_id WHERE u.id = ?`, [user_id]);

    const niveau_actuel = user.niveau || 0;
    const salaire_actuel = salaires_par_niveau[niveau_actuel] || 0;
    const invitations_restantes = Math.max(0, (user.invitations_requises || 3) - (user.invitations_actuelles || 0));

    const message_erreur = req.session.salaire_error || null;
    const message_blacklist = req.session.salaire_blacklist || false;
    delete req.session.salaire_error;
    delete req.session.salaire_blacklist;

    res.render('salaire', { user, niveau_actuel, salaire_actuel, salaires_par_niveau, invitations_restantes, message_erreur, message_blacklist });
  } catch (e) { console.error(e); res.redirect('/'); }
});

router.post('/salaire', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  const [[user]] = await db.query(`SELECT v.niveau FROM vip v WHERE v.user_id = ?`, [user_id]);
  const niveau = user ? (user.niveau || 0) : 0;
  if (niveau === 0) {
    req.session.salaire_error = 'Vous devez atteindre le niveau VIP 1 pour recevoir un salaire.';
  } else {
    req.session.salaire_blacklist = true;
  }
  res.redirect('/salaire');
});

module.exports = router;
