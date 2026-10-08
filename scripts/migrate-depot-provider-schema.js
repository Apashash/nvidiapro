const { Pool } = require('pg');
const { applyDepotProviderSchema } = require('./depot-provider-schema');

const connectionString = process.env.SUPABASE_DATABASE_URL || process.env.DATABASE_URL;
const isSupabase = Boolean(process.env.SUPABASE_DATABASE_URL);

async function migrate() {
  if (!connectionString) {
    throw new Error('SUPABASE_DATABASE_URL ou DATABASE_URL doit être configuré sur le serveur.');
  }

  const pool = new Pool({
    connectionString,
    ssl: isSupabase || process.env.NODE_ENV === 'production'
      ? { rejectUnauthorized: false }
      : false,
  });

  let client;
  try {
    client = await pool.connect();
    const columns = await applyDepotProviderSchema(client);
    console.log(`Migration terminée et vérifiée : ${columns.join(', ')}.`);
  } finally {
    client?.release();
    await pool.end();
  }
}

if (require.main === module) {
  migrate().catch(error => {
    console.error('Échec de la migration du schéma des dépôts :', error.message);
    process.exitCode = 1;
  });
}

module.exports = { migrate };
