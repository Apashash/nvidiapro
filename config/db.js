const { Pool } = require('pg');

const pool = new Pool({
  connectionString: process.env.DATABASE_URL,
  ssl: process.env.NODE_ENV === 'production' ? { rejectUnauthorized: false } : false,
});

// Convert MySQL ? placeholders to PostgreSQL $1, $2, ...
function convertPlaceholders(sql) {
  let i = 0;
  return sql.replace(/\?/g, () => `$${++i}`);
}

// Auto-append RETURNING id to INSERT statements so result.insertId works
function prepareSql(sql) {
  let s = convertPlaceholders(sql);
  if (/^\s*INSERT\s+/i.test(s) && !/RETURNING/i.test(s)) {
    s = s.trimEnd().replace(/;?\s*$/, '') + ' RETURNING id';
  }
  return s;
}

// Build a mysql2-compatible result: [rows_or_header, fields]
function buildResult(pgResult, originalSql) {
  if (/^\s*INSERT\s+/i.test(originalSql)) {
    return [{ insertId: pgResult.rows[0]?.id ?? null, affectedRows: pgResult.rowCount }, []];
  }
  if (/^\s*(UPDATE|DELETE)\s+/i.test(originalSql)) {
    return [{ affectedRows: pgResult.rowCount }, []];
  }
  return [pgResult.rows, []];
}

const db = {
  async query(sql, params = []) {
    const pgSql = prepareSql(sql);
    const result = await pool.query(pgSql, params);
    return buildResult(result, sql);
  },

  async getConnection() {
    const client = await pool.connect();
    return {
      async query(sql, params = []) {
        const pgSql = prepareSql(sql);
        const result = await client.query(pgSql, params);
        return buildResult(result, sql);
      },
      async beginTransaction() { await client.query('BEGIN'); },
      async commit() { await client.query('COMMIT'); },
      async rollback() { await client.query('ROLLBACK'); },
      release() { client.release(); },
    };
  },
};

module.exports = db;
