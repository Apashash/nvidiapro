const test = require('node:test');
const assert = require('node:assert/strict');
const {
  calculateUsdtAmount,
  calculateManualUsdtWithdrawalAmount,
  findAshtechCryptoAsset,
  getCryptoExpiry,
  getManualUsdtWithdrawalAssets,
  getCryptoPollTimeoutMs,
  normalizeAshtechCryptoAssets,
  normalizeAshtechCryptoCollectResponse,
} = require('../services/ashtechCrypto');

const asset = {
  asset_code: 'USDT.TRC20',
  coin: 'USDT',
  name: 'USDT',
  network: 'TRON',
  network_label: 'Tron (TRON)',
  memo_required: false,
  memo_type: null,
  currency: 'USDT',
};

const payment = {
  transaction_id: 'tx_123',
  reference: 'ashtech_ref_123',
  merchant_reference: 'merchant_ref_123',
  status: 'pending',
  payment_method: 'crypto',
  asset_code: asset.asset_code,
  network: asset.network,
  address: 'TExampleAddress',
  memo: null,
  memo_type: null,
  amount: 25,
  currency: 'USDT',
  amount_usdt: 25,
  credited_amount: 24.5,
  fee_amount: 0.5,
  gross_amount_usdt: 25,
  provider_fee_percent: 0,
  provider_fee_amount_usdt: 0,
  ashtech_fee_percent: 2,
  ashtech_fee_amount_usdt: 0.5,
  total_fee_percent: 2,
  total_fee_amount_usdt: 0.5,
  credited_amount_usdt: 24.5,
  fee_amount_usdt: 0.5,
  fee_percent: 2,
  expires_at: '2026-09-26T12:15:00.000Z',
  created_at: '2026-09-26T12:00:00.000Z',
};

test('normalizes the live AshTech crypto asset catalogue without adding networks', () => {
  assert.deepEqual(normalizeAshtechCryptoAssets({ assets: [asset] }), [asset]);
  assert.equal(findAshtechCryptoAsset([asset], asset.asset_code), asset);
  assert.equal(findAshtechCryptoAsset([asset], 'USDT.UNKNOWN'), null);
  assert.throws(() => normalizeAshtechCryptoAssets({ assets: [] }), /aucun actif valide/i);
});

test('converts FCFA to a two-decimal USDT request using the selected rate', () => {
  assert.equal(calculateUsdtAmount(200, 600), '0.33');
  assert.equal(calculateUsdtAmount(6000, 600), '10.00');
  assert.throws(() => calculateUsdtAmount(0, 600), /invalide/i);
});

test('converts net FCFA to USDT without rounding the manual payout upward', () => {
  assert.equal(calculateManualUsdtWithdrawalAmount(1000, 600), '1.66');
  assert.equal(calculateManualUsdtWithdrawalAmount(6000, 600), '10.00');
  assert.throws(() => calculateManualUsdtWithdrawalAmount(1, 600), /trop faible/i);
});

test('limits manual USDT withdrawal choices to networks without a required memo', () => {
  assert.deepEqual(
    getManualUsdtWithdrawalAssets([
      asset,
      { ...asset, asset_code: 'USDT.MEMO', memo_required: true },
      { ...asset, asset_code: 'BTC.TRC20', coin: 'BTC' },
    ]),
    [asset],
  );
});

test('validates the documented crypto collect response against the selected asset', () => {
  const normalized = normalizeAshtechCryptoCollectResponse(payment, asset);
  assert.equal(normalized.transaction_id, payment.transaction_id);
  assert.equal(normalized.address, payment.address);
  assert.equal(normalized.amount_usdt, 25);
  assert.equal(normalized.memo, null);
  assert.throws(
    () => normalizeAshtechCryptoCollectResponse({ ...payment, network: 'OTHER' }, asset),
    /ne correspond pas/i,
  );
  assert.throws(
    () => normalizeAshtechCryptoCollectResponse({ ...payment, address: '' }, asset),
    /address valide/i,
  );
});

test('requires the destination memo when the selected network mandates one', () => {
  const memoAsset = { ...asset, memo_required: true, memo_type: 'destination tag' };
  assert.throws(
    () => normalizeAshtechCryptoCollectResponse(payment, memoAsset),
    /memo\/tag requis/i,
  );
  const normalized = normalizeAshtechCryptoCollectResponse({
    ...payment,
    memo: '987654321',
    memo_type: 'destination tag',
  }, memoAsset);
  assert.equal(normalized.memo, '987654321');
});

test('uses provider expiry when present and enforces the documented 15-minute fallback', () => {
  assert.equal(getCryptoExpiry(payment), new Date(payment.expires_at).toISOString());
  assert.equal(
    getCryptoExpiry({ created_at: null, expires_at: null }, Date.parse('2026-09-26T12:00:00Z')),
    '2026-09-26T12:15:00.000Z',
  );
});

test('keeps server status polling active through the provider expiry plus grace', () => {
  const now = Date.parse('2026-09-26T12:00:00.000Z');
  assert.equal(
    getCryptoPollTimeoutMs({ expires_at: '2026-09-26T13:00:00.000Z' }, now),
    65 * 60 * 1000,
  );
  assert.equal(getCryptoPollTimeoutMs({}, now), 20 * 60 * 1000);
});