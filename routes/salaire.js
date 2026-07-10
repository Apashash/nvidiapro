const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { requireAuth } = require('../middleware/auth');

// ── GET /salaire ──────────────────────────────────────────────────────────────
router.get('/salaire', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  try {
    // 1. User + VIP info
    const [[user]] = await db.query(`
      SELECT u.*, COALESCE(v.niveau,0) as niveau, v.invitations_actuelles, v.invitations_requises
      FROM utilisateurs u LEFT JOIN vip v ON u.id = v.user_id WHERE u.id = ?`, [user_id]);

    const niveau_actuel = user.niveau || 0;

    // 2. Paliers from DB
    const [paliers] = await db.query('SELECT * FROM vip_paliers ORDER BY niveau ASC');

    // 3. Palier courant (niveau actuel du user)
    const palier_actuel = paliers.find(p => p.niveau === niveau_actuel) || null;
    const salaire_actuel = palier_actuel ? parseFloat(palier_actuel.montant_cadeau) : 0;
    const filleuls_requis = palier_actuel ? parseInt(palier_actuel.filleuls_requis) : 3;

    // 4. Filleuls directs (niveau 1) avec détail actif/inactif
    const [filleuls_directs] = await db.query(`
      SELECT u.id, u.nom, u.telephone, u.date_inscription,
        CASE WHEN EXISTS (
          SELECT 1 FROM commandes c
          WHERE c.user_id = u.id AND c.statut = 'actif' AND c.date_fin >= CURRENT_DATE
        ) THEN true ELSE false END as actif
      FROM utilisateurs u
      WHERE u.parrain_id = ?
      ORDER BY u.date_inscription DESC`, [user_id]);

    const filleuls_actifs = filleuls_directs.filter(f => f.actif).length;
    const seuil_atteint = niveau_actuel > 0 && filleuls_actifs >= filleuls_requis;

    // 5. Déjà réclamé aujourd'hui ?
    const [[claimed]] = await db.query(`
      SELECT COUNT(*)::int as nb FROM historique_revenus
      WHERE user_id = ? AND type = 'salaire'
        AND date_paiement >= CURRENT_DATE`, [user_id]);
    const deja_reclame = (claimed.nb || 0) > 0;

    const message_erreur = req.session.salaire_error || null;
    const salaire_success = req.session.salaire_success || null;
    delete req.session.salaire_error;
    delete req.session.salaire_success;

    res.render('salaire', {
      user, niveau_actuel, salaire_actuel, paliers,
      palier_actuel, filleuls_requis, filleuls_directs,
      filleuls_actifs, seuil_atteint, deja_reclame, message_erreur, salaire_success,
    });
  } catch (e) {
    console.error('GET /salaire error:', e);
    res.redirect('/');
  }
});

// ── POST /salaire (réclamer) ──────────────────────────────────────────────────
router.post('/salaire', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  try {
    const [[user]] = await db.query(`
      SELECT COALESCE(v.niveau,0) as niveau FROM utilisateurs u
      LEFT JOIN vip v ON u.id = v.user_id WHERE u.id = ?`, [user_id]);
    const niveau_actuel = user ? (user.niveau || 0) : 0;

    if (niveau_actuel === 0) {
      req.session.salaire_error = 'Vous devez atteindre le niveau VIP 1 pour recevoir un salaire.';
      return res.redirect('/salaire');
    }

    // Palier courant
    const [[palier]] = await db.query(
      'SELECT * FROM vip_paliers WHERE niveau = ?', [niveau_actuel]);
    if (!palier) {
      req.session.salaire_error = 'Palier introuvable. Contactez le support.';
      return res.redirect('/salaire');
    }

    const filleuls_requis = parseInt(palier.filleuls_requis);
    const montant = parseFloat(palier.montant_cadeau);

    // Compter filleuls actifs
    const [[cnt]] = await db.query(`
      SELECT COUNT(*)::int as nb FROM utilisateurs u
      WHERE u.parrain_id = ?
        AND EXISTS (
          SELECT 1 FROM commandes c
          WHERE c.user_id = u.id AND c.statut = 'actif' AND c.date_fin >= CURRENT_DATE
        )`, [user_id]);
    const filleuls_actifs = cnt.nb || 0;

    if (filleuls_actifs < filleuls_requis) {
      req.session.salaire_error = `Il vous faut ${filleuls_requis} filleuls actifs (plans achetés) pour percevoir votre salaire. Vous en avez ${filleuls_actifs}.`;
      return res.redirect('/salaire');
    }

    // Déjà réclamé aujourd'hui ?
    const [[claimed]] = await db.query(`
      SELECT COUNT(*)::int as nb FROM historique_revenus
      WHERE user_id = ? AND type = 'salaire' AND date_paiement >= CURRENT_DATE`, [user_id]);
    if ((claimed.nb || 0) > 0) {
      req.session.salaire_error = 'Vous avez déjà réclamé votre salaire aujourd\'hui. Revenez demain !';
      return res.redirect('/salaire');
    }

    // Transaction : crédit solde + historique
    const conn = await db.getConnection();
    try {
      await conn.beginTransaction();
      await conn.query(
        'UPDATE soldes SET solde = solde + ? WHERE user_id = ?', [montant, user_id]);
      await conn.query(
        `INSERT INTO historique_revenus (user_id, montant, type, date_paiement)
         VALUES (?, ?, 'salaire', NOW())`, [user_id, montant]);
      await conn.commit();
    } catch (e) {
      await conn.rollback();
      throw e;
    } finally {
      conn.release();
    }

    req.session.salaire_success = montant;
    res.redirect('/salaire');
  } catch (e) {
    console.error('POST /salaire error:', e);
    req.session.salaire_error = 'Une erreur est survenue. Veuillez réessayer.';
    res.redirect('/salaire');
  }
});

module.exports = router;
