const mysql = require('mysql2/promise');

const pool = mysql.createPool({
  host: process.env.DB_HOST || 'sql104.iceiy.com',
  user: process.env.DB_USER || 'icei_40255736',
  password: process.env.DB_PASS || 'Apashash28',
  database: process.env.DB_NAME || 'icei_40255736_2026',
  charset: 'utf8mb4',
  timezone: '+00:00',
  waitForConnections: true,
  connectionLimit: 10,
});

module.exports = pool;
