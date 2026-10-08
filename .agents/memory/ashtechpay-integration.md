---
name: AshTechPay Direct API integration
description: Current Direct API contract, account identity, OTP handling, payout and signed-webhook rules for this project.
---

Use the official AshTech Pay Direct API documentation as the contract. This project intentionally uses Direct API only; do not add Hosted Checkout without an explicit product request.

Direct calls use `ASHTECH_API_KEY` as a server-side Bearer key and the matching `ASHTECH_USER_ID`. Collection requests use documented plain currency codes, `country_code`, `operator`, `reference`, and `notify_url`; use the live collection country catalogue. Payouts use the separate live payout catalogue and their documented payout request fields. Status queries include the same profile `user_id`.

For `400 otp_required`, retain AshTech's returned OTP-session `reference` and optional `ussd_code` exactly. Retry with the same amount, currency, phone, operator, country and provider session reference plus the OTP. An invalid OTP may retry that same session; an expired OTP starts a new collection with a new reference. Do not replace provider references or create a second request after an uncertain response. Display a returned Wave `wave_url` exactly; never synthesize one.

Use the signed webhook contract exactly: `X-Ashtech-Event-Id`, Unix-second `X-Ashtech-Timestamp`, and `X-Ashtech-Signature: sha256=...`; HMAC-SHA256 covers `timestamp + "." + raw_request_body`. Keep `ASHTECH_WEBHOOK_SECRET` server-side, reject stale/invalid signatures, deduplicate events, and verify the current transaction status through Direct API before changing balances. `ASHTECH_NOTIFY_URL` must be the configured public HTTPS callback.

**Why:** Incorrect session references or webhook verification can misattribute a payment or credit a balance twice; the user explicitly scoped this project to Direct API only.

**How to apply:** Recheck the official docs and current live country catalogue before changing an AshTech request, status, signature, or payout contract. Keep credentials out of browser code and use the exact provider response fields.
