const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const {
  getWithdrawalPrerequisiteMessage,
  VALIDATED_DEPOSIT_REQUIRED_MESSAGE,
  PURCHASED_ACTION_REQUIRED_MESSAGE,
} = require('../services/withdrawalEligibility');

test('blocks withdrawal until there is a validated deposit', () => {
  assert.equal(
    getWithdrawalPrerequisiteMessage({
      hasValidatedDeposit: false,
      hasPurchasedAction: true,
    }),
    VALIDATED_DEPOSIT_REQUIRED_MESSAGE
  );
});

test('still requires a purchased action after a validated deposit', () => {
  assert.equal(
    getWithdrawalPrerequisiteMessage({
      hasValidatedDeposit: true,
      hasPurchasedAction: false,
    }),
    PURCHASED_ACTION_REQUIRED_MESSAGE
  );
});

test('allows prerequisite checks only when both conditions are met', () => {
  assert.equal(
    getWithdrawalPrerequisiteMessage({
      hasValidatedDeposit: true,
      hasPurchasedAction: true,
    }),
    null
  );
});

test('withdrawal route counts only validated deposits', () => {
  const route = fs.readFileSync(path.join(__dirname, '..', 'routes/retrait.js'), 'utf8');
  assert.match(route, /depots WHERE user_id = \? AND statut = 'valide'/);
});