---
name: AshTech crypto API documentation limits
description: Verified crypto catalogue and collect response contract, with QR encoding still undocumented.
---

The current official documentation describes `GET /v1/crypto/assets`, the `POST /v1/crypto/collect` request, and its successful response contract. The catalogue returns `{ assets: [...] }`; each active asset includes `asset_code`, `coin`, `name`, `network`, `network_label`, `memo_required`, `memo_type`, and `currency`.

The collect response includes a transaction ID and reference, status, payment method, asset/network, receiving address, optional memo/tag, amount and currency fields, and creation/expiry timestamps. Validate the returned asset/network against the selected live catalogue entry; display the returned address and memo separately. Do not invent a QR payload or network encoding: the QR format remains undocumented.

**Why:** Incorrect address, memo/tag, or QR handling can direct a customer to send funds incorrectly, and the skill explicitly prohibits inventing provider response fields.

**How to apply:** Use the documented response fields and live asset catalogue, confirm transaction status server-side before crediting, and omit QR output until AshTech publishes an exact compatible format.

Manual USDT withdrawals are separate from the documented collection API. Calculate the payout from the configured FCFA/USDT rate after the withdrawal fee, then require an administrator to send the funds externally and confirm the request. Do not invent an automated payout endpoint. Current withdrawal network choices are drawn from active USDT catalogue assets without a required memo; that catalogue does not guarantee that the administrator's wallet can send on each network.

**Why:** No provider payout contract is documented, and the product owner selected manual USDT fulfillment at the configured rate.

**How to apply:** Keep the off-platform send and admin confirmation explicit; before broadening supported networks, confirm the admin wallet's capabilities or maintain a separate payout-network allowlist.