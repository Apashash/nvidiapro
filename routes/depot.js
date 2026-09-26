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
  getAshtechCryptoAssets,
  getOperatorProvider,
  getSoleasApiKey,
  getSoleasServices,
  findSoleasServiceForOperator,
  initiateSoleasCollection,
  executeSoleasCollection,
  verifySoleasCollection,
  normalizeSoleasCountryCode,
  createPaymentReference,
} = require('../services/paymentProviders');
const {
  CRYPTO_PENDING_TTL_MS,
  calculateUsdtAmount,
  findAshtechCryptoAsset,
  getCryptoExpiry,
  getCryptoPollTimeoutMs,
  normalizeAshtechCryptoCollectResponse,
} = require('../services/ashtechCrypto');
const { sanitizePaymentMessage } = require('../services/paymentText');

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
const CRYPTO_FCFA_PER_USDT = 600;
const CRYPTO_POLL_INTERVAL_MS = 30000;

function getCryptoRate() {
  return CRYPTO_FCFA_PER_USDT;
}

function normalizeCountryName(value) {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim()
    .toLocaleLowerCase('fr-FR');
}

function resolveUserCryptoCountryCode(user, countries) {
  const countryName = normalizeCountryName(user?.pays);
  const nameMatch = countries.find(country =>
    normalizeCountryName(country.name) === countryName
      || String(country.code).toUpperCase() === String(user?.pays || '').trim().toUpperCase()
  );
  if (nameMatch) return nameMatch.code;

  let phone = String(user?.telephone || '').replace(/\D/g, '');
  if (phone.startsWith('00')) phone = phone.slice(2);
  const dialCodeMatch = Object.entries(countryDialCodes)
    .sort((a, b) => b[1].length - a[1].length)
    .find(([, dialCode]) => phone.startsWith(dialCode));
  if (!dialCodeMatch) return '';
  return countries.find(country => country.code === dialCodeMatch[0])?.code || '';
}

function normalizeAshtechTransactionStatus(status) {
  const normalized = String(status || '').trim().toLowerCase();
  if (normalized === 'completed' || normalized === 'success') return 'success';
  if (['failed', 'rejected', 'cancelled', 'expired'].includes(normalized)) return 'failed';
  return 'pending';
}

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
  const prefix = status ? `Paiement (HTTP ${status})` : 'Paiement';

  if (typeof responseBody === 'string' && responseBody.trim()) {
    return sanitizePaymentMessage(`${prefix} : ${responseBody.trim().slice(0, 300)}`);
  }

  if (responseBody && typeof responseBody === 'object') {
    const code = responseBody.error || responseBody.code || responseBody.type;
    const message = responseBody.message || responseBody.detail || responseBody.error_description;
    if (code && message) return sanitizePaymentMessage(`${prefix} [${code}] : ${message}`);
    if (message) return sanitizePaymentMessage(`${prefix} : ${message}`);
    if (code) return sanitizePaymentMessage(`${prefix} [${code}]`);
  }

  if (error.code === 'ECONNABORTED' || error.code === 'ETIMEDOUT') {
    return 'Délai d’attente dépassé, le serveur de paiement n’a pas répondu.';
  }
  if (error.code === 'ENOTFOUND' || error.code === 'EAI_AGAIN') {
    return 'Serveur de paiement introuvable.';
  }
  if (!error.response && error.message) {
    return sanitizePaymentMessage(`Erreur de paiement : ${error.message}`);
  }
  return 'Erreur inconnue du serveur de paiement.';
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
    const error   = req.session.error
      ? sanitizePaymentMessage(req.session.error)
      : null;
    const failed  = req.query.failed === '1' ? 'Paiement échoué. Veuillez réessayer.' : null;
    let pendingCrypto = req.session.pending_crypto || null;
    if (pendingCrypto?.depot_id) {
      const [[pendingCryptoDepot]] = await db.query(
        'SELECT statut FROM depots WHERE id = ? AND user_id = ?',
        [pendingCrypto.depot_id, user_id]
      );
      if (!pendingCryptoDepot || pendingCryptoDepot.statut !== 'en_attente') {
        if (String(req.session.pending_depot_id) === String(pendingCrypto.depot_id)) {
          delete req.session.pending_depot_id;
          delete req.session.pending_numero;
          delete req.session.pending_wave_url;
        }
        delete req.session.pending_crypto;
        pendingCrypto = null;
      }
    }
    const pending_crypto = pendingCrypto
      ? {
          ...pendingCrypto,
          status_message: pendingCrypto.status_message
            ? sanitizePaymentMessage(pendingCrypto.status_message)
            : null,
          expired: Boolean(pendingCrypto.expires_at)
            && Date.parse(pendingCrypto.expires_at) <= Date.now(),
        }
      : null;
    const pending_depot_id = pending_crypto?.depot_id || req.session.pending_depot_id || null;
    const pending_numero   = req.session.pending_numero   || null;
    const pending_wave_url = req.session.pending_wave_url || null;
    const otp_pending      = req.session.otp_pending
      ? {
          ...req.session.otp_pending,
          message: sanitizePaymentMessage(req.session.otp_pending.message || ''),
        }
      : null;
    const depot_notice = req.session.depot_notice || null;
    const depotForm        = req.session.depot_form || {};
    delete req.session.error;
    delete req.session.depot_notice;
    delete req.session.pending_depot_id;
    delete req.session.pending_numero;
    delete req.session.pending_wave_url;
    delete req.session.depot_form;
    const params = await getParams();
    const depotMin = parseFloat(params.depot_minimum ?? 200);
    const cryptoRate = getCryptoRate();
    const countries = await getAshtechCountries();
    res.render('depot', {
      user, countries, error, failed, depot_notice, depotMin, cryptoRate,
      pending_depot_id, pending_numero, pending_wave_url, pending_crypto, otp_pending,
      selectedCountryCode: depotForm.country_code || '',
      selectedOperator: depotForm.operateur || '',
      selectedCryptoAsset: depotForm.crypto_asset_code || '',
      selectedCryptoCountryCode: depotForm.crypto_country_code
        || resolveUserCryptoCountryCode(user, countries),
    });
  } catch (e) {
    console.error('GET /depot error:', e);
    res.redirect('/');
  }
});

router.get('/depot/crypto/assets', requireAuth, async (req, res) => {
  try {
    const assets = await getAshtechCryptoAssets();
    res.json({ assets });
  } catch (error) {
    console.error(
      'AshTechPay crypto assets error:',
      error.response?.status || error.message,
    );
    res.status(503).json({
      error: 'Le catalogue crypto est temporairement indisponible.',
    });
  }
});

router.post('/depot/crypto/new-request', requireAuth, async (req, res) => {
  const clearPendingCryptoSession = () => {
    delete req.session.pending_crypto;
    delete req.session.pending_depot_id;
    delete req.session.pending_numero;
    delete req.session.pending_wave_url;
    delete req.session.depot_form;
  };

  try {
    const depot_id = Number(req.session.pending_crypto?.depot_id);
    if (!Number.isSafeInteger(depot_id) || depot_id <= 0) {
      req.session.error = 'Aucune demande crypto en attente à quitter.';
      return res.redirect('/depot');
    }

    const [[depot]] = await db.query(
      'SELECT statut FROM depots WHERE id = ? AND user_id = ?',
      [depot_id, req.session.user_id]
    );
    if (!depot) {
      clearPendingCryptoSession();
      req.session.error = 'La demande précédente est introuvable. Vous pouvez en créer une nouvelle.';
      return res.redirect('/depot');
    }

    clearPendingCryptoSession();
    if (depot.statut === 'en_attente') {
      req.session.depot_notice = 'Votre demande précédente reste en attente et continue d’être suivie. Si vous avez déjà envoyé des fonds, n’effectuez pas un deuxième paiement. Tout paiement confirmé sur l’une ou l’autre demande sera crédité.';
    }
    return res.redirect('/depot');
  } catch (error) {
    console.error('Could not reopen crypto deposit form:', error.message);
    req.session.error = 'Impossible de vérifier la demande précédente. Réessayez dans quelques instants.';
    return res.redirect('/depot');
  }
});

router.post('/depot/crypto/process', requireAuth, async (req, res) => {
  let cryptoAttempt = null;
  let cryptoApiKey = null;
  try {
  const user_id = req.session.user_id;
  const montant = Number(req.body.montant);
  const assetCode = String(req.body.asset_code || '').trim();
  const countryCode = String(req.body.crypto_country_code || '').trim().toUpperCase();
  const firstName = String(req.body.firstName || '').trim();
  const lastName = String(req.body.lastName || '').trim();
  const email = String(req.body.email || '').trim();

  req.session.depot_form = {
    country_code: 'CRYPTO',
    crypto_asset_code: assetCode,
    crypto_country_code: countryCode,
  };

  const rejectForm = message => {
    req.session.error = message;
    return res.redirect('/depot');
  };

  const params = await getParams();
  const depotMin = parseFloat(params.depot_minimum ?? 200);
  if (!Number.isFinite(montant) || !Number.isInteger(montant)
      || montant <= 0 || montant < depotMin) {
    return rejectForm(
      `Le montant minimum de dépôt est de ${depotMin.toLocaleString('fr-FR')} FCFA, en montant entier.`,
    );
  }
  if (!firstName || firstName.length > 100 || !lastName || lastName.length > 100) {
    return rejectForm('Saisissez votre prénom et votre nom pour le paiement crypto.');
  }
  if (email.length > 254 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    return rejectForm('Saisissez une adresse e-mail valide pour le paiement crypto.');
  }

  const apiKey = getAshtechApiKey();
  if (!apiKey) {
    return rejectForm('Le service de paiement crypto est momentanément indisponible.');
  }
  cryptoApiKey = apiKey;

  const countries = await getAshtechCountries();
  const country = countries.find(item => item.code === countryCode);
  if (!country) return rejectForm('Ce pays n’est pas pris en charge pour le paiement crypto.');

  let assets;
  try {
    assets = await getAshtechCryptoAssets();
  } catch (error) {
    console.error(
      'AshTechPay crypto assets error during deposit:',
      error.response?.status || error.message,
    );
    return rejectForm('Impossible de vérifier les actifs crypto disponibles. Réessayez plus tard.');
  }
  const asset = findAshtechCryptoAsset(assets, assetCode);
  if (!asset) return rejectForm('Cet actif ou réseau crypto n’est plus disponible.');

  const cryptoRate = getCryptoRate();
  let usdtAmount;
  try {
    usdtAmount = calculateUsdtAmount(montant, cryptoRate);
  } catch (error) {
    return rejectForm('Le montant saisi ne peut pas être converti en USDT.');
  }

  const reference = createPaymentReference();
  let depot_id;
  try {
    const [result] = await db.query(
      "INSERT INTO depots (user_id, montant, methode, numero_transaction, pays, fournisseur, provider_service_id, statut) VALUES (?, ?, ?, ?, ?, ?, ?, 'en_attente')",
      [
        user_id,
        montant,
        `Crypto ${asset.coin} (${asset.network_label})`,
        reference,
        country.name,
        'ashtechpay',
        null,
      ]
    );
    depot_id = result.insertId;
    cryptoAttempt = {
      depot_id,
      amount_fcfa: montant,
      reference,
    };
  } catch (error) {
    console.error('crypto depot insert error:', error.message);
    return rejectForm("Erreur lors de l'enregistrement du dépôt crypto.");
  }

  const requestCreatedAt = new Date().toISOString();
  cryptoAttempt.created_at = requestCreatedAt;
  const notify_url = buildNotifyUrl(req);
  const payload = {
    amount: Number(usdtAmount),
    currency: 'USDT',
    asset_code: asset.asset_code,
    country: country.code,
    reference,
    customer: { email, firstName, lastName },
    notify_url,
  };

  let responseData;
  let responseReceivedAt;
  try {
    const response = await axios.post(
      `${ASHTECH_API_BASE}/v1/crypto/collect`,
      payload,
      {
        headers: {
          Authorization: `Bearer ${apiKey}`,
          'Content-Type': 'application/json',
        },
        timeout: 15000,
      }
    );
    responseData = response.data;
    responseReceivedAt = Date.now();
    cryptoAttempt.expires_at = getCryptoExpiry(responseData, responseReceivedAt);
  } catch (error) {
    const status = error.response?.status;
    if (status && status >= 400 && status < 500 && status !== 408 && status !== 409) {
      await db.query(
        "UPDATE depots SET statut = 'rejete' WHERE id = ? AND statut = 'en_attente'",
        [depot_id]
      );
      req.session.error = formatAshtechError(error);
      return res.redirect('/depot');
    }

    // A timeout or provider 5xx can be ambiguous. Keep the reference pending
    // and do not retry the create request or claim that it was rejected.
    const expiresAt = new Date(Date.now() + CRYPTO_PENDING_TTL_MS).toISOString();
    req.session.pending_depot_id = depot_id;
    req.session.pending_crypto = {
      depot_id,
      amount_fcfa: montant,
      reference,
      created_at: requestCreatedAt,
      expires_at: expiresAt,
      status_message: 'La création n’a pas pu être confirmée. Ne relancez pas le paiement ; vérifiez le statut de cette demande.',
    };
    req.session.error = 'La demande n’a pas été confirmée. Ne soumettez pas une nouvelle demande immédiatement ; vérifiez son statut.';
    console.error(
      `AshTechPay crypto create error for depot ${depot_id}:`,
      status || error.code || error.message,
    );
    return res.redirect('/depot');
  }

  const transactionId = typeof responseData?.transaction_id === 'string'
    ? responseData.transaction_id.trim()
    : '';
  cryptoAttempt.transaction_id = transactionId || null;
  if (transactionId) {
    await db.query(
      'UPDATE depots SET numero_transaction = ?, provider_transaction_id = ? WHERE id = ?',
      [`${reference}|${transactionId}`, transactionId, depot_id]
    );
  }

  let payment;
  try {
    payment = normalizeAshtechCryptoCollectResponse(responseData, asset);
  } catch (error) {
    if (transactionId) {
      pollTransactionStatus(depot_id, transactionId, apiKey, {
        timeoutMs: getCryptoPollTimeoutMs(responseData, responseReceivedAt || Date.now()),
        intervalMs: CRYPTO_POLL_INTERVAL_MS,
      });
    }
    req.session.pending_depot_id = depot_id;
    req.session.pending_crypto = {
      depot_id,
      amount_fcfa: montant,
      reference,
      transaction_id: transactionId || null,
      created_at: requestCreatedAt,
      expires_at: cryptoAttempt.expires_at,
      status_message: 'La réponse de paiement est incomplète. Ne transférez pas de fonds ; la demande reste en vérification.',
    };
    req.session.error = 'Les détails de réception ne peuvent pas être vérifiés. Ne transférez pas de fonds.';
    console.error(`AshTechPay crypto response validation error for depot ${depot_id}:`, error.message);
    return res.redirect('/depot');
  }

  const expiresAt = getCryptoExpiry(payment, responseReceivedAt || Date.now());
  pollTransactionStatus(depot_id, payment.transaction_id, apiKey, {
    timeoutMs: getCryptoPollTimeoutMs(payment, responseReceivedAt || Date.now()),
    intervalMs: CRYPTO_POLL_INTERVAL_MS,
  });

  req.session.pending_depot_id = depot_id;
  req.session.pending_numero = null;
  req.session.pending_wave_url = null;
  req.session.pending_crypto = {
    depot_id,
    amount_fcfa: montant,
    reference,
    transaction_id: payment.transaction_id,
    status: payment.status,
    coin: asset.coin,
    name: asset.name,
    asset_code: payment.asset_code,
    network: payment.network,
    network_label: asset.network_label,
    address: payment.status === 'pending' ? payment.address : null,
    memo: payment.status === 'pending' ? payment.memo : null,
    memo_type: payment.status === 'pending' ? payment.memo_type : null,
    amount: payment.amount,
    currency: payment.currency,
    amount_usdt: payment.amount_usdt,
    credited_amount_usdt: payment.credited_amount_usdt,
    total_fee_amount_usdt: payment.total_fee_amount_usdt,
    created_at: payment.created_at || requestCreatedAt,
    expires_at: expiresAt,
    status_message: payment.status === 'pending'
      ? null
      : 'Vérification du statut de cette demande en cours.',
  };
  delete req.session.depot_form;
  return res.redirect('/depot');
  } catch (error) {
    console.error(
      'POST /depot/crypto/process error:',
      error.response?.status || error.code || error.message,
    );
    if (res.headersSent) return;
    if (cryptoAttempt) {
      if (cryptoAttempt.transaction_id && cryptoApiKey) {
        try {
          await db.query(
            'UPDATE depots SET numero_transaction = ?, provider_transaction_id = ? WHERE id = ? AND statut = ?',
            [
              `${cryptoAttempt.reference}|${cryptoAttempt.transaction_id}`,
              cryptoAttempt.transaction_id,
              cryptoAttempt.depot_id,
              'en_attente',
            ]
          );
        } catch (databaseError) {
          console.error('Could not persist AshTechPay crypto transaction id:', databaseError.message);
        }
        pollTransactionStatus(cryptoAttempt.depot_id, cryptoAttempt.transaction_id, cryptoApiKey, {
          timeoutMs: getCryptoPollTimeoutMs(cryptoAttempt, Date.now()),
          intervalMs: CRYPTO_POLL_INTERVAL_MS,
        });
      }
      req.session.pending_depot_id = cryptoAttempt.depot_id;
      req.session.pending_crypto = {
        ...cryptoAttempt,
        expires_at: cryptoAttempt.expires_at
          || new Date(Date.now() + CRYPTO_PENDING_TTL_MS).toISOString(),
        status_message: 'La demande a été enregistrée mais son statut est incertain. Ne soumettez pas une nouvelle demande ; le serveur vérifie la transaction.',
      };
      req.session.error = 'Le statut de la demande crypto ne peut pas encore être confirmé.';
    } else {
      req.session.error = 'Erreur serveur lors du traitement du dépôt crypto. Réessayez plus tard.';
    }
    return res.redirect('/depot');
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
  const reference = createPaymentReference();

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
      req.session.error = 'Le service de paiement est momentanément indisponible.';
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
  if (body && typeof body === 'object') {
    const code = body.code || body.error?.code;
    const message = body.message || body.error?.message || body.error?.details;
    if (message) {
      const readable = typeof message === 'string' ? message : JSON.stringify(message);
      return `${prefix}${code ? ` [${code}]` : ''} : ${readable.slice(0, 500)}`;
    }
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
  depot_id, montant, currency, numero, operateur, country_code,
  reference, notify_url, service_id,
}) {
  try {
    const services = await getSoleasServices(country_code, currency);
    const configuredService = services.find(service =>
      Number(service.id) === Number(service_id) && service.is_can_collect
    );
    const service = configuredService
      || findSoleasServiceForOperator(country_code, operateur, services);
    if (!service || !service.is_can_collect) {
      throw new Error(`Aucun service MySoleas actif pour ${operateur} (${country_code}).`);
    }

    const data = await initiateSoleasCollection({
      wallet: normalizeProviderWallet(numero, country_code),
      amount: montant,
      currency,
      provider: service.code,
      transactionUuid: crypto.randomUUID(),
      invoiceReference: reference,
      description: 'AshTechPay',
    });

    await onSoleasCollectAccepted(req, depot_id, data, { reference, numero });
    delete req.session.depot_form;
    res.redirect('/depot');
  } catch (error) {
    const providerMessage = String(error?.response?.data?.message || error?.message || '');
    if (error.transactionReference && /otp|required.*code|code.*required/i.test(providerMessage)) {
      const transactionId = String(error.transactionReference);
      await db.query(
        'UPDATE depots SET numero_transaction=?, provider_transaction_id=? WHERE id=? AND statut=?',
        [`${reference}|${transactionId}`, transactionId, depot_id, 'en_attente']
      );
      req.session.otp_pending = {
        provider: 'soleaspay',
        depot_id,
        transaction_reference: transactionId,
        invoice_reference: reference,
        notify_url,
        message: providerMessage || 'Un code OTP est requis pour confirmer ce dépôt.',
      };
      req.session.pending_numero = numero;
      return res.redirect('/depot');
    }

    console.error('MySoleas collect error:', error.response?.data || error.message);
    await db.query("UPDATE depots SET statut = 'rejete' WHERE id = ?", [depot_id]);
    req.session.error = formatSoleasError(error);
    res.redirect('/depot');
  }
}

async function onSoleasCollectAccepted(req, depot_id, data, {
  reference, numero,
}) {
  const transactionId = String(data?.data?.transaction_reference || data?.transaction_reference || '').trim();
  await db.query(
    'UPDATE depots SET numero_transaction = ?, provider_transaction_id = ? WHERE id = ?',
    [`${reference}|${transactionId}`, transactionId, depot_id]
  );

  pollSoleasTransactionStatus(depot_id, transactionId);
  req.session.pending_depot_id = depot_id;
  req.session.pending_numero = numero;
  req.session.pending_wave_url = data?.data?.confirmation_url || data?.confirmation_url || null;
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

  if (otp_pending.provider === 'soleaspay') {
    try {
      const data = await executeSoleasCollection({
        transactionReference: otp_pending.transaction_reference,
        invoiceReference: otp_pending.invoice_reference,
        otp,
      });
      delete req.session.otp_pending;
      await onSoleasCollectAccepted(req, depot_id, data, {
        reference: otp_pending.invoice_reference,
        numero: req.session.pending_numero,
      });
      delete req.session.pending_numero;
      return res.redirect('/depot');
    } catch (error) {
      console.error('MySoleas OTP verify error:', error.response?.data || error.message);
      req.session.otp_pending = otp_pending;
      req.session.error = formatSoleasError(error);
      return res.redirect('/depot');
    }
  }

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
        reference: createPaymentReference(), notify_url,
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

// MySoleas V4 expects the provider wallet without the country calling code.
function normalizeProviderWallet(phone, countryCode) {
  const dialCode = countryDialCodes[countryCode];
  let digits = String(phone || '').replace(/\D/g, '');
  if (digits.startsWith('00')) digits = digits.slice(2);
  if (dialCode && digits.startsWith(dialCode)) digits = digits.slice(dialCode.length);
  if (digits.startsWith('0')) digits = digits.slice(1);
  return digits;
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

function pollTransactionStatus(depot_id, transaction_id, apiKey, options = {}) {
  const startedAt = Date.now();
  const timeoutMs = Number.isFinite(options.timeoutMs) ? options.timeoutMs : POLL_TIMEOUT_MS;
  const intervalMs = Number.isFinite(options.intervalMs) ? options.intervalMs : POLL_INTERVAL_MS;

  const tick = async () => {
    // Enforce the timeout up front, before any DB/API call and regardless of
    // which branch (success/error) would otherwise reschedule — guarantees
    // polling always terminates and never leaks an unbounded timer chain.
    if (Date.now() - startedAt > timeoutMs) {
      console.warn(`⏱ Polling timeout pour depot ${depot_id} (transaction ${transaction_id})`);
      return;
    }

    try {
      const [[depot]] = await db.query('SELECT * FROM depots WHERE id = ?', [depot_id]);
      if (!depot || depot.statut !== 'en_attente') return; // already finalized (e.g. by webhook)

      const { data } = await axios.get(
        `${ASHTECH_API_BASE}/v1/transaction/${encodeURIComponent(transaction_id)}`,
        { headers: { Authorization: `Bearer ${apiKey}` }, timeout: 10000 }
      );

      const normalizedStatus = normalizeAshtechTransactionStatus(data.status);
      if (normalizedStatus !== 'pending') {
        await finalizeDepot(depot, normalizedStatus);
        return; // status changed to a final state — stop polling
      }

      // still pending — check again in 3s
      setTimeout(tick, intervalMs);
    } catch (e) {
      console.error(`AshtechPay polling error (depot ${depot_id}):`, e.response?.data || e.message);
      // keep retrying until timeout, in case of a transient network/API error
      setTimeout(tick, intervalMs);
    }
  };

  setTimeout(tick, intervalMs);
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

function pollSoleasTransactionStatus(depot_id, transactionId) {
  const startedAt = Date.now();

  const tick = async () => {
    if (Date.now() - startedAt > POLL_TIMEOUT_MS) {
      console.warn(`⏱ SoleasPay polling timeout pour depot ${depot_id} (${payId})`);
      return;
    }

    try {
      const [[depot]] = await db.query('SELECT * FROM depots WHERE id = ?', [depot_id]);
      if (!depot || depot.statut !== 'en_attente') return;

      const data = await verifySoleasCollection({ transactionReference: transactionId });
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
      const apiKey = provider === 'ashtechpay' ? getAshtechApiKey() : null;
      if (transaction_id && provider === 'soleaspay') {
        try {
          const data = await verifySoleasCollection({ transactionReference: transaction_id });
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
            `${ASHTECH_API_BASE}/v1/transaction/${encodeURIComponent(transaction_id)}`,
            { headers: { Authorization: `Bearer ${apiKey}` }, timeout: 10000 }
          );
          const normalizedStatus = normalizeAshtechTransactionStatus(data.status);
          if (normalizedStatus !== 'pending') {
            await finalizeDepot(depot, normalizedStatus);
            depot.statut = normalizedStatus === 'success' ? 'valide' : 'rejete';
          }
        } catch (e) {
          // Live check failed (network/API) — fall back to the last known DB status
          console.error(`AshtechPay live status check error (depot ${depot_id}):`, e.response?.data || e.message);
        }
      }
    }

    if (depot.statut !== 'en_attente'
        && String(req.session.pending_crypto?.depot_id) === String(depot_id)) {
      delete req.session.pending_crypto;
      if (String(req.session.pending_depot_id) === String(depot_id)) {
        delete req.session.pending_depot_id;
        delete req.session.pending_numero;
        delete req.session.pending_wave_url;
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

async function verifyAshtechWebhookDepot(depot, webhookTransactionId) {
  if (depot.fournisseur && depot.fournisseur !== 'ashtechpay') {
    console.warn(`AshTechPay callback ignored for non-AshTech depot ${depot.id}`);
    return;
  }

  const apiKey = getAshtechApiKey();
  const storedTransactionId = depot.provider_transaction_id || '';
  if (storedTransactionId && webhookTransactionId
      && storedTransactionId !== webhookTransactionId) {
    console.warn(`AshTechPay callback transaction mismatch for depot ${depot.id}`);
    return;
  }

  const transactionId = storedTransactionId || webhookTransactionId;
  if (!transactionId || !apiKey) {
    console.warn(`AshTechPay callback cannot verify depot ${depot.id}`);
    return;
  }

  const { data } = await axios.get(
    `${ASHTECH_API_BASE}/v1/transaction/${encodeURIComponent(transactionId)}`,
    { headers: { Authorization: `Bearer ${apiKey}` }, timeout: 10000 }
  );
  const returnedTransactionId = typeof data.transaction_id === 'string'
    ? data.transaction_id.trim()
    : '';
  if (returnedTransactionId && returnedTransactionId !== transactionId) {
    console.warn(`AshTechPay callback response transaction mismatch for depot ${depot.id}`);
    return;
  }
  if (!storedTransactionId) {
    const localReference = String(depot.numero_transaction || '').split('|')[0];
    const providerReferences = [
      data.merchant_reference,
      data.external_reference,
      data.reference,
      data.data?.merchant_reference,
      data.data?.external_reference,
      data.data?.reference,
    ].filter(value => typeof value === 'string').map(value => value.trim());
    if (!localReference || !providerReferences.includes(localReference)) {
      console.warn(`AshTechPay callback reference mismatch for depot ${depot.id}`);
      return;
    }

    await db.query(
      'UPDATE depots SET numero_transaction = ?, provider_transaction_id = ? WHERE id = ? AND provider_transaction_id IS NULL',
      [`${localReference}|${transactionId}`, transactionId, depot.id]
    );
    depot.provider_transaction_id = transactionId;
  }

  const verifiedStatus = normalizeAshtechTransactionStatus(data.status);
  if (verifiedStatus === 'pending') {
    console.log(`AshTechPay callback verified as pending for depot ${depot.id}`);
    return;
  }

  await finalizeDepot(depot, verifiedStatus);
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

  const webhookStatus = event === 'payment.completed'
    ? 'success'
    : event === 'payment.failed'
      ? 'failed'
      : normalizeAshtechTransactionStatus(status);
  if (webhookStatus === 'pending') {
    // Unknown / pending — ignore, let polling handle it
    console.log(`AshtechPay callback: unhandled event="${event}" status="${status}" — ignoring`);
    return;
  }

  try {
    let depot = null;
    if (reference) {
      const [[byReference]] = await db.query(
        "SELECT * FROM depots WHERE numero_transaction = ? OR split_part(numero_transaction, '|', 1) = ? LIMIT 1",
        [reference, reference]
      );
      depot = byReference || null;
    }
    if (!depot && transaction_id) {
      const [[byTransaction]] = await db.query(
        "SELECT * FROM depots WHERE provider_transaction_id = ? OR split_part(numero_transaction, '|', 2) = ? LIMIT 1",
        [transaction_id, transaction_id]
      );
      depot = byTransaction || null;
    }
    if (!depot) {
      console.warn('AshTechPay callback: deposit not found');
      return;
    }

    await verifyAshtechWebhookDepot(depot, transaction_id);

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
