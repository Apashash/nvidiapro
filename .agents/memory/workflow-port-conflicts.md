---
name: Workflow port conflicts
description: Diagnose Start App failures caused by an already-running process on the same port.
---

When a Start App launch reports `EADDRINUSE` but the preview still responds, check the active process and endpoint before treating it as an application failure. Restart the managed workflow once after identifying the existing server; avoid repeated restarts that collide with it.

**Why:** A healthy previous server remained bound to port 5000 while a new workflow start failed.

**How to apply:** For future Start App `EADDRINUSE` errors, check the listener and endpoint first, then restart after clearing or replacing the stale server.