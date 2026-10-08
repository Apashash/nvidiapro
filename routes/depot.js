const express = require('express');
const router = express.Router();
const db = require('../config/db');
const { requireAuth } = require('../middleware/auth');
const crypto = require('crypto');
const { getParams } = require('../services/params');
const {
  SOLEASPAY_API_BASE,
  getAshtechApiKey,
  getAshtechCountries,
  getAshtechPayoutCountries,
  filterEnabledPaymentOperators,
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
const ashtechPay = require('../services/ashtechPay');
const {
  normalizeAshtechTransactionStatus,
  validateAshtechNotifyUrl,
  verifyAshtechWebhookSignature,
} = require('../services/ashtechPay');
const {
  normalizeAshtechPayoutStatus,
  parseProviderMetadata,
  settleAshtechPayout,
} = require('../services/ashtechPayouts');
const {
  CRYPTO_PENDING_TTL_MS,
  calculateUsdtAmount,
  findAshtechCryptoAsset,
  getCryptoExpiry,
  getCryptoPollTimeoutMs,
  normalizeAshtechCryptoCollectResponse,
} = require('../services/ashtechCrypto');
const { formatAshtechError } = require('../services/ashtechError');
const { sanitizePaymentMessage } = require('../services/paymentText');
const {
  createDepotInsertDiagnostic,
  formatDepotInsertError,
} = require('../services/depotInsertError');
const {
  findAccountPaymentCountry,
  normalizeCountryName,
} = require('../services/accountPaymentCountry');

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
const CRYPTO_POLL_INTERVAL_MS = 30000;

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


// ── GET /depot ───────────────────────────────────────────────────────────────
router.get('/depot', requireAuth, async (req, res) => {
  try {
    const user_id = req.session.user_id;
    const [[user]] = await db.query('SELECT * FROM utilisateurs WHERE id = ?', [user_id]);
    const flashError = req.session.error;
    const depotInsertDiagnostic = req.session.depot_insert_diagnostic || null;
    const error = flashError
      ? flashError?.provider === 'ashtechpay'
        ? flashError.body || `AshTechPay (HTTP ${flashError.status || 'inconnu'}) : réponse vide.`
        : sanitizePaymentMessage(flashError)
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
    delete req.session.depot_insert_diagnostic;
    delete req.session.depot_notice;
    delete req.session.pending_depot_id;
    delete req.session.pending_numero;
    delete req.session.pending_wave_url;
    delete req.session.depot_form;
    const params = await getParams();
    const depotMin = parseFloat(params.depot_minimum ?? 200);
    const configuredCryptoRate = params.taux_usdt_fcfa === undefined || params.taux_usdt_fcfa === ''
      ? 600
      : Number(params.taux_usdt_fcfa);
    const cryptoRate = Number.isFinite(configuredCryptoRate) && configuredCryptoRate > 0
      ? configuredCryptoRate
      : 0;
    let allCountries = [];
    let catalogueUnavailable = false;
    try {
      allCountries = await getAshtechCountries();
    } catch (catalogueError) {
      catalogueUnavailable = true;
      console.error('AshTechPay country catalogue unavailable:', catalogueError.response?.status || catalogueError.message);
    }
    const accountCountry = findAccountPaymentCountry(user?.pays, allCountries);
    const countries = accountCountry ? [accountCountry] : [];
    const enabledPaymentCountries = filterEnabledPaymentOperators(
      allCountries,
      params.payment_provider_mappings
    );
    const displayError = error || (catalogueUnavailable
      ? 'Le catalogue des moyens de paiement est indisponible. Réessayez plus tard.'
      : null);
    res.render('depot', {
      user, countries, cryptoCountries: enabledPaymentCountries, error: displayError, failed, depot_notice, depotMin, cryptoRate,
      depotInsertDiagnostic: user?.is_admin ? depotInsertDiagnostic : null,
      pending_depot_id, pending_numero, pending_wave_url, pending_crypto, otp_pending,
      selectedCountryCode: depotForm.country_code || '',
      selectedOperator: depotForm.operateur || '',
      selectedCryptoAsset: depotForm.crypto_asset_code || '',
      selectedCryptoCountryCode: depotForm.crypto_country_code
        || resolveUserCryptoCountryCode(user, allCountries),
    });
  } catch (e) {
    console.error('GET /depot error:', e);
    res.redirect('/');
  }
});

router.get('/depot/fees', requireAuth, async (req, res) => {
  try {
    const fees = await ashtechPay.getFees();
    res.json(fees);
  } catch (error) {
    console.error('AshTechPay fees lookup failed:', error.response?.status || error.message);
    res.status(503).json({ error: 'Le barème AshTechPay est temporairement indisponible.' });
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

  if (!getAshtechApiKey()) {
    return rejectForm('Le service de paiement crypto est momentanément indisponible.');
  }

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

  const configuredCryptoRate = params.taux_usdt_fcfa === undefined || params.taux_usdt_fcfa === ''
    ? 600
    : Number(params.taux_usdt_fcfa);
  if (!Number.isFinite(configuredCryptoRate) || configuredCryptoRate <= 0) {
    return rejectForm('Le taux USDT/FCFA n’est pas configuré.');
  }
  const cryptoRate = configuredCryptoRate;
  let usdtAmount;
  try {
    usdtAmount = calculateUsdtAmount(montant, cryptoRate);
  } catch (error) {
    return rejectForm('Le montant saisi ne peut pas être converti en USDT.');
  }

  const reference = createPaymentReference();
  const notify_url = validateAshtechNotifyUrl(process.env.ASHTECH_NOTIFY_URL);
  let depot_id;
  try {
    const [result] = await db.query(
      "INSERT INTO depots (user_id, montant, methode, numero_transaction, pays, fournisseur, provider_service_id, provider_metadata, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'en_attente')",
      [
        user_id,
        montant,
        `Crypto ${asset.coin} (${asset.network_label})`,
        reference,
        country.name,
        'ashtechpay',
        null,
        JSON.stringify({ asset_code: asset.asset_code, crypto_amount: Number(usdtAmount), reference }),
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
    responseData = await ashtechPay.createCryptoCollection(payload);
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
      pollTransactionStatus(depot_id, transactionId, {
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
  pollTransactionStatus(depot_id, payment.transaction_id, {
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
      if (cryptoAttempt.transaction_id) {
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
        pollTransactionStatus(cryptoAttempt.depot_id, cryptoAttempt.transaction_id, {
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
  let user;
  try {
    [[user]] = await db.query(
      'SELECT pays FROM utilisateurs WHERE id = ?',
      [user_id]
    );
  } catch (error) {
    console.error('Could not validate deposit account country:', error.message);
    req.session.error = 'Impossible de vérifier le pays de votre compte. Réessayez.';
    return res.redirect('/depot');
  }
  const accountCountry = findAccountPaymentCountry(user?.pays, countries);
  if (!country) {
    req.session.error = 'Pays non supporté';
    return res.redirect('/depot');
  }
  if (!accountCountry || country.code !== accountCountry.code) {
    req.session.error = 'Le dépôt Mobile Money est limité au pays de votre compte. Choisissez le crypto pour un autre pays.';
    return res.redirect('/depot');
  }
  if (!country.operators.includes(operateur)) {
    req.session.error = 'Opérateur invalide pour ce pays';
    return res.redirect('/depot');
  }

  const providerConfig = await getOperatorProvider(country_code, operateur);
  if (!providerConfig.enabled) {
    req.session.error = 'Cet opérateur est temporairement désactivé.';
    return res.redirect('/depot');
  }
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
      "INSERT INTO depots (user_id, montant, methode, numero_transaction, pays, fournisseur, provider_service_id, provider_metadata, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'en_attente')",
      [user_id, montant, `${operateur} (${country.name})`, reference, country.name,
        providerConfig.provider, providerConfig.serviceId, JSON.stringify({ country_code, operator: operateur, reference })]
    );
    depot_id = result.insertId;
  } catch (e) {
    console.error('depot insert error:', e);
    const diagnostic = createDepotInsertDiagnostic(e, reference);
    req.session.depot_insert_diagnostic = diagnostic;
    req.session.error = formatDepotInsertError(diagnostic);
    return res.redirect('/depot');
  }

  let notify_url = null;
  if (providerConfig.provider === 'ashtechpay') {
    try {
      notify_url = validateAshtechNotifyUrl(process.env.ASHTECH_NOTIFY_URL);
    } catch (error) {
      await db.query("UPDATE depots SET statut = 'rejete' WHERE id = ? AND statut = 'en_attente'", [depot_id]);
      req.session.error = 'Le point de notification sécurisé AshTechPay n’est pas configuré.';
      return res.redirect('/depot');
    }
  }
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
    if (!getAshtechApiKey()) {
      await db.query("UPDATE depots SET statut = 'rejete' WHERE id = ? AND statut = 'en_attente'", [depot_id]);
      req.session.error = 'Le service de paiement est momentanément indisponible.';
      return res.redirect('/depot');
    }

    const payload = { amount: montant, currency, phone: numero, operator: operateur, country_code, reference, notify_url };
    const data = await ashtechPay.createMobileMoneyCollection(payload);
    if (!data?.transaction_id) {
      req.session.pending_depot_id = depot_id;
      req.session.pending_numero = numero;
      req.session.error = 'AshTechPay n’a pas retourné de référence de transaction. La demande reste en vérification.';
      return res.redirect('/depot');
    }
    await onCollectAccepted(req, depot_id, data);
    delete req.session.depot_form;
    res.redirect('/depot');

  } catch (e) {
    const apiError = e.response?.data;

    // ── OTP requis : ne pas rejeter le dépôt, demander le code à l'utilisateur ──
    if (e.response?.status === 400 && apiError?.error === 'otp_required') {
      const ussdCode = typeof apiError.ussd_code === 'string' ? apiError.ussd_code : null;
      // AshtechPay renvoie un `reference` dans la réponse 400 otp_required (ex: "DEP-A1B2C3D4") —
      // ce reference généré par AshtechPay (PAS le nôtre) est OBLIGATOIRE pour l'étape 2 (retry OTP).
      // Sans lui, l'API renvoie 502 server_error ; avec un mauvais reference, elle renvoie
      // désormais 400 missing_reference. Il ne faut donc PAS l'omettre au retry.
      const otpReference = typeof apiError.reference === 'string' ? apiError.reference.trim() : '';
      if (!otpReference) {
        await db.query("UPDATE depots SET statut = 'rejete' WHERE id = ? AND statut = 'en_attente'", [depot_id]);
        req.session.error = 'AshTechPay n’a pas retourné la référence de session OTP requise. Aucun nouveau code ne sera envoyé.';
        return res.redirect('/depot');
      }
      await db.query(
        "UPDATE depots SET provider_metadata = provider_metadata || ?::jsonb WHERE id = ?",
        [JSON.stringify({ otp_reference: otpReference }), depot_id],
      );
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
    req.session.error = formatAshtechError(e);
    if (!e.response || e.response.status >= 500 || e.response.status === 408 || e.response.status === 409) {
      req.session.pending_depot_id = depot_id;
      req.session.pending_numero = numero;
      return res.redirect('/depot');
    }
    await db.query("UPDATE depots SET statut = 'rejete' WHERE id = ? AND statut = 'en_attente'", [depot_id]);
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
  const notify_url = otp_pending.notify_url || null;

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
    if (!payload.reference || !notify_url) throw new Error('La session OTP ou l’URL de notification est manquante.');
    const data = await ashtechPay.createMobileMoneyCollection({ ...payload, otp, notify_url });

    delete req.session.otp_pending;
    if (!data?.transaction_id) {
      req.session.pending_depot_id = depot_id;
      req.session.pending_numero = payload.phone || null;
      req.session.error = 'La confirmation OTP est en cours de vérification. Ne renvoyez pas le code.';
      return res.redirect('/depot');
    }
    await onCollectAccepted(req, depot_id, data);
    res.redirect('/depot');

  } catch (e) {
    const apiError = e.response?.data;

    if (e.response?.status === 400 && apiError?.error === 'invalid_otp') {
      req.session.otp_pending = {
        ...otp_pending,
        message: apiError.message || 'Code OTP incorrect. Saisissez le code reçu pour cette même session.',
      };
      req.session.error = formatAshtechError(e);
      return res.redirect('/depot');
    }

    if (e.response?.status === 400 && apiError?.error === 'otp_expired') {
      delete req.session.otp_pending;
      const reference = createPaymentReference();
      await db.query(
        'UPDATE depots SET numero_transaction = ?, provider_transaction_id = NULL, provider_metadata = provider_metadata || ?::jsonb WHERE id = ? AND statut = ?',
        [reference, JSON.stringify({ reference, otp_reference: null }), depot_id, 'en_attente'],
      );
      req.session.error = 'La session OTP a expiré. Une nouvelle session de paiement va être créée.';
      return initiateCollect(req, res, {
        depot_id, montant: payload.amount, currency: payload.currency, numero: payload.phone,
        operateur: payload.operator, country_code: payload.country_code,
        reference, notify_url, provider: 'ashtechpay',
      });
    }

    console.error('AshtechPay OTP verify error:', apiError || e.message);
    const status = e.response?.status;
    if (status === 409 && apiError?.error === 'otp_confirmation_in_progress') {
      delete req.session.otp_pending;
      req.session.pending_depot_id = depot_id;
      req.session.pending_numero = payload.phone || null;
      req.session.error = 'Une confirmation est déjà en cours. Ne renvoyez pas le code ; le statut sera vérifié.';
      return res.redirect('/depot');
    }
    if (!status || status >= 500 || status === 408 || status === 409) {
      delete req.session.otp_pending;
      req.session.pending_depot_id = depot_id;
      req.session.pending_numero = payload.phone || null;
      req.session.error = 'La confirmation OTP n’a pas pu être vérifiée. Ne renvoyez pas le code ; le statut sera vérifié.';
      return res.redirect('/depot');
    }

    delete req.session.otp_pending;
    await db.query("UPDATE depots SET statut = 'rejete' WHERE id = ? AND statut = 'en_attente'", [depot_id]);
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
async function onCollectAccepted(req, depot_id, data) {
  const [[depot]] = await db.query('SELECT numero_transaction FROM depots WHERE id = ?', [depot_id]);
  const reference = (depot?.numero_transaction || '').split('|')[0];

  await db.query(
    "UPDATE depots SET numero_transaction = ?, provider_transaction_id = ? WHERE id = ?",
    [`${reference}|${data.transaction_id}`, data.transaction_id, depot_id]
  );

  // Poll AshtechPay every 3s as a backup to the webhook, until the status changes.
  pollTransactionStatus(depot_id, data.transaction_id);

  req.session.pending_depot_id = depot_id;
  req.session.pending_numero   = data.phone || req.session.pending_numero || null;
  // Wave doesn't push a USSD prompt — the client must open a link to confirm.
  req.session.pending_wave_url = typeof data.wave_url === 'string' ? data.wave_url : null;
}

// ── Server-side polling of AshtechPay GET /v1/transaction/:id ───────────────
// Runs alongside the webhook as a fallback (in case the webhook never arrives).
// Polls every 3s until the status is a final one (success/failed) or a
// timeout is reached, then applies the same finalization logic as the webhook.
const POLL_INTERVAL_MS = 3000;
const POLL_TIMEOUT_MS  = 5 * 60 * 1000; // stop after 5 minutes

function pollTransactionStatus(depot_id, transaction_id, options = {}) {
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

      const data = await ashtechPay.getTransactionStatus(transaction_id);

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
      } else if (transaction_id && provider === 'ashtechpay') {
        try {
          const data = await ashtechPay.getTransactionStatus(transaction_id);
          const normalizedStatus = normalizeAshtechTransactionStatus(data.status);
          if (normalizedStatus !== 'pending') {
            await finalizeDepot(depot, normalizedStatus);
            depot.statut = normalizedStatus === 'success' ? 'valide' : 'rejete';
          }
        } catch (e) {
          console.error(`AshTechPay live status check error (depot ${depot_id}):`, e.response?.data || e.message);
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
  const webhookSecret = process.env.ASHTECH_WEBHOOK_SECRET;
  const eventId = String(req.get('X-Ashtech-Event-Id') || '').trim();
  const timestamp = req.get('X-Ashtech-Timestamp') || '';
  const signatureHeader = req.get('X-Ashtech-Signature') || '';
  return Boolean(eventId && verifyAshtechWebhookSignature({
    secret: webhookSecret,
    timestamp,
    signature: signatureHeader,
    rawBody,
  }));
}

async function findAshtechWebhookTarget(payload) {
  const transactionId = String(payload.transaction_id || '').trim();
  const references = [payload.merchant_reference, payload.reference]
    .filter(value => typeof value === 'string')
    .map(value => value.trim())
    .filter(Boolean);

  if (transactionId) {
    const [[depot]] = await db.query(
      "SELECT * FROM depots WHERE fournisseur = 'ashtechpay' AND provider_transaction_id = ? LIMIT 1",
      [transactionId],
    );
    if (depot) return { type: 'deposit', record: depot, transactionId };
    const [[withdrawal]] = await db.query(
      "SELECT * FROM retraits WHERE fournisseur = 'ashtechpay' AND provider_transaction_id = ? LIMIT 1",
      [transactionId],
    );
    if (withdrawal) return { type: 'payout', record: withdrawal, transactionId };
  }

  for (const reference of references) {
    const [[depot]] = await db.query(
      "SELECT * FROM depots WHERE fournisseur = 'ashtechpay' AND (numero_transaction = ? OR split_part(numero_transaction, '|', 1) = ?) LIMIT 1",
      [reference, reference],
    );
    if (depot) return { type: 'deposit', record: depot, transactionId };
    const [[withdrawal]] = await db.query(
      "SELECT * FROM retraits WHERE fournisseur = 'ashtechpay' AND provider_order_id = ? LIMIT 1",
      [reference],
    );
    if (withdrawal) return { type: 'payout', record: withdrawal, transactionId };
  }
  return null;
}

async function processAshtechWebhook(payload, eventId) {
  const target = await findAshtechWebhookTarget(payload);
  if (!target) {
    console.warn(`AshTechPay webhook ${eventId}: no matching local transaction`);
    return;
  }
  if (parseProviderMetadata(target.record.provider_metadata).last_ashtech_event_id === eventId) {
    return;
  }

  const transactionId = target.record.provider_transaction_id || target.transactionId;
  if (!transactionId) throw new Error('AshTechPay transaction_id is not available yet.');
  const data = await ashtechPay.getTransactionStatus(transactionId);
  if (data.transaction_id && String(data.transaction_id) !== String(transactionId)) {
    throw new Error('AshTechPay transaction_id mismatch.');
  }

  if (!target.record.provider_transaction_id) {
    const reference = String(target.record.numero_transaction || target.record.provider_order_id || '');
    await db.query(
      target.type === 'deposit'
        ? 'UPDATE depots SET provider_transaction_id = ?, numero_transaction = ? WHERE id = ? AND provider_transaction_id IS NULL'
        : 'UPDATE retraits SET provider_transaction_id = ? WHERE id = ? AND provider_transaction_id IS NULL',
      target.type === 'deposit'
        ? [transactionId, `${reference.split('|')[0]}|${transactionId}`, target.record.id]
        : [transactionId, target.record.id],
    );
  }

  if (target.type === 'deposit') {
    const status = normalizeAshtechTransactionStatus(data.status);
    await finalizeDepot(target.record, status);
    await db.query(
      'UPDATE depots SET provider_metadata = provider_metadata || ?::jsonb WHERE id = ?',
      [JSON.stringify({ last_ashtech_event_id: eventId }), target.record.id],
    );
    return;
  }

  await settleAshtechPayout(db, target.record.id, normalizeAshtechPayoutStatus(data.status));
  await db.query(
    'UPDATE retraits SET provider_metadata = provider_metadata || ?::jsonb WHERE id = ?',
    [JSON.stringify({ last_ashtech_event_id: eventId }), target.record.id],
  );
}

// ── POST /ashtechpay_callback  (webhook) ─────────────────────────────────────
// AshtechPay calls this URL when a transaction is completed/failed.
// Docs webhook payload:
//   { event: "payment.completed"|"payment.failed", transaction_id, reference,
//     status: "completed"|"failed", amount (net), total_amount (brut), currency, ... }
// Note: status field is "completed" (not "success") — map accordingly.
router.post('/ashtechpay_callback', async (req, res) => {
  const rawBody = Buffer.isBuffer(req.body) ? req.body : Buffer.from('');

  if (!isValidAshtechWebhook(req, rawBody)) {
    return res.status(401).json({ received: false, error: 'Invalid webhook signature' });
  }

  let payload;
  try {
    payload = JSON.parse(rawBody.toString('utf8'));
  } catch {
    return res.status(400).json({ received: false, error: 'Invalid webhook payload' });
  }

  const eventId = String(req.get('X-Ashtech-Event-Id') || '').trim();
  if (!['payment.completed', 'payment.failed', 'payout.completed', 'payout.failed'].includes(payload.event)) {
    return res.status(200).json({ received: true, ignored: true });
  }
  try {
    await processAshtechWebhook(payload, eventId);
    return res.status(200).json({ received: true });
  } catch (e) {
    console.error(`AshTechPay webhook ${eventId} processing error:`, e.response?.data || e.message);
    return res.status(503).json({ received: false, error: 'Webhook processing could not be confirmed.' });
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
