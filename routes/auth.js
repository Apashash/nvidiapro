const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { getParams } = require('../services/params');
const crypto = require('crypto');

const paysEligibles = {
  '+237': 'Cameroun',
  '+228': 'Togo',
  '+229': 'Bénin',
  '+225': "Côte d'Ivoire",
  '+226': 'Burkina Faso',
  '+241': 'Gabon',
  '+242': 'Congo Brazzaville',
  '+227': 'Niger',
  '+223': 'Mali',
};

const isoMap = {
  CM: '+237', TG: '+228', BJ: '+229',
  CI: '+225', BF: '+226', GA: '+241', CG: '+242',
  NE: '+227', ML: '+223',
};

function isValidIndicatif(indicatif) {
  return /^\+[1-9]\d{0,3}$/.test(String(indicatif || '').trim());
}

function normalizeIndicatif(indicatif) {
  const value = String(indicatif || '').trim();
  return /^\d{1,4}$/.test(value) ? `+${value}` : value;
}

function generateReferralCode() {
  return `Dangote${crypto.randomInt(10_000_000, 100_000_000)}`;
}

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
    const { telephone, mot_de_passe } = req.body;
    const indicatif = normalizeIndicatif(req.body.indicatif);
    const tel = (telephone || '').replace(/[^0-9]/g, '');
    const full_tel = indicatif + tel;

    req.session.form_data = { indicatif, telephone };

    if (!indicatif || !tel || !mot_de_passe) throw new Error('Tous les champs sont obligatoires');
    if (!isValidIndicatif(indicatif)) throw new Error('Indicatif de pays non valide.');
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
  const form_data = req.session.form_data || null;
  delete req.session.error;
  delete req.session.form_data;
  res.render('inscription1', { code_parrain, error, form_data, pays_eligibles: paysEligibles, iso_map: isoMap });
});

// GET /inscription1
router.get('/inscription1', (req, res) => {
  res.set('Cache-Control', 'no-store, no-cache, must-revalidate');
  const code_parrain = req.query.p || req.session.parrain_code || '';
  if (req.query.p) req.session.parrain_code = req.query.p;
  const error = req.session.error || null;
  const form_data = req.session.form_data || null;
  delete req.session.error;
  delete req.session.form_data;
  res.render('inscription1', { code_parrain, error, form_data, pays_eligibles: paysEligibles, iso_map: isoMap });
});

// POST /inscription1
router.post('/inscription1', async (req, res) => {
  try {
    const { nom, telephone, mot_de_passe, confirmation } = req.body;
    const paysSelectionne = req.body.pays;
    const indicatif = normalizeIndicatif(req.body.indicatif);
    const paysConnu = paysEligibles[indicatif];
    const pays = paysConnu || paysSelectionne;
    const telLocal = (telephone || '').replace(/[^0-9]/g, '');
    const tel = indicatif + telLocal;
    req.session.form_data = { nom, pays, indicatif, telephone };

    if (!nom || !pays || !indicatif || !telLocal || !mot_de_passe) {
      throw new Error('Tous les champs sont obligatoires');
    }
    if (!paysConnu && paysSelectionne !== 'Autre') {
      throw new Error('Veuillez sélectionner un pays ou choisir « Autre ».');
    }
    if (!isValidIndicatif(indicatif)) throw new Error('Indicatif de pays non valide.');
    if (!/^\d{5,15}$/.test(telLocal)) throw new Error('Numéro de téléphone invalide.');
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

    // Generate a new referral code while preserving all existing legacy codes.
    let code_parrainage = '';
    for (let attempt = 0; attempt < 5; attempt += 1) {
      const candidate = generateReferralCode();
      const [matches] = await db.query(
        'SELECT id FROM utilisateurs WHERE code_parrainage = ?',
        [candidate]
      );
      if (!matches.length) {
        code_parrainage = candidate;
        break;
      }
    }
    if (!code_parrainage) throw new Error('Impossible de générer un code de parrainage unique.');

    const conn = await db.getConnection();
    try {
      await conn.beginTransaction();

      const [result] = await conn.query(
        'INSERT INTO utilisateurs (nom, pays, telephone, mot_de_passe, parrain_id, code_parrainage) VALUES (?, ?, ?, ?, ?, ?)',
        [nom, pays, tel, mot_de_passe, parrain_id, code_parrainage]
      );
      const user_id = result.insertId;

      const { welcome_bonus } = await getParams();
      const parsedBonus = Number(welcome_bonus);
      const bonusAmount = (welcome_bonus !== undefined && Number.isFinite(parsedBonus) && parsedBonus >= 0) ? parsedBonus : 250;
      await conn.query('INSERT INTO soldes (user_id, solde, solde_precedent) VALUES (?, ?, ?)', [user_id, bonusAmount, bonusAmount]);
      if (bonusAmount > 0) {
        await conn.query(
          "INSERT INTO historique_revenus (user_id, montant, type, source) VALUES (?, ?, 'bonus', 'bonus_inscription')",
          [user_id, bonusAmount]
        );
      }
      await conn.query('INSERT INTO vip (user_id, niveau, pourcentage, invitations_requises, invitations_actuelles) VALUES (?, 0, 0, 3, 0)', [user_id]).catch(() => {});
      await conn.query('INSERT INTO filleuls (user_id) VALUES (?)', [user_id]).catch(() => {});
      await conn.query('INSERT INTO connexions_journalieres (user_id) VALUES (?)', [user_id]).catch(() => {});
      await conn.query('INSERT INTO pieces (user_id, solde, solde_precedent) VALUES (?, 0, 0)', [user_id]).catch(() => {});

      await conn.commit();

      req.session.user_id = user_id;
      req.session.user_nom = nom;
      req.session.pays = pays;
      delete req.session.parrain_code;
      delete req.session.form_data;
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
