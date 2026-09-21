const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { requireAuth } = require('../middleware/auth');
const axios = require('axios');
const crypto = require('crypto');
const { getParams } = require('../services/params');
const {
  ASHTECH_API_BASE,
  SOLEASPAY_API_BASE,
  getAshtechApiKey,
  getAshtechCountries,
  getOperatorProvider,
  getSoleasApiKey,
} = require('../services/paymentProviders');

const countryDialCodes = {
  CM: '237',
  TG: '228',
  BJ: '229',
  CI: '225',
  BF: '226',
  GA: '241',
  CG: '242',
  NE: '227',
  ML: '223',
  SN: '221',
};

// Legacy fallback moved to services/paymentProviders.js.
/*
const fallbackAshtechCountries = [
  { code: 'CM', name: 'Cameroun',          currency: 'XAF', operators: ['Orange Money', 'MTN Mobile Money'] },
  { code: 'TG', name: 'Togo',              currency: 'XOF', operators: ['Flooz (Moov)', 'T-Money'] },
  { code: 'BJ', name: 'Bénin',             currency: 'XOF', operators: ['Moov Money', 'MTN Mobile Money'] },
  { code: 'CI', name: "Côte d'Ivoire",     currency: 'XOF', operators: ['Moov Money', 'Orange Money', 'MTN Mobile Money', 'Wave'] },
  { code: 'BF', name: 'Burkina Faso',      currency: 'XOF', operators: ['Moov Money', 'Orange Money'] },
  { code: 'GA', name: 'Gabon',             currency: 'XAF', operators: ['Airtel Money', 'Moov Money'] },
  { code: 'CG', name: 'Congo Brazzaville', currency: 'XAF', operators: ['Airtel Money', 'MTN Mobile Money'] },
  { code: 'NE', name: 'Niger',            currency: 'XOF', operators: ['Airtel Money'] },
  { code: 'ML', name: 'Mali',             currency: 'XOF', operators: ['Moov Money', 'Orange Money'] },
];
*/

// Operators that AshtechPay may ask an OTP for, per the docs' "OTP requis" table.
// Used only to decide whether to show a short "un code peut vous être demandé"
// hint up front — the actual otp_required signal always comes from the API
// response, so this list is informational, not authoritative.
const otpProneOperators = new Set(['Orange Money', 'Wave']);

function formatAshtechError(error) {
  const status = error.response?.status;
  const responseBody = error.response?.data;
  const prefix = status ? `AshTechPay (HTTP ${status})` : 'AshTechPay';

  if (typeof responseBody === 'string' && responseBody.trim()) {
    return `${prefix} : ${responseBody.trim().slice(0, 300)}`;
  }

  if (responseBody && typeof responseBody === 'object') {
    const code = responseBody.error || responseBody.code || responseBody.type;
    const message = responseBody.message || responseBody.detail || responseBody.error_description;
    if (code && message) return `${prefix} [${code}] : ${message}`;
    if (message) return `${prefix} : ${message}`;
    if (code) return `${prefix} [${code}]`;
  }

  if (error.code === 'ECONNABORTED' || error.code === 'ETIMEDOUT') {
    return 'AshTechPay : délai d’attente dépassé, le serveur n’a pas répondu.';
  }
  if (error.code === 'ENOTFOUND' || error.code === 'EAI_AGAIN') {
    return 'AshTechPay : serveur de paiement introuvable.';
  }
  if (!error.response && error.message) {
    return `AshTechPay : ${error.message}`;
  }
  return 'AshTechPay : erreur inconnue du serveur de paiement.';
}

/*
function normalizeAshtechCountries(payload) {
  const countries = Array.isArray(payload)
    ? payload
    : (Array.isArray(payload?.countries) ? payload.countries : []);

  return countries
    .map(country => {
      const operators = Array.isArray(country.operators)
        ? country.operators.map(operator => {
            if (typeof operator === 'string') return operator.trim();
            return String(operator?.code || operator?.name || '').trim();
          }).filter(Boolean)
        : [];
      return {
        code: String(country.code || country.country_code || '').trim().toUpperCase(),
        name: String(country.name || country.country || '').trim(),
        currency: String(country.currency || '').trim().toUpperCase(),
        operators,
      };
    })
    .filter(country => country.code && country.name && country.currency && country.operators.length);
}

async function getAshtechCountries() {
  const now = Date.now();
  if (ashtechCountriesCache && now - ashtechCountriesCachedAt < COUNTRY_CACHE_TTL_MS) {
    return ashtechCountriesCache;
  }

  const apiKey = getAshtechApiKey();
  if (!apiKey) return fallbackAshtechCountries;

  try {
    const { data } = await axios.get(`${ASHTECH_API_BASE}/v1/countries`, {
      headers: { Authorization: `Bearer ${apiKey}` },
      timeout: 10000,
    });
    const countries = normalizeAshtechCountries(data);
    if (!countries.length) throw new Error('Catalogue AshTechPay vide ou invalide');
    ashtechCountriesCache = countries;
    ashtechCountriesCachedAt = now;
    return countries;
  } catch (error) {
    console.error('AshTechPay countries catalogue error:', error.response?.data || error.message);
    return ashtechCountriesCache || fallbackAshtechCountries;
  }
}
*/

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
    const depotForm        = req.session.depot_form || {};
    delete req.session.error;
    delete req.session.pending_depot_id;
    delete req.session.pending_numero;
    delete req.session.pending_wave_url;
    delete req.session.depot_form;
    const params = await getParams();
    const depotMin = parseFloat(params.depot_minimum ?? 200);
    const countries = await getAshtechCountries();
    res.render('depot', {
      user, countries, error, failed, depotMin,
      pending_depot_id, pending_numero, pending_wave_url, otp_pending,
      selectedCountryCode: depotForm.country_code || '',
      selectedOperator: depotForm.operateur || '',
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
  const numeroInput  = (req.body.numero       || '').trim();
  // Keep the selected country/operator after an API or validation error,
  // without storing the phone number in the session.
  req.session.depot_form = { country_code, operateur };

  // ── Validations ────────────────────────────────────────────────────────────
  const params = await getParams();
  const depotMin = parseFloat(params.depot_minimum ?? 200);
  if (!Number.isFinite(montant) || montant <= 0 || montant < depotMin) {
    req.session.error = `Le montant minimum de dépôt est de ${depotMin.toLocaleString('fr-FR')} FCFA.`;
    return res.redirect('/depot');
  }
  if (!country_code || !operateur || !numeroInput) {
    req.session.error = 'Tous les champs sont obligatoires';
    return res.redirect('/depot');
  }
  if (!/^[0-9]{6,15}$/.test(numeroInput)) {
    req.session.error = 'Numéro de téléphone invalide (chiffres uniquement, 6–15 chiffres)';
    return res.redirect('/depot');
  }

  // Validate country & operator against our known list
  const countries = await getAshtechCountries();
  const country = countries.find(c => c.code === country_code);
  if (!country) {
    req.session.error = 'Pays non supporté';
    return res.redirect('/depot');
  }
  if (!country.operators.includes(operateur)) {
    req.session.error = 'Opérateur invalide pour ce pays';
    return res.redirect('/depot');
  }

  const providerConfig = await getOperatorProvider(country_code, operateur);
  if (providerConfig.provider === 'soleaspay' && !providerConfig.serviceId) {
    req.session.error = 'SoleasPay : le service de paiement n’est pas configuré pour cet opérateur.';
    return res.redirect('/depot');
  }

  const numero = normalizeInternationalPhone(numeroInput, country_code);
  if (!/^[0-9]{8,15}$/.test(numero)) {
    req.session.error = 'Numéro de téléphone invalide pour le pays sélectionné';
    return res.redirect('/depot');
  }

  const currency  = country.currency;
  const reference = `DEP_${user_id}_${Date.now()}`;

  // ── Insert depot record (en_attente) ────────────────────────────────────────
  let depot_id;
  try {
    const [result] = await db.query(
      "INSERT INTO depots (user_id, montant, methode, numero_transaction, pays, fournisseur, provider_service_id, statut) VALUES (?, ?, ?, ?, ?, ?, ?, 'en_attente')",
      [user_id, montant, `${operateur} (${country.name})`, reference, country.name,
        providerConfig.provider, providerConfig.serviceId]
    );
    depot_id = result.insertId;
  } catch (e) {
    console.error('depot insert error:', e);
    req.session.error = "Erreur lors de l'enregistrement du dépôt";
    return res.redirect('/depot');
  }

  const notify_url = buildNotifyUrl(req);
  await initiateCollect(req, res, {
    depot_id, montant, currency, numero, operateur, country_code, reference, notify_url,
    provider: providerConfig.provider, service_id: providerConfig.serviceId,
  });
});

// Route the deposit through the provider selected for this country/operator.
async function initiateCollect(req, res, args) {
  if (args.provider === 'soleaspay') return initiateSoleasCollect(req, res, args);
  return initiateAshtechCollect(req, res, args);
}

// Shared "step 1" /v1/collect call — used both for the initial deposit
// submission and to restart a fresh OTP session when AshtechPay reports
// otp_expired/missing_reference on the confirmation step (per docs: "relancez
// la requête sans otp pour initier une nouvelle session").
async function initiateAshtechCollect(req, res, { depot_id, montant, currency, numero, operateur, country_code, reference, notify_url }) {
  try {
    const apiKey = getAshtechApiKey();
    if (!apiKey) {
      await db.query("UPDATE depots SET statut = 'rejete' WHERE id = ? AND statut = 'en_attente'", [depot_id]);
      req.session.error = 'AshTechPay : clé API Direct API absente du serveur.';
      return res.redirect('/depot');
    }

    const payload = { amount: montant, currency, phone: numero, operator: operateur, country_code, reference, notify_url };

    const { data } = await axios.post(
      `${ASHTECH_API_BASE}/v1/collect`,
      payload,
      { headers: { Authorization: `Bearer ${apiKey}`, 'Content-Type': 'application/json' }, timeout: 15000 }
    );

    await onCollectAccepted(req, depot_id, data, apiKey);
    delete req.session.depot_form;
    res.redirect('/depot');

  } catch (e) {
    const apiError = e.response?.data;

    // ── OTP requis : ne pas rejeter le dépôt, demander le code à l'utilisateur ──
    if (e.response?.status === 400 && apiError?.error === 'otp_required') {
      // Si ussd_code contient le placeholder "montant" (ex: BF → "*144*4*6*montant#"),
      // on le remplace par le montant réel pour que l'utilisateur compose le bon code.
      let ussdCode = apiError.ussd_code || null;
      if (ussdCode && ussdCode.includes('montant')) {
        ussdCode = ussdCode.replace('montant', Math.round(montant).toString());
      }
      // AshtechPay renvoie un `reference` dans la réponse 400 otp_required (ex: "DEP-A1B2C3D4") —
      // ce reference généré par AshtechPay (PAS le nôtre) est OBLIGATOIRE pour l'étape 2 (retry OTP).
      // Sans lui, l'API renvoie 502 server_error ; avec un mauvais reference, elle renvoie
      // désormais 400 missing_reference. Il ne faut donc PAS l'omettre au retry.
      const otpReference = apiError.reference || reference;
      req.session.otp_pending = {
        depot_id, notify_url,
        payload: { amount: montant, currency, phone: numero, operator: operateur, country_code, reference: otpReference },
        ussd_code: ussdCode,
        message: apiError.message || 'Un code de confirmation (OTP) est requis pour finaliser ce paiement.',
      };
      req.session.pending_numero = numero;
      return res.redirect('/depot');
    }

    console.error(`AshtechPay collect error [HTTP ${e.response?.status}] country=${country_code} operator=${operateur} currency=${currency}:`, JSON.stringify(apiError) || e.message);
    // Mark deposit as rejected if API call failed for any other reason
    await db.query("UPDATE depots SET statut = 'rejete' WHERE id = ?", [depot_id]);
    req.session.error = formatAshtechError(e);
    res.redirect('/depot');
  }
}

function formatSoleasError(error) {
  const status = error.response?.status;
  const body = error.response?.data;
  const prefix = status ? `SoleasPay (HTTP ${status})` : 'SoleasPay';

  if (typeof body === 'string' && body.trim()) {
    return `${prefix} : ${body.trim().slice(0, 300)}`;
  }
  if (body && typeof body === 'object' && body.message) {
    return `${prefix}${body.code ? ` [${body.code}]` : ''} : ${String(body.message).slice(0, 300)}`;
  }
  if (error.code === 'ECONNABORTED' || error.code === 'ETIMEDOUT') {
    return 'SoleasPay : délai d’attente dépassé, le serveur n’a pas répondu.';
  }
  if (error.code === 'ENOTFOUND' || error.code === 'EAI_AGAIN') {
    return 'SoleasPay : serveur de paiement introuvable.';
  }
  if (error.message) return `SoleasPay : ${error.message}`;
  return 'SoleasPay : erreur inconnue du serveur de paiement.';
}

function createSoleasResponseError(data, status) {
  const error = new Error(data?.message || 'SoleasPay a refusé la demande.');
  error.response = { status, data };
  return error;
}

async function initiateSoleasCollect(req, res, {
  depot_id, montant, currency, numero, reference, notify_url, service_id,
}) {
  try {
    const apiKey = getSoleasApiKey();
    if (!apiKey) {
      await db.query("UPDATE depots SET statut = 'rejete' WHERE id = ? AND statut = 'en_attente'", [depot_id]);
      req.session.error = 'SoleasPay : clé API absente du serveur.';
      return res.redirect('/depot');
    }

    const [[user]] = await db.query('SELECT nom FROM utilisateurs WHERE id = ?', [req.session.user_id]);
    const protocol = req.headers['x-forwarded-proto'] || req.protocol;
    const host = req.headers['x-forwarded-host'] || req.headers.host;
    const baseUrl = `${protocol}://${host}`;
    const payload = {
      wallet: numero,
      amount: montant,
      currency,
      order_id: reference,
      description: `Dépôt Groupe Dangote ${reference}`,
      payer: user?.nom || 'Client Groupe Dangote',
      successUrl: `${baseUrl}/depot?payment=success`,
      failureUrl: `${baseUrl}/depot?payment=failed`,
    };

    const { data, status } = await axios.post(
      `${SOLEASPAY_API_BASE}/api/agent/bills/V3`,
      payload,
      {
        headers: {
          'x-api-key': apiKey,
          operation: '2',
          service: String(service_id),
          'Content-Type': 'application/json',
        },
        timeout: 15000,
      }
    );

    if (!data || data.success !== true || !data.data?.reference) {
      throw createSoleasResponseError(data, status);
    }

    await onSoleasCollectAccepted(req, depot_id, data, {
      apiKey, serviceId: service_id, reference, notify_url, numero,
    });
    delete req.session.depot_form;
    res.redirect('/depot');
  } catch (error) {
    console.error('SoleasPay collect error:', error.response?.data || error.message);
    await db.query("UPDATE depots SET statut = 'rejete' WHERE id = ?", [depot_id]);
    req.session.error = formatSoleasError(error);
    res.redirect('/depot');
  }
}

async function onSoleasCollectAccepted(req, depot_id, data, {
  apiKey, serviceId, reference, numero,
}) {
  const payId = String(data.data.reference || data.data.transaction_reference || '').trim();
  await db.query(
    'UPDATE depots SET numero_transaction = ?, provider_transaction_id = ? WHERE id = ?',
    [`${reference}|${payId}`, payId, depot_id]
  );

  pollSoleasTransactionStatus(depot_id, reference, payId, serviceId, apiKey);
  req.session.pending_depot_id = depot_id;
  req.session.pending_numero = numero;
  req.session.pending_wave_url = null;
}

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
  const notify_url = otp_pending.notify_url || buildNotifyUrl(req);

  try {
    const apiKey = getAshtechApiKey();
    if (!apiKey) throw new Error('ASHTECHPAY_API_KEY non définie');

    // Le retry OTP DOIT inclure le `reference` renvoyé par AshtechPay dans la réponse 400
    // otp_required de l'étape 1 (stocké dans payload.reference — PAS notre propre référence
    // interne). Sans lui, AshtechPay renvoie 502 server_error / 400 missing_reference.
    const { data } = await axios.post(
      `${ASHTECH_API_BASE}/v1/collect`,
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

    // Session OTP expirée (>15 min) ou introuvable, ou reference manquant/invalide :
    // la doc recommande de relancer l'appel SANS otp/reference pour démarrer une
    // nouvelle session OTP, plutôt que de rejeter le dépôt.
    if (e.response?.status === 400 && (apiError?.error === 'otp_expired' || apiError?.error === 'missing_reference')) {
      delete req.session.otp_pending;
      req.session.error = apiError.error === 'otp_expired'
        ? "Le code a expiré. Un nouveau code vous a été envoyé, veuillez réessayer."
        : "La session de confirmation a été perdue. Un nouveau code vous a été envoyé, veuillez réessayer.";
      return initiateCollect(req, res, {
        depot_id, montant: payload.amount, currency: payload.currency, numero: payload.phone,
        operateur: payload.operator, country_code: payload.country_code,
        reference: `DEP_${req.session.user_id}_${Date.now()}`, notify_url,
      });
    }

    console.error('AshtechPay OTP verify error:', apiError || e.message);

    // Erreur 5xx (erreur serveur AshtechPay) ou réseau : c'est peut-être transitoire.
    // On garde l'otp_pending pour que l'utilisateur puisse réessayer sans perdre son dépôt.
    // On ne rejette le dépôt que pour les erreurs 4xx définitives (hors otp_required géré au-dessus).
    const status = e.response?.status;
    if (!status || status >= 500) {
      req.session.otp_pending = otp_pending; // conserver pour réessai
      req.session.error = 'Erreur temporaire du serveur de paiement. Réessayez dans quelques secondes.';
      return res.redirect('/depot');
    }

    // Erreur 4xx définitive (code invalide, session expirée, etc.) → rejeter
    delete req.session.otp_pending;
    await db.query("UPDATE depots SET statut = 'rejete' WHERE id = ?", [depot_id]);
    req.session.error = formatAshtechError(e);
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

function normalizeInternationalPhone(phone, countryCode) {
  const dialCode = countryDialCodes[countryCode];
  if (!dialCode) return phone;

  let digits = phone;
  if (digits.startsWith('00')) digits = digits.slice(2);
  if (digits.startsWith(dialCode)) return digits;
  if (digits.startsWith('0')) digits = digits.slice(1);
  return `${dialCode}${digits}`;
}

// Shared handling for any AshtechPay /v1/collect call that came back 202 —
// whether that happened on the first try (USSD push / Wave) or after
// resubmitting with an `otp`. Stores the transaction id, kicks off the
// server-side poller, and sets what the pending-payment card needs to render.
async function onCollectAccepted(req, depot_id, data, apiKey) {
  const [[depot]] = await db.query('SELECT numero_transaction FROM depots WHERE id = ?', [depot_id]);
  const reference = (depot?.numero_transaction || '').split('|')[0];

  await db.query(
    "UPDATE depots SET numero_transaction = ?, provider_transaction_id = ? WHERE id = ?",
    [`${reference}|${data.transaction_id}`, data.transaction_id, depot_id]
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
        `${ASHTECH_API_BASE}/v1/transaction/${transaction_id}`,
        { headers: { Authorization: `Bearer ${apiKey}` }, timeout: 10000 }
      );

      // AshtechPay /v1/transaction/:id peut retourner "success" ou "completed" pour les
      // paiements réussis selon la version de l'API. On accepte les deux.
      const isSuccess = data.status === 'success' || data.status === 'completed';
      const isFailed  = data.status === 'failed'  || data.status === 'rejected' || data.status === 'cancelled';
      if (isSuccess || isFailed) {
        await finalizeDepot(depot, isSuccess ? 'success' : 'failed');
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

function soleasStatus(data) {
  const status = String(data?.status || data?.data?.status || '').toUpperCase();
  const message = String(data?.message || '').toLowerCase();
  if (['SUCCESS', 'COMPLETED', 'VALIDATED', 'APPROVED'].includes(status)
      || /completed|successfully|approved|validated/.test(message)) return 'success';
  if (['FAILURE', 'FAILED', 'REFUND', 'REJECTED', 'CANCELLED'].includes(status)
      || /failed|failure|refund|reject|cancel/.test(message)) return 'failed';
  return 'pending';
}

function pollSoleasTransactionStatus(depot_id, orderId, payId, serviceId, apiKey) {
  const startedAt = Date.now();

  const tick = async () => {
    if (Date.now() - startedAt > POLL_TIMEOUT_MS) {
      console.warn(`⏱ SoleasPay polling timeout pour depot ${depot_id} (${payId})`);
      return;
    }

    try {
      const [[depot]] = await db.query('SELECT * FROM depots WHERE id = ?', [depot_id]);
      if (!depot || depot.statut !== 'en_attente') return;

      const { data } = await axios.get(`${SOLEASPAY_API_BASE}/api/agent/verif-pay`, {
        params: { orderId, payId },
        headers: {
          'x-api-key': apiKey,
          operation: '2',
          service: String(serviceId),
        },
        timeout: 10000,
      });
      const status = soleasStatus(data);
      if (status !== 'pending') {
        await finalizeDepot(depot, status);
        return;
      }
      setTimeout(tick, POLL_INTERVAL_MS);
    } catch (error) {
      console.error(`SoleasPay polling error (depot ${depot_id}):`, error.response?.data || error.message);
      setTimeout(tick, POLL_INTERVAL_MS);
    }
  };

  setTimeout(tick, POLL_INTERVAL_MS);
}

// Credit referral commissions only after a deposit has been confirmed.
// Level 1 is the depositor's direct sponsor, then levels 2 and 3 follow
// the sponsor chain.
async function creditDepositReferralCommissions(conn, depot, rates) {
  const depositAmount = parseFloat(depot.montant);
  let memberId = depot.user_id;

  for (let level = 0; level < rates.length && memberId; level += 1) {
    const [[member]] = await conn.query(
      'SELECT parrain_id FROM utilisateurs WHERE id = ?',
      [memberId]
    );
    const sponsorId = member?.parrain_id;
    if (!sponsorId) break;

    const bonus = Math.round(depositAmount * rates[level] * 100) / 100;
    if (bonus > 0) {
      const [[sponsorBalance]] = await conn.query(
        'SELECT id FROM soldes WHERE user_id = ?',
        [sponsorId]
      );
      if (sponsorBalance) {
        await conn.query(
          'UPDATE soldes SET solde = solde + ? WHERE user_id = ?',
          [bonus, sponsorId]
        );
      } else {
        await conn.query(
          'INSERT INTO soldes (user_id, solde) VALUES (?, ?)',
          [sponsorId, bonus]
        );
      }
      await conn.query(
        "INSERT INTO historique_revenus (user_id, montant, type, source) VALUES (?, ?, 'parrainage', 'parrainage')",
        [sponsorId, bonus]
      );
    }

    memberId = sponsorId;
  }
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
      const transaction_id = depot.provider_transaction_id || (depot.numero_transaction || '').split('|')[1];
      const provider = depot.fournisseur || 'ashtechpay';
      const apiKey = provider === 'soleaspay' ? getSoleasApiKey() : getAshtechApiKey();
      if (transaction_id && apiKey && provider === 'soleaspay') {
        try {
          const orderId = (depot.numero_transaction || '').split('|')[0];
          const { data } = await axios.get(`${SOLEASPAY_API_BASE}/api/agent/verif-pay`, {
            params: { orderId, payId: transaction_id },
            headers: {
              'x-api-key': apiKey,
              operation: '2',
              service: String(depot.provider_service_id || ''),
            },
            timeout: 10000,
          });
          const status = soleasStatus(data);
          if (status !== 'pending') {
            await finalizeDepot(depot, status);
            depot.statut = status === 'success' ? 'valide' : 'rejete';
          }
        } catch (e) {
          console.error(`SoleasPay live status check error (depot ${depot_id}):`, e.response?.data || e.message);
        }
      } else if (transaction_id && apiKey) {
        try {
          const { data } = await axios.get(
            `${ASHTECH_API_BASE}/v1/transaction/${transaction_id}`,
            { headers: { Authorization: `Bearer ${apiKey}` }, timeout: 10000 }
          );
          // Accept both "success"/"completed" for success, "failed"/"rejected"/"cancelled" for failure
      const isSuccessLive = data.status === 'success' || data.status === 'completed';
      const isFailedLive  = data.status === 'failed'  || data.status === 'rejected' || data.status === 'cancelled';
      if (isSuccessLive || isFailedLive) {
            await finalizeDepot(depot, isSuccessLive ? 'success' : 'failed');
            depot.statut = isSuccessLive ? 'valide' : 'rejete';
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

function isValidAshtechWebhook(req, rawBody) {
  const webhookSecret = process.env.ASHTECHPAY_WEBHOOK_SECRET
    || process.env.ASHTECH_WEBHOOK_SECRET
    || process.env.WHSEC;

  // Signature verification is enabled automatically once the merchant adds
  // the documented whsec_... secret. Without it, keep compatibility with
  // accounts that have not configured signed webhooks yet.
  if (!webhookSecret) return true;

  const timestamp = req.get('X-Ashtech-Timestamp') || '';
  const signatureHeader = req.get('X-Ashtech-Signature') || '';
  const timestampSeconds = Number(timestamp);
  if (!timestamp || !Number.isFinite(timestampSeconds)
      || Math.abs(Date.now() / 1000 - timestampSeconds) > 300) {
    return false;
  }

  const provided = signatureHeader.replace(/^sha256=/i, '').trim();
  const expected = crypto
    .createHmac('sha256', webhookSecret)
    .update(`${timestamp}.${rawBody}`)
    .digest('hex');
  const expectedBuffer = Buffer.from(expected, 'utf8');
  const providedBuffer = Buffer.from(provided, 'utf8');
  return expectedBuffer.length === providedBuffer.length
    && crypto.timingSafeEqual(expectedBuffer, providedBuffer);
}

// ── POST /ashtechpay_callback  (webhook) ─────────────────────────────────────
// AshtechPay calls this URL when a transaction is completed/failed.
// Docs webhook payload:
//   { event: "payment.completed"|"payment.failed", transaction_id, reference,
//     status: "completed"|"failed", amount (net), total_amount (brut), currency, ... }
// Note: status field is "completed" (not "success") — map accordingly.
router.post('/ashtechpay_callback', async (req, res) => {
  const rawBody = Buffer.isBuffer(req.body)
    ? req.body.toString('utf8')
    : JSON.stringify(req.body || {});

  if (!isValidAshtechWebhook(req, rawBody)) {
    return res.status(401).json({ received: false, error: 'Invalid webhook signature' });
  }

  let payload;
  try {
    payload = JSON.parse(rawBody);
  } catch (error) {
    return res.status(400).json({ received: false, error: 'Invalid webhook payload' });
  }

  const { event, transaction_id, reference, status } = payload;

  // Always respond 200 first (as recommended by docs) so AshtechPay stops retrying
  res.status(200).json({ received: true });

  if (!reference && !transaction_id) {
    console.warn('AshtechPay callback: missing reference and transaction_id');
    return;
  }

  // Normalize event-based and status-based signals to our internal "success"/"failed"
  // The webhook sends status: "completed" for success, "failed" for failure.
  // event field: "payment.completed" or "payment.failed" is also available.
  let normalizedStatus;
  if (event === 'payment.completed' || status === 'completed' || status === 'success') {
    normalizedStatus = 'success';
  } else if (event === 'payment.failed' || status === 'failed' || status === 'rejected' || status === 'cancelled') {
    normalizedStatus = 'failed';
  } else {
    // Unknown / pending — ignore, let polling handle it
    console.log(`AshtechPay callback: unhandled event="${event}" status="${status}" — ignoring`);
    return;
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
      if (!depot2) {
        console.warn(`AshtechPay callback: depot not found for reference="${reference}" tx="${transaction_id}"`);
        return;
      }
      await finalizeDepot(depot2, normalizedStatus);
      return;
    }
    await finalizeDepot(depot, normalizedStatus);

  } catch (e) {
    console.error('AshtechPay callback error:', e);
  }
});

function isValidSoleasCallback(req) {
  const configuredKey = process.env.SOLEASPAY_CALLBACK_PRIVATE_KEY
    || process.env.SOLEAS_CALLBACK_PRIVATE_KEY;
  if (!configuredKey) return true;

  const providedKey = String(req.get('x-private-key') || '').trim();
  const configuredBuffer = Buffer.from(configuredKey, 'utf8');
  const providedBuffer = Buffer.from(providedKey, 'utf8');
  return providedBuffer.length > 0
    && configuredBuffer.length === providedBuffer.length
    && crypto.timingSafeEqual(configuredBuffer, providedBuffer);
}

// ── POST /soleaspay_callback ─────────────────────────────────────────────────
// SoleasPay callback payload: success, status, data.external_reference,
// data.reference, data.transaction_reference, amount and currency.
router.post('/soleaspay_callback', async (req, res) => {
  if (!isValidSoleasCallback(req)) {
    return res.status(401).json({ received: false, error: 'Invalid callback signature' });
  }

  const rawBody = Buffer.isBuffer(req.body)
    ? req.body.toString('utf8')
    : JSON.stringify(req.body || {});
  let payload;
  try {
    payload = JSON.parse(rawBody);
  } catch {
    return res.status(400).json({ received: false, error: 'Invalid callback payload' });
  }

  // Acknowledge before database work so SoleasPay does not retry a valid callback
  // just because the application took longer to finalize the deposit.
  res.status(200).json({ received: true });

  const callbackData = payload.data || {};
  const orderId = String(
    callbackData.external_reference
      || callbackData.order_id
      || payload.external_reference
      || payload.order_id
      || ''
  ).trim();
  const payId = String(
    callbackData.transaction_reference
      || callbackData.reference
      || payload.transaction_reference
      || payload.reference
      || ''
  ).trim();
  if (!orderId && !payId) {
    console.warn('SoleasPay callback: missing external_reference and payment reference');
    return;
  }

  try {
    const [[depot]] = await db.query(
      `SELECT * FROM depots
       WHERE fournisseur = 'soleaspay'
         AND (
           numero_transaction LIKE ?
           OR provider_transaction_id = ?
         )
       LIMIT 1`,
      [`${orderId}|%`, payId]
    );
    if (!depot) {
      console.warn(`SoleasPay callback: depot not found for order="${orderId}" pay="${payId}"`);
      return;
    }

    await finalizeDepot(depot, soleasStatus(payload));
  } catch (error) {
    console.error('SoleasPay callback error:', error);
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
    const params = await getParams();
    const commissionRates = [
      parseFloat(params.commission_niveau1 ?? 20) / 100,
      parseFloat(params.commission_niveau2 ?? 10) / 100,
      parseFloat(params.commission_niveau3 ?? 5) / 100,
    ];
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

      // Credit the depositor's balance.
      const [[sl]] = await conn.query('SELECT id FROM soldes WHERE user_id = ?', [depot.user_id]);
      if (sl) {
        await conn.query('UPDATE soldes SET solde = solde + ? WHERE user_id = ?', [depot.montant, depot.user_id]);
      } else {
        await conn.query('INSERT INTO soldes (user_id, solde) VALUES (?, ?)', [depot.user_id, depot.montant]);
      }

      // Referral earnings are based on the confirmed deposit amount, not on
      // the purchase of an investment plan/action.
      await creditDepositReferralCommissions(conn, depot, commissionRates);

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
