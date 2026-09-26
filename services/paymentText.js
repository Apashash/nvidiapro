function sanitizePaymentMessage(message) {
  return String(message ?? '')
    .replace(/\bAshTech(?:\s*Pay)?\b/gi, 'service de paiement');
}

module.exports = { sanitizePaymentMessage };