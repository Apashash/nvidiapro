const test = require('node:test');
const assert = require('node:assert/strict');
const {
  MAX_VIP_LEVEL,
  getNextVipSalaryLevel,
  validateVipSalaryTierInput,
} = require('../services/vipSalaryTiers');

test('next salary tier is the first missing VIP level', () => {
  assert.equal(getNextVipSalaryLevel([{ niveau: 1 }, { niveau: 3 }]), 2);
  assert.equal(getNextVipSalaryLevel([]), 1);
});

test('no next tier is returned after every VIP level is configured', () => {
  const tiers = Array.from({ length: MAX_VIP_LEVEL }, (_, index) => ({ niveau: index + 1 }));
  assert.equal(getNextVipSalaryLevel(tiers), null);
});

test('valid create data is normalized for storage', () => {
  const result = validateVipSalaryTierInput({
    niveau: '2',
    label: '  VIP Argent  ',
    filleuls_requis: '6',
    montant_cadeau: '2500.50',
  });

  assert.equal(result.valid, true);
  assert.deepEqual(result.value, {
    id: null,
    niveau: 2,
    label: 'VIP Argent',
    filleuls_requis: 6,
    montant_cadeau: 2500.5,
  });
});

test('valid edit data requires an id and does not accept a tier-level change', () => {
  const result = validateVipSalaryTierInput({
    id: '12',
    niveau: '9',
    label: 'VIP Or',
    filleuls_requis: '9',
    montant_cadeau: '5000',
  });

  assert.equal(result.valid, true);
  assert.equal(result.value.id, 12);
  assert.equal(result.value.niveau, null);
});

test('invalid levels, referral counts, labels, and payout amounts are rejected', () => {
  const base = {
    niveau: '1',
    label: 'VIP 1',
    filleuls_requis: '3',
    montant_cadeau: '1000',
  };
  const invalidInputs = [
    { ...base, niveau: String(MAX_VIP_LEVEL + 1) },
    { ...base, filleuls_requis: '0' },
    { ...base, filleuls_requis: '1.5' },
    { ...base, montant_cadeau: '0' },
    { ...base, montant_cadeau: '1000.001' },
    { ...base, montant_cadeau: '10000000.01' },
    { ...base, label: 'x'.repeat(256) },
    { ...base, id: '0' },
  ];

  for (const input of invalidInputs) {
    assert.equal(validateVipSalaryTierInput(input).valid, false, JSON.stringify(input));
  }
});