const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { requireAuth } = require('../middleware/auth');

router.get('/roue', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  try {
    const [[user]] = await db.query('SELECT last_spin_time FROM utilisateurs WHERE id = ?', [user_id]);
    const [[soldeRow]] = await db.query('SELECT solde FROM soldes WHERE user_id = ?', [user_id]);
    const solde = soldeRow ? soldeRow.solde : 0;
    const devise = (req.session.pays || 'Cameroun') === 'Cameroun' ? 'XAF' : 'XOF';

    const cooldown = 48 * 3600;
    let can_spin = true;
    let remaining_time = 0;
    if (user && user.last_spin_time) {
      const elapsed = (Date.now() - new Date(user.last_spin_time).getTime()) / 1000;
      if (elapsed < cooldown) { can_spin = false; remaining_time = Math.ceil(cooldown - elapsed); }
    }
    res.render('roue', { can_spin, remaining_time, solde, devise });
  } catch (e) { console.error(e); res.redirect('/'); }
});

router.post('/spin_roue', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  res.json(await doSpin(user_id));
});

router.get('/spin_roue', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  res.json(await doSpin(user_id));
});

async function doSpin(user_id) {
  const cooldown = 48 * 3600;
  const [[user]] = await db.query('SELECT last_spin_time FROM utilisateurs WHERE id = ?', [user_id]);
  if (user && user.last_spin_time) {
    const elapsed = (Date.now() - new Date(user.last_spin_time).getTime()) / 1000;
    if (elapsed < cooldown) {
      return { status: 'cooldown', remaining_time: Math.ceil(cooldown - elapsed), message: 'Veuillez attendre 48h entre chaque spin.' };
    }
  }

  const prizes = { 25: 900, 300: 30, 500: 30, 700: 20, 1500: 15, 2000: 3, 0: 2 };
  const total = Object.values(prizes).reduce((a, b) => a + b, 0);
  let rand = Math.floor(Math.random() * total) + 1;
  let gains = 0;
  for (const [prize, weight] of Object.entries(prizes)) {
    rand -= weight;
    if (rand <= 0) { gains = parseInt(prize); break; }
  }

  const conn = await db.getConnection();
  try {
    await conn.beginTransaction();
    await conn.query('UPDATE soldes SET solde = solde + ? WHERE user_id = ?', [gains, user_id]);
    await conn.query('UPDATE utilisateurs SET last_spin_time = NOW() WHERE id = ?', [user_id]);
    if (gains > 0) {
      await conn.query("INSERT INTO historique_revenus (user_id, montant, type) VALUES (?, ?, 'bonus')", [user_id, gains]);
    }
    await conn.commit();
    const [[sl]] = await conn.query('SELECT solde FROM soldes WHERE user_id = ?', [user_id]);
    return { status: 'success', gains, new_solde: sl.solde };
  } catch (e) {
    await conn.rollback();
    return { status: 'error', message: 'Erreur lors de la mise à jour du solde.' };
  } finally { conn.release(); }
}

module.exports = router;
