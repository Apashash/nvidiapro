# NVIDIA Technologie — Plateforme d'Investissement

## Overview
French-language mobile investment platform for West African users (Cameroun, Côte d'Ivoire, Sénégal, etc.).

**Stack:** Node.js + Express.js + EJS templates + PostgreSQL (Replit built-in, via mysql2-compatible wrapper) + express-session

**Port:** 3000

**Run:** `node server.js`

## Architecture
- `server.js` — Express app entry point, session setup, route mounts
- `config/db.js` — mysql2 connection pool (external MySQL at sql104.iceiy.com)
- `middleware/auth.js` — `requireAuth` and `requireAdmin` session middleware
- `routes/` — one file per feature (auth, dashboard, investissement, depot, retrait, compte, equipe, portefeuille, roue, salaire, cadeau, faq, admin)
- `views/` — EJS templates
- `views/partials/menu.ejs` — shared bottom navigation bar
- `public/uploads/` — user-uploaded post images (multer)

## Key Features
- Mobile Money deposits via SoleasPay API
- Investment plans (Action VIP 1–11) with daily yield over 125 days
- 3-level MLM referral system (20% / 10% / 5% commissions)
- VIP tiers with daily salary
- Lucky wheel (roue) — spin every 48h
- Gift codes (cadeau)
- Admin panel at `/adminxyz`

## Database
Replit built-in PostgreSQL (via `DATABASE_URL` env var, auto-provisioned).
`config/db.js` is a mysql2-compatible wrapper around `pg` (converts `?` → `$N` placeholders, returns `[rows, fields]` tuples, provides `getConnection()` with transaction support).

**First-time setup:** after cloning/importing, run `npm run setup-db` to create all 15 tables and seed the 11 investment plans. The script is idempotent (`IF NOT EXISTS` / `ON CONFLICT DO NOTHING`) and safe to re-run.

Schema lives in `scripts/setup-db.js`. Tables: `utilisateurs`, `soldes`, `vip`, `planinvestissement`, `commandes`, `depots`, `retraits`, `portefeuilles`, `transaction_passwords`, `historique_revenus`, `posts`, `codes_utilises`, `filleuls`, `connexions_journalieres`, `pieces`.

## Security Notes
- Passwords stored plaintext in DB (matches original PHP — do not add bcrypt without a migration)
- Admin password hardcoded: `Apashash28`
- Transaction PIN: 4-digit numeric, stored in `users.transaction_password`
- Session secret via `SESSION_SECRET` env var

## User Preferences
- Keep EJS templates in `views/`, routes in `routes/`
- Match original PHP behaviour unless explicitly asked to change
- No bcrypt — plaintext password comparison throughout
