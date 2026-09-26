---
name: AshTech crypto API documentation limits
description: Verified crypto catalogue shape and unresolved collect-response details.
---

The official crypto documentation describes `GET /v1/crypto/assets` and provides the `POST /v1/crypto/collect` request body. A live assets response includes `asset_code`, `coin`, `name`, `network`, `network_label`, `memo_required`, `memo_type`, and `currency`.

The collect page says a successful response provides a payment address and, depending on network, `memo` or `tag`, but does not show the successful response JSON or a QR payload/encoding. The transaction page documents status checks, not the crypto creation response shape. Do not infer the address key or QR format; obtain an official schema or a successful response sample with keys preserved and values redacted. Do not create a live payment just to discover the schema.

The docs' AI-skill page lists specialized names such as `ashtech-crypto`, but the documented `npx skills add https://doc.ashtechpay.com --all` currently installs only the generic `ashtech` skill, and the CLI reports `ashtech-crypto` as unavailable. Recheck the installer if the docs change.

**Why:** Incorrect address, memo/tag, or QR handling can direct a customer to send funds incorrectly, and the skill explicitly prohibits inventing provider response fields.

**How to apply:** Before implementing crypto collection details, confirm the exact successful response contract and network-compatible QR format from AshTech Pay. The asset selector can use the live catalogue independently.