---
name: Mobile language stability
description: Browser stability constraint for the client-side language selector.
---

Client-side translation must run on initial page load and on an explicit language selection, not through a continuous whole-page mutation observer.

**Why:** On mobile browsers, observing and re-translating pages with timers, counters, or dynamic notifications can freeze the visible page after a language change even when desktop automation shows no JavaScript error.

**How to apply:** Keep language changes synchronous and bounded to the current DOM. If dynamic content needs translation, translate it at the point where that content is rendered rather than observing the entire document.