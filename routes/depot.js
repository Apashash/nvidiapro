const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { requireAuth } = require('../middleware/auth');
const axios = require('axios');
const { getParams } = require('../services/params');

// Countries & operators from AshtechPay /v1/countries
// Stored locally to avoid an extra API call on every page load.
// `currency` must match the plain ISO code AshtechPay expects in /v1/collect
// (XAF, XOF, GNF, CDF…) — NOT the per-country display suffixes (XOFC, XAFG…)
// shown on the docs page, which are cosmetic only.
const ashtechCountries = [
  { code: 'CM', name: 'Cameroun',          currency: 'XAF', operators: ['Orange Money', 'MTN Mobile Money'] },
  { code: 'TG', name: 'Togo',              currency: 'XOF', operators: ['Flooz (Moov)', 'T-Money'] },
  { code: 'BJ', name: 'Bénin',             currency: 'XOF', operators: ['Moov Money', 'MTN Mobile Money'] },
  { code: 'CI', name: "Côte d'Ivoire",     currency: 'XOF', operators: ['Moov Money', 'Orange Money', 'MTN Mobile Money', 'Wave'] },
  { code: 'BF', name: 'Burkina Faso',      currency: 'XOF', operators: ['Moov Money', 'Orange Money'] },
  { code: 'GA', name: 'Gabon',             currency: 'XAF', operators: ['Airtel Money', 'Moov Money'] },
  { code: 'CG', name: 'Congo Brazzaville', currency: 'XAF', operators: ['Airtel Money', 'MTN Mobile Money'] },
];

// Operators that AshtechPay may ask an OTP for, per the docs' "OTP requis" table.
// Used only to decide whether to show a short "un code peut vous être demandé"
// hint up front — the actual otp_required signal always comes from the API
// response, so this list is informational, not authoritative.
const otpProneOperators = new Set(['Orange Money', 'Wave']);

// ── GET /depot ───────────────────────────────────────────────────────────────
router.get('/depot', requireAuth, async (req, res) => {
  try {
    const user_id = req.session.user_id;
    const [[user]] = await db.query('SELECT * FROM utilisateurs WHERE id = ?', [user_id]);
    const error   = req.session.error  || null;
    const failed  = req.query.failed === '1' ? 'Paiement échoué. Veuillez réessayer.' : null;
    const pending_depot_id = req.session.pending_depot_id || null;
    const pending_numero   = req.session.pending_numero   || null;
    const pending_wave_url = req.session.pending_wave_url || null;
    const otp_pending      = req.session.otp_pending      || null;
    delete req.session.error;
    delete req.session.pending_depot_id;
    delete req.session.pending_numero;
    delete req.session.pending_wave_url;
    const params = await getParams();
    const depotMin = parseFloat(params.depot_minimum ?? 200);
    res.render('depot', {
      user, countries: ashtechCountries, error, failed, depotMin,
      pending_depot_id, pending_numero, pending_wave_url, otp_pending,
    });
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

    const notify_url = buildNotifyUrl(req);
    const payload = { amount: montant, currency, phone: numero, operator: operateur, country_code, reference, notify_url };

    const { data } = await axios.post(
      'https://ashtechpay.top/v1/collect',
      payload,
      { headers: { Authorization: `Bearer ${apiKey}`, 'Content-Type': 'application/json' }, timeout: 15000 }
    );

    await onCollectAccepted(req, depot_id, data, apiKey);
    res.redirect('/depot');

  } catch (e) {
    const apiError = e.response?.data;

    // ── OTP requis : ne pas rejeter le dépôt, demander le code à l'utilisateur ──
    if (e.response?.status === 400 && apiError?.error === 'otp_required') {
      req.session.otp_pending = {
        depot_id,
        payload: { amount: montant, currency, phone: numero, operator: operateur, country_code, reference },
        ussd_code: apiError.ussd_code || null,
        message: apiError.message || 'Un code de confirmation (OTP) est requis pour finaliser ce paiement.',
      };
      req.session.pending_numero = numero;
      return res.redirect('/depot');
    }

    console.error('AshtechPay collect error:', apiError || e.message);
    // Mark deposit as rejected if API call failed for any other reason
    await db.query("UPDATE depots SET statut = 'rejete' WHERE id = ?", [depot_id]);
    req.session.error = apiError?.message || 'Erreur de connexion au serveur de paiement';
    res.redirect('/depot');
  }
});

// ── POST /depot/otp/verify — soumission du code OTP pour les opérateurs
// (Orange Money, principalement) qui l'exigent avant de confirmer la collecte.
router.post('/depot/otp/verify', requireAuth, async (req, res) => {
  const otp_pending = req.session.otp_pending;
  const otp = (req.body.otp || '').trim();

  if (!otp_pending) {
    req.session.error = 'Aucun paiement en attente de code OTP.';
    return res.redirect('/depot');
  }
  if (!/^[0-9]{4,8}$/.test(otp)) {
    req.session.error = 'Code OTP invalide (4 à 8 chiffres).';
    return res.redirect('/depot');
  }

  const { depot_id, payload } = otp_pending;

  try {
    const apiKey = process.env.ASHTECHPAY_API_KEY;
    if (!apiKey) throw new Error('ASHTECHPAY_API_KEY non définie');

    const notify_url = buildNotifyUrl(req);
    const { data } = await axios.post(
      'https://ashtechpay.top/v1/collect',
      { ...payload, otp, notify_url },
      { headers: { Authorization: `Bearer ${apiKey}`, 'Content-Type': 'application/json' }, timeout: 15000 }
    );

    delete req.session.otp_pending;
    await onCollectAccepted(req, depot_id, data, apiKey);
    res.redirect('/depot');

  } catch (e) {
    const apiError = e.response?.data;

    // Code invalide/expiré : on redemande l'OTP plutôt que de rejeter le dépôt.
    if (e.response?.status === 400 && apiError?.error === 'otp_required') {
      req.session.otp_pending = {
        ...otp_pending,
        ussd_code: apiError.ussd_code || otp_pending.ussd_code,
        message: apiError.message || 'Code OTP invalide ou expiré. Veuillez réessayer.',
      };
      req.session.error = apiError.message || 'Code OTP invalide ou expiré. Veuillez réessayer.';
      return res.redirect('/depot');
    }

    console.error('AshtechPay OTP verify error:', apiError || e.message);
    delete req.session.otp_pending;
    await db.query("UPDATE depots SET statut = 'rejete' WHERE id = ?", [depot_id]);
    req.session.error = apiError?.message || 'Erreur de connexion au serveur de paiement';
    res.redirect('/depot');
  }
});

// ── GET /depot/otp/cancel — abandonne un paiement en attente d'OTP ───────────
router.get('/depot/otp/cancel', requireAuth, async (req, res) => {
  const otp_pending = req.session.otp_pending;
  delete req.session.otp_pending;
  if (otp_pending?.depot_id) {
    await db.query("UPDATE depots SET statut = 'rejete' WHERE id = ? AND statut = 'en_attente'", [otp_pending.depot_id]);
  }
  res.redirect('/depot');
});

function buildNotifyUrl(req) {
  const protocol = req.headers['x-forwarded-proto'] || req.protocol;
  const host     = req.headers['x-forwarded-host']  || req.headers.host;
  return `${protocol}://${host}/ashtechpay_callback`;
}

// Shared handling for any AshtechPay /v1/collect call that came back 202 —
// whether that happened on the first try (USSD push / Wave) or after
// resubmitting with an `otp`. Stores the transaction id, kicks off the
// server-side poller, and sets what the pending-payment card needs to render.
async function onCollectAccepted(req, depot_id, data, apiKey) {
  const [[depot]] = await db.query('SELECT numero_transaction FROM depots WHERE id = ?', [depot_id]);
  const reference = (depot?.numero_transaction || '').split('|')[0];

  await db.query(
    "UPDATE depots SET numero_transaction = ? WHERE id = ?",
    [`${reference}|${data.transaction_id}`, depot_id]
  );

  // Poll AshtechPay every 3s as a backup to the webhook, until the status changes.
  pollTransactionStatus(depot_id, data.transaction_id, apiKey);

  req.session.pending_depot_id = depot_id;
  req.session.pending_numero   = data.phone || req.session.pending_numero || null;
  // Wave doesn't push a USSD prompt — the client must open a link to confirm.
  req.session.pending_wave_url = data.flow === 'wave' ? data.wave_url : null;
}

// ── Server-side polling of AshtechPay GET /v1/transaction/:id ───────────────
// Runs alongside the webhook as a fallback (in case the webhook never arrives).
// Polls every 3s until the status is a final one (success/failed) or a
// timeout is reached, then applies the same finalization logic as the webhook.
const POLL_INTERVAL_MS = 3000;
const POLL_TIMEOUT_MS  = 5 * 60 * 1000; // stop after 5 minutes

function pollTransactionStatus(depot_id, transaction_id, apiKey) {
  const startedAt = Date.now();

  const tick = async () => {
    // Enforce the timeout up front, before any DB/API call and regardless of
    // which branch (success/error) would otherwise reschedule — guarantees
    // polling always terminates and never leaks an unbounded timer chain.
    if (Date.now() - startedAt > POLL_TIMEOUT_MS) {
      console.warn(`⏱ Polling timeout pour depot ${depot_id} (transaction ${transaction_id})`);
      return;
    }

    try {
      const [[depot]] = await db.query('SELECT * FROM depots WHERE id = ?', [depot_id]);
      if (!depot || depot.statut !== 'en_attente') return; // already finalized (e.g. by webhook)

      const { data } = await axios.get(
        `https://ashtechpay.top/v1/transaction/${transaction_id}`,
        { headers: { Authorization: `Bearer ${apiKey}` }, timeout: 10000 }
      );

      if (data.status === 'success' || data.status === 'failed') {
        await finalizeDepot(depot, data.status === 'success' ? 'success' : 'failed');
        return; // status changed to a final state — stop polling
      }

      // still pending — check again in 3s
      setTimeout(tick, POLL_INTERVAL_MS);
    } catch (e) {
      console.error(`AshtechPay polling error (depot ${depot_id}):`, e.response?.data || e.message);
      // keep retrying until timeout, in case of a transient network/API error
      setTimeout(tick, POLL_INTERVAL_MS);
    }
  };

  setTimeout(tick, POLL_INTERVAL_MS);
}

// ── GET /depot/status/:id  (polling by client) ───────────────────────────────
// If still en_attente, actively re-checks AshtechPay before answering — this
// is what makes the "Vérifier" button on /historique work even after the
// background poller has timed out (5 min) or the server has restarted.
router.get('/depot/status/:id', requireAuth, async (req, res) => {
  try {
    const depot_id = parseInt(req.params.id);
    const user_id  = req.session.user_id;
    const [[depot]] = await db.query(
      'SELECT * FROM depots WHERE id = ? AND user_id = ?',
      [depot_id, user_id]
    );
    if (!depot) return res.status(404).json({ error: 'Introuvable' });

    if (depot.statut === 'en_attente') {
      const transaction_id = (depot.numero_transaction || '').split('|')[1];
      const apiKey = process.env.ASHTECHPAY_API_KEY;
      if (transaction_id && apiKey) {
        try {
          const { data } = await axios.get(
            `https://ashtechpay.top/v1/transaction/${transaction_id}`,
            { headers: { Authorization: `Bearer ${apiKey}` }, timeout: 10000 }
          );
          if (data.status === 'success' || data.status === 'failed') {
            await finalizeDepot(depot, data.status);
            depot.statut = data.status === 'success' ? 'valide' : 'rejete';
          }
        } catch (e) {
          // Live check failed (network/API) — fall back to the last known DB status
          console.error(`AshtechPay live status check error (depot ${depot_id}):`, e.response?.data || e.message);
        }
      }
    }

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
      const result2 = await finalizeDepot(depot2, status);
      return res.json(result2);
    }
    const result = await finalizeDepot(depot, status);
    return res.json(result);

  } catch (e) {
    console.error('AshtechPay callback error:', e);
    res.status(500).json({ error: 'Internal error' });
  }
});

// Shared finalization logic — called from both the webhook handler and the
// server-side status poller (pollTransactionStatus above). Idempotent: only
// the first caller to flip a depot out of 'en_attente' actually credits it.
async function finalizeDepot(depot, status) {
  // Idempotency: already processed
  if (depot.statut === 'valide') {
    return { success: true, message: 'Already processed' };
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
        return { success: true, message: 'Already processed' };
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
      return { success: true, message: 'Deposit validated' };
    } catch (e) {
      await conn.rollback();
      throw e;
    } finally {
      conn.release();
    }
  } else if (status === 'pending') {
    return { success: true, message: 'Payment still pending' };
  } else {
    // failed / cancelled / etc.
    await db.query(
      "UPDATE depots SET statut = 'rejete' WHERE id = ? AND statut = 'en_attente'",
      [depot.id]
    );
    return { success: true, message: 'Deposit rejected' };
  }
}

module.exports = router;
