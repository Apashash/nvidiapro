---
name: Deposit referral and withdrawal rules
description: Referral commission and withdrawal eligibility business rules.
---

Referral commissions are credited only when a deposit reaches the validated state, using the configured level rates; buying an investment action must not create a second referral commission. Withdrawal eligibility requires at least one purchased action, not necessarily an active or unexpired plan.

**Why:** The product rule is based on deposited funds for referral earnings, while requiring an action purchase before withdrawals.

**How to apply:** Keep deposit finalization idempotent so webhook and polling cannot credit the same deposit twice, and keep the withdrawal check aligned with the existence of a purchase record.