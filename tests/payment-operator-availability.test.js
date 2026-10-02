const test = require('node:test');
const assert = require('node:assert/strict');
const {
  filterEnabledPaymentOperators,
  isPaymentOperatorEnabled,
  parseProviderMappings,
} = require('../services/paymentProviders');

const countries = [
  { code: 'CM', name: 'Cameroun', currency: 'XAF', operators: ['Orange Money', 'MTN Mobile Money'] },
  { code: 'CI', name: "Côte d'Ivoire", currency: 'XOF', operators: ['Wave'] },
];

test('payment operators stay enabled when no explicit status is stored', () => {
  assert.equal(isPaymentOperatorEnabled('CM', 'Orange Money', {}), true);
  assert.equal(filterEnabledPaymentOperators(countries, null).length, 2);
});

test('disabled operators are filtered and countries with none are omitted', () => {
  const mappings = {
    CM: {
      'Orange Money': { enabled: false },
      'MTN Mobile Money': { enabled: false },
    },
    CI: {
      Wave: { enabled: true },
    },
  };

  assert.deepEqual(
    filterEnabledPaymentOperators(countries, mappings).map(country => [country.code, country.operators]),
    [['CI', ['Wave']]]
  );
  assert.deepEqual(countries[0].operators, ['Orange Money', 'MTN Mobile Money']);
});

test('provider mapping parser accepts persisted JSON and object values', () => {
  const mappings = { CM: { Wave: { enabled: false } } };
  assert.deepEqual(parseProviderMappings(JSON.stringify(mappings)), mappings);
  assert.deepEqual(parseProviderMappings(mappings), mappings);
});