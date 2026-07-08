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
    const [[user]] = await db.query('SELECT * FROM utilisateurs WHERE id = ?', [user_id]);
    const [[soldeRow]] = await db.query('SELECT solde FROM soldes WHERE user_id = ?', [user_id]);
    const solde = soldeRow ? parseFloat(soldeRow.solde) : 0;

    const hGmt = parseInt(new Date().toUTCString().split(' ')[4].split(':')[0]);
    const day = new Date().getUTCDay();
    const retraits_disponibles = day >= 1 && day <= 6 && hGmt >= 9 && hGmt < 19;

    const erreurs = [];
    if (!retraits_disponibles) erreurs.push('Les retraits sont disponibles du lundi au samedi de 9h à 19h GMT.');
    else {
      const [[cmds]] = await db.query("SELECT COUNT(*)::int as nb FROM commandes WHERE user_id = ? AND date_fin >= CURRENT_DATE", [user_id]);
      if (Number(cmds.nb) === 0) erreurs.push("Vous devez avoir au moins un plan d'investissement en cours pour effectuer un retrait.");

      const [[deps]] = await db.query("SELECT COUNT(*)::int as nb FROM depots WHERE user_id = ? AND statut = 'valide'", [user_id]);
      if (Number(deps.nb) === 0) erreurs.push("Vous devez avoir effectué au moins un dépôt validé pour effectuer un retrait.");

      const [[recents]] = await db.query(
        "SELECT COUNT(*)::int as nb FROM retraits WHERE user_id = ? AND statut IN ('en_attente', 'valide') AND date_demande >= NOW() - INTERVAL '24 hours'",
        [user_id]
      );
      if (Number(recents.nb) > 0) erreurs.push("Vous ne pouvez effectuer qu'un seul retrait toutes les 24 heures.");
    }

    if (erreurs.length) {
      const msg = `<div class='notification error'>${erreurs[0]}</div>`;
      return res.render('retrait', { user, solde, retraits_disponibles, message: msg });
    }

    const montant = parseFloat(req.body.montant);

    if (!montant) {
      return res.render('retrait', { user, solde, retraits_disponibles, message: "<div class='notification error'>Veuillez remplir tous les champs.</div>" });
    }
    if (montant > solde) {
      return res.render('retrait', { user, solde, retraits_disponibles, message: "<div class='notification error'>Solde insuffisant.</div>" });
    }
    if (montant < 1200) {
      return res.render('retrait', { user, solde, retraits_disponibles, message: "<div class='notification error'>Le montant minimum de retrait est de 1 200 XOF.</div>" });
    }

    const conn = await db.getConnection();
    try {
      await conn.beginTransaction();
      await conn.query('UPDATE soldes SET solde = solde - ? WHERE user_id = ?', [montant, user_id]);
      await conn.query(
        "INSERT INTO retraits (user_id, montant, methode, numero_compte, statut) VALUES (?, ?, ?, ?, 'en_attente')",
        [user_id, montant, 'Mobile Money', user.telephone]
      );
      await conn.commit();
    } catch (e) { await conn.rollback(); throw e; } finally { conn.release(); }

    req.session.retrait_message = "<div class='notification success'>Votre demande de retrait a été soumise avec succès. Elle sera traitée dans les 24h.</div>";
    res.redirect('/retrait');
  } catch (e) {
    console.error(e);
    req.session.retrait_message = `<div class='notification error'>Erreur: ${e.message}</div>`;
    res.redirect('/retrait');
  }
});

module.exports = router;
