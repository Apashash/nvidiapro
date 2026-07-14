---
name: AshtechPay collect API integration
description: Field names, currency format, and OTP flow quirks for the /v1/collect Mobile Money API (routes/depot.js)
---

Confirmed against the live API (docs: https://ashtechpay.top/docs/api) with a real ASHTECHPAY_API_KEY:

- The webhook field in the `/v1/collect` request body is `notify_url`, not `callback_url`. Sending the wrong key silently means AshtechPay never calls the webhook back — no error, just missing confirmations. Always verify request field names against the docs page directly, the API doesn't reject unknown fields.
- `currency` must be a plain code (`XAF`, `XOF`, `GNF`, `CDF`). The docs page's per-country badges (`XOFC`, `XAFG`, etc.) are cosmetic UI labels only — sending them to `/v1/collect` would send an invalid currency.
- Some operators require an OTP: initial `/v1/collect` returns `400 {error: "otp_required", message, ussd_code}`. Resubmit the exact same payload plus an `otp` field. `ussd_code` is non-null only for Orange Money Burkina Faso (USSD-composed OTP); it's null for Orange Money elsewhere (OTP arrives by SMS).
- Wave operator responses from this merchant account do NOT include the docs' `flow`/`wave_url` fields — they come back as a plain `202 pending` like a USSD push. Don't assume `flow` is always present; treat its absence as a normal push flow rather than an error.
- **Why:** the country/currency list in `routes/depot.js` was hardcoded with invented per-country currency suffixes and the wrong webhook field name before this was caught — payments would have silently failed to reconcile via webhook.
- **How to apply:** if this API ever needs another endpoint or field added, re-fetch `https://ashtechpay.top/docs/api` rather than trusting older assumptions — the account's live `/v1/countries` operator list can also drift from the docs examples.
