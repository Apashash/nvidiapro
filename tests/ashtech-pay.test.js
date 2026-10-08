const test = require('node:test');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const {
  createAshtechPayClient,
  normalizeAshtechTransactionStatus,
  validateAshtechNotifyUrl,
  verifyAshtechWebhookSignature,
} = require('../services/ashtechPay');
const {
  normalizeAshtechPayoutStatus,
  parseProviderMetadata,
  settleAshtechPayout,
} = require('../services/ashtechPayouts');

function fakeHttp() {
  const calls = [];
  return {
    calls,
    get: async (url, options) => {
      calls.push({ method: 'get', url, options });
      return { data: { status: 'pending' } };
    },
    post: async (url, body, options) => {
      calls.push({ method: 'post', url, body, options });
      return { data: { transaction_id: 'txn-test' } };
    },
  };
}

test('Direct API calls use the Direct key and attach the configured profile user_id', async () => {
  const http = fakeHttp();
  const client = createAshtechPayClient({
    http,
    env: {
      ASHTECH_API_BASE: 'https://payments.example.test',
      ASHTECH_API_KEY: 'direct-test-key',
      ASHTECH_USER_ID: 'merchant-profile-42',
    },
  });

  await client.getCountries('payout');
  await client.getFees();
  await client.getCryptoAssets();
  await client.createMobileMoneyCollection({ amount: 5000, reference: 'order-1' });
  await client.createCryptoCollection({ amount: 25, reference: 'order-2' });
  await client.getTransactionStatus('transaction/3');
  await client.createMobileMoneyPayout({ reference: 'order-4' });
  await client.createCryptoPayout({ reference: 'order-5' });

  assert.equal(http.calls[0].url, 'https://payments.example.test/v1/countries');
  assert.deepEqual(http.calls[0].options.params, { operation: 'payout' });
  assert.equal(http.calls[1].url, 'https://payments.example.test/v1/fees');
  assert.equal(http.calls[2].url, 'https://payments.example.test/v1/crypto/assets');
  for (const call of [http.calls[3], http.calls[4], http.calls[6], http.calls[7]]) {
    assert.equal(call.options.headers.Authorization, 'Bearer direct-test-key');
    assert.equal(call.body.user_id, 'merchant-profile-42');
  }
  assert.equal(http.calls[5].url, 'https://payments.example.test/v1/transaction/transaction%2F3');
  assert.deepEqual(http.calls[5].options.params, { user_id: 'merchant-profile-42' });
});

test('Webhook verification uses the raw body, Unix-second timestamp, and HMAC-SHA256', () => {
  const secret = 'webhook-test-secret';
  const timestamp = String(Math.floor(Date.now() / 1000));
  const body = Buffer.from('{"event":"payment.completed","transaction_id":"txn-1"}');
  const signature = crypto.createHmac('sha256', secret)
    .update(`${timestamp}.`)
    .update(body)
    .digest('hex');

  assert.equal(verifyAshtechWebhookSignature({
    secret,
    timestamp,
    signature: `sha256=${signature}`,
    rawBody: body,
  }), true);
  assert.equal(verifyAshtechWebhookSignature({
    secret,
    timestamp: String(Number(timestamp) - 600),
    signature,
    rawBody: body,
  }), false);
  assert.equal(verifyAshtechWebhookSignature({
    secret,
    timestamp,
    signature,
    rawBody: Buffer.from(`${body.toString()} `),
  }), false);
  assert.equal(verifyAshtechWebhookSignature({
    secret,
    timestamp: `${timestamp}000`,
    signature,
    rawBody: body,
  }), false);
});

test('notify URL must be public HTTPS and point to the exact callback route', () => {
  assert.equal(
    validateAshtechNotifyUrl('https://payments.example.test/ashtechpay_callback'),
    'https://payments.example.test/ashtechpay_callback',
  );
  for (const invalid of [
    'http://payments.example.test/ashtechpay_callback',
    'https://localhost/ashtechpay_callback',
    'https://10.0.0.2/ashtechpay_callback',
    'https://payments.example.test/other',
    'https://payments.example.test/ashtechpay_callback?next=/',
  ]) {
    assert.throws(() => validateAshtechNotifyUrl(invalid));
  }
});

test('unrecognized provider statuses remain pending rather than being treated as failure', () => {
  assert.equal(normalizeAshtechTransactionStatus('completed'), 'success');
  assert.equal(normalizeAshtechTransactionStatus('failed'), 'failed');
  assert.equal(normalizeAshtechTransactionStatus('cancelled'), 'pending');
  assert.equal(normalizeAshtechPayoutStatus('pending_manual'), 'pending');
  assert.equal(normalizeAshtechPayoutStatus('completed'), 'success');
  assert.equal(normalizeAshtechPayoutStatus('rejected'), 'unknown');
});

test('provider metadata parser tolerates already-decoded JSONB and invalid legacy values', () => {
  assert.deepEqual(parseProviderMetadata({ asset_code: 'usdt_trc20' }), { asset_code: 'usdt_trc20' });
  assert.deepEqual(parseProviderMetadata('{"reference":"order-1"}'), { reference: 'order-1' });
  assert.deepEqual(parseProviderMetadata('{bad json'), {});
  assert.deepEqual(parseProviderMetadata(null), {});
});

test('a failed AshTech payout refunds the debited balance once, even if settlement is repeated', async () => {
  const withdrawal = {
    id: 8,
    user_id: 42,
    montant: '1200.00',
    statut: 'en_cours',
    fournisseur: 'ashtechpay',
  };
  let balance = 0;
  const db = {
    async getConnection() {
      return {
        async beginTransaction() {},
        async commit() {},
        async rollback() {},
        release() {},
        async query(sql, values) {
          if (sql.startsWith('SELECT * FROM retraits')) return [[withdrawal], []];
          if (sql.startsWith('UPDATE soldes')) {
            balance += Number(values[0]);
            return [{ affectedRows: 1 }, []];
          }
          if (sql.startsWith('UPDATE retraits')) {
            withdrawal.statut = 'rejete';
            return [{ affectedRows: 1 }, []];
          }
          throw new Error(`Unexpected test query: ${sql}`);
        },
      };
    },
  };

  assert.deepEqual(await settleAshtechPayout(db, withdrawal.id, 'failed'), { ok: true, status: 'failed' });
  assert.equal(balance, 1200);
  assert.equal(withdrawal.statut, 'rejete');
  assert.deepEqual(await settleAshtechPayout(db, withdrawal.id, 'failed'), { ok: true, status: 'rejete' });
  assert.equal(balance, 1200);
});
