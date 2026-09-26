---
name: AshTech crypto API documentation limits
description: Verified crypto catalogue and collect response contract, with QR encoding still undocumented.
---

The current official documentation describes `GET /v1/crypto/assets`, the `POST /v1/crypto/collect` request, and its successful response contract. The catalogue returns `{ assets: [...] }`; each active asset includes `asset_code`, `coin`, `name`, `network`, `network_label`, `memo_required`, `memo_type`, and `currency`.

The collect response includes a transaction ID and reference, status, payment method, asset/network, receiving address, optional memo/tag, amount and currency fields, and creation/expiry timestamps. Validate the returned asset/network against the selected live catalogue entry; display the returned address and memo separately. Do not invent a QR payload or network encoding: the QR format remains undocumented.

**Why:** Incorrect address, memo/tag, or QR handling can direct a customer to send funds incorrectly, and the skill explicitly prohibits inventing provider response fields.

**How to apply:** Use the documented response fields and live asset catalogue, confirm transaction status server-side before crediting, and omit QR output until AshTech publishes an exact compatible format.