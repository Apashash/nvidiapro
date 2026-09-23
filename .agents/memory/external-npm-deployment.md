---
name: External npm deployment
description: Replit-generated npm lockfiles may reference an internal package firewall that external hosts cannot resolve.
---

Before deploying this Node application outside Replit, verify that package.json and package-lock.json contain no `replit.internal` package URLs. External hosts such as Plesk must resolve packages through the public npm registry.

**Why:** Replit can write `package-firewall.replit.internal` into lockfile `resolved` URLs. Plesk then fails with `ENOTFOUND` before the application starts, even though the code and package versions are valid.

**How to apply:** remove unused dependencies that pull unnecessary transitive packages, regenerate the lockfile using `https://registry.npmjs.org`, search the lockfile for `replit.internal`, and validate with a clean `npm ci` before pushing.