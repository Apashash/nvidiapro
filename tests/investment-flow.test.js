const test = require('node:test');
const assert = require('node:assert/strict');
const { parseStrategy, rateForAmount } = require('../services/investmentStrategy');

const refineryStrategy = [
  { min: 2250, max: 6000, rate: 5 },
  { min: 6001, max: 15000, rate: 7 },
  { min: 15001, max: 40000, rate: 10 },
  { min: 40001, max: 170000, rate: 15 },
  { min: 170001, max: 10000000, rate: 20 },
  { min: 10000001, max: null, rate: 20 },
];

test('Dangote Refinery: 10 actions cost 2250 FCFA and earn 112.50 FCFA/day', () => {
  const amount = 225 * 10;
  const rate = rateForAmount(amount, refineryStrategy);
  const dailyGain = Math.round(amount * rate / 100 * 100) / 100;

  assert.equal(amount, 2250);
  assert.equal(rate, 5);
  assert.equal(dailyGain, 112.5);
});

test('rateForAmount keeps the previous rate inside legacy decimal gaps', () => {
  assert.equal(rateForAmount(6000, refineryStrategy), 5);
  assert.equal(rateForAmount(6000.5, refineryStrategy), 5);
  assert.equal(rateForAmount(6001, refineryStrategy), 7);
  assert.equal(rateForAmount(15000.5, refineryStrategy), 7);
  assert.equal(rateForAmount(15001, refineryStrategy), 10);
});

test('stored JSON strategies are normalized without changing their rate tiers', () => {
  const parsed = parseStrategy(JSON.stringify(refineryStrategy));
  assert.deepEqual(parsed, refineryStrategy);
  assert.equal(rateForAmount(10000001, parsed), 20);
});

test('invalid amounts do not produce an investment rate', () => {
  assert.equal(rateForAmount(-1, refineryStrategy), 0);
  assert.equal(rateForAmount('not-a-number', refineryStrategy), 0);
});