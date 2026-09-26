const countryDialCodes = {
  CM: '237',
  TG: '228',
  BJ: '229',
  CI: '225',
  BF: '226',
  GA: '241',
  CG: '242',
  NE: '227',
  ML: '223',
  SN: '221',
};

const accountCountryCodeByName = {
  cameroun: 'CM',
  cameroon: 'CM',
  togo: 'TG',
  benin: 'BJ',
  cote: 'CI',
  "cote d'ivoire": 'CI',
  "cote d’ivoire": 'CI',
  'ivory coast': 'CI',
  'burkina faso': 'BF',
  gabon: 'GA',
  'congo brazzaville': 'CG',
  'republic of the congo': 'CG',
  'congo republic': 'CG',
  niger: 'NE',
  mali: 'ML',
  senegal: 'SN',
};

function normalizeCountryName(value) {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim()
    .toLocaleLowerCase('fr-FR');
}

function findAccountPaymentCountry(accountCountry, countries) {
  const value = String(accountCountry || '').trim();
  if (!value || !Array.isArray(countries)) return null;
  const normalizedName = normalizeCountryName(value);
  const normalizedCode = value.toUpperCase();
  const exactMatch = countries.find(country =>
    normalizeCountryName(country.name) === normalizedName
      || String(country.code || '').trim().toUpperCase() === normalizedCode
  );
  if (exactMatch) return exactMatch;

  const aliasCode = accountCountryCodeByName[normalizedName];
  return aliasCode
    ? countries.find(country => String(country.code || '').trim().toUpperCase() === aliasCode) || null
    : null;
}

function getCountryDialCode(countryCode) {
  return countryDialCodes[String(countryCode || '').trim().toUpperCase()] || '';
}

module.exports = {
  findAccountPaymentCountry,
  getCountryDialCode,
  normalizeCountryName,
};