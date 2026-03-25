# Vercel and Neon Migration Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Adapt the existing Replit-hosted full-stack app so it runs on Vercel with Neon-backed PostgreSQL sessions and database access.

**Architecture:** Keep the current React + Vite frontend and Express API, but split deployment concerns for Vercel: static frontend output served from the Vite build and API routes handled by a Vercel serverless entrypoint. Remove Replit-specific defaults from the client and Vite config, and persist authentication sessions in PostgreSQL so login survives stateless serverless execution.

**Tech Stack:** React, Vite, Express, TypeScript, Drizzle ORM, Neon PostgreSQL, express-session, connect-pg-simple, Vercel serverless functions

---

### Task 1: Document the deployment-specific behavior

**Files:**
- Create: `docs/plans/2026-03-25-vercel-neon-migration.md`
- Modify: `README.md`

**Step 1: Write the failing test**

No code test for this task. Validation is a documentation diff review.

**Step 2: Run test to verify it fails**

No automated failure step for this task.

**Step 3: Write minimal implementation**

Document the required environment variables, Vercel build output, and Neon setup flow for database + sessions.

**Step 4: Run test to verify it passes**

Run: `rg -n "Vercel|Neon|SESSION_SECRET|DATABASE_URL" README.md`
Expected: matching deployment guidance is present.

**Step 5: Commit**

```bash
git add docs/plans/2026-03-25-vercel-neon-migration.md README.md
git commit -m "docs: add vercel and neon deployment guide"
```

### Task 2: Remove Replit-specific frontend/runtime defaults

**Files:**
- Modify: `vite.config.ts`
- Modify: `client/src/lib/apiBase.ts`

**Step 1: Write the failing test**

Create a lightweight test that asserts:
- the default API base resolves to same-origin `/api`
- the Vite base defaults to `/` rather than `./`

**Step 2: Run test to verify it fails**

Run: `node --import tsx --test tests/deployment/runtime-config.test.ts`
Expected: FAIL because the current client default points to `/replit/php-api` and the Vite base is `./`.

**Step 3: Write minimal implementation**

Update the client API base fallback to same-origin and make the Vite base configurable for root/subpath deployments without hardcoding Replit assumptions.

**Step 4: Run test to verify it passes**

Run: `node --import tsx --test tests/deployment/runtime-config.test.ts`
Expected: PASS

**Step 5: Commit**

```bash
git add client/src/lib/apiBase.ts vite.config.ts tests/deployment/runtime-config.test.ts
git commit -m "fix: remove replit runtime defaults"
```

### Task 3: Make session auth work in Vercel serverless execution

**Files:**
- Modify: `server/auth.ts`
- Modify: `server/app.ts`

**Step 1: Write the failing test**

Create a test that validates session middleware configuration selects a PostgreSQL-backed store when `DATABASE_URL` is present and production cookies stay proxy-safe.

**Step 2: Run test to verify it fails**

Run: `node --import tsx --test tests/deployment/session-config.test.ts`
Expected: FAIL because the current auth setup uses the default in-memory session store.

**Step 3: Write minimal implementation**

Extract or add session configuration that:
- uses `connect-pg-simple` with the existing `sessions` table
- trusts proxy in production/Vercel
- keeps secure cookies in production

**Step 4: Run test to verify it passes**

Run: `node --import tsx --test tests/deployment/session-config.test.ts`
Expected: PASS

**Step 5: Commit**

```bash
git add server/auth.ts server/app.ts tests/deployment/session-config.test.ts
git commit -m "fix: persist sessions for vercel deployment"
```

### Task 4: Add the Vercel deployment entrypoint and config

**Files:**
- Create: `api/index.ts`
- Create: `vercel.json`

**Step 1: Write the failing test**

Create a test that checks:
- the serverless entrypoint exports a handler
- the Vercel config rewrites `/api/*` to the function and routes other requests to the SPA

**Step 2: Run test to verify it fails**

Run: `node --import tsx --test tests/deployment/vercel-config.test.ts`
Expected: FAIL because the Vercel entrypoint/config do not exist yet.

**Step 3: Write minimal implementation**

Add a cached serverless Express handler and Vercel configuration for static output + API rewrites.

**Step 4: Run test to verify it passes**

Run: `node --import tsx --test tests/deployment/vercel-config.test.ts`
Expected: PASS

**Step 5: Commit**

```bash
git add api/index.ts vercel.json tests/deployment/vercel-config.test.ts
git commit -m "feat: add vercel deployment entrypoint"
```

### Task 5: Verify the integrated build

**Files:**
- Modify: `package.json`
- Modify: `README.md`

**Step 1: Write the failing test**

No extra code test. Integration verification is by typecheck and build.

**Step 2: Run test to verify it fails**

Run: `npm run check`
Expected: FAIL if any new types or imports are broken.

**Step 3: Write minimal implementation**

Add any missing script support or docs required to produce a clean local and Vercel build.

**Step 4: Run test to verify it passes**

Run: `npm run check`
Expected: PASS

Run: `npm run build`
Expected: PASS

**Step 5: Commit**

```bash
git add package.json README.md
git commit -m "chore: finalize vercel deployment support"
```
