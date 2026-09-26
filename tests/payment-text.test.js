const test = require('node:test');
const assert = require('node:assert/strict');
const { sanitizePaymentMessage } = require('../services/paymentText');

test('removes provider names from customer-facing payment messages', () => {
  for (const message of [
    'AshTechPay a confirmé le paiement.',
    'AshTech doit retourner les détails.',
    'Erreur venant de ashtech pay.',
  ]) {
    const sanitized = sanitizePaymentMessage(message);
    assert.doesNotMatch(sanitized, /ashtech/i);
    assert.match(sanitized, /service de paiement/i);
  }
});

test('preserves payment messages that do not contain provider names', () => {
  assert.equal(
    sanitizePaymentMessage('Vérifiez le réseau avant le transfert.'),
    'Vérifiez le réseau avant le transfert.',
  );
});