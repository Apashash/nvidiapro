const axios = require('axios');
const crypto = require('crypto');
const { getParams } = require('./params');

const ASHTECH_API_BASE = process.env.ASHTECH_API_BASE || 'https://www.ashtechpay.com';
const SOLEASPAY_API_BASE = process.env.MYSOLEAS_API_BASE
  || process.env.SOLEASPAY_API_BASE
  || 'https://api.mysoleas.com';
const SOLEASPAY_AUTH_BASE = process.env.MYSOLEAS_AUTH_BASE
  || process.env.SOLEASPAY_AUTH_BASE
  || 'https://account.mysoleas.com';
const COUNTRY_CACHE_TTL_MS = 5 * 60 * 1000;

const fallbackAshtechCountries = [
  { code: 'CM', name: 'Cameroun',          currency: 'XAF', operators: ['Orange Money', 'MTN Mobile Money'] },
  { code: 'TG', name: 'Togo',              currency: 'XOF', operators: ['Flooz (Moov)', 'T-Money'] },
  { code: 'BJ', name: 'Bénin',             currency: 'XOF', operators: ['Moov Money', 'MTN Mobile Money'] },
  { code: 'CI', name: "Côte d'Ivoire",     currency: 'XOF', operators: ['Moov Money', 'Orange Money', 'MTN Mobile Money', 'Wave'] },
  { code: 'BF', name: 'Burkina Faso',      currency: 'XOF', operators: ['Moov Money', 'Orange Money'] },
  { code: 'GA', name: 'Gabon',             currency: 'XAF', operators: ['Airtel Money', 'Moov Money'] },
  { code: 'CG', name: 'Congo Brazzaville', currency: 'XAF', operators: ['Airtel Money', 'MTN Mobile Money'] },
  { code: 'NE', name: 'Niger',             currency: 'XOF', operators: ['Airtel Money'] },
  { code: 'ML', name: 'Mali',              currency: 'XOF', operators: ['Moov Money', 'Orange Money'] },
];

// SoleasPay's documented services-list is generic rather than country/operator
// specific. These are the two Mobile Money services documented by SoleasPay V3.
const fallbackSoleasServices = [
  { id: 1, code: 'mtn_cmr', name: 'MTN Cameroon', description: 'Mobile Money', countryCode: 'CMR', currency: 'XAF', is_active: true, is_public: true, is_need_otp: false, is_can_collect: true, is_can_disburse: true },
  { id: 2, code: 'orange_cmr', name: 'Orange Cameroon', description: 'Orange Money', countryCode: 'CMR', currency: 'XAF', is_active: true, is_public: true, is_need_otp: false, is_can_collect: true, is_can_disburse: true },
];

let ashtechCountriesCache = null;
let ashtechCountriesCachedAt = 0;
let soleasServicesCache = null;
let soleasServicesCachedAt = 0;
let soleasBearerToken = null;
let soleasBearerTokenExpiresAt = 0;

function getAshtechApiKey() {
  return process.env.ASHTECH_API_KEY || process.env.ASHTECHPAY_API_KEY || null;
}

function getSoleasApiKey() {
  return process.env.SOLEASPAY_API_KEY
    || process.env.SOLEAS_API_KEY
    || process.env.SOLEASPAY_PUBLIC_API_KEY
    || null;
}

function getSoleasPrivateSecret() {
  return process.env.SOLEASPAY_PRIVATE_SECRET_KEY
    || process.env.SOLEASPAY_PRIVATE_SECRET
    || process.env.SOLEAS_PRIVATE_SECRET_KEY
    || null;
}

function getSoleasClientId() {
  return process.env.MYSOLEAS_CLIENT_ID
    || process.env.SOLEASPAY_CLIENT_ID
    || process.env.SOLEAS_CLIENT_ID
    || null;
}

function getSoleasClientSecret() {
  return process.env.MYSOLEAS_CLIENT_SECRET
    || process.env.SOLEASPAY_CLIENT_SECRET
    || process.env.SOLEAS_CLIENT_SECRET
    || null;
}

async function getSoleasBearerToken() {
  const now = Date.now();
  if (soleasBearerToken && now < soleasBearerTokenExpiresAt) return soleasBearerToken;

  const clientId = getSoleasClientId();
  const clientSecret = getSoleasClientSecret();
  if (!clientId) throw new Error('MySoleas : client_id OAuth2 absent du serveur.');
  if (!clientSecret) throw new Error('MySoleas : client_secret OAuth2 absent du serveur.');

  const { data } = await axios.post(`${SOLEASPAY_AUTH_BASE}/oauth/v2/token`, {
    grant_type: 'client_credentials',
    client_id: clientId,
    client_secret: clientSecret,
    scope: 'payments services countries providers',
  }, { headers: { 'Content-Type': 'application/json' }, timeout: 15000 });

  if (!data?.access_token) throw new Error(data?.message || 'MySoleas : génération du token impossible.');
  soleasBearerToken = String(data.access_token);
  const expiresIn = Number(data.expires_in);
  soleasBearerTokenExpiresAt = now + (Number.isFinite(expiresIn) && expiresIn > 60
    ? Math.max(60, expiresIn - 60) * 1000
    : 55 * 60 * 1000);
  return soleasBearerToken;
}

function soleasHeaders(token, extra = {}) {
  return {
    'x-sp-auth-token': `Bearer ${token}`,
    'Content-Type': 'application/json',
    ...extra,
  };
}

function responseTransaction(data) {
  return data?.data && typeof data.data === 'object' ? data.data : data;
}

function transactionReference(data) {
  return String(responseTransaction(data)?.transaction_reference || '').trim();
}

function providerReference(data) {
  return String(responseTransaction(data)?.provider_reference || '').trim();
}

function createSoleasError(data, status, fallback = 'MySoleas a refusé la demande.') {
  const details = data?.error?.details;
  const error = new Error(data?.message || data?.error?.message || fallback);
  error.response = { status, data };
  if (details) error.details = details;
  return error;
}

async function initiateSoleasCollection({
  wallet, amount, currency, provider, transactionUuid, invoiceReference, description, otp,
}) {
  const token = await getSoleasBearerToken();
  const transaction_uuid = transactionUuid || crypto.randomUUID();
  const invoice_reference = invoiceReference || `DEP_${Date.now()}`;
  const { data: intent, status: intentStatus } = await axios.post(
    `${SOLEASPAY_API_BASE}/collection/intent`,
    {
      amount: Math.round(Number(amount)),
      currency: String(currency),
      transaction_uuid,
      provider: String(provider),
      channel: 'PROVIDER',
      customer_wallet: String(wallet),
      description: description || 'Dépôt Groupe Dangote',
    },
    {
      headers: soleasHeaders(token, { 'X-Idempotency-Key': transaction_uuid }),
      timeout: 15000,
    }
  );
  if (!intent?.success || !transactionReference(intent)) {
    throw createSoleasError(intent, intentStatus, 'MySoleas n’a pas créé l’intention de dépôt.');
  }
  return executeSoleasCollection({
    transactionReference: transactionReference(intent),
    invoiceReference: invoice_reference,
    otp,
    token,
    intent,
  });
}

async function executeSoleasCollection({
  transactionReference: reference, invoiceReference, otp, token: suppliedToken, intent,
}) {
  const token = suppliedToken || await getSoleasBearerToken();
  const payload = {
    transaction_reference: String(reference),
    invoice_reference: String(invoiceReference),
  };
  if (otp !== undefined && otp !== null && String(otp).trim()) payload.otp = Number(otp);

  try {
    const { data, status } = await axios.post(
      `${SOLEASPAY_API_BASE}/collection/execute`,
      payload,
      { headers: soleasHeaders(token), timeout: 15000 }
    );
    if (!data?.success || !transactionReference(data)) {
      throw createSoleasError(data, status, 'MySoleas n’a pas exécuté le dépôt.');
    }
    return data;
  } catch (error) {
    // Keep the intent reference so the UI can ask for an OTP without creating
    // a second payment intent.
    if (!error.transactionReference) error.transactionReference = String(reference);
    if (!error.invoiceReference) error.invoiceReference = String(invoiceReference);
    if (intent) error.intent = intent;
    throw error;
  }
}

async function verifySoleasCollection({ transactionReference: reference }) {
  const token = await getSoleasBearerToken();
  const { data } = await axios.post(
    `${SOLEASPAY_API_BASE}/collection/status`,
    { transaction_reference: String(reference) },
    { headers: soleasHeaders(token), timeout: 10000 }
  );
  return data;
}

async function initiateSoleasDisbursement({
  wallet, amount, currency, provider, transactionUuid, invoiceReference, description,
}) {
  const token = await getSoleasBearerToken();
  const transaction_uuid = transactionUuid || crypto.randomUUID();
  const invoice_reference = invoiceReference || `RET_${Date.now()}`;
  const { data: intent, status: intentStatus } = await axios.post(
    `${SOLEASPAY_API_BASE}/disbursement/intent`,
    {
      amount: Math.round(Number(amount)),
      currency: String(currency),
      transaction_uuid,
      provider: String(provider),
      channel: 'PROVIDER',
      customer_wallet: String(wallet),
      description: description || 'Retrait Groupe Dangote',
    },
    {
      headers: soleasHeaders(token, { 'X-Idempotency-Key': transaction_uuid }),
      timeout: 15000,
    }
  );
  if (!intent?.success || !transactionReference(intent)) {
    throw createSoleasError(intent, intentStatus, 'MySoleas n’a pas créé l’intention de retrait.');
  }
  const { data } = await axios.post(
    `${SOLEASPAY_API_BASE}/disbursement/execute`,
    {
      transaction_reference: transactionReference(intent),
      invoice_reference,
    },
    { headers: soleasHeaders(token), timeout: 15000 }
  );
  if (!data?.success || !transactionReference(data)) {
    throw createSoleasError(data, 200, 'MySoleas n’a pas exécuté le retrait.');
  }
  return data;
}

async function verifySoleasDisbursement({ transactionReference: reference }) {
  const token = await getSoleasBearerToken();
  const { data } = await axios.post(
    `${SOLEASPAY_API_BASE}/disbursement/status`,
    { transaction_reference: String(reference) },
    { headers: soleasHeaders(token), timeout: 10000 }
  );
  return data;
}

function normalizeAshtechCountries(payload) {
  const countries = Array.isArray(payload)
    ? payload
    : (Array.isArray(payload?.countries) ? payload.countries : []);

  return countries
    .map(country => ({
      code: String(country.code || country.country_code || '').trim().toUpperCase(),
      name: String(country.name || country.country || '').trim(),
      currency: String(country.currency || '').trim().toUpperCase(),
      operators: Array.isArray(country.operators)
        ? country.operators.map(operator => {
            if (typeof operator === 'string') return operator.trim();
            return String(operator?.code || operator?.name || '').trim();
          }).filter(Boolean)
        : [],
    }))
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

function normalizeSoleasCountryCode(value) {
  const code = String(value || '').trim().toUpperCase();
  const alpha3 = {
    CM: 'CMR', TG: 'TGO', BJ: 'BEN', CI: 'CIV', BF: 'BFA',
    GA: 'GAB', CG: 'COG', NE: 'NER', ML: 'MLI', SN: 'SEN',
  };
  return alpha3[code] || code;
}

async function getSoleasServices(countryCode, currency) {
  const now = Date.now();
  const cacheKey = `${normalizeSoleasCountryCode(countryCode)}:${String(currency || '').toUpperCase()}`;
  if (soleasServicesCache?.cacheKey === cacheKey
      && now - soleasServicesCachedAt < COUNTRY_CACHE_TTL_MS) {
    return soleasServicesCache.services;
  }

  try {
    const token = await getSoleasBearerToken();
    const params = { page: 1, limit: 100 };
    if (countryCode) params.country = normalizeSoleasCountryCode(countryCode);
    if (currency) params.currency = String(currency).trim().toUpperCase();
    const { data } = await axios.get(`${SOLEASPAY_API_BASE}/service/list`, {
      params,
      headers: soleasHeaders(token),
      timeout: 10000,
    });
    const services = (Array.isArray(data) ? data : data?.data)
      ?.map(service => {
        const name = String(service.name || service.description || service.code || '').trim();
        const description = String(service.description || '').trim();
        return {
          id: Number(service.id),
          code: String(service.code || service.provider || '').trim().toLowerCase(),
          name,
          description,
          countryCode: normalizeSoleasCountryCode(service.country),
          currency: String(service.currency || '').trim().toUpperCase(),
          provider: String(service.provider || '').trim(),
          type: String(service.type || '').trim(),
          is_active: service.is_active !== false,
          is_public: service.is_public !== false,
          is_need_otp: service.is_need_otp === true,
          is_can_collect: service.is_can_collect === true,
          is_can_disburse: service.is_can_disburse === true,
          confirmation_method: service.confirmation_method || null,
          confirmation_helper: service.confirmation_helper || null,
        };
      })
      .filter(service => Number.isInteger(service.id) && service.id > 0
        && service.code && service.name && service.is_active && service.is_public);

    if (!services?.length) throw new Error('Catalogue MySoleas vide ou invalide');
    soleasServicesCache = { cacheKey, services };
    soleasServicesCachedAt = now;
    return services;
  } catch (error) {
    console.error('MySoleas services catalogue error:', error.response?.data || error.message);
    if (soleasServicesCache?.services?.length) return soleasServicesCache.services;
    return fallbackSoleasServices.filter(service =>
      (!countryCode || service.countryCode === normalizeSoleasCountryCode(countryCode))
      && (!currency || service.currency === String(currency).toUpperCase())
    );
  }
}

function parseProviderMappings(rawValue) {
  if (!rawValue) return {};
  try {
    const parsed = JSON.parse(rawValue);
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
  } catch {
    return {};
  }
}

function normalizeOperatorLabel(value) {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toUpperCase()
    .replace(/[^A-Z0-9]+/g, ' ')
    .trim();
}

function findSoleasServiceForOperator(countryCode, operator, services) {
  const code = normalizeSoleasCountryCode(countryCode);
  const operatorLabel = normalizeOperatorLabel(operator);
  const countryServices = (services || []).filter(service => service.countryCode === code);
  let aliases = [operatorLabel];

  if (operatorLabel.includes('ORANGE')) aliases = ['ORANGE', 'OM'];
  else if (operatorLabel.includes('MTN')) aliases = ['MTN', 'MOMO'];
  else if (operatorLabel.includes('MOOV') || operatorLabel.includes('FLOOZ')) aliases = ['MOOV', 'FLOOZ'];
  else if (operatorLabel.includes('WAVE')) aliases = ['WAVE'];
  else if (operatorLabel.includes('T MONEY')) aliases = ['T MONEY', 'TMONEY'];
  else if (operatorLabel.includes('AIRTEL')) aliases = ['AIRTEL'];

  return countryServices.find(service => {
    const serviceLabel = normalizeOperatorLabel(`${service.code} ${service.name} ${service.provider}`);
    return aliases.some(alias => serviceLabel.includes(alias));
  }) || null;
}

async function getOperatorProvider(countryCode, operator) {
  const params = await getParams();
  const mappings = parseProviderMappings(params.payment_provider_mappings);
  const configured = mappings[countryCode]?.[operator];

  if (configured?.provider === 'soleaspay') {
    const serviceId = Number(configured.service_id);
    return {
      provider: 'soleaspay',
      serviceId: Number.isInteger(serviceId) && serviceId > 0 ? serviceId : null,
    };
  }

  return { provider: 'ashtechpay', serviceId: null };
}

module.exports = {
  ASHTECH_API_BASE,
  SOLEASPAY_API_BASE,
  SOLEASPAY_AUTH_BASE,
  fallbackSoleasServices,
  getAshtechApiKey,
  getAshtechCountries,
  getOperatorProvider,
  getSoleasApiKey,
  getSoleasPrivateSecret,
  getSoleasClientId,
  getSoleasClientSecret,
  getSoleasBearerToken,
  getSoleasServices,
  initiateSoleasCollection,
  executeSoleasCollection,
  verifySoleasCollection,
  initiateSoleasDisbursement,
  verifySoleasDisbursement,
  findSoleasServiceForOperator,
  normalizeSoleasCountryCode,
  normalizeOperatorLabel,
  parseProviderMappings,
};