---
name: AshtechPay collect API integration
description: Field names, currency format, and OTP flow quirks for the /v1/collect Mobile Money API (routes/depot.js)
---

Confirmed against the live API (docs: https://ashtechpay.top/docs/api) with a real ASHTECHPAY_API_KEY:

- The webhook field in the `/v1/collect` request body is `notify_url`, not `callback_url`. Sending the wrong key silently means AshtechPay never calls the webhook back — no error, just missing confirmations. Always verify request field names against the docs page directly, the API doesn't reject unknown fields.
- `currency` must be a plain code (`XAF`, `XOF`, `GNF`, `CDF`). The docs page's per-country badges (`XOFC`, `XAFG`, etc.) are cosmetic UI labels only — sending them to `/v1/collect` would send an invalid currency.
- Some operators require an OTP: initial `/v1/collect` returns `400 {error: "otp_required", message, ussd_code}`. **OTP retry must NOT include the `reference` field** — the docs example omits it, and sending the same reference causes AshtechPay to treat it as a new duplicate transaction → 500 "Une erreur interne s'est produite". Use `const { reference: _omit, ...retryPayload } = payload` before resending.
- OTP USSD flows: Orange CI uses `#144*82#`, SN uses `#144*391#`, BF uses `*144*4*6*montant#` (amount embedded). `ussd_code` is non-null for USSD OTP; null for OTP SMS (LigdiCash BF wallet — SMS automatique).
- **Webhook status**: AshtechPay webhook sends `status: "completed"` (NOT `"success"`) and `event: "payment.completed"`. Polling `/v1/transaction/:id` may return either. Always accept both `"completed"` and `"success"` as success. The callback handler should respond 200 FIRST before processing (per docs recommendation).
- Wave operator responses from this merchant account do NOT include the docs' `flow`/`wave_url` fields — they come back as a plain `202 pending` like a USSD push. Don't assume `flow` is always present; treat its absence as a normal push flow rather than an error.
- **Why:** OTP retry with `reference` caused AshtechPay 500 for Burkina Faso. Webhook `status: "completed"` mismatch silently rejected all webhook-confirmed deposits.
- **How to apply:** if this API ever needs another endpoint or field added, re-fetch `https://ashtechpay.top/docs/api` (it's a React SPA — fetch the JS bundle and search for the relevant section). Accept multiple status strings defensively for all status checks.
