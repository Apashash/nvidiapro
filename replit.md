# Groupe Dangote (GD) — Plateforme d'Investissement

## Overview
French-language mobile investment platform for West African users (Cameroun, Côte d'Ivoire, Sénégal, etc.).

**Stack:** Node.js + Express.js + EJS templates + PostgreSQL (Replit built-in, via `DATABASE_URL`) + express-session

**Port:** 5000

**Run:** `node server.js`

## Running on Replit

1. Dependencies install automatically on first run via `npm install`.
2. On first setup, initialise the database: `npm run setup-db`
   - Creates all 18 tables and seeds the 11 investment plans (idempotent — safe to re-run).
3. Start the app: `node server.js` (or use the **Start App** workflow).

The workflow is configured in `.replit` and waits for port 5000 before marking the app as ready.

## Architecture
- `server.js` — Express app entry point, session setup, route mounts
- `config/db.js` — pg Pool wrapper; reads `SUPABASE_DATABASE_URL` (preferred) or `DATABASE_URL`; converts `?` placeholders to `$N` for PostgreSQL
- `middleware/auth.js` — `requireAuth` and `requireAdmin` session middleware
- `routes/` — one file per feature (auth, dashboard, investissement, depot, retrait, compte, equipe, roue, salaire, cadeau, faq, admin)
- `views/` — EJS templates
- `views/partials/menu.ejs` — shared bottom navigation bar
- `uploads/` — user-uploaded post images (multer)

## Key Features
- Mobile Money deposits and USDT collections via AshTechPay Direct API
- Mobile Money and USDT payouts via AshTechPay Direct API
- Investment plan (Action VIP 1) with daily yield over 125 days
- 3-level MLM referral system (20% / 10% / 5% commissions)
- VIP tiers with daily salary
- Lucky wheel (roue) — spin every 48h
- Gift codes (cadeau)
- Admin panel at `/adminxyz`

## Database
Replit built-in PostgreSQL is used by default (auto-provisioned `DATABASE_URL`). If `SUPABASE_DATABASE_URL` is set as a secret it takes precedence.

`config/db.js` wraps `pg` with a mysql2-compatible interface: converts `?` → `$N` placeholders and returns `[rows, fields]` tuples.

**First-time setup:** run `npm run setup-db` to create all tables and seed investment plans.

After deploying the AshTechPay integration changes, run `npm run setup-db` once to add provider metadata and lookup indexes. Configure AshTechPay values through Replit Secrets; never put keys in browser code or chat. Set the callback URL in the AshTechPay Direct API dashboard to the exact public HTTPS value in `ASHTECH_NOTIFY_URL`.

Schema lives in `scripts/setup-db.js`. Tables: `utilisateurs`, `soldes`, `vip`, `planinvestissement`, `commandes`, `depots`, `retraits`, `portefeuilles`, `transaction_passwords`, `historique_revenus`, `posts`, `codes_utilises`, `filleuls`, `connexions_journalieres`, `pieces`, and more.

## Secrets / Environment Variables
| Variable | Required | Purpose |
|---|---|---|
| `SESSION_SECRET` | Yes | Express session signing |
| `DATABASE_URL` | Auto (Replit) | PostgreSQL connection (Replit built-in) |
| `SUPABASE_DATABASE_URL` | Optional | Override DB with Supabase instance |
| `ASHTECH_API_KEY` | Required for Direct API operations | AshTechPay Direct API key; configured on the server only |
| `ASHTECH_USER_ID` | Required for Direct API operations | Profile user ID associated with `ASHTECH_API_KEY` |
| `ASHTECH_WEBHOOK_SECRET` | Required for signed AshTechPay webhooks | Signing secret configured in the AshTechPay Direct API dashboard |
| `ASHTECH_NOTIFY_URL` | Required for new AshTechPay operations | Public HTTPS URL ending exactly in `/ashtechpay_callback` |
| `ASHTECH_API_BASE` | Optional | API base override; defaults to `https://www.ashtechpay.com` |
| `SOLEASPAY_API_KEY` | Optional | MySoleas merchant API key, used for public phone verification |
| `MYSOLEAS_CLIENT_ID` | Required for MySoleas deposits/withdrawals | Gateway OAuth2 application ID sent as `sp_client_id` |
| `MYSOLEAS_CLIENT_SECRET` | Required for MySoleas deposits/withdrawals | Gateway OAuth2 application secret sent as `sp_client_secret` |
| `MYSOLEAS_API_BASE` | Optional | MySoleas gateway base; defaults to `https://api.mysoleas.com` |
| `MYSOLEAS_AUTH_BASE` | Optional | MySoleas Identity base; defaults to `https://account.mysoleas.com` |
| `SOLEASPAY_API_BASE` | Legacy alias | Accepted as a gateway base override during migration |
| `SOLEASPAY_CLIENT_ID` | Legacy alias | Accepted as an alias for `MYSOLEAS_CLIENT_ID` |
| `SOLEASPAY_CLIENT_SECRET` | Legacy alias | Accepted as an alias for `MYSOLEAS_CLIENT_SECRET` |

## Security Notes
- Passwords stored plaintext in DB (matches original PHP — do not add bcrypt without a migration)
- Admin password hardcoded: `Apashash28`
- Transaction PIN: 4-digit numeric, stored in `users.transaction_password`
- Session secret via `SESSION_SECRET` env var

## User Preferences
- Keep EJS templates in `views/`, routes in `routes/`
- Match original PHP behaviour unless explicitly asked to change
- No bcrypt — plaintext password comparison throughout
