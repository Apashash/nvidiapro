const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { requireAuth } = require('../middleware/auth');
const multer = require('multer');
const path = require('path');

const storage = multer.diskStorage({
  destination: 'uploads/',
  filename: (req, file, cb) => {
    const ext = path.extname(file.originalname);
    cb(null, Date.now() + '-' + Math.random().toString(36).slice(2) + ext);
  },
});
const upload = multer({
  storage,
  limits: { fileSize: 5 * 1024 * 1024 },
  fileFilter: (req, file, cb) => {
    const allowed = ['image/jpeg', 'image/jpg', 'image/png'];
    cb(null, allowed.includes(file.mimetype));
  },
});

router.get('/', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  try {
    const [[user]] = await db.query(
      'SELECT u.*, s.solde FROM utilisateurs u LEFT JOIN soldes s ON u.id = s.user_id WHERE u.id = ?',
      [user_id]
    );
    if (!user) return res.redirect('/connexion');

    const [[rev]] = await db.query(
      'SELECT SUM(montant) as total FROM historique_revenus WHERE user_id = ?',
      [user_id]
    );

    const [posts] = await db.query(
      "SELECT p.*, u.nom FROM posts p LEFT JOIN utilisateurs u ON p.user_id = u.id WHERE p.statut = 'valide' ORDER BY p.date_creation DESC LIMIT 10"
    );

    // 2 plans VIP actifs pour la section aperçu du dashboard
    const [plans] = await db.query(
      "SELECT * FROM planinvestissement WHERE COALESCE(bloque, false) = false ORDER BY id ASC LIMIT 2"
    );

    const devise = 'FCFA';
    const success_message = req.session.success_message || null;
    const error_message = req.session.error_message || null;
    delete req.session.success_message;
    delete req.session.error_message;

    res.render('index', { user, solde: user.solde || 0, revenus: rev, posts, plans, devise, success_message, error_message, notifications: [] });
  } catch (e) {
    console.error(e);
    res.redirect('/connexion');
  }
});

router.post('/', requireAuth, upload.single('image'), async (req, res) => {
  const user_id = req.session.user_id;
  try {
    const message = (req.body.message || '').trim();
    if (message && req.file) {
      await db.query(
        "INSERT INTO posts (user_id, message, image, statut) VALUES (?, ?, ?, 'en_attente')",
        [user_id, message, req.file.filename]
      );
      req.session.success_message = 'Votre post a été soumis et sera vérifié avant publication!';
    } else {
      req.session.error_message = 'Veuillez remplir tous les champs.';
    }
  } catch (e) {
    req.session.error_message = 'Erreur lors de l\'enregistrement du post.';
  }
  res.redirect('/');
});

module.exports = router;
