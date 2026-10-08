# Desktop Commander QA — 2026-10-08

## Environment
- Docker Compose: pupilovo-wordpress and pupilovo-mysql running.
- Codex left running; no source code modified.
- RAM snapshot: 7.1 GiB total, ~2.5 GiB available, ~795 MiB swap used.

## Checks started
- Full suite of 10 PHP scripts from `wordpress/plugins/pupilovo-dropshipping/tests/*.php`, sequentially in WordPress container.
- Admin app `npm run lint && npm run build`.

## Confirmed failure
- `stage-a-integration.php` failed with exit code 255 at line 86: `FAIL: supplier list is filtered and paginated`. Earlier checks for draft supplier creation and credential redaction passed. Test fixture cleanup ran. Investigate REST supplier list filtering/pagination and test fixture assumptions.

## Pending
- Wait for remaining PHP test scripts and frontend build to finish, capture their actual exit statuses.
- Check queued CatalogImportManager integration, browser UI, load tests and independent ZIP installation after regressions are addressed.
- Do not label production ready.

## Update — confirmed fix
- Root cause: Stage A test helper passed GET `search` as body params rather than query params.
- Fixed `tests/stage-a-integration.php` to call `set_query_params` for GET, retaining body params for other methods.
- Stage A rerun: **23/23 PASS**, exit 0; Stage B catalog **15/15 PASS**; Stage B REST **10/10 PASS**.
- Admin ESLint and TypeScript/Vite build PASS, exit 0. Output admin.js 687.11 kB / 205.29 kB gzip.
- Remaining suite running sequentially, Stage C underway at last check. `git diff --check` PASS.
- PHP CLI is not installed on Ubuntu host; use PHP inside WordPress Docker container for syntax/tests.

## Phase 1 continuation
- Sequential regression found Stage C failure: `single product selection persists` after mapping checks. Root cause appears to be assertion against global `SelectionRepository::count()` while other suppliers have selections in shared local database.
- Updated Stage C integration assertions to validate `changed` and selection counts scoped by unique test supplier, including bulk select and deselect. Stage C rerun launched; result not yet verified.
- Stage D and remaining regression scripts still executing; do not claim they passed until exit codes are observed.
