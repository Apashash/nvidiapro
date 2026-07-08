#!/usr/bin/env node
/**
 * Database setup script — creates all tables and seeds investment plans.
 * Run once after cloning: node scripts/setup-db.js
 */

require('dotenv').config();
const { Pool } = require('pg');

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
  transaction_password VARCHAR(10)
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

CREATE TABLE IF NOT EXISTS planinvestissement (
  id SERIAL PRIMARY KEY,
  nom VARCHAR(255),
  prix NUMERIC(15,2),
  duree_jours INTEGER,
  gain_journalier NUMERIC(15,2)
);

CREATE TABLE IF NOT EXISTS commandes (
  id SERIAL PRIMARY KEY,
  user_id INTEGER REFERENCES utilisateurs(id),
  plan_id INTEGER REFERENCES planinvestissement(id),
  montant NUMERIC(15,2),
  gain_journalier NUMERIC(15,2),
  date_debut TIMESTAMP DEFAULT NOW(),
  date_fin TIMESTAMP,
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

CREATE TABLE IF NOT EXISTS portefeuilles (
  id SERIAL PRIMARY KEY,
  user_id INTEGER REFERENCES utilisateurs(id),
  nom_portefeuille VARCHAR(255),
  pays VARCHAR(100),
  methode_paiement VARCHAR(100),
  numero_telephone VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS transaction_passwords (
  id SERIAL PRIMARY KEY,
  user_id INTEGER UNIQUE REFERENCES utilisateurs(id),
  password VARCHAR(10)
);

CREATE TABLE IF NOT EXISTS historique_revenus (
  id SERIAL PRIMARY KEY,
  user_id INTEGER REFERENCES utilisateurs(id),
  montant NUMERIC(15,2),
  type VARCHAR(100),
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
`;

const SEED_PLANS = `
INSERT INTO planinvestissement (nom, prix, duree_jours, gain_journalier)
VALUES
  ('Action VIP 1',  1000,    125, 16),
  ('Action VIP 2',  3000,    125, 50),
  ('Action VIP 3',  5000,    125, 84),
  ('Action VIP 4',  10000,   125, 170),
  ('Action VIP 5',  20000,   125, 340),
  ('Action VIP 6',  50000,   125, 860),
  ('Action VIP 7',  100000,  125, 1720),
  ('Action VIP 8',  200000,  125, 3440),
  ('Action VIP 9',  500000,  125, 8600),
  ('Action VIP 10', 1000000, 125, 17200),
  ('Action VIP 11', 2000000, 125, 34400)
ON CONFLICT DO NOTHING;
`;

async function setup() {
  const client = await pool.connect();
  try {
    console.log('Creating tables…');
    await client.query(SCHEMA);
    console.log('✓ Schema ready');

    console.log('Seeding investment plans…');
    const res = await client.query(SEED_PLANS);
    console.log(`✓ Plans seeded (${res.rowCount} inserted)`);

    console.log('\nDatabase setup complete.');
  } catch (err) {
    console.error('Setup failed:', err.message);
    process.exit(1);
  } finally {
    client.release();
    await pool.end();
  }
}

setup();
