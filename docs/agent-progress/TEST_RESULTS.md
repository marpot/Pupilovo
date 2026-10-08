# Test results

## Baseline z poprzedniej sesji

- PHP syntax: pass.
- Supplier Hub ESLint + TypeScript + Vite build: pass.
- Storefront ESLint + TypeScript + Vite build: pass.
- Docker integration Stage A: 23/23 pass.
- WooCommerce, Action Scheduler, XMLReader: available.

## B1

- PHP lint wszystkich klas `src`: pass.
- Security unit: 16/16 pass (cipher round-trip/tamper, header injection, SSRF/private IP/scheme/credentials/mixed DNS).
- Stage A regression after schema v2: 23/23 pass.

## B2

- Parser unit: 14/14 pass (XML attributes/nesting/repetition, CSV, TSV, nested JSON, inspection, mapping, GTIN, malformed input).
- Catalog integration: 15/15 pass (schema v2, first ingest, offers, idempotency, history, partial/suspicious/complete feed semantics).
- Wykryty i naprawiony błąd przesunięcia formatu checksumy SQL; test regresyjny pozostaje w zestawie.

## B3

- REST source/catalog integration: 10/10 pass.
- React Admin ESLint, TypeScript and Vite production build: pass.

## C

- Category/selection integration: 8/8 pass.
- Wykryty i naprawiony niejednoznaczny alias `supplier_id` w masowym odznaczaniu.

## D — pricing foundation

- Pricing unit: 8/8 pass (markup vs margin, costs, payment fee, net/gross VAT, FX requirement, invalid data, rounding).
- Import/sync integration: 12/12 pass (dry-run bez zmian Woo, jawne potwierdzenie, konflikt ręcznego SKU, szkic, cena/stan, powtórny import bez duplikatu, ręczny sync pól cena/stan).
- Variable/image integration: 6/6 pass (warianty, ceny, idempotencja, blokada niebezpiecznego URL obrazu).
- Action Scheduler queue/schedule: 6/6 pass (API, queue, cancel, worker, recurring schedule, disable schedule).
- PHP lint nowych klas importu i `git diff --check`: pass.

## Checkpoint zatrzymania 2026-10-08

- Pierwszy ESLint nowych widoków: **9 błędów** — 1 błąd składni JSX i 8 naruszeń `react-hooks/set-state-in-effect`; naprawione.
- Ponowny `npm run lint`: pass (proces emituje niekrytyczne komunikaty środowiska `Failed to create stream fd: Operation not permitted`).
- `npm run build`: pass; TypeScript oba configi pass; Vite 21 modułów, `admin.css` 10.83 kB (3.08 kB gzip), `admin.js` 715.92 kB (208.33 kB gzip).
- PHP lint wszystkich plików w `src/`: pass.
- `git diff --check`: pass.
- Najnowsze testy integracyjne przed zatrzymaniem: import/sync 12/12, warianty/obrazy 6/6, kolejka/harmonogram 6/6.
- Pełny zestaw regresji nie został ponowiony po dodaniu `CatalogImportManager`, `DiagnosticsRestController` i nowych widoków React — wymagany jako pierwszy krok po wznowieniu.
