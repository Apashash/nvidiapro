const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { requireAuth } = require('../middleware/auth');
const axios = require('axios');
const { getParams } = require('../services/params');

// Countries & operators from AshtechPay /v1/countries
// Stored locally to avoid an extra API call on every page load
const ashtechCountries = [
  { code: 'BJ', name: 'Bénin',          currency: 'XOF', operators: ['Moov Money', 'MTN Mobile Money'] },
  { code: 'BF', name: 'Burkina Faso',    currency: 'XOF', operators: ['Moov Money', 'Orange Money'] },
  { code: 'CM', name: 'Cameroun',        currency: 'XAF', operators: ['Orange Money', 'MTN Mobile Money'] },
  { code: 'CF', name: 'Centrafrique',    currency: 'XAF', operators: ['Orange Money'] },
  { code: 'CG', name: 'Congo',           currency: 'XAF', operators: ['Airtel Money', 'MTN Mobile Money'] },
  { code: 'CI', name: "Côte d'Ivoire",   currency: 'XOF', operators: ['Moov Money', 'Orange Money', 'MTN Mobile Money', 'Wave'] },
  { code: 'GA', name: 'Gabon',           currency: 'XAF', operators: ['Airtel Money', 'Moov Money'] },
  { code: 'ML', name: 'Mali',            currency: 'XOF', operators: ['Moov Money', 'Orange Money'] },
  { code: 'NE', name: 'Niger',           currency: 'XOF', operators: ['Orange Money', 'Airtel Money'] },
  { code: 'CD', name: 'RD Congo',        currency: 'CDF', operators: ['Airtel Money', 'Orange Money', 'Vodacom M-Pesa'] },
  { code: 'SN', name: 'Sénégal',         currency: 'XOF', operators: ['Wave', 'Orange Money', 'Free Money'] },
  { code: 'TD', name: 'Tchad',           currency: 'XAF', operators: ['Airtel Money', 'Moov Money'] },
  { code: 'TG', name: 'Togo',            currency: 'XOF', operators: ['Flooz (Moov)', 'T-Money'] },
];

// ── GET /depot ───────────────────────────────────────────────────────────────
router.get('/depot', requireAuth, async (req, res) => {
  try {
    const user_id = req.session.user_id;
    const [[user]] = await db.query('SELECT * FROM utilisateurs WHERE id = ?', [user_id]);
    const error   = req.session.error  || null;
    const failed  = req.query.failed === '1' ? 'Paiement échoué. Veuillez réessayer.' : null;
    const pending_depot_id = req.session.pending_depot_id || null;
    const pending_numero   = req.session.pending_numero   || null;
    delete req.session.error;
    delete req.session.pending_depot_id;
    delete req.session.pending_numero;
    const params = await getParams();
    const depotMin = parseFloat(params.depot_minimum ?? 200);
    res.render('depot', { user, countries: ashtechCountries, error, failed, depotMin, pending_depot_id, pending_numero });
  } catch (e) {
    console.error('GET /depot error:', e);
    res.redirect('/');
  }
});

// ── POST /depot/process ──────────────────────────────────────────────────────
router.post('/depot/process', requireAuth, async (req, res) => {
  const user_id      = req.session.user_id;
  const montant      = parseFloat(req.body.montant || 0);
  const country_code = (req.body.country_code || '').trim().toUpperCase();
  const operateur    = (req.body.operateur    || '').trim();
  const numero       = (req.body.numero       || '').trim();

  // ── Validations ────────────────────────────────────────────────────────────
  const params = await getParams();
  const depotMin = parseFloat(params.depot_minimum ?? 200);
  if (montant < depotMin) {
    req.session.error = `Le montant minimum de dépôt est de ${depotMin.toLocaleString('fr-FR')} FCFA.`;
    return res.redirect('/depot');
  }
  if (!country_code || !operateur || !numero) {
    req.session.error = 'Tous les champs sont obligatoires';
    return res.redirect('/depot');
  }
  if (!/^[0-9]{6,15}$/.test(numero)) {
    req.session.error = 'Numéro de téléphone invalide (chiffres uniquement, 6–15 chiffres)';
    return res.redirect('/depot');
  }

  // Validate country & operator against our known list
  const country = ashtechCountries.find(c => c.code === country_code);
  if (!country) {
    req.session.error = 'Pays non supporté';
    return res.redirect('/depot');
  }
  if (!country.operators.includes(operateur)) {
    req.session.error = 'Opérateur invalide pour ce pays';
    return res.redirect('/depot');
  }

  const currency  = country.currency;
  const reference = `DEP_${user_id}_${Date.now()}`;

  // ── Insert depot record (en_attente) ────────────────────────────────────────
  let depot_id;
  try {
    const [result] = await db.query(
      "INSERT INTO depots (user_id, montant, methode, numero_transaction, pays, statut) VALUES (?, ?, ?, ?, ?, 'en_attente')",
      [user_id, montant, `${operateur} (${country.name})`, reference, country.name]
    );
    depot_id = result.insertId;
  } catch (e) {
    console.error('depot insert error:', e);
    req.session.error = "Erreur lors de l'enregistrement du dépôt";
    return res.redirect('/depot');
  }

  // ── Call AshtechPay /v1/collect ─────────────────────────────────────────────
  try {
    const apiKey = process.env.ASHTECHPAY_API_KEY;
    if (!apiKey) throw new Error('ASHTECHPAY_API_KEY non définie');

    const protocol = req.headers['x-forwarded-proto'] || req.protocol;
    const host     = req.headers['x-forwarded-host']  || req.headers.host;
    const callback_url = `${protocol}://${host}/ashtechpay_callback`;

    const { data } = await axios.post(
      'https://ashtechpay.top/v1/collect',
      { amount: montant, currency, phone: numero, operator: operateur, country_code, reference, callback_url },
      { headers: { Authorization: `Bearer ${apiKey}`, 'Content-Type': 'application/json' }, timeout: 15000 }
    );

    // Store AshtechPay transaction_id alongside our reference
    await db.query(
      "UPDATE depots SET numero_transaction = ? WHERE id = ?",
      [`${reference}|${data.transaction_id}`, depot_id]
    );

    req.session.pending_depot_id = depot_id;
    req.session.pending_numero   = numero;
    res.redirect('/depot');

  } catch (e) {
    console.error('AshtechPay collect error:', e.response?.data || e.message);
    // Mark deposit as rejected if API call failed
    await db.query("UPDATE depots SET statut = 'rejete' WHERE id = ?", [depot_id]);
    req.session.error = e.response?.data?.message || 'Erreur de connexion au serveur de paiement';
    res.redirect('/depot');
  }
});

// ── GET /depot/status/:id  (polling by client) ───────────────────────────────
router.get('/depot/status/:id', requireAuth, async (req, res) => {
  try {
    const depot_id = parseInt(req.params.id);
    const user_id  = req.session.user_id;
    const [[depot]] = await db.query(
      'SELECT id, statut, montant FROM depots WHERE id = ? AND user_id = ?',
      [depot_id, user_id]
    );
    if (!depot) return res.status(404).json({ error: 'Introuvable' });
    res.json({ statut: depot.statut, montant: depot.montant });
  } catch (e) {
    res.status(500).json({ error: 'Erreur serveur' });
  }
});

// ── POST /ashtechpay_callback  (webhook) ─────────────────────────────────────
// AshtechPay calls this URL when a transaction is completed/failed.
// Payload: { transaction_id, reference, status, amount, credited_amount, currency, ... }
router.post('/ashtechpay_callback', async (req, res) => {
  const { transaction_id, reference, status, amount } = req.body || {};

  if (!reference && !transaction_id) {
    return res.status(400).json({ error: 'Missing reference' });
  }

  try {
    // Find the depot by our reference OR by the stored transaction_id
    const [[depot]] = await db.query(
      `SELECT * FROM depots WHERE numero_transaction LIKE ? OR numero_transaction = ? LIMIT 1`,
      [`${reference}|%`, reference]
    );

    if (!depot) {
      // Also try by transaction_id suffix
      const [[depot2]] = await db.query(
        `SELECT * FROM depots WHERE numero_transaction LIKE ? LIMIT 1`,
        [`%|${transaction_id}`]
      );
      if (!depot2) return res.status(404).json({ error: 'Depot not found' });
      return handleCallback(depot2, status, res, db);
    }
    return handleCallback(depot, status, res, db);

  } catch (e) {
    console.error('AshtechPay callback error:', e);
    res.status(500).json({ error: 'Internal error' });
  }
});

async function handleCallback(depot, status, res, db) {
  // Idempotency: already processed
  if (depot.statut === 'valide') {
    return res.json({ success: true, message: 'Already processed' });
  }

  if (status === 'success') {
    const conn = await db.getConnection();
    try {
      await conn.beginTransaction();

      // Atomic: only credit if still en_attente
      const [upd] = await conn.query(
        "UPDATE depots SET statut = 'valide', date_validation = NOW() WHERE id = ? AND statut = 'en_attente'",
        [depot.id]
      );
      if (upd.affectedRows === 0) {
        await conn.rollback();
        return res.json({ success: true, message: 'Already processed' });
      }

      // Credit balance
      const [[sl]] = await conn.query('SELECT id FROM soldes WHERE user_id = ?', [depot.user_id]);
      if (sl) {
        await conn.query('UPDATE soldes SET solde = solde + ? WHERE user_id = ?', [depot.montant, depot.user_id]);
      } else {
        await conn.query('INSERT INTO soldes (user_id, solde) VALUES (?, ?)', [depot.user_id, depot.montant]);
      }

      await conn.commit();
      console.log(`✓ Dépôt ${depot.id} validé — ${depot.montant} crédité à user ${depot.user_id}`);
      res.json({ success: true, message: 'Deposit validated' });
    } catch (e) {
      await conn.rollback();
      throw e;
    } finally {
      conn.release();
    }
  } else if (status === 'pending') {
    res.json({ success: true, message: 'Payment still pending' });
  } else {
    // failed / cancelled / etc.
    await db.query(
      "UPDATE depots SET statut = 'rejete' WHERE id = ? AND statut = 'en_attente'",
      [depot.id]
    );
    res.json({ success: true, message: 'Deposit rejected' });
  }
}

module.exports = router;
