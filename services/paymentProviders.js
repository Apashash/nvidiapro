const axios = require('axios');
const { getParams } = require('./params');

const ASHTECH_API_BASE = process.env.ASHTECH_API_BASE || 'https://www.ashtechpay.com';
const SOLEASPAY_API_BASE = process.env.SOLEASPAY_API_BASE || 'https://soleaspay.com';
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
  { id: 1, name: 'MOMO', description: 'Mobile Money', type: 'TRUSTEECURRENCY', is_active: true },
  { id: 2, name: 'OM', description: 'Orange Money', type: 'TRUSTEECURRENCY', is_active: true },
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

async function getSoleasBearerToken() {
  const now = Date.now();
  if (soleasBearerToken && now < soleasBearerTokenExpiresAt) return soleasBearerToken;

  const apiKey = getSoleasApiKey();
  const privateSecret = getSoleasPrivateSecret();
  if (!apiKey) throw new Error('SoleasPay : clé API absente du serveur.');
  if (!privateSecret) throw new Error('SoleasPay : clé privée de paiement absente du serveur.');

  const { data } = await axios.post(`${SOLEASPAY_API_BASE}/api/action/auth`, {
    public_apikey: apiKey,
    private_secretkey: privateSecret,
  }, { headers: { 'Content-Type': 'application/json' }, timeout: 15000 });

  if (!data?.token) throw new Error(data?.message || 'SoleasPay : authentification impossible.');
  soleasBearerToken = String(data.token);
  soleasBearerTokenExpiresAt = now + 55 * 60 * 1000;
  return soleasBearerToken;
}

async function initiateSoleasDisbursement({ wallet, amount, currency, serviceId }) {
  const token = await getSoleasBearerToken();
  const { data, status } = await axios.post(
    `${SOLEASPAY_API_BASE}/api/action/account/withdraw`,
    { wallet: String(wallet), amount: Number(amount), currency: String(currency) },
    {
      headers: {
        Authorization: `Bearer ${token}`,
        'X-SP-AUTH-TOKEN': `Bearer ${token}`,
        operation: '4',
        service: String(serviceId),
        'Content-Type': 'application/json',
      },
      timeout: 15000,
    }
  );

  if (!data?.success || !data?.data?.reference) {
    const error = new Error(data?.message || 'SoleasPay a refusé le retrait.');
    error.response = { status, data };
    throw error;
  }
  return data;
}

async function verifySoleasDisbursement({ orderId, payId, serviceId }) {
  const apiKey = getSoleasApiKey();
  if (!apiKey) throw new Error('SoleasPay : clé API absente du serveur.');
  const { data } = await axios.get(`${SOLEASPAY_API_BASE}/api/agent/verif-pay`, {
    params: { orderId: String(orderId), payId: String(payId) },
    headers: {
      'x-api-key': apiKey,
      operation: '4',
      service: String(serviceId),
    },
    timeout: 10000,
  });
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

async function getSoleasServices() {
  const now = Date.now();
  if (soleasServicesCache && now - soleasServicesCachedAt < COUNTRY_CACHE_TTL_MS) {
    return soleasServicesCache;
  }

  try {
    const headers = {};
    const apiKey = getSoleasApiKey();
    if (apiKey) headers['x-api-key'] = apiKey;

    const { data } = await axios.get(`${SOLEASPAY_API_BASE}/api/services-list`, {
      headers,
      timeout: 10000,
    });
    const services = (Array.isArray(data) ? data : data?.data)
      ?.map(service => {
        const name = String(service.name || '').trim();
        const description = String(service.description || '').trim();
        const searchable = `${name} ${description}`.toUpperCase();
        const type = String(service.type || '').trim();
        const isMobileMoney = type === 'TRUSTEECURRENCY'
          || /\b(MOMO|MONEY|MOOV|WAVE|AIRTEL|FLOOZ|OM)\b/.test(searchable);
        return {
          id: Number(service.id),
          name,
          description,
          type,
          countryCode: String(service.country?.code || '').trim().toUpperCase(),
          countryName: String(service.country?.name || '').trim(),
          is_active: service.is_active !== false,
          withdrawable: service.withdrawable !== false,
          isMobileMoney,
        };
      })
      .filter(service => Number.isInteger(service.id) && service.id > 0
        && service.name && service.is_active && service.isMobileMoney)
      .map(({ isMobileMoney, ...service }) => service);

    if (!services?.length) throw new Error('Catalogue SoleasPay vide ou invalide');
    soleasServicesCache = services;
    soleasServicesCachedAt = now;
    return services;
  } catch (error) {
    console.error('SoleasPay services catalogue error:', error.response?.data || error.message);
    return soleasServicesCache || fallbackSoleasServices;
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
  const code = String(countryCode || '').trim().toUpperCase();
  const operatorLabel = normalizeOperatorLabel(operator);
  const countryServices = (services || []).filter(service => service.countryCode === code);
  let aliases = [];

  if (operatorLabel.includes('ORANGE')) aliases = ['OM'];
  else if (operatorLabel.includes('MTN')) aliases = ['MOMO'];
  else if (operatorLabel.includes('MOOV') || operatorLabel.includes('FLOOZ')) aliases = ['MOOV'];
  else if (operatorLabel.includes('WAVE')) aliases = ['WAVE'];
  else if (operatorLabel.includes('T MONEY')) aliases = ['T MONEY'];
  else if (operatorLabel.includes('AIRTEL')) aliases = ['AIRTEL'];

  return countryServices.find(service => {
    const serviceLabel = normalizeOperatorLabel(service.name);
    return aliases.some(alias => serviceLabel.startsWith(alias + ' ') || serviceLabel === alias);
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
  fallbackSoleasServices,
  getAshtechApiKey,
  getAshtechCountries,
  getOperatorProvider,
  getSoleasApiKey,
  getSoleasPrivateSecret,
  getSoleasBearerToken,
  getSoleasServices,
  initiateSoleasDisbursement,
  verifySoleasDisbursement,
  findSoleasServiceForOperator,
  normalizeOperatorLabel,
  parseProviderMappings,
};