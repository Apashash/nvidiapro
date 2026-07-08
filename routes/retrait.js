const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { requireAuth } = require('../middleware/auth');
const nodemailer = require('nodemailer');

router.get('/retrait', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  try {
    const [[user]] = await db.query('SELECT * FROM utilisateurs WHERE id = ?', [user_id]);
    const [[soldeRow]] = await db.query('SELECT solde FROM soldes WHERE user_id = ?', [user_id]);
    const solde = soldeRow ? parseFloat(soldeRow.solde) : 0;

    const hGmt = parseInt(new Date().toUTCString().split(' ')[4].split(':')[0]);
    const day = new Date().getUTCDay(); // 0=Sun, 6=Sat
    const retraits_disponibles = day >= 1 && day <= 6 && hGmt >= 9 && hGmt < 19;

    const message = req.session.retrait_message || null;
    delete req.session.retrait_message;

    res.render('retrait', { user, solde, retraits_disponibles, message });
  } catch (e) {
    console.error(e);
    res.redirect('/');
  }
});

router.post('/retrait', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  try {
    const [[soldeRow]] = await db.query('SELECT solde FROM soldes WHERE user_id = ?', [user_id]);
    const solde = soldeRow ? parseFloat(soldeRow.solde) : 0;

    const hGmt = parseInt(new Date().toUTCString().split(' ')[4].split(':')[0]);
    const day = new Date().getUTCDay();
    const retraits_disponibles = day >= 1 && day <= 6 && hGmt >= 9 && hGmt < 19;

    if (!retraits_disponibles)
      return res.json({ success: false, message: 'Les retraits sont disponibles du lundi au samedi de 9h à 19h GMT.' });

    const [[cmds]] = await db.query("SELECT COUNT(*)::int as nb FROM commandes WHERE user_id = ? AND date_fin >= CURRENT_DATE", [user_id]);
    if (Number(cmds.nb) === 0)
      return res.json({ success: false, message: "Vous devez avoir au moins un plan d'investissement actif pour effectuer un retrait." });

    const [[recents]] = await db.query(
      "SELECT COUNT(*)::int as nb FROM retraits WHERE user_id = ? AND statut IN ('en_attente', 'valide') AND date_demande >= NOW() - INTERVAL '24 hours'",
      [user_id]
    );
    if (Number(recents.nb) > 0)
      return res.json({ success: false, message: "Vous ne pouvez effectuer qu'un seul retrait toutes les 24 heures." });

    const montant   = parseFloat(req.body.montant);
    const numero    = (req.body.numero || '').trim();
    const nom       = (req.body.nom || '').trim();
    const operateur = (req.body.operateur || '').trim();
    const pays      = (req.body.pays || '').trim();

    if (!montant || !numero || !nom || !operateur || !pays)
      return res.json({ success: false, message: 'Veuillez remplir tous les champs.' });
    if (montant < 1200)
      return res.json({ success: false, message: 'Le montant minimum de retrait est de 1 200 FCFA.' });
    if (montant > solde)
      return res.json({ success: false, message: 'Solde insuffisant.' });

    const methode = `${operateur} (${pays})`;

    const conn = await db.getConnection();
    try {
      await conn.beginTransaction();
      await conn.query('UPDATE soldes SET solde = solde - ? WHERE user_id = ?', [montant, user_id]);
      await conn.query(
        "INSERT INTO retraits (user_id, montant, methode, numero_compte, statut) VALUES (?, ?, ?, ?, 'en_attente')",
        [user_id, montant, methode, numero]
      );
      await conn.commit();
    } catch (e) { await conn.rollback(); throw e; } finally { conn.release(); }

    res.json({ success: true });
  } catch (e) {
    console.error(e);
    res.json({ success: false, message: 'Erreur serveur: ' + e.message });
  }
});

module.exports = router;
