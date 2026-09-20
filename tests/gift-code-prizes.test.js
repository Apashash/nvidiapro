const test = require('node:test');
const assert = require('node:assert/strict');
const {
  drawPrize,
  parsePrizeTiers,
  validatePrizeTiers,
} = require('../services/giftCodePrizes');

const referenceTiers = [
  { min: 0, max: 0, probability: 14 },
  { min: 25, max: 100, probability: 70 },
  { min: 101, max: 300, probability: 10 },
  { min: 301, max: 400, probability: 5 },
  { min: 401, max: 500, probability: 1 },
];

test('validates the reference prize configuration at exactly 100 percent', () => {
  assert.deepEqual(validatePrizeTiers(referenceTiers), referenceTiers);
  assert.deepEqual(parsePrizeTiers(JSON.stringify(referenceTiers)), referenceTiers);
});

test('rejects incomplete probabilities and overlapping ranges', () => {
  assert.throws(
    () => validatePrizeTiers([{ min: 0, max: 10, probability: 50 }]),
    /exactement de 100/
  );
  assert.throws(
    () => validatePrizeTiers([
      { min: 0, max: 100, probability: 50 },
      { min: 100, max: 200, probability: 50 },
    ]),
    /chevaucher/
  );
});

test('draws a zero-gain result and an amount inside the selected range', () => {
  assert.equal(drawPrize(referenceTiers, () => 0.05).amount, 0);

  const randomValues = [0.85, 0.5];
  const result = drawPrize(referenceTiers, () => randomValues.shift());
  assert.equal(result.tier.probability, 10);
  assert.ok(result.amount >= 101 && result.amount <= 300);
});

test('legacy fixed codes remain supported, including decimal amounts', () => {
  const tiers = parsePrizeTiers(null, 25.5);
  assert.deepEqual(tiers, [{ min: 25.5, max: 25.5, probability: 100 }]);
  assert.equal(drawPrize(tiers, () => 0.5).amount, 25.5);
});