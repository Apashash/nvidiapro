#!/usr/bin/env node
/**
 * Database setup script — creates all tables and seeds investment plans.
 * Run once after cloning: npm run setup-db
 * Safe to re-run: all statements are idempotent.
 */

const { Pool } = require('pg');

if (!process.env.DATABASE_URL) {
  console.error('Error: DATABASE_URL environment variable is not set.');
  process.exit(1);
}

const pool = new Pool({
  connectionString: process.env.DATABASE_URL,
  ssl: process.env.NODE_ENV === 'production' ? { rejectUnauthorized: false } : false,
});

const SCHEMA = `
CREATE TABLE IF NOT EXISTS utilisateurs (
  id SERIAL PRIMARY KEY,
  nom VARCHAR(255) NOT NULL,
  pays VARCHAR(100),
  telephone VARCHAR(50) UNIQUE NOT NULL,
  mot_de_passe VARCHAR(255) NOT NULL,
  parrain_id INTEGER REFERENCES utilisateurs(id),
  code_parrainage VARCHAR(50) UNIQUE,
  date_inscription TIMESTAMP DEFAULT NOW(),
  last_spin_time TIMESTAMP,
  is_admin BOOLEAN DEFAULT false,
  is_banned BOOLEAN DEFAULT false,
  retrait_bloque BOOLEAN DEFAULT false
);

CREATE TABLE IF NOT EXISTS soldes (
  id SERIAL PRIMARY KEY,
  user_id INTEGER UNIQUE REFERENCES utilisateurs(id),
  solde NUMERIC(15,2) DEFAULT 0,
  solde_precedent NUMERIC(15,2) DEFAULT 0
);

CREATE TABLE IF NOT EXISTS vip (
  id SERIAL PRIMARY KEY,
  user_id INTEGER UNIQUE REFERENCES utilisateurs(id),
  niveau INTEGER DEFAULT 0,
  pourcentage NUMERIC(5,2) DEFAULT 0,
  invitations_requises INTEGER DEFAULT 0,
  invitations_actuelles INTEGER DEFAULT 0
);

-- rendement_journalier is the daily yield rate as a percentage (e.g. 1.72).
-- The app computes gain_journalier = prix * rendement_journalier / 100 at runtime.
CREATE TABLE IF NOT EXISTS planinvestissement (
  id SERIAL PRIMARY KEY,
  nom VARCHAR(255) UNIQUE NOT NULL,
  prix NUMERIC(15,2),
  duree_jours INTEGER,
  rendement_journalier NUMERIC(8,4),
  image_url VARCHAR(500),
  description TEXT
);

CREATE TABLE IF NOT EXISTS commandes (
  id SERIAL PRIMARY KEY,
  user_id INTEGER REFERENCES utilisateurs(id),
  plan_id INTEGER REFERENCES planinvestissement(id),
  montant NUMERIC(15,2),
  gain_journalier NUMERIC(15,2),
  date_debut TIMESTAMP DEFAULT NOW(),
  date_fin TIMESTAMP,
  date_creation TIMESTAMP DEFAULT NOW(),
  statut VARCHAR(50) DEFAULT 'actif'
);

CREATE TABLE IF NOT EXISTS depots (
  id SERIAL PRIMARY KEY,
  user_id INTEGER REFERENCES utilisateurs(id),
  montant NUMERIC(15,2),
  methode VARCHAR(100),
  numero_transaction VARCHAR(255),
  pays VARCHAR(100),
  statut VARCHAR(50) DEFAULT 'en_attente',
  date_depot TIMESTAMP DEFAULT NOW(),
  date_validation TIMESTAMP
);

CREATE TABLE IF NOT EXISTS retraits (
  id SERIAL PRIMARY KEY,
  user_id INTEGER REFERENCES utilisateurs(id),
  montant NUMERIC(15,2),
  methode VARCHAR(100),
  numero_compte VARCHAR(255),
  statut VARCHAR(50) DEFAULT 'en_attente',
  date_demande TIMESTAMP DEFAULT NOW(),
  date_traitement TIMESTAMP
);

-- commande_id links a revenue entry to a specific investment order.
-- date_paiement records when the daily yield was credited.
CREATE TABLE IF NOT EXISTS historique_revenus (
  id SERIAL PRIMARY KEY,
  user_id INTEGER REFERENCES utilisateurs(id),
  commande_id INTEGER REFERENCES commandes(id),
  montant NUMERIC(15,2),
  type VARCHAR(100),
  date_paiement TIMESTAMP DEFAULT NOW(),
  date_creation TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS posts (
  id SERIAL PRIMARY KEY,
  user_id INTEGER REFERENCES utilisateurs(id),
  message TEXT,
  image VARCHAR(255),
  statut VARCHAR(50) DEFAULT 'en_attente',
  date_creation TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS codes_utilises (
  id SERIAL PRIMARY KEY,
  user_id INTEGER REFERENCES utilisateurs(id),
  code VARCHAR(100),
  montant NUMERIC(15,2),
  date_utilisation TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS filleuls (
  id SERIAL PRIMARY KEY,
  user_id INTEGER REFERENCES utilisateurs(id),
  filleul_id INTEGER REFERENCES utilisateurs(id),
  niveau INTEGER DEFAULT 1,
  date_inscription TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS connexions_journalieres (
  id SERIAL PRIMARY KEY,
  user_id INTEGER REFERENCES utilisateurs(id),
  date_connexion DATE DEFAULT CURRENT_DATE
);

CREATE TABLE IF NOT EXISTS pieces (
  id SERIAL PRIMARY KEY,
  user_id INTEGER UNIQUE REFERENCES utilisateurs(id),
  solde NUMERIC(15,2) DEFAULT 0,
  solde_precedent NUMERIC(15,2) DEFAULT 0
);

CREATE TABLE IF NOT EXISTS app_parametres (
  cle VARCHAR(100) PRIMARY KEY,
  valeur TEXT
);

CREATE TABLE IF NOT EXISTS vip_paliers (
  id SERIAL PRIMARY KEY,
  niveau INTEGER UNIQUE NOT NULL,
  label VARCHAR(255),
  filleuls_requis INTEGER DEFAULT 0,
  montant_cadeau NUMERIC(15,2) DEFAULT 0
);
`;

// rendement_journalier values computed from original absolute gains:
//   gain_journalier = prix * rendement_journalier / 100
// e.g. VIP 1: 1000 * 1.6 / 100 = 16 FCFA/day
const SEED_PLANS = `
INSERT INTO planinvestissement (nom, prix, duree_jours, rendement_journalier, image_url, description)
VALUES
  ('Action VIP 1',        1000,    125, 1.60,  NULL, 'Plan d''entrée — 1 000 FCFA'),
  ('Action VIP 2',        3000,    125, 1.667, NULL, 'Plan standard — 3 000 FCFA'),
  ('Action VIP 3',        5000,    125, 1.68,  NULL, 'Plan avancé — 5 000 FCFA'),
  ('Action VIP 4',        10000,   125, 1.70,  NULL, 'Plan pro — 10 000 FCFA'),
  ('Action VIP 5',        20000,   125, 1.70,  NULL, 'Plan premium — 20 000 FCFA'),
  ('Action VIP 6',        50000,   125, 1.72,  NULL, 'Plan élite — 50 000 FCFA'),
  ('Action VIP 7',        100000,  125, 1.72,  NULL, 'Plan or — 100 000 FCFA'),
  ('Action VIP 8',        200000,  125, 1.72,  NULL, 'Plan platine — 200 000 FCFA'),
  ('Action VIP 9',        500000,  125, 1.72,  NULL, 'Plan diamant — 500 000 FCFA'),
  ('Action VIP 10',       1000000, 125, 1.72,  NULL, 'Plan royal — 1 000 000 FCFA'),
  ('Action VIP 11',       2000000, 125, 1.72,  NULL, 'Plan légende — 2 000 000 FCFA')
ON CONFLICT (nom) DO NOTHING;
`;

async function setup() {
  const client = await pool.connect();
  try {
    console.log('Creating tables…');
    await client.query(SCHEMA);
    // Add columns that may be missing from older installs (idempotent)
    const alterations = [
      `ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS is_banned BOOLEAN DEFAULT false`,
      `ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS retrait_bloque BOOLEAN DEFAULT false`,
    ];
    for (const sql of alterations) await client.query(sql);
    console.log('✓ Schema ready (17 tables)');

    console.log('Seeding investment plans…');
    const res = await client.query(SEED_PLANS);
    console.log(`✓ Plans seeded (${res.rowCount} inserted)`);

    console.log('\nDatabase setup complete. Run `node server.js` to start the app.');
  } catch (err) {
    console.error('Setup failed:', err.message);
    process.exit(1);
  } finally {
    client.release();
    await pool.end();
  }
}

setup();
