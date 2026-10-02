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

test('POST withdrawal checks prerequisites before creating a withdrawal request', () => {
  const route = fs.readFileSync(path.join(__dirname, '..', 'routes/retrait.js'), 'utf8');
  const postHandlerStart = route.indexOf("router.post('/retrait'");
  const prerequisiteCheck = route.indexOf(
    'const { hasPurchasedAction, hasValidatedDeposit } = await getWithdrawalPrerequisites(user_id);',
    postHandlerStart
  );
  const rejectedWhenIneligible = route.indexOf('if (withdrawalPrerequisiteMessage)', prerequisiteCheck);
  const withdrawalInsert = route.indexOf('INSERT INTO retraits', postHandlerStart);

  assert.ok(postHandlerStart >= 0, 'POST /retrait route exists');
  assert.ok(prerequisiteCheck > postHandlerStart, 'POST route checks both prerequisites');
  assert.ok(rejectedWhenIneligible > prerequisiteCheck, 'POST route rejects missing prerequisites');
  assert.ok(withdrawalInsert > rejectedWhenIneligible, 'request is inserted only after prerequisite checks');
});

test('withdrawal page shows the prerequisite message and blocks an unavailable request', () => {
  const view = fs.readFileSync(path.join(__dirname, '..', 'views/retrait.ejs'), 'utf8');
  assert.match(view, /<span><%= withdrawalPrerequisiteMessage %><\/span>/);
  assert.match(view, /if \(!retraitDisponible\)/);
  assert.match(view, /withdrawalPrerequisiteMessage \|\| scheduleMessage/);
});