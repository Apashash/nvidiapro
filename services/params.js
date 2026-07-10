/**
 * Shared params cache — reads app_parametres from DB with a 5-second TTL.
 * All routes should use getParams() instead of querying directly.
 */
const db = require('../config/db');

let _cache = null;
let _cacheAt = 0;
const TTL = 5000; // 5 s

async function getParams() {
  const now = Date.now();
  if (_cache && (now - _cacheAt) < TTL) return _cache;
  const [rows] = await db.query('SELECT cle, valeur FROM app_parametres');
  const p = {};
  rows.forEach(r => { p[r.cle] = r.valeur; });
  _cache = p;
  _cacheAt = now;
  return p;
}

function invalidateCache() {
  _cache = null;
}

module.exports = { getParams, invalidateCache };
