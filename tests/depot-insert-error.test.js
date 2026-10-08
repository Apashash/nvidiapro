const test = require('node:test');
const assert = require('node:assert/strict');
const {
  createDepotInsertDiagnostic,
  formatDepotInsertError,
} = require('../services/depotInsertError');

test('deposit insert diagnostics explain common PostgreSQL failures safely', () => {
  const diagnostic = createDepotInsertDiagnostic({
    code: '42703',
    message: 'column provider_service_id does not exist',
    column: 'provider_service_id',
    table: 'depots',
  }, 'Ashpayabcde-12345678-abcd');

  assert.equal(diagnostic.code, '42703');
  assert.match(diagnostic.summary, /colonne nécessaire est absente/);
  assert.equal(diagnostic.column, 'provider_service_id');
  assert.match(formatDepotInsertError(diagnostic), /Ashpayabcde-12345678-abcd/);
  assert.doesNotMatch(formatDepotInsertError(diagnostic), /provider_service_id|column/);
});

test('deposit diagnostics redact connection details and omit PostgreSQL detail/query fields', () => {
  const diagnostic = createDepotInsertDiagnostic({
    code: '23514',
    message: 'rejected postgres://user:password@db.example/app',
    detail: 'Key (user_id)=(12345) violates a rule',
    query: 'INSERT INTO depots ...',
    constraint: 'depots_status_check',
  }, 'reference-1');

  assert.equal(diagnostic.message, 'rejected [URL de connexion masquée]');
  assert.equal(diagnostic.detail, 'Key (user_id)=[valeur masquée] violates a rule');
  assert.equal(diagnostic.query, undefined);
  assert.equal(diagnostic.constraint, 'depots_status_check');
});

test('unrecognized PostgreSQL errors get a safe fallback diagnosis', () => {
  const diagnostic = createDepotInsertDiagnostic({ message: 'failure' }, 'reference-2');

  assert.equal(diagnostic.code, 'INCONNU');
  assert.match(diagnostic.summary, /La base a refusé l’enregistrement/);
});
