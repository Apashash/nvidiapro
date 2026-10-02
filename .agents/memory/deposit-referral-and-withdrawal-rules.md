---
name: Deposit referral and withdrawal rules
description: Referral commission and withdrawal eligibility business rules.
---

Referral commissions are credited only when a deposit reaches the validated state, using the configured level rates; buying an investment action must not create a second referral commission. Withdrawal eligibility requires at least one validated deposit and at least one purchased action; the action does not need to be active or unexpired.

**Why:** The product rule is based on confirmed deposits for referral earnings, and the user explicitly added a confirmed-deposit prerequisite for withdrawals.

**How to apply:** Keep deposit finalization idempotent so webhook and polling cannot credit the same deposit twice; only validated deposits qualify for withdrawals, which still also require a purchase record.