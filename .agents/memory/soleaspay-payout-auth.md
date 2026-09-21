---
name: SoleasPay payout authentication
description: Credentials and endpoint requirements for SoleasPay disbursements.
---

SoleasPay payout authentication requires the API ACCESS key and the generated secret key from the same account. The callback signing key is not a substitute.

**Why:** The public API key works with the service catalogue, but `/api/action/auth` rejects a mismatched or callback secret before any payout can be initiated.

**How to apply:** Use `SOLEASPAY_API_KEY` as `public_apikey` and `SOLEASPAY_PRIVATE_SECRET_KEY` as `private_secretkey`, then call `/api/action/account/withdraw` with Bearer headers, `operation=4`, and a withdrawable service ID.