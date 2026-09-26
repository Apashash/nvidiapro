const test = require('node:test');
const assert = require('node:assert/strict');
const {
  findAccountPaymentCountry,
  getCountryDialCode,
} = require('../services/accountPaymentCountry');

const supportedCountries = [
  { code: 'CM', name: 'Cameroun' },
  { code: 'CI', name: "Côte d'Ivoire" },
];

test('matches a registered country by its localized name or ISO code', () => {
  assert.equal(findAccountPaymentCountry('Cameroun', supportedCountries), supportedCountries[0]);
  assert.equal(findAccountPaymentCountry('CM', supportedCountries), supportedCountries[0]);
  assert.equal(findAccountPaymentCountry("COTE D'IVOIRE", supportedCountries), supportedCountries[1]);
});

test('matches registered French country names when the live catalogue uses English names', () => {
  const englishCountries = [
    { code: 'CM', name: 'Cameroon' },
    { code: 'BJ', name: 'Benin' },
  ];
  assert.equal(findAccountPaymentCountry('Cameroun', englishCountries), englishCountries[0]);
  assert.equal(findAccountPaymentCountry('Bénin', englishCountries), englishCountries[1]);
});

test('does not expose a Mobile Money country for custom identifiers', () => {
  assert.equal(findAccountPaymentCountry('Autre', supportedCountries), null);
  assert.equal(findAccountPaymentCountry('', supportedCountries), null);
});

test('returns the matching phone dial code for supported countries', () => {
  assert.equal(getCountryDialCode('CM'), '237');
  assert.equal(getCountryDialCode('CMR'), '');
});