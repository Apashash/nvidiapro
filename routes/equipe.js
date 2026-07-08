const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { requireAuth } = require('../middleware/auth');

async function getFilleulsByLevel(user_id, level) {
  let current = [user_id];
  for (let i = 1; i <= level; i++) {
    if (!current.length) return [];
    const placeholders = current.map(() => '?').join(',');
    const [rows] = await db.query(`SELECT id FROM utilisateurs WHERE parrain_id IN (${placeholders})`, current);
    current = rows.map(r => r.id);
    if (i === level) return current;
  }
  return [];
}

async function getInvestissementActif(ids) {
  if (!ids.length) return 0;
  const placeholders = ids.map(() => '?').join(',');
  const [[row]] = await db.query(
    `SELECT COALESCE(SUM(montant), 0) as total FROM commandes WHERE user_id IN (${placeholders}) AND statut = 'actif' AND date_fin >= CURRENT_DATE`,
    ids
  );
  return parseFloat(row.total || 0);
}

router.get('/equipe', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  try {
    const [[user]] = await db.query('SELECT * FROM utilisateurs WHERE id = ?', [user_id]);

    const [f1, f2, f3] = await Promise.all([
      getFilleulsByLevel(user_id, 1),
      getFilleulsByLevel(user_id, 2),
      getFilleulsByLevel(user_id, 3),
    ]);

    const [inv1, inv2, inv3] = await Promise.all([
      getInvestissementActif(f1),
      getInvestissementActif(f2),
      getInvestissementActif(f3),
    ]);

    // Get filleul details for level 1
    let filleuls_details = [];
    if (f1.length) {
      const placeholders = f1.map(() => '?').join(',');
      const [details] = await db.query(
        `SELECT u.id, u.nom, u.telephone, u.pays, u.date_inscription, s.solde,
         (SELECT COUNT(*) FROM commandes c WHERE c.user_id = u.id AND c.statut = 'actif' AND c.date_fin >= CURRENT_DATE) as has_active
         FROM utilisateurs u LEFT JOIN soldes s ON u.id = s.user_id
         WHERE u.id IN (${placeholders}) ORDER BY u.date_inscription DESC`,
        f1
      );
      filleuls_details = details;
    }

    const [[vip]] = await db.query('SELECT * FROM vip WHERE user_id = ?', [user_id]);

    const baseUrl = process.env.REPLIT_DEV_DOMAIN
      ? `https://${process.env.REPLIT_DEV_DOMAIN}`
      : `${req.protocol}://${req.get('host')}`;

    res.render('equipe', {
      user,
      filleuls_niveau1: f1, filleuls_niveau2: f2, filleuls_niveau3: f3,
      invest_niveau1: inv1, invest_niveau2: inv2, invest_niveau3: inv3,
      filleuls_details,
      vip: vip || { niveau: 0, invitations_actuelles: 0, invitations_requises: 3 },
      baseUrl,
    });
  } catch (e) {
    console.error(e);
    res.redirect('/');
  }
});

// AJAX endpoint for filleuls list
router.get('/get_filleuls', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  const level = parseInt(req.query.level) || 1;
  try {
    const ids = await getFilleulsByLevel(user_id, level);
    if (!ids.length) return res.json({ filleuls: [] });
    const placeholders = ids.map(() => '?').join(',');
    const [filleuls] = await db.query(
      `SELECT u.id, u.nom, u.telephone, u.pays, s.solde FROM utilisateurs u LEFT JOIN soldes s ON u.id = s.user_id WHERE u.id IN (${placeholders})`,
      ids
    );
    res.json({ filleuls });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

module.exports = router;
