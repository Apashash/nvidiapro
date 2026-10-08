---
name: AshTech Direct API crypto flows
description: Verified crypto collection and USDT payout contracts, asset catalogues, and memo/tag handling.
---

The current official documentation describes `GET /v1/crypto/assets`, `POST /v1/crypto/collect`, and `POST /v1/payouts/crypto`. Use the live catalogue; only active assets can be selected. A payout must use the exact `asset_code` and network. Include the destination memo/tag when required by that catalogue entry.

For pay-in, use the exact returned receiving address, optional memo/tag, amount/currency and expiry data; verify status server-side before crediting. Do not invent a QR payload or network encoding: the QR format remains undocumented. For payouts, send the documented `user_id`, stable `reference`, exact `asset_code`, destination address, required destination memo, amount, `fee_bearer`, and configured `notify_url`.

The app's existing withdrawal calculation and FCFA/USDT rate remain product rules; preserve them while passing the resulting payout amount to the provider. Pending or `pending_manual` payouts retain the debit and must be reconciled by status/reference rather than reissued under a new reference.

**Why:** A wrong chain or missing memo can irreversibly misdirect funds, and a duplicate payout can debit the user twice.

**How to apply:** Keep collection and payout flows separate; preserve the exact active asset code, memo, reference, amount, and provider status. Do not invent QR data or fall back to manual settlement without an explicit product decision.