---
name: External npm deployment
description: Replit-generated npm lockfiles may reference an internal package firewall that external hosts cannot resolve.
---

Before deploying this Node application outside Replit, verify that package.json and package-lock.json contain no `replit.internal` package URLs. External hosts such as Plesk must resolve packages through the public npm registry.

**Why:** Replit can write `package-firewall.replit.internal` into lockfile `resolved` URLs. Plesk then fails with `ENOTFOUND` before the application starts, even though the code and package versions are valid.

**How to apply:** remove unused dependencies that pull unnecessary transitive packages, regenerate the lockfile using `https://registry.npmjs.org`, search the lockfile for `replit.internal`, and validate with a clean `npm ci` before pushing.

For deployment archives, do not run `npm pack` against the Replit workspace root unless package contents are explicitly allowlisted. Its default rules can include hidden workspace state such as `.cache` and `.local`. Create archives from the app's required source directories instead, and keep runtime uploads separate.

**Why:** A workspace-root package can be much larger than the deployable app and may carry internal or user-generated files that do not belong on the external host.

**How to apply:** Use an explicit source allowlist, include only empty upload directories when needed, and inspect the finished archive's file list before distribution.