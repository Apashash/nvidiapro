---
name: Persistent external sessions
description: Session persistence requirements for the Express app when deployed outside Replit.
---

External deployments must use a PostgreSQL-backed Express session store rather than the default in-memory store.

**Why:** Plesk can restart the Node process or route requests across workers; the in-memory store then loses `user_id` between login and the next protected action, sending the user back to `/connexion`.

**How to apply:** Keep the session table in the PostgreSQL setup schema, use the native `pg` pool with the session store, keep `SESSION_SECRET` stable across restarts, and enable secure cookies only when the external deployment is served over HTTPS.