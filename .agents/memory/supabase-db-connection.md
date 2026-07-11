---
    name: Supabase DB connection
    description: This app connects to Supabase Postgres, not the built-in Replit DB, via a SUPABASE_DATABASE_URL secret.
    ---

    The app's real database is Supabase, not the Replit-provisioned Postgres. Connection precedence: `SUPABASE_DATABASE_URL` (secret) takes priority over the runtime-managed `DATABASE_URL` in both `config/db.js` and `scripts/setup-db.js`.

    **Why:** the project was originally scaffolded against the local Replit DB, but production data actually lives in Supabase; schema/migration scripts must target Supabase or changes silently apply to the wrong database and errors like "relation does not exist" appear in production while looking fine in dev.

    **How to apply:** when adding new tables/migrations, always run `npm run setup-db` after confirming `SUPABASE_DATABASE_URL` is set — this script now uses the same precedence logic as `config/db.js`. If you ever add a new script that opens its own `pg` `Pool`, use the same `SUPABASE_DATABASE_URL || DATABASE_URL` fallback (with SSL `rejectUnauthorized: false` when Supabase is used) instead of hardcoding `DATABASE_URL`.

    **Fresh import/re-clone gotcha:** secrets do not carry over on a fresh import — `SUPABASE_DATABASE_URL` will be missing and the app silently falls back to the empty Replit-provisioned DB (still runs, just with no real data). Check `viewEnvVars({ type: "secret" })` for it and ask the user to re-provide the connection string via `requestSecrets` before assuming the DB is empty.
    