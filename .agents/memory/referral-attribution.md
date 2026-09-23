---
name: Referral attribution
description: Reliable referral-code propagation during signup on mobile and external deployments.
---

Referral signup forms must submit the invitation code as a hidden field, with the session value used only as a fallback.

**Why:** A user can open the invitation link and submit later, after a mobile browser or external deployment has lost the session; relying only on session state silently creates users without a sponsor.

**How to apply:** Keep the code visible/locked for invited signups, submit the same value in the form, and resolve `req.body.code_parrain` before `req.session.parrain_code` during registration.