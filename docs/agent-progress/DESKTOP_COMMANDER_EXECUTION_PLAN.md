# Desktop Commander execution plan — 2026-10-08

Goal: verify and finish Pupilovo Supplier Hub stage F without requiring Codex model quota. Do not run a second coding agent in the worktree. Preserve all existing uncommitted changes and test data boundaries.

## Phase 1 — Baseline and regression
- Await current sequential suite of 10 PHP test scripts; record per-script exit code and failure message.
- Stage A GET test helper fixed; rerun 23/23 pass. Stage B catalog 15/15 pass; Stage B REST 10/10 pass. Frontend ESLint/TypeScript/Vite build pass.
- Run remaining tests and PHP syntax checks inside Docker WordPress container (host has no PHP CLI).
- Fix only demonstrated defects, one small change at a time, and rerun affected tests plus regression.

## Phase 2 — Import/synchronization
- Add isolated integration tests for CatalogImportManager: upload, URL, Action Scheduler, catalog persistence, temp cleanup, failure and retries. Never target a real supplier or production WooCommerce store.
- Verify stock and price sync touches only explicitly linked products and allowed fields.

## Phase 3 — UI/REST QA
- Exercise 10 admin sections, forms, validation, loading/error/empty states, confirmation dialogs, keyboard navigation, and REST permission boundaries.
- Record reproducible defects; avoid broad refactors. Use browser-capable tool for visual checks if available; otherwise label visual QA unverified.

## Phase 4 — Performance and security
- Benchmark synthetic 100, 2000, 10000 product feeds sequentially, record wall time and peak RAM. Stop if laptop memory pressure is excessive.
- Review auth/capabilities, nonce, SSRF, credential redaction, input validation, file upload, image limits, safe uninstall and retention.

## Phase 5 — Release candidate
- Complete admin/developer docs; package only runtime files and compiled assets, excluding tests/node_modules/secrets.
- Verify clean WordPress/WooCommerce installation in isolated Docker stack; activate plugin, run smoke tests, verify uninstall/retention.
- Only then call it release candidate; production readiness needs real supplier staging validation.

## Operational constraints
- Do not push, merge, reset, clean, delete store products or change production data.
- Keep reports in docs/agent-progress; update after each completed phase.
- Stop on approval-required or destructive operations and request permission.
