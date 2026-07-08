const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { requireAuth } = require('../middleware/auth');

router.get('/cadeau', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  try {
    const [[user]] = await db.query('SELECT u.*, s.solde FROM utilisateurs u LEFT JOIN soldes s ON u.id = s.user_id WHERE u.id = ?', [user_id]);
    const [[vip]] = await db.query('SELECT * FROM vip WHERE user_id = ?', [user_id]);
    const error = req.session.cadeau_error || null;
    const success = req.session.cadeau_success || null;
    delete req.session.cadeau_error;
    delete req.session.cadeau_success;

    // Filleuls actifs (ayant investi) et inscrits
    const [[{ filleuls_actifs }]] = await db.query(
      `SELECT COUNT(DISTINCT c.user_id) AS filleuls_actifs
       FROM utilisateurs u2
       LEFT JOIN commandes c ON c.user_id = u2.id AND c.statut = 'actif'
       WHERE u2.parrain_id = ?`, [user_id]);
    const [[{ filleuls_inscrits }]] = await db.query(
      `SELECT COUNT(*) AS filleuls_inscrits FROM utilisateurs WHERE parrain_id = ?`, [user_id]);

    const est_eligible = filleuls_actifs >= 10 || filleuls_inscrits >= 50;
    const message_eligibilite = `Pour créer vos propres codes cadeau, vous devez avoir soit 10 filleuls actifs (ayant investi), soit 50 filleuls inscrits. Actuellement : ${filleuls_actifs} actifs / ${filleuls_inscrits} inscrits.`;

    res.render('cadeau', { user, vip, error, success, filleuls_actifs, filleuls_inscrits, est_eligible, message_eligibilite });
  } catch (e) { console.error(e); res.redirect('/'); }
});

router.post('/cadeau', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  const code = (req.body.code || '').trim().toUpperCase();
  if (!code) {
    req.session.cadeau_error = 'Veuillez entrer un code cadeau.';
    return res.redirect('/cadeau');
  }
  try {
    const [[existing]] = await db.query('SELECT * FROM codes_utilises WHERE user_id = ? AND code = ?', [user_id, code]);
    if (existing) {
      req.session.cadeau_error = 'Vous avez déjà utilisé ce code.';
      return res.redirect('/cadeau');
    }
    // Codes gérés manuellement - exemple de validation simple
    const validCodes = { 'ALTIORA2026': 500, 'BONUS100': 100, 'WELCOME250': 250 };
    const montant = validCodes[code];
    if (!montant) {
      req.session.cadeau_error = 'Code cadeau invalide ou expiré.';
      return res.redirect('/cadeau');
    }
    const conn = await db.getConnection();
    try {
      await conn.beginTransaction();
      await conn.query('UPDATE soldes SET solde = solde + ? WHERE user_id = ?', [montant, user_id]);
      await conn.query('INSERT INTO codes_utilises (user_id, code, montant) VALUES (?, ?, ?)', [user_id, code, montant]);
      await conn.query("INSERT INTO historique_revenus (user_id, montant, type) VALUES (?, ?, 'bonus')", [user_id, montant]);
      await conn.commit();
      req.session.cadeau_success = `Code validé ! Vous avez reçu ${montant} FCFA.`;
    } catch (e) { await conn.rollback(); throw e; } finally { conn.release(); }
    res.redirect('/cadeau');
  } catch (e) {
    req.session.cadeau_error = 'Erreur: ' + e.message;
    res.redirect('/cadeau');
  }
});

module.exports = router;
