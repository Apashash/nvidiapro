const express = require('express');
const router = express.Router();
const db = require('../config/db');

const SECURITY_CODE = process.env.ADMIN_CODE || 'Apashash28';
const SESSION_TIMEOUT = 30 * 60 * 1000;

async function requireAppAdmin(req, res, next) {
  if (!req.session.user_id) return res.redirect('/');
  try {
    const [[user]] = await db.query('SELECT is_admin FROM utilisateurs WHERE id=?', [req.session.user_id]);
    if (!user || !user.is_admin) return res.redirect('/');
    next();
  } catch (e) { return res.redirect('/'); }
}

function requireAdminAuth(req, res, next) {
  if (!req.session.user_id) return res.redirect('/');
  if (!req.session.security_authenticated) return res.redirect('/adminxyz');
  const now = Date.now();
  if (req.session.security_last_access && (now - req.session.security_last_access) > SESSION_TIMEOUT) {
    req.session.security_authenticated = false;
    return res.redirect('/adminxyz');
  }
  req.session.security_last_access = now;
  next();
}

const { getParams, invalidateCache } = require('../services/params');

async function getPendingCounts() {
  const [[d]] = await db.query("SELECT COUNT(*) as c FROM depots WHERE statut='en_attente'");
  const [[r]] = await db.query("SELECT COUNT(*) as c FROM retraits WHERE statut='en_attente'");
  const [[p]] = await db.query("SELECT COUNT(*) as c FROM posts WHERE statut='en_attente'");
  const depots   = parseInt(d.c) || 0;
  const retraits = parseInt(r.c) || 0;
  const affiches = parseInt(p.c) || 0;
  return { total: depots + retraits + affiches, depots, retraits, affiches };
}

// Attach pending notification counts to every authenticated admin page render
// (skip the polling endpoint itself, which computes its own counts).
router.use(async (req, res, next) => {
  if (req.session.security_authenticated && req.path !== '/adminxyz/pending-counts') {
    try {
      res.locals.pendingCounts = await getPendingCounts();
    } catch (e) {
      console.error(e);
      res.locals.pendingCounts = { total: 0, depots: 0, retraits: 0, affiches: 0 };
    }
  }
  next();
});

// AJAX polling endpoint for the notification bell
router.get('/adminxyz/pending-counts', (req, res) => {
  if (!req.session.user_id || !req.session.security_authenticated) {
    return res.status(401).json({ error: 'unauthenticated', total: 0, depots: 0, retraits: 0, affiches: 0 });
  }
  getPendingCounts()
    .then(counts => res.json(counts))
    .catch(() => res.status(500).json({ total: 0, depots: 0, retraits: 0, affiches: 0 }));
});

// ── Login ──────────────────────────────────────────────────────────────────────
router.get('/adminxyz', requireAppAdmin, (req, res) => {
  if (req.session.security_authenticated) return res.redirect('/adminxyz/dashboard');
  const error = req.session.admin_error || null;
  delete req.session.admin_error;
  res.render('admin_login', { error });
});

router.post('/adminxyz', requireAppAdmin, (req, res) => {
  if (req.body.security_code === SECURITY_CODE) {
    const uid = req.session.user_id;
    const unom = req.session.user_nom;
    req.session.security_authenticated = true;
    req.session.security_last_access = Date.now();
    req.session.user_id = uid;
    req.session.user_nom = unom;
    res.redirect('/adminxyz/dashboard');
  } else {
    req.session.admin_error = 'Code de sécurité incorrect';
    res.redirect('/adminxyz');
  }
});

// ── Dashboard ──────────────────────────────────────────────────────────────────
router.get('/adminxyz/dashboard', requireAdminAuth, async (req, res) => {
  try {
    const [[stats_users]]            = await db.query('SELECT COUNT(*) as total FROM utilisateurs');
    const [[stats_depots]]           = await db.query("SELECT COALESCE(SUM(montant),0) as somme FROM depots WHERE statut='valide'");
    const [[stats_retraits]]         = await db.query("SELECT COALESCE(SUM(montant),0) as somme FROM retraits WHERE statut='valide'");
    const [[stats_pending_depots]]   = await db.query("SELECT COUNT(*) as total FROM depots WHERE statut='en_attente'");
    const [[stats_pending_retraits]] = await db.query("SELECT COUNT(*) as total FROM retraits WHERE statut='en_attente'");
    const [[stats_plans_actifs]]     = await db.query('SELECT COUNT(*) as total FROM planinvestissement');
    const [[stats_avec_invest]]      = await db.query("SELECT COUNT(DISTINCT user_id) as total FROM commandes WHERE statut='actif'");

    const today     = new Date(); today.setHours(0,0,0,0);
    const yesterday = new Date(today); yesterday.setDate(yesterday.getDate() - 1);
    const todayStr     = today.toISOString().split('T')[0];
    const yesterdayStr = yesterday.toISOString().split('T')[0];

    const [[dep_today]]     = await db.query("SELECT COALESCE(SUM(montant),0) as total, COUNT(*) as cnt FROM depots WHERE statut='valide' AND date_depot::date=?::date", [todayStr]);
    const [[dep_yesterday]] = await db.query("SELECT COALESCE(SUM(montant),0) as total, COUNT(*) as cnt FROM depots WHERE statut='valide' AND date_depot::date=?::date", [yesterdayStr]);
    const [[ret_today]]     = await db.query("SELECT COALESCE(SUM(montant),0) as total, COUNT(*) as cnt FROM retraits WHERE statut='valide' AND date_demande::date=?::date", [todayStr]);
    const [[ret_yesterday]] = await db.query("SELECT COALESCE(SUM(montant),0) as total, COUNT(*) as cnt FROM retraits WHERE statut='valide' AND date_demande::date=?::date", [yesterdayStr]);

    const months = [];
    const monthLabels = [];
    for (let i = 5; i >= 0; i--) {
      const d = new Date(); d.setDate(1); d.setMonth(d.getMonth() - i);
      months.push({ year: d.getFullYear(), month: d.getMonth() + 1 });
      monthLabels.push(d.toLocaleString('fr-FR', { month: 'short' }));
    }
    const monthlyData = await Promise.all(months.map(async ({ year, month }) => {
      const [[dv]] = await db.query("SELECT COUNT(*) as cnt FROM depots WHERE statut='valide' AND EXTRACT(YEAR FROM date_depot)=? AND EXTRACT(MONTH FROM date_depot)=?", [year, month]);
      const [[dr]] = await db.query("SELECT COUNT(*) as cnt FROM depots WHERE statut='rejete' AND EXTRACT(YEAR FROM date_depot)=? AND EXTRACT(MONTH FROM date_depot)=?", [year, month]);
      const [[rv]] = await db.query("SELECT COUNT(*) as cnt FROM retraits WHERE statut='valide' AND EXTRACT(YEAR FROM date_demande)=? AND EXTRACT(MONTH FROM date_demande)=?", [year, month]);
      const [[rr]] = await db.query("SELECT COUNT(*) as cnt FROM retraits WHERE statut='rejete' AND EXTRACT(YEAR FROM date_demande)=? AND EXTRACT(MONTH FROM date_demande)=?", [year, month]);
      return { dep_valides: parseInt(dv.cnt)||0, dep_rejetes: parseInt(dr.cnt)||0, ret_valides: parseInt(rv.cnt)||0, ret_rejetes: parseInt(rr.cnt)||0 };
    }));

    const chartData = {
      tvh: {
        dep_today: parseFloat(dep_today.total)||0,         dep_today_count: parseInt(dep_today.cnt)||0,
        dep_yesterday: parseFloat(dep_yesterday.total)||0, dep_yesterday_count: parseInt(dep_yesterday.cnt)||0,
        ret_today: parseFloat(ret_today.total)||0,         ret_today_count: parseInt(ret_today.cnt)||0,
        ret_yesterday: parseFloat(ret_yesterday.total)||0, ret_yesterday_count: parseInt(ret_yesterday.cnt)||0,
      },
      months: monthLabels,
      monthly: monthlyData,
    };

    res.render('admin', {
      currentPage: 'dashboard', pageTitle: 'Tableau de bord',
      stats_users, stats_depots, stats_retraits, stats_pending_depots,
      stats_pending_retraits, stats_plans_actifs, stats_avec_invest,
      chartData,
    });
  } catch (e) { console.error(e); res.status(500).send('Erreur: ' + e.message); }
});

// ── Plans VIP ──────────────────────────────────────────────────────────────────
router.get('/adminxyz/plans', requireAdminAuth, async (req, res) => {
  try {
    const [plans] = await db.query('SELECT * FROM planinvestissement ORDER BY id ASC');
    const counts  = await Promise.all(plans.map(async p => {
      const [[c]] = await db.query("SELECT COUNT(*) as total FROM commandes WHERE plan_id=? AND statut='actif'", [p.id]);
      return parseInt(c.total)||0;
    }));
    res.render('admin', { currentPage: 'plans', pageTitle: 'Plans VIP', plans, counts });
  } catch (e) { console.error(e); res.status(500).send('Erreur: ' + e.message); }
});

// ── Utilisateurs ───────────────────────────────────────────────────────────────
router.get('/adminxyz/utilisateurs', requireAdminAuth, async (req, res) => {
  try {
    const [users] = await db.query(`
      SELECT u.id, u.nom, u.telephone, u.pays, u.date_inscription, u.is_admin, u.code_parrainage,
             COALESCE(s.solde,0) as solde,
             COALESCE(v.niveau,0) as niveau_vip,
             (SELECT COUNT(*) FROM utilisateurs f WHERE f.parrain_id=u.id) as nb_filleuls,
             (SELECT COUNT(*) FROM commandes c WHERE c.user_id=u.id AND c.statut='actif') as nb_commandes
      FROM utilisateurs u
      LEFT JOIN soldes s ON u.id=s.user_id
      LEFT JOIN vip v ON u.id=v.user_id
      ORDER BY u.id DESC LIMIT 300`);
    res.render('admin', { currentPage: 'utilisateurs', pageTitle: 'Utilisateurs', users });
  } catch (e) { console.error(e); res.status(500).send('Erreur: ' + e.message); }
});

// ── Dépôts ─────────────────────────────────────────────────────────────────────
router.get('/adminxyz/depots', requireAdminAuth, async (req, res) => {
  try {
    const [depots] = await db.query(`
      SELECT d.*, u.nom, u.telephone FROM depots d
      LEFT JOIN utilisateurs u ON d.user_id=u.id
      ORDER BY d.date_depot DESC LIMIT 300`);
    res.render('admin', { currentPage: 'depots', pageTitle: 'Dépôts', depots });
  } catch (e) { console.error(e); res.status(500).send('Erreur: ' + e.message); }
});

// ── Retraits ───────────────────────────────────────────────────────────────────
router.get('/adminxyz/retraits', requireAdminAuth, async (req, res) => {
  try {
    const [retraits] = await db.query(`
      SELECT r.*, u.nom, u.telephone FROM retraits r
      LEFT JOIN utilisateurs u ON r.user_id=u.id
      ORDER BY r.date_demande DESC LIMIT 300`);
    res.render('admin', { currentPage: 'retraits', pageTitle: 'Retraits', retraits });
  } catch (e) { console.error(e); res.status(500).send('Erreur: ' + e.message); }
});

// ── Cadeaux VIP ────────────────────────────────────────────────────────────────
router.get('/adminxyz/cadeaux', requireAdminAuth, async (req, res) => {
  try {
    const [codes] = await db.query(`
      SELECT c.*, u.nom, u.telephone FROM codes_utilises c
      LEFT JOIN utilisateurs u ON c.user_id=u.id
      ORDER BY c.date_utilisation DESC LIMIT 300`);
    res.render('admin', { currentPage: 'cadeaux', pageTitle: 'Cadeaux VIP', codes });
  } catch (e) { console.error(e); res.status(500).send('Erreur: ' + e.message); }
});

// ── Salaires VIP ───────────────────────────────────────────────────────────────
router.get('/adminxyz/salaires', requireAdminAuth, async (req, res) => {
  try {
    const [paliers] = await db.query('SELECT * FROM vip_paliers ORDER BY niveau ASC');
    res.render('admin', { currentPage: 'salaires', pageTitle: 'Salaires VIP', paliers });
  } catch (e) { console.error(e); res.status(500).send('Erreur: ' + e.message); }
});

// ── Transactions ───────────────────────────────────────────────────────────────
router.get('/adminxyz/transactions', requireAdminAuth, async (req, res) => {
  try {
    const [transactions] = await db.query(`
      SELECT h.*, u.nom, u.telephone FROM historique_revenus h
      LEFT JOIN utilisateurs u ON h.user_id=u.id
      ORDER BY h.date_creation DESC LIMIT 500`);
    res.render('admin', { currentPage: 'transactions', pageTitle: 'Transactions', transactions });
  } catch (e) { console.error(e); res.status(500).send('Erreur: ' + e.message); }
});

// ── Affiches ───────────────────────────────────────────────────────────────────
router.get('/adminxyz/affiches', requireAdminAuth, async (req, res) => {
  try {
    const [posts] = await db.query(`
      SELECT p.*, u.nom FROM posts p
      LEFT JOIN utilisateurs u ON p.user_id=u.id
      ORDER BY p.date_creation DESC LIMIT 300`);
    res.render('admin', { currentPage: 'affiches', pageTitle: 'Affiches', posts });
  } catch (e) { console.error(e); res.status(500).send('Erreur: ' + e.message); }
});

// ── Paramètres ─────────────────────────────────────────────────────────────────
router.get('/adminxyz/parametres', requireAdminAuth, async (req, res) => {
  try {
    const params = await getParams();
    res.render('admin', { currentPage: 'parametres', pageTitle: 'Paramètres', params });
  } catch (e) { console.error(e); res.status(500).send('Erreur: ' + e.message); }
});

router.post('/adminxyz/parametres/save', requireAdminAuth, async (req, res) => {
  const { cle, valeur } = req.body;
  if (!cle) return res.json({ success: false, message: 'Clé manquante' });
  // Validate URLs — link params must start with http(s):// or be empty
  const linkKeys = ['whatsapp_link', 'telegram_link', 'whatsapp_group_link'];
  if (linkKeys.includes(cle) && valeur && !/^https?:\/\//i.test(valeur)) {
    return res.json({ success: false, message: 'Le lien doit commencer par https://' });
  }
  try {
    await db.query('INSERT INTO app_parametres (cle, valeur) VALUES (?,?) ON CONFLICT (cle) DO UPDATE SET valeur=?', [cle, valeur, valeur]);
    invalidateCache();
    res.json({ success: true });
  } catch (e) {
    console.error(e);
    res.json({ success: false, message: e.message });
  }
});

// ── AJAX: Verser revenus ───────────────────────────────────────────────────────
// Delegates to the same row-locked payDueCommande() used by the 24h auto-payout
// scheduler and the manual user "collecter" endpoint, so admin-triggered payouts
// can never double-credit a commande that was already paid by another path.
router.post('/adminxyz/verser-revenus', requireAdminAuth, async (req, res) => {
  try {
    const { payDueCommande } = require('../services/autoPayout');
    const [commandes] = await db.query("SELECT id FROM commandes WHERE statut='actif' AND date_fin >= CURRENT_DATE");
    let versed = 0;
    for (const cmd of commandes) {
      const gain = await payDueCommande(cmd.id);
      if (gain > 0) versed++;
    }
    // Close out orders whose end date has passed
    await db.query("UPDATE commandes SET statut='termine' WHERE statut='actif' AND date_fin < CURRENT_DATE");
    res.json({ success: true, message: `Revenus versés à ${versed} investisseur(s).` });
  } catch (e) {
    console.error(e);
    res.json({ success: false, message: e.message });
  }
});

// ── AJAX: Actions ──────────────────────────────────────────────────────────────
router.post('/adminxyz/action', requireAdminAuth, async (req, res) => {
  const { action, id, montant, user_id, nom, prix, duree_jours, rendement_journalier, description,
          label, filleuls_requis, montant_cadeau, niveau } = req.body;
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
          else     await conn.query('INSERT INTO soldes (user_id,solde) VALUES (?,?)', [dep.user_id, dep.montant]);
          await conn.query("UPDATE depots SET statut='valide', date_validation=NOW() WHERE id=?", [id]);
          await conn.commit();
        } catch (e) { await conn.rollback(); throw e; } finally { conn.release(); }
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
        if (ret.statut === 'en_attente')
          await db.query('UPDATE soldes SET solde=solde+? WHERE user_id=?', [ret.montant, ret.user_id]);
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

      case 'delete_post':
        await db.query('DELETE FROM posts WHERE id=?', [id]);
        return res.json({ success: true });

      case 'toggle_admin': {
        const [[u]] = await db.query('SELECT is_admin FROM utilisateurs WHERE id=?', [id]);
        if (!u) return res.json({ success: false, message: 'Utilisateur non trouvé' });
        await db.query('UPDATE utilisateurs SET is_admin=? WHERE id=?', [!u.is_admin, id]);
        return res.json({ success: true, is_admin: !u.is_admin });
      }

      case 'update_plan':
        await db.query(
          'UPDATE planinvestissement SET nom=?, prix=?, duree_jours=?, rendement_journalier=?, image_url=?, description=? WHERE id=?',
          [nom, prix, duree_jours, rendement_journalier, req.body.image_url || null, description, id]);
        return res.json({ success: true });

      case 'add_plan': {
        if (!nom || !prix || !duree_jours || !rendement_journalier)
          return res.json({ success: false, message: 'Tous les champs sont requis' });
        await db.query(
          'INSERT INTO planinvestissement (nom, prix, duree_jours, rendement_journalier, image_url, description) VALUES (?,?,?,?,?,?)',
          [nom, prix, duree_jours, rendement_journalier, req.body.image_url || null, description || '']);
        return res.json({ success: true });
      }

      case 'toggle_plan_lock': {
        const [[pl]] = await db.query('SELECT bloque FROM planinvestissement WHERE id=?', [id]);
        if (!pl) return res.json({ success: false, message: 'Plan non trouvé' });
        await db.query('UPDATE planinvestissement SET bloque=? WHERE id=?', [!pl.bloque, id]);
        return res.json({ success: true, bloque: !pl.bloque });
      }

      case 'delete_plan': {
        const [[activeCount]] = await db.query("SELECT COUNT(*) as cnt FROM commandes WHERE plan_id=? AND statut='actif'", [id]);
        if (parseInt(activeCount.cnt) > 0) return res.json({ success: false, message: 'Ce plan a des investissements actifs' });
        await db.query('DELETE FROM planinvestissement WHERE id=?', [id]);
        return res.json({ success: true });
      }

      case 'update_palier':
        await db.query(
          'UPDATE vip_paliers SET label=?, filleuls_requis=?, montant_cadeau=? WHERE id=?',
          [label, filleuls_requis, montant_cadeau, id]);
        return res.json({ success: true });

      case 'add_palier': {
        if (!niveau) return res.json({ success: false, message: 'Niveau requis' });
        await db.query(
          'INSERT INTO vip_paliers (niveau, label, filleuls_requis, montant_cadeau) VALUES (?,?,?,?)',
          [niveau, label || '', filleuls_requis || 0, montant_cadeau || 0]);
        return res.json({ success: true });
      }

      case 'delete_palier':
        await db.query('DELETE FROM vip_paliers WHERE id=?', [id]);
        return res.json({ success: true });

      case 'toggle_ban': {
        const [[ub]] = await db.query('SELECT is_banned FROM utilisateurs WHERE id=?', [id]);
        if (!ub) return res.json({ success: false, message: 'Utilisateur non trouvé' });
        await db.query('UPDATE utilisateurs SET is_banned=? WHERE id=?', [!ub.is_banned, id]);
        return res.json({ success: true, is_banned: !ub.is_banned });
      }

      case 'toggle_retrait_block': {
        const [[ur]] = await db.query('SELECT retrait_bloque FROM utilisateurs WHERE id=?', [id]);
        if (!ur) return res.json({ success: false, message: 'Utilisateur non trouvé' });
        await db.query('UPDATE utilisateurs SET retrait_bloque=? WHERE id=?', [!ur.retrait_bloque, id]);
        return res.json({ success: true, retrait_bloque: !ur.retrait_bloque });
      }

      case 'delete_user': {
        const [[ud]] = await db.query('SELECT id FROM utilisateurs WHERE id=?', [id]);
        if (!ud) return res.json({ success: false, message: 'Utilisateur non trouvé' });
        await db.query('DELETE FROM historique_revenus WHERE user_id=?', [id]);
        await db.query('DELETE FROM commandes WHERE user_id=?', [id]);
        await db.query('DELETE FROM depots WHERE user_id=?', [id]);
        await db.query('DELETE FROM retraits WHERE user_id=?', [id]);
        await db.query('DELETE FROM soldes WHERE user_id=?', [id]);
        await db.query('DELETE FROM filleuls WHERE user_id=? OR filleul_id=?', [id, id]);
        await db.query('DELETE FROM connexions_journalieres WHERE user_id=?', [id]);
        await db.query('DELETE FROM codes_utilises WHERE user_id=?', [id]);
        await db.query('DELETE FROM vip WHERE user_id=?', [id]);
        await db.query('DELETE FROM pieces WHERE user_id=?', [id]);
        await db.query('DELETE FROM utilisateurs WHERE id=?', [id]);
        return res.json({ success: true });
      }

      case 'update_user_infos': {
        const { nom, pays } = req.body;
        await db.query('UPDATE utilisateurs SET nom=?, pays=? WHERE id=?', [nom, pays, id]);
        return res.json({ success: true });
      }

      case 'update_password': {
        const { mot_de_passe } = req.body;
        if (!mot_de_passe || mot_de_passe.length < 4) return res.json({ success: false, message: 'Mot de passe trop court' });
        await db.query('UPDATE utilisateurs SET mot_de_passe=? WHERE id=?', [mot_de_passe, id]);
        return res.json({ success: true });
      }

      default:
        return res.json({ success: false, message: 'Action inconnue' });
    }
  } catch (e) {
    console.error(e);
    res.json({ success: false, message: e.message });
  }
});

// ── User detail pages ──────────────────────────────────────────────────────────
async function getUserById(id) {
  const [[user]] = await db.query(`
    SELECT u.*, s.solde, v.niveau as niveau_vip,
      (SELECT COUNT(*) FROM filleuls f WHERE f.user_id=u.id) as nb_filleuls,
      (SELECT COUNT(*) FROM commandes c WHERE c.user_id=u.id AND c.statut='actif') as nb_commandes,
      (SELECT u2.nom FROM utilisateurs u2 WHERE u2.id=u.parrain_id) as parrain_nom
    FROM utilisateurs u
    LEFT JOIN soldes s ON s.user_id=u.id
    LEFT JOIN vip v ON v.user_id=u.id
    WHERE u.id=?`, [id]);
  return user;
}

router.get('/adminxyz/utilisateurs/:id', requireAdminAuth, async (req, res) => {
  try {
    const user = await getUserById(req.params.id);
    if (!user) return res.redirect('/adminxyz/dashboard?t=utilisateurs');
    res.render('admin_user', { user, section: 'menu', pageTitle: user.nom, backUrl: '/adminxyz/utilisateurs', transactions: [], depots: [], retraits: [] });
  } catch (e) { console.error(e); res.status(500).send('Erreur: ' + e.message); }
});

router.get('/adminxyz/utilisateurs/:id/solde', requireAdminAuth, async (req, res) => {
  try {
    const user = await getUserById(req.params.id);
    if (!user) return res.redirect('/adminxyz/dashboard?t=utilisateurs');
    res.render('admin_user', { user, section: 'solde', pageTitle: 'Modifier le solde', backUrl: `/adminxyz/utilisateurs/${user.id}`, transactions: [], depots: [], retraits: [] });
  } catch (e) { console.error(e); res.status(500).send('Erreur: ' + e.message); }
});

router.get('/adminxyz/utilisateurs/:id/infos', requireAdminAuth, async (req, res) => {
  try {
    const user = await getUserById(req.params.id);
    if (!user) return res.redirect('/adminxyz/dashboard?t=utilisateurs');
    res.render('admin_user', { user, section: 'infos', pageTitle: 'Informations & Sécurité', backUrl: `/adminxyz/utilisateurs/${user.id}`, transactions: [], depots: [], retraits: [] });
  } catch (e) { console.error(e); res.status(500).send('Erreur: ' + e.message); }
});

router.get('/adminxyz/utilisateurs/:id/transactions', requireAdminAuth, async (req, res) => {
  try {
    const user = await getUserById(req.params.id);
    if (!user) return res.redirect('/adminxyz/dashboard?t=utilisateurs');
    const [transactions] = await db.query('SELECT * FROM historique_revenus WHERE user_id=? ORDER BY date_creation DESC LIMIT 100', [req.params.id]);
    const [depots]       = await db.query('SELECT * FROM depots WHERE user_id=? ORDER BY date_depot DESC LIMIT 50', [req.params.id]);
    const [retraits]     = await db.query('SELECT * FROM retraits WHERE user_id=? ORDER BY date_demande DESC LIMIT 50', [req.params.id]);
    res.render('admin_user', { user, section: 'transactions', pageTitle: 'Historique des transactions', backUrl: `/adminxyz/utilisateurs/${user.id}`, transactions, depots, retraits });
  } catch (e) { console.error(e); res.status(500).send('Erreur: ' + e.message); }
});

// ── Logout ─────────────────────────────────────────────────────────────────────
router.get('/adminxyz/logout', (req, res) => {
  delete req.session.security_authenticated;
  res.redirect('/adminxyz');
});

module.exports = router;
