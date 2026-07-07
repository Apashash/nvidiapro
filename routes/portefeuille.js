const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { requireAuth } = require('../middleware/auth');

const pays_methodes = {
  'Cameroun': ['mtn', 'orange'],
  'Togo': ['tmoney', 'moov'],
  'Sénégal': ['orange', 'free', 'wave'],
  'Niger': ['airtel'],
  'Mali': ['orange', 'moov', 'wave'],
  "Côte d'Ivoire": ['orange', 'mtn', 'moov', 'wave'],
  'Gabon': ['airtel', 'moov'],
  'République démocratique du Congo': ['m_pesa', 'orange', 'afrimoney', 'airtel'],
  'Congo-Brazzaville': ['airtel', 'mtn'],
  'Burkina Faso': ['moov', 'orange'],
  'Bénin': ['mtn', 'moov'],
};

const methodes_noms = {
  mtn: 'MTN Mobile Money', orange: 'Orange Money', tmoney: 'T-Money',
  moov: 'Moov Money', free: 'Free Money', wave: 'Wave',
  airtel: 'Airtel Money', m_pesa: 'M-PESA', afrimoney: 'Afrimoney',
};

router.get('/portefeuille', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  try {
    const [tpRows] = await db.query('SELECT * FROM transaction_passwords WHERE user_id = ?', [user_id]);
    const has_transaction_password = tpRows.length > 0;

    const [wRows] = await db.query('SELECT * FROM portefeuilles WHERE user_id = ?', [user_id]);
    const has_wallet = wRows.length > 0;
    const wallet_data = has_wallet ? wRows[0] : null;

    const error = req.session.error || null;
    const success = req.session.success || null;
    delete req.session.error;
    delete req.session.success;

    res.render('portefeuille', { has_wallet, wallet_data, has_transaction_password, pays_methodes, methodes_noms, error, success });
  } catch (e) { console.error(e); res.redirect('/'); }
});

router.post('/portefeuille', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  try {
    const [tpRows] = await db.query('SELECT * FROM transaction_passwords WHERE user_id = ?', [user_id]);
    const has_transaction_password = tpRows.length > 0;

    if (!has_transaction_password) {
      req.session.error = "Vous devez d'abord créer un mot de passe de transaction";
      return res.redirect('/portefeuille');
    }

    const transaction_password = req.body.transaction_password || '';
    const [[tp]] = await db.query('SELECT * FROM transaction_passwords WHERE user_id = ? AND password = ?', [user_id, transaction_password]);
    if (!tp) {
      req.session.error = 'Mot de passe de transaction incorrect';
      return res.redirect('/portefeuille');
    }

    const nom_portefeuille = (req.body.nom_portefeuille || '').trim();
    const pays = req.body.pays || '';
    const methode_paiement = req.body.methode_paiement || '';
    const numero_telephone = (req.body.numero_telephone || '').trim();
    const confirm_telephone = (req.body.confirm_telephone || '').trim();

    if (!nom_portefeuille || !pays || !methode_paiement || !numero_telephone) {
      req.session.error = 'Tous les champs sont obligatoires';
      return res.redirect('/portefeuille');
    }
    if (numero_telephone !== confirm_telephone) {
      req.session.error = 'Les numéros de téléphone ne correspondent pas';
      return res.redirect('/portefeuille');
    }
    if (!/^\d+$/.test(numero_telephone)) {
      req.session.error = 'Le numéro de téléphone ne doit contenir que des chiffres';
      return res.redirect('/portefeuille');
    }

    const [wRows] = await db.query('SELECT id FROM portefeuilles WHERE user_id = ?', [user_id]);
    if (wRows.length) {
      await db.query('UPDATE portefeuilles SET nom_portefeuille = ?, pays = ?, methode_paiement = ?, numero_telephone = ? WHERE user_id = ?',
        [nom_portefeuille, pays, methode_paiement, numero_telephone, user_id]);
      req.session.success = 'Portefeuille mis à jour avec succès';
    } else {
      await db.query('INSERT INTO portefeuilles (user_id, nom_portefeuille, pays, methode_paiement, numero_telephone) VALUES (?, ?, ?, ?, ?)',
        [user_id, nom_portefeuille, pays, methode_paiement, numero_telephone]);
      req.session.success = 'Portefeuille créé avec succès';
    }
    res.redirect('/portefeuille');
  } catch (e) {
    req.session.error = 'Erreur: ' + e.message;
    res.redirect('/portefeuille');
  }
});

// Update transaction password
router.post('/update_transaction_password', requireAuth, async (req, res) => {
  const user_id = req.session.user_id;
  const { old_pin, new_pin, confirm_pin } = req.body;

  try {
    const [[existing]] = await db.query('SELECT * FROM transaction_passwords WHERE user_id = ?', [user_id]);
    if (existing && existing.password !== old_pin) {
      return res.json({ success: false, message: 'Ancien code incorrect' });
    }
    if (!/^\d{4}$/.test(new_pin)) {
      return res.json({ success: false, message: 'Le code doit contenir exactement 4 chiffres' });
    }
    if (new_pin !== confirm_pin) {
      return res.json({ success: false, message: 'Les codes ne correspondent pas' });
    }
    if (existing) {
      await db.query('UPDATE transaction_passwords SET password = ? WHERE user_id = ?', [new_pin, user_id]);
    } else {
      await db.query('INSERT INTO transaction_passwords (user_id, password) VALUES (?, ?)', [user_id, new_pin]);
    }
    res.json({ success: true, message: 'Mot de passe mis à jour avec succès' });
  } catch (e) {
    res.json({ success: false, message: 'Erreur: ' + e.message });
  }
});

module.exports = router;
