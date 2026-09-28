const test = require('node:test');
const assert = require('node:assert/strict');
const { formatAshtechError } = require('../services/ashtechError');

test('preserves the exact AshTechPay JSON error body without adding a prefix', () => {
  const responseBody = {
    error: 'server_error',
    message: 'Une erreur interne s’est produite.',
  };

  assert.deepEqual(
    formatAshtechError({
      response: { status: 503, data: responseBody },
    }),
    {
      provider: 'ashtechpay',
      body: JSON.stringify(responseBody),
      status: 503,
    },
  );
});

test('preserves a text error body including its whitespace', () => {
  const body = '  Payment rejected by provider.\n';

  assert.deepEqual(
    formatAshtechError({ response: { status: 422, data: body } }),
    { provider: 'ashtechpay', body, status: 422 },
  );
});

test('shows the actual network error when AshTechPay did not return a response', () => {
  assert.deepEqual(
    formatAshtechError({
      code: 'ECONNRESET',
      message: 'socket hang up',
    }),
    {
      provider: 'ashtechpay',
      body: 'ECONNRESET: socket hang up',
      status: null,
    },
  );
});