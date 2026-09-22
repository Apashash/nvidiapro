---
name: MySoleas V4 payment authentication
description: Credentials and endpoint requirements for current MySoleas gateway payment flows.
---

MySoleas V4 Gateway server-to-server payment calls use `POST https://account.mysoleas.com/oauth/token` with JSON `grant_type: client_credentials`, `sp_client_id`, and `sp_client_secret`, then send the returned JWT as `x-sp-auth-token: Bearer <token>`. The gateway is `https://api.mysoleas.com`.

**Why:** The Identity Cloud `/oauth/v2/token` contract uses different fields; the Gateway quickstart uses `/oauth/token` and the `sp_` field names. The old `/api/action/auth` flow with `public_apikey` and `private_secretkey` belongs to V3.

**How to apply:** Configure a MySoleas Gateway OAuth application with `MYSOLEAS_CLIENT_ID` and `MYSOLEAS_CLIENT_SECRET`, obtain the token from `/oauth/token`, and use it for collection/disbursement intent, execute, and status endpoints. Keep merchant API keys only for public API-key endpoints such as phone verification.