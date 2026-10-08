const test = require('node:test');
const assert = require('node:assert/strict');
const {
  DEPOT_PROVIDER_COLUMNS,
  DEPOT_PROVIDER_SCHEMA_STATEMENTS,
  applyDepotProviderSchema,
} = require('../scripts/depot-provider-schema');

function createFakeClient({ tableExists = true, columns = DEPOT_PROVIDER_COLUMNS } = {}) {
  const calls = [];
  return {
    calls,
    async query(sql, values) {
      calls.push({ sql, values });
      if (sql.includes('to_regclass')) {
        return { rows: [{ table_name: tableExists ? 'public.depots' : null }] };
      }
      if (sql.includes('information_schema.columns')) {
        return { rows: columns.map(column_name => ({ column_name })) };
      }
      return { rows: [] };
    },
  };
}

test('deposit provider migration adds all missing columns before creating the index', async () => {
  const client = createFakeClient();
  const columns = await applyDepotProviderSchema(client);

  assert.deepEqual(columns, DEPOT_PROVIDER_COLUMNS);
  assert.deepEqual(
    client.calls.slice(1, -1).map(call => call.sql),
    [...DEPOT_PROVIDER_SCHEMA_STATEMENTS],
  );
  assert.match(client.calls.at(-1).sql, /information_schema\.columns/);
  assert.match(client.calls[1].sql, /ADD COLUMN IF NOT EXISTS fournisseur/);
  assert.match(client.calls.at(-2).sql, /CREATE INDEX IF NOT EXISTS/);
});

test('deposit provider migration stops if the deposits table is absent', async () => {
  const client = createFakeClient({ tableExists: false });

  await assert.rejects(
    applyDepotProviderSchema(client),
    /table public\.depots est absente/,
  );
  assert.equal(client.calls.length, 1);
});

test('deposit provider migration reports columns that remain missing', async () => {
  const client = createFakeClient({
    columns: DEPOT_PROVIDER_COLUMNS.filter(column => column !== 'provider_service_id'),
  });

  await assert.rejects(
    applyDepotProviderSchema(client),
    /provider_service_id/,
  );
});
