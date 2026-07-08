const express = require('express');
const router = express.Router();
const db = require('../config/db');

const SECURITY_CODE = process.env.ADMIN_CODE || 'Apashash28';
const SESSION_TIMEOUT = 30 * 60 * 1000; // 30 minutes

function requireAdminAuth(req, res, next) {
  if (!req.session.security_authenticated) return res.redirect('/adminxyz');
  const now = Date.now();
  if (req.session.security_last_access && (now - req.session.security_last_access) > SESSION_TIMEOUT) {
    req.session.security_authenticated = false;
    return res.redirect('/adminxyz');
  }
  req.session.security_last_access = now;
  next();
}

// Admin login page
router.get('/adminxyz', (req, res) => {
  if (req.session.security_authenticated) return res.redirect('/adminxyz/dashboard');
  const error = req.session.admin_error || null;
  delete req.session.admin_error;
  res.render('admin_login', { error });
});

router.post('/adminxyz', (req, res) => {
  if (req.body.security_code === SECURITY_CODE) {
    req.session.security_authenticated = true;
    req.session.security_last_access = Date.now();
    res.redirect('/adminxyz/dashboard');
  } else {
    req.session.admin_error = 'Code de sécurité incorrect';
    res.redirect('/adminxyz');
  }
});

// Direct admin-panel access for accounts flagged as admin (bypasses the security-code prompt)
router.get('/admin-access', async (req, res) => {
  if (!req.session || !req.session.user_id) return res.redirect('/connexion');
  try {
    const [[user]] = await db.query('SELECT is_admin FROM utilisateurs WHERE id = ?', [req.session.user_id]);
    if (!user || !user.is_admin) return res.redirect('/compte');
    req.session.security_authenticated = true;
    req.session.security_last_access = Date.now();
    res.redirect('/adminxyz/dashboard');
  } catch (e) {
    console.error(e);
    res.redirect('/compte');
  }
});

// Admin dashboard
router.get('/adminxyz/dashboard', requireAdminAuth, async (req, res) => {
  try {
    const [[stats_users]] = await db.query('SELECT COUNT(*) as total FROM utilisateurs');
    const [[stats_depots]] = await db.query("SELECT COUNT(*) as total, SUM(montant) as somme FROM depots WHERE statut = 'valide'");
    const [[stats_retraits]] = await db.query("SELECT COUNT(*) as total, SUM(montant) as somme FROM retraits WHERE statut = 'valide'");
    const [[stats_pending_depots]] = await db.query("SELECT COUNT(*) as total FROM depots WHERE statut = 'en_attente'");
    const [[stats_pending_retraits]] = await db.query("SELECT COUNT(*) as total FROM retraits WHERE statut = 'en_attente'");

    const [users] = await db.query('SELECT u.*, s.solde FROM utilisateurs u LEFT JOIN soldes s ON u.id = s.user_id ORDER BY u.id DESC LIMIT 50');
    const [depots] = await db.query("SELECT d.*, u.nom, u.telephone FROM depots d LEFT JOIN utilisateurs u ON d.user_id = u.id ORDER BY d.date_depot DESC LIMIT 50");
    const [retraits] = await db.query("SELECT r.*, u.nom, u.telephone FROM retraits r LEFT JOIN utilisateurs u ON r.user_id = u.id ORDER BY r.date_demande DESC LIMIT 50");
    const [posts] = await db.query("SELECT p.*, u.nom FROM posts p LEFT JOIN utilisateurs u ON p.user_id = u.id WHERE p.statut = 'en_attente' ORDER BY p.date_creation DESC LIMIT 30");

    res.render('admin', {
      stats_users, stats_depots, stats_retraits, stats_pending_depots, stats_pending_retraits,
      users, depots, retraits, posts,
    });
  } catch (e) { console.error(e); res.status(500).send('Erreur serveur: ' + e.message); }
});

// Admin AJAX actions
router.post('/adminxyz/action', requireAdminAuth, async (req, res) => {
  const { action, id, montant, user_id, message } = req.body;
  try {
    switch (action) {
      case 'validate_deposit': {
        const [[dep]] = await db.query('SELECT * FROM depots WHERE id = ?', [id]);
        if (!dep || dep.statut === 'valide') return res.json({ success: false, message: 'Dépôt déjà traité' });
        const conn = await db.getConnection();
        await conn.beginTransaction();
        const [[sl]] = await conn.query('SELECT * FROM soldes WHERE user_id = ?', [dep.user_id]);
        if (sl) await conn.query('UPDATE soldes SET solde = solde + ? WHERE user_id = ?', [dep.montant, dep.user_id]);
        else await conn.query('INSERT INTO soldes (user_id, solde) VALUES (?, ?)', [dep.user_id, dep.montant]);
        await conn.query("UPDATE depots SET statut = 'valide', date_validation = NOW() WHERE id = ?", [id]);
        await conn.commit(); conn.release();
        return res.json({ success: true });
      }
      case 'reject_deposit':
        await db.query("UPDATE depots SET statut = 'rejete' WHERE id = ?", [id]);
        return res.json({ success: true });
      case 'validate_retrait': {
        const [[ret]] = await db.query('SELECT * FROM retraits WHERE id = ?', [id]);
        if (!ret) return res.json({ success: false, message: 'Retrait non trouvé' });
        await db.query("UPDATE retraits SET statut = 'valide', date_traitement = NOW() WHERE id = ?", [id]);
        return res.json({ success: true });
      }
      case 'reject_retrait': {
        const [[ret]] = await db.query('SELECT * FROM retraits WHERE id = ?', [id]);
        if (!ret) return res.json({ success: false });
        if (ret.statut === 'en_attente') {
          await db.query('UPDATE soldes SET solde = solde + ? WHERE user_id = ?', [ret.montant, ret.user_id]);
        }
        await db.query("UPDATE retraits SET statut = 'rejete', date_traitement = NOW() WHERE id = ?", [id]);
        return res.json({ success: true });
      }
      case 'update_balance':
        await db.query('UPDATE soldes SET solde = ? WHERE user_id = ?', [montant, user_id]);
        await db.query('UPDATE utilisateurs SET solde = ? WHERE id = ?', [montant, user_id]).catch(() => {});
        return res.json({ success: true });
      case 'validate_post':
        await db.query("UPDATE posts SET statut = 'valide' WHERE id = ?", [id]);
        return res.json({ success: true });
      case 'reject_post':
        await db.query("UPDATE posts SET statut = 'refuse' WHERE id = ?", [id]);
        return res.json({ success: true });
      case 'toggle_admin': {
        const [[u]] = await db.query('SELECT is_admin FROM utilisateurs WHERE id = ?', [id]);
        if (!u) return res.json({ success: false, message: 'Utilisateur non trouvé' });
        await db.query('UPDATE utilisateurs SET is_admin = ? WHERE id = ?', [!u.is_admin, id]);
        return res.json({ success: true, is_admin: !u.is_admin });
      }
      default:
        return res.json({ success: false, message: 'Action inconnue' });
    }
  } catch (e) {
    console.error(e);
    res.json({ success: false, message: e.message });
  }
});

// Admin logout
router.get('/adminxyz/logout', (req, res) => {
  req.session.security_authenticated = false;
  res.redirect('/adminxyz');
});

module.exports = router;
