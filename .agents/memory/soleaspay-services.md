---
name: SoleasPay service catalogue
description: External SoleasPay services-list response shape and service selection constraint.
---

SoleasPay’s services-list endpoint returns a successful object with a `data` array. Active Mobile Money entries are country-specific and identify the service by numeric `id` and names such as MOMO CM, OM CI, MOOV TG, or WAVE CI. The response does not consistently include the previously documented `type` field.

**Why:** Filtering only on `type === TRUSTEECURRENCY` makes a valid merchant catalogue appear empty and silently replaces it with the generic fallback services.

**How to apply:** Accept `data[]`, filter active Mobile Money entries from the service name/description when type is absent, and persist the selected numeric service ID per country/operator. Map known operator labels automatically by country (Orange→OM, MTN→MOMO, Moov/Flooz→MOOV, Wave→WAVE, T-Money→T-MONEY, Airtel→AIRTEL); show manual selection only when no exact active service exists.