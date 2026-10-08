const axios = require('axios');
const crypto = require('crypto');
const net = require('node:net');

const DEFAULT_ASHTECH_API_BASE = 'https://www.ashtechpay.com';
const WEBHOOK_MAX_AGE_MS = 5 * 60 * 1000;

function requiredSetting(env, name, message) {
  const value = String(env[name] || '').trim();
  if (!value) throw new Error(message || `${name} n’est pas configuré sur le serveur.`);
  return value;
}

function normalizeApiBase(value) {
  return String(value || DEFAULT_ASHTECH_API_BASE).replace(/\/+$/, '');
}

function normalizeAshtechTransactionStatus(status) {
  const normalized = String(status || '').trim().toLowerCase();
  if (normalized === 'success' || normalized === 'completed') return 'success';
  if (normalized === 'failed') return 'failed';
  return 'pending';
}

function validateAshtechNotifyUrl(value) {
  const configured = String(value || '').trim();
  if (!configured) {
    throw new Error('ASHTECH_NOTIFY_URL doit être configuré avec l’URL HTTPS publique de /ashtechpay_callback.');
  }

  let parsed;
  try {
    parsed = new URL(configured);
  } catch {
    throw new Error('ASHTECH_NOTIFY_URL doit être une URL HTTPS valide.');
  }

  const hostname = parsed.hostname.toLowerCase();
  const privateAddress = hostname === 'localhost'
    || hostname.endsWith('.localhost')
    || hostname.endsWith('.local')
    || hostname.endsWith('.internal')
    || Boolean(net.isIP(hostname.replace(/^\[|\]$/g, '')))
    || /^127\./.test(hostname)
    || /^10\./.test(hostname)
    || /^192\.168\./.test(hostname)
    || /^169\.254\./.test(hostname)
    || /^172\.(1[6-9]|2\d|3[01])\./.test(hostname);
  if (parsed.protocol !== 'https:' || privateAddress || parsed.port
      || parsed.username || parsed.password
      || parsed.pathname !== '/ashtechpay_callback'
      || parsed.search || parsed.hash) {
    throw new Error('ASHTECH_NOTIFY_URL doit être une URL HTTPS publique se terminant par /ashtechpay_callback.');
  }

  return parsed.toString();
}

function verifyAshtechWebhookSignature({
  secret,
  timestamp,
  signature,
  rawBody,
  now = Date.now(),
  maxAgeMs = WEBHOOK_MAX_AGE_MS,
}) {
  const webhookSecret = String(secret || '').trim();
  const timestampText = String(timestamp || '').trim();
  const signatureText = String(signature || '').trim().replace(/^sha256=/i, '');
  const body = Buffer.isBuffer(rawBody) ? rawBody : Buffer.from(String(rawBody || ''), 'utf8');
  if (!webhookSecret || !/^\d{10}$/.test(timestampText)
      || !/^[a-f0-9]{64}$/i.test(signatureText)) return false;

  const timestampNumber = Number(timestampText);
  const timestampMs = timestampNumber * 1000;
  if (!Number.isFinite(timestampMs) || Math.abs(now - timestampMs) > maxAgeMs) return false;

  const expected = crypto.createHmac('sha256', webhookSecret)
    .update(Buffer.concat([Buffer.from(`${timestampText}.`, 'utf8'), body]))
    .digest();
  const provided = Buffer.from(signatureText, 'hex');
  return provided.length === expected.length && crypto.timingSafeEqual(provided, expected);
}

function createAshtechPayClient({ http = axios, env = process.env } = {}) {
  const apiBase = normalizeApiBase(env.ASHTECH_API_BASE);

  function getAshtechApiKey() {
    return String(env.ASHTECH_API_KEY || '').trim() || null;
  }

  function getAshtechUserId() {
    return requiredSetting(
      env,
      'ASHTECH_USER_ID',
      'ASHTECH_USER_ID doit correspondre au profil AshTech Pay associé à ASHTECH_API_KEY.',
    );
  }

  function directHeaders() {
    const apiKey = getAshtechApiKey();
    if (!apiKey) throw new Error('ASHTECH_API_KEY n’est pas configuré sur le serveur.');
    return { Authorization: `Bearer ${apiKey}`, 'Content-Type': 'application/json' };
  }

  async function getCountries(operation) {
    const params = operation === 'payout' ? { operation: 'payout' } : undefined;
    const { data } = await http.get(`${apiBase}/v1/countries`, {
      headers: directHeaders(),
      params,
      timeout: 10000,
    });
    return data;
  }

  async function getFees() {
    const { data } = await http.get(`${apiBase}/v1/fees`, {
      headers: directHeaders(),
      timeout: 10000,
    });
    return data;
  }

  async function getCryptoAssets() {
    const { data } = await http.get(`${apiBase}/v1/crypto/assets`, {
      headers: directHeaders(),
      timeout: 10000,
    });
    return data;
  }

  async function createMobileMoneyCollection(payload) {
    const { data } = await http.post(
      `${apiBase}/v1/collect`,
      { ...payload, user_id: getAshtechUserId() },
      { headers: directHeaders(), timeout: 15000 },
    );
    return data;
  }

  async function createCryptoCollection(payload) {
    const { data } = await http.post(
      `${apiBase}/v1/crypto/collect`,
      { ...payload, user_id: getAshtechUserId() },
      { headers: directHeaders(), timeout: 15000 },
    );
    return data;
  }

  async function getTransactionStatus(transactionId) {
    const id = String(transactionId || '').trim();
    if (!id) throw new Error('transaction_id AshTech Pay absent.');
    const { data } = await http.get(
      `${apiBase}/v1/transaction/${encodeURIComponent(id)}`,
      { headers: directHeaders(), params: { user_id: getAshtechUserId() }, timeout: 10000 },
    );
    return data;
  }

  async function createMobileMoneyPayout(payload) {
    const { data } = await http.post(
      `${apiBase}/v1/payouts/mobile-money`,
      { ...payload, user_id: getAshtechUserId() },
      { headers: directHeaders(), timeout: 15000 },
    );
    return data;
  }

  async function createCryptoPayout(payload) {
    const { data } = await http.post(
      `${apiBase}/v1/payouts/crypto`,
      { ...payload, user_id: getAshtechUserId() },
      { headers: directHeaders(), timeout: 15000 },
    );
    return data;
  }

  return {
    apiBase,
    getAshtechApiKey,
    getAshtechUserId,
    getCountries,
    getFees,
    getCryptoAssets,
    createMobileMoneyCollection,
    createCryptoCollection,
    getTransactionStatus,
    createMobileMoneyPayout,
    createCryptoPayout,
  };
}

const ashtechPayClient = createAshtechPayClient();

module.exports = {
  ...ashtechPayClient,
  DEFAULT_ASHTECH_API_BASE,
  WEBHOOK_MAX_AGE_MS,
  createAshtechPayClient,
  normalizeAshtechTransactionStatus,
  validateAshtechNotifyUrl,
  verifyAshtechWebhookSignature,
};
