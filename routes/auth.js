const express = require('express');
const router = express.Router();
const db = require('../config/db');

const paysEligibles = {
  '+229': 'Bénin',
  '+226': 'Burkina Faso',
  '+237': 'Cameroun',
  '+221': 'Sénégal',
  '+225': "Côte d'Ivoire",
  '+223': 'Mali',
  '+228': 'Togo',
};

const isoMap = {
  BJ: '+229', BF: '+226', CM: '+237',
  CG: '+242', CI: '+225', ML: '+223', TG: '+228',
};

// GET /connexion
router.get('/connexion', (req, res) => {
  if (req.session.user_id) return res.redirect('/');
  const error = req.session.error || null;
  const form_data = req.session.form_data || { indicatif: '+237', telephone: '' };
  delete req.session.error;
  delete req.session.form_data;
  res.render('connexion', { error, form_data, paysEligibles, isoMap });
});

// POST /connexion
router.post('/connexion', async (req, res) => {
  try {
    const { indicatif, telephone, mot_de_passe } = req.body;
    const tel = (telephone || '').replace(/[^0-9]/g, '');
    const full_tel = indicatif + tel;

    req.session.form_data = { indicatif, telephone };

    if (!indicatif || !tel || !mot_de_passe) throw new Error('Tous les champs sont obligatoires');
    if (!paysEligibles[indicatif]) throw new Error('Code pays non valide.');
    if (!/^\d{5,15}$/.test(tel)) throw new Error('Numéro de téléphone invalide.');

    const [rows] = await db.query('SELECT * FROM utilisateurs WHERE telephone = ?', [full_tel]);
    if (!rows.length) throw new Error('Aucun compte trouvé avec ce numéro');

    const user = rows[0];
    if (user.mot_de_passe !== mot_de_passe) throw new Error('Mot de passe incorrect');

    req.session.user_id = user.id;
    req.session.user_nom = user.nom;
    req.session.pays = user.pays;
    delete req.session.form_data;
    res.redirect('/');
  } catch (e) {
    req.session.error = e.message;
    res.redirect('/connexion');
  }
});

// GET /inscription — rend directement la page (évite le redirect que Safari mobile télécharge)
router.get('/inscription', (req, res) => {
  res.set('Cache-Control', 'no-store, no-cache, must-revalidate');
  const p = req.query.p || '';
  if (p) req.session.parrain_code = p;
  const code_parrain = p || req.session.parrain_code || '';
  const error = req.session.error || null;
  delete req.session.error;
  res.render('inscription1', { code_parrain, error, pays_eligibles: paysEligibles });
});

// GET /inscription1
router.get('/inscription1', (req, res) => {
  res.set('Cache-Control', 'no-store, no-cache, must-revalidate');
  const code_parrain = req.query.p || req.session.parrain_code || '';
  if (req.query.p) req.session.parrain_code = req.query.p;
  const error = req.session.error || null;
  delete req.session.error;
  res.render('inscription1', { code_parrain, error, pays_eligibles: paysEligibles });
});

// POST /inscription1
router.post('/inscription1', async (req, res) => {
  try {
    const { nom, pays, indicatif, telephone, mot_de_passe, confirmation } = req.body;
    const tel = indicatif + (telephone || '').replace(/[^0-9]/g, '');

    if (!nom || !pays || !tel || !mot_de_passe) throw new Error('Tous les champs sont obligatoires');
    if (mot_de_passe !== confirmation) throw new Error('Les mots de passe ne correspondent pas');

    // Check duplicate
    const [existing] = await db.query('SELECT id FROM utilisateurs WHERE telephone = ?', [tel]);
    if (existing.length) throw new Error('Un compte existe déjà avec ce numéro de téléphone');

    // Parrain
    let parrain_id = null;
    const code_parrain = req.session.parrain_code || '';
    if (code_parrain) {
      const [parrains] = await db.query(
        'SELECT id FROM utilisateurs WHERE code_parrainage = ? OR RIGHT(code_parrainage, 5) = ?',
        [code_parrain, code_parrain]
      );
      if (parrains.length) parrain_id = parrains[0].id;
    }

    // Generate referral code
    const code_parrainage = 'NV' + Date.now().toString().slice(-6) + Math.floor(Math.random() * 999);

    const conn = await db.getConnection();
    try {
      await conn.beginTransaction();

      const [result] = await conn.query(
        'INSERT INTO utilisateurs (nom, pays, telephone, mot_de_passe, parrain_id, code_parrainage) VALUES (?, ?, ?, ?, ?, ?)',
        [nom, pays, tel, mot_de_passe, parrain_id, code_parrainage]
      );
      const user_id = result.insertId;

      await conn.query('INSERT INTO soldes (user_id, solde, solde_precedent) VALUES (?, 250, 250)', [user_id]);
      await conn.query('INSERT INTO vip (user_id, niveau, pourcentage, invitations_requises, invitations_actuelles) VALUES (?, 0, 0, 3, 0)', [user_id]).catch(() => {});
      await conn.query('INSERT INTO filleuls (user_id) VALUES (?)', [user_id]).catch(() => {});
      await conn.query('INSERT INTO connexions_journalieres (user_id) VALUES (?)', [user_id]).catch(() => {});
      await conn.query('INSERT INTO pieces (user_id, solde, solde_precedent) VALUES (?, 0, 0)', [user_id]).catch(() => {});

      await conn.commit();

      req.session.user_id = user_id;
      req.session.user_nom = nom;
      req.session.pays = pays;
      delete req.session.parrain_code;
      res.redirect('/');
    } catch (e) {
      await conn.rollback();
      throw e;
    } finally {
      conn.release();
    }
  } catch (e) {
    req.session.error = e.message;
    res.redirect('/inscription1');
  }
});

// GET /deconnexion
router.get('/deconnexion', (req, res) => {
  req.session.destroy(() => res.redirect('/connexion'));
});

module.exports = router;
