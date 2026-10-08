const DEPOT_PROVIDER_COLUMNS = Object.freeze([
  'fournisseur',
  'provider_transaction_id',
  'provider_service_id',
  'provider_metadata',
]);

const DEPOT_PROVIDER_SCHEMA_STATEMENTS = Object.freeze([
  `ALTER TABLE public.depots ADD COLUMN IF NOT EXISTS fournisseur VARCHAR(50)`,
  `ALTER TABLE public.depots ADD COLUMN IF NOT EXISTS provider_transaction_id VARCHAR(255)`,
  `ALTER TABLE public.depots ADD COLUMN IF NOT EXISTS provider_service_id INTEGER`,
  `ALTER TABLE public.depots ADD COLUMN IF NOT EXISTS provider_metadata JSONB NOT NULL DEFAULT '{}'::jsonb`,
  `CREATE INDEX IF NOT EXISTS depots_provider_transaction_id_idx ON public.depots(provider_transaction_id) WHERE provider_transaction_id IS NOT NULL`,
]);

async function applyDepotProviderSchema(client) {
  const tableResult = await client.query(
    `SELECT to_regclass('public.depots') AS table_name`,
  );
  if (!tableResult.rows?.[0]?.table_name) {
    throw new Error('La table public.depots est absente; exécutez d’abord le setup initial de la base.');
  }

  for (const statement of DEPOT_PROVIDER_SCHEMA_STATEMENTS) {
    await client.query(statement);
  }

  const result = await client.query(
    `SELECT column_name
       FROM information_schema.columns
      WHERE table_schema = 'public'
        AND table_name = 'depots'
        AND column_name = ANY($1::text[])`,
    [DEPOT_PROVIDER_COLUMNS],
  );
  const present = new Set((result.rows || []).map(row => row.column_name));
  const missing = DEPOT_PROVIDER_COLUMNS.filter(column => !present.has(column));
  if (missing.length) {
    throw new Error(`Migration incomplète; colonnes manquantes : ${missing.join(', ')}`);
  }

  return DEPOT_PROVIDER_COLUMNS;
}

module.exports = {
  DEPOT_PROVIDER_COLUMNS,
  DEPOT_PROVIDER_SCHEMA_STATEMENTS,
  applyDepotProviderSchema,
};
