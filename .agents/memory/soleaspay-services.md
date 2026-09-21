---
name: MySoleas V4 service catalogue
description: Current MySoleas gateway service catalogue and provider selection rules.
---

The current MySoleas V4 gateway uses `GET https://api.mysoleas.com/service/list` with an OAuth Bearer JWT in `x-sp-auth-token`. Services are filtered by ISO alpha-3 country (`CMR`, not `CM`) and currency, then selected by their string `code` such as `mtn_cmr` or `orange_cmr`. Only `is_active`/`is_public` services with `is_can_collect` or `is_can_disburse` should be used.

**Why:** The former `/api/services-list` V3 contract and numeric service headers were replaced by the V4 gateway contract; sending a numeric ID to a V4 transaction is not sufficient.

**How to apply:** Keep the admin mapping compatible with stored numeric catalogue IDs, resolve each selected ID against the live V4 catalogue, and send the resolved string `service.code` in collection/disbursement intents. Normalize country codes before matching operators.