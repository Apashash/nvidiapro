---
name: MySoleas V4 payment authentication
description: Credentials and endpoint requirements for current MySoleas gateway payment flows.
---

MySoleas V4 server-to-server payment calls use OAuth2 `client_credentials` at `https://account.mysoleas.com/oauth/v2/token`, then send the returned JWT as `x-sp-auth-token: Bearer <token>`. The gateway is `https://api.mysoleas.com`.

**Why:** The old `/api/action/auth` flow with `public_apikey` and `private_secretkey` belongs to the V3 documentation and returns `Bad credentials` for the current account flow.

**How to apply:** Configure a MySoleas OAuth application with `MYSOLEAS_CLIENT_ID` and `MYSOLEAS_CLIENT_SECRET`, request scopes `payments services countries providers`, and use the token for collection/disbursement intent, execute, and status endpoints. Keep merchant API keys only for public API-key endpoints such as phone verification.