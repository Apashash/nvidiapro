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
    // Regenerate session to prevent session fixation
    req.session.regenerate((err) => {
      if (err) { req.session.admin_error = 'Erreur session'; return res.redirect('/adminxyz'); }
      req.session.security_authenticated = true;
      req.session.security_last_access = Date.now();
      res.redirect('/adminxyz/dashboard');
    });
  } else {
    req.session.admin_error = 'Code de sécurité incorrect';
    res.redirect('/adminxyz');
  }
});

// Admin dashboard (main SPA shell)
router.get('/adminxyz/dashboard', requireAdminAuth, async (req, res) => {
  try {
    // Stats for initial render
    const [[stats_users]] = await db.query('SELECT COUNT(*) as total FROM utilisateurs');
    const [[stats_depots]] = await db.query("SELECT COUNT(*) as total, COALESCE(SUM(montant),0) as somme FROM depots WHERE statut = 'valide'");
    const [[stats_retraits]] = await db.query("SELECT COUNT(*) as total, COALESCE(SUM(montant),0) as somme FROM retraits WHERE statut = 'valide'");
    const [[stats_pending_depots]] = await db.query("SELECT COUNT(*) as total FROM depots WHERE statut = 'en_attente'");
    const [[stats_pending_retraits]] = await db.query("SELECT COUNT(*) as total FROM retraits WHERE statut = 'en_attente'");
    const [[stats_plans_actifs]] = await db.query("SELECT COUNT(*) as total FROM planinvestissement");
    const [[stats_avec_invest]] = await db.query("SELECT COUNT(DISTINCT user_id) as total FROM commandes WHERE statut = 'actif'");

    res.render('admin', {
      stats_users,
      stats_depots,
      stats_retraits,
      stats_pending_depots,
      stats_pending_retraits,
      stats_plans_actifs,
      stats_avec_invest,
    });
  } catch (e) { console.error(e); res.status(500).send('Erreur serveur: ' + e.message); }
});

// ── API: Dashboard chart data ─────────────────────────────────────────────────
router.get('/adminxyz/api/chart-data', requireAdminAuth, async (req, res) => {
  try {
    const today = new Date(); today.setHours(0,0,0,0);
    const yesterday = new Date(today); yesterday.setDate(yesterday.getDate() - 1);
    const todayStr = today.toISOString().split('T')[0];
    const yesterdayStr = yesterday.toISOString().split('T')[0];

    // Today vs Yesterday
    const [[dep_today]] = await db.query(
      "SELECT COALESCE(SUM(montant),0) as total FROM depots WHERE statut='valide' AND date_depot::date = ?::date", [todayStr]);
    const [[dep_yesterday]] = await db.query(
      "SELECT COALESCE(SUM(montant),0) as total FROM depots WHERE statut='valide' AND date_depot::date = ?::date", [yesterdayStr]);
    const [[ret_today]] = await db.query(
      "SELECT COALESCE(SUM(montant),0) as total FROM retraits WHERE statut='valide' AND date_demande::date = ?::date", [todayStr]);
    const [[ret_yesterday]] = await db.query(
      "SELECT COALESCE(SUM(montant),0) as total FROM retraits WHERE statut='valide' AND date_demande::date = ?::date", [yesterdayStr]);

    const [[dep_today_count]] = await db.query(
      "SELECT COUNT(*) as cnt FROM depots WHERE statut='valide' AND date_depot::date = ?::date", [todayStr]);
    const [[dep_yesterday_count]] = await db.query(
      "SELECT COUNT(*) as cnt FROM depots WHERE statut='valide' AND date_depot::date = ?::date", [yesterdayStr]);
    const [[ret_today_count]] = await db.query(
      "SELECT COUNT(*) as cnt FROM retraits WHERE statut='valide' AND date_demande::date = ?::date", [todayStr]);
    const [[ret_yesterday_count]] = await db.query(
      "SELECT COUNT(*) as cnt FROM retraits WHERE statut='valide' AND date_demande::date = ?::date", [yesterdayStr]);

    // 6 months activity
    const months = [];
    const monthLabels = [];
    for (let i = 5; i >= 0; i--) {
      const d = new Date(); d.setDate(1); d.setMonth(d.getMonth() - i);
      months.push({ year: d.getFullYear(), month: d.getMonth() + 1 });
      monthLabels.push(d.toLocaleString('fr-FR', { month: 'short' }));
    }

    const monthlyData = await Promise.all(months.map(async ({ year, month }) => {
      const [[dv]] = await db.query(
        "SELECT COUNT(*) as cnt FROM depots WHERE statut='valide' AND EXTRACT(YEAR FROM date_depot)=? AND EXTRACT(MONTH FROM date_depot)=?", [year, month]);
      const [[dr]] = await db.query(
        "SELECT COUNT(*) as cnt FROM depots WHERE statut='rejete' AND EXTRACT(YEAR FROM date_depot)=? AND EXTRACT(MONTH FROM date_depot)=?", [year, month]);
      const [[rv]] = await db.query(
        "SELECT COUNT(*) as cnt FROM retraits WHERE statut='valide' AND EXTRACT(YEAR FROM date_demande)=? AND EXTRACT(MONTH FROM date_demande)=?", [year, month]);
      const [[rr]] = await db.query(
        "SELECT COUNT(*) as cnt FROM retraits WHERE statut='rejete' AND EXTRACT(YEAR FROM date_demande)=? AND EXTRACT(MONTH FROM date_demande)=?", [year, month]);
      return { dep_valides: parseInt(dv.cnt)||0, dep_rejetes: parseInt(dr.cnt)||0, ret_valides: parseInt(rv.cnt)||0, ret_rejetes: parseInt(rr.cnt)||0 };
    }));

    res.json({
      today_vs_yesterday: {
        dep_today: parseFloat(dep_today.total)||0,
        dep_yesterday: parseFloat(dep_yesterday.total)||0,
        ret_today: parseFloat(ret_today.total)||0,
        ret_yesterday: parseFloat(ret_yesterday.total)||0,
        dep_today_count: parseInt(dep_today_count.cnt)||0,
        dep_yesterday_count: parseInt(dep_yesterday_count.cnt)||0,
        ret_today_count: parseInt(ret_today_count.cnt)||0,
        ret_yesterday_count: parseInt(ret_yesterday_count.cnt)||0,
      },
      months: monthLabels,
      monthly: monthlyData,
    });
  } catch (e) { console.error(e); res.json({ error: e.message }); }
});

// ── API: Plans VIP ─────────────────────────────────────────────────────────────
router.get('/adminxyz/api/plans', requireAdminAuth, async (req, res) => {
  try {
    const [plans] = await db.query('SELECT * FROM planinvestissement ORDER BY prix ASC');
    const counts = await Promise.all(plans.map(async p => {
      const [[c]] = await db.query("SELECT COUNT(*) as total FROM commandes WHERE plan_id=? AND statut='actif'", [p.id]);
      return parseInt(c.total)||0;
    }));
    res.json({ plans, counts });
  } catch (e) { res.json({ error: e.message }); }
});

// ── API: Utilisateurs ─────────────────────────────────────────────────────────
router.get('/adminxyz/api/utilisateurs', requireAdminAuth, async (req, res) => {
  try {
    const [users] = await db.query(`
      SELECT u.id, u.nom, u.telephone, u.pays, u.date_inscription, u.is_admin, u.code_parrainage,
             COALESCE(s.solde,0) as solde,
             COALESCE(v.niveau,0) as niveau_vip,
             (SELECT COUNT(*) FROM utilisateurs WHERE parrain_id=u.id) as nb_filleuls,
             (SELECT COUNT(*) FROM commandes WHERE user_id=u.id AND statut='actif') as nb_commandes
      FROM utilisateurs u
      LEFT JOIN soldes s ON u.id=s.user_id
      LEFT JOIN vip v ON u.id=v.user_id
      ORDER BY u.id DESC
      LIMIT 200`);
    res.json({ users });
  } catch (e) { res.json({ error: e.message }); }
});

// ── API: Dépôts ───────────────────────────────────────────────────────────────
router.get('/adminxyz/api/depots', requireAdminAuth, async (req, res) => {
  try {
    const [depots] = await db.query(`
      SELECT d.*, u.nom, u.telephone FROM depots d
      LEFT JOIN utilisateurs u ON d.user_id=u.id
      ORDER BY d.date_depot DESC LIMIT 200`);
    res.json({ depots });
  } catch (e) { res.json({ error: e.message }); }
});

// ── API: Retraits ─────────────────────────────────────────────────────────────
router.get('/adminxyz/api/retraits', requireAdminAuth, async (req, res) => {
  try {
    const [retraits] = await db.query(`
      SELECT r.*, u.nom, u.telephone FROM retraits r
      LEFT JOIN utilisateurs u ON r.user_id=u.id
      ORDER BY r.date_demande DESC LIMIT 200`);
    res.json({ retraits });
  } catch (e) { res.json({ error: e.message }); }
});

// ── API: Cadeaux VIP ──────────────────────────────────────────────────────────
router.get('/adminxyz/api/cadeaux', requireAdminAuth, async (req, res) => {
  try {
    const [codes] = await db.query(`
      SELECT c.*, u.nom, u.telephone FROM codes_utilises c
      LEFT JOIN utilisateurs u ON c.user_id=u.id
      ORDER BY c.date_utilisation DESC LIMIT 200`);
    res.json({ codes });
  } catch (e) { res.json({ error: e.message }); }
});

// ── API: Salaires VIP ─────────────────────────────────────────────────────────
router.get('/adminxyz/api/salaires', requireAdminAuth, async (req, res) => {
  try {
    const [salaires] = await db.query(`
      SELECT u.id, u.nom, u.telephone, COALESCE(v.niveau,0) as niveau,
             v.invitations_actuelles, v.invitations_requises,
             COALESCE(s.solde,0) as solde
      FROM utilisateurs u
      LEFT JOIN vip v ON u.id=v.user_id
      LEFT JOIN soldes s ON u.id=s.user_id
      WHERE COALESCE(v.niveau,0) > 0
      ORDER BY v.niveau DESC, u.nom ASC
      LIMIT 200`);
    res.json({ salaires });
  } catch (e) { res.json({ error: e.message }); }
});

// ── API: Transactions ─────────────────────────────────────────────────────────
router.get('/adminxyz/api/transactions', requireAdminAuth, async (req, res) => {
  try {
    const [transactions] = await db.query(`
      SELECT h.*, u.nom, u.telephone FROM historique_revenus h
      LEFT JOIN utilisateurs u ON h.user_id=u.id
      ORDER BY h.date_creation DESC LIMIT 300`);
    res.json({ transactions });
  } catch (e) { res.json({ error: e.message }); }
});

// ── API: Affiches (Posts) ─────────────────────────────────────────────────────
router.get('/adminxyz/api/affiches', requireAdminAuth, async (req, res) => {
  try {
    const [posts] = await db.query(`
      SELECT p.*, u.nom FROM posts p
      LEFT JOIN utilisateurs u ON p.user_id=u.id
      ORDER BY p.date_creation DESC LIMIT 200`);
    res.json({ posts });
  } catch (e) { res.json({ error: e.message }); }
});

// ── Verser les revenus journaliers ────────────────────────────────────────────
router.post('/adminxyz/verser-revenus', requireAdminAuth, async (req, res) => {
  const conn = await db.getConnection();
  try {
    await conn.beginTransaction();
    const today = new Date().toISOString().split('T')[0];

    // Get all active commandes
    const [commandes] = await conn.query(`
      SELECT c.*, p.rendement_journalier FROM commandes c
      LEFT JOIN planinvestissement p ON c.plan_id=p.id
      WHERE c.statut='actif'`);

    let versed = 0;
    for (const cmd of commandes) {
      const gain = parseFloat(cmd.montant) * parseFloat(cmd.rendement_journalier) / 100;
      if (gain <= 0) continue;

      // Check if date_fin passed
      if (cmd.date_fin && new Date() > new Date(cmd.date_fin)) {
        await conn.query("UPDATE commandes SET statut='termine' WHERE id=?", [cmd.id]);
        continue;
      }

      // Credit gain
      const [[sl]] = await conn.query('SELECT * FROM soldes WHERE user_id=?', [cmd.user_id]);
      if (sl) await conn.query('UPDATE soldes SET solde=solde+? WHERE user_id=?', [gain, cmd.user_id]);
      else await conn.query('INSERT INTO soldes (user_id,solde) VALUES (?,?)', [cmd.user_id, gain]);

      await conn.query(
        'INSERT INTO historique_revenus (user_id,commande_id,montant,type,date_paiement) VALUES (?,?,?,?,NOW())',
        [cmd.user_id, cmd.id, gain, 'paiement_journalier']
      );
      versed++;
    }

    await conn.commit(); conn.release();
    res.json({ success: true, message: `Revenus versés à ${versed} investisseur(s).` });
  } catch (e) {
    await conn.rollback(); conn.release();
    console.error(e);
    res.json({ success: false, message: e.message });
  }
});

// ── Admin AJAX actions ─────────────────────────────────────────────────────────
router.post('/adminxyz/action', requireAdminAuth, async (req, res) => {
  const { action, id, montant, user_id, message, prix, duree_jours, rendement_journalier, nom, description } = req.body;
  try {
    switch (action) {
      case 'validate_deposit': {
        const [[dep]] = await db.query('SELECT * FROM depots WHERE id=?', [id]);
        if (!dep || dep.statut === 'valide') return res.json({ success: false, message: 'Dépôt déjà traité' });
        const conn = await db.getConnection();
        try {
          await conn.beginTransaction();
          const [[sl]] = await conn.query('SELECT * FROM soldes WHERE user_id=?', [dep.user_id]);
          if (sl) await conn.query('UPDATE soldes SET solde=solde+? WHERE user_id=?', [dep.montant, dep.user_id]);
          else await conn.query('INSERT INTO soldes (user_id,solde) VALUES (?,?)', [dep.user_id, dep.montant]);
          await conn.query("UPDATE depots SET statut='valide', date_validation=NOW() WHERE id=?", [id]);
          await conn.commit();
        } catch (txErr) {
          await conn.rollback();
          throw txErr;
        } finally {
          conn.release();
        }
        return res.json({ success: true });
      }
      case 'reject_deposit':
        await db.query("UPDATE depots SET statut='rejete' WHERE id=?", [id]);
        return res.json({ success: true });

      case 'validate_retrait': {
        const [[ret]] = await db.query('SELECT * FROM retraits WHERE id=?', [id]);
        if (!ret) return res.json({ success: false, message: 'Retrait non trouvé' });
        await db.query("UPDATE retraits SET statut='valide', date_traitement=NOW() WHERE id=?", [id]);
        return res.json({ success: true });
      }
      case 'reject_retrait': {
        const [[ret]] = await db.query('SELECT * FROM retraits WHERE id=?', [id]);
        if (!ret) return res.json({ success: false });
        if (ret.statut === 'en_attente') {
          await db.query('UPDATE soldes SET solde=solde+? WHERE user_id=?', [ret.montant, ret.user_id]);
        }
        await db.query("UPDATE retraits SET statut='rejete', date_traitement=NOW() WHERE id=?", [id]);
        return res.json({ success: true });
      }
      case 'update_balance':
        await db.query('UPDATE soldes SET solde=? WHERE user_id=?', [montant, user_id]);
        return res.json({ success: true });

      case 'validate_post':
        await db.query("UPDATE posts SET statut='valide' WHERE id=?", [id]);
        return res.json({ success: true });
      case 'reject_post':
        await db.query("UPDATE posts SET statut='refuse' WHERE id=?", [id]);
        return res.json({ success: true });

      case 'toggle_admin': {
        const [[u]] = await db.query('SELECT is_admin FROM utilisateurs WHERE id=?', [id]);
        if (!u) return res.json({ success: false, message: 'Utilisateur non trouvé' });
        await db.query('UPDATE utilisateurs SET is_admin=? WHERE id=?', [!u.is_admin, id]);
        return res.json({ success: true, is_admin: !u.is_admin });
      }
      case 'delete_user': {
        // Soft delete or actual delete
        await db.query('DELETE FROM utilisateurs WHERE id=?', [id]);
        return res.json({ success: true });
      }
      case 'update_plan': {
        await db.query(
          'UPDATE planinvestissement SET prix=?, duree_jours=?, rendement_journalier=?, description=? WHERE id=?',
          [prix, duree_jours, rendement_journalier, description, id]
        );
        return res.json({ success: true });
      }
      case 'create_code_cadeau': {
        // Insert a "virtual" code by storing it - handled via a simple table
        // Since we don't have a separate codes table, we just acknowledge
        return res.json({ success: true, message: 'Code créé (géré manuellement dans le code)' });
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
