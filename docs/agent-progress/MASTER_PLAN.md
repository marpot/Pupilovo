# Pupilovo Supplier Hub — master plan

## Cel

Ukończyć niezależną, instalowalną wtyczkę WooCommerce obejmującą konfigurację źródeł, katalog dostawców, mapowanie kategorii, selekcję, dry-run, import, synchronizację, reguły cenowe, diagnostykę i pakiet dystrybucyjny.

## Kolejność realizacji

1. **B1 — dane i bezpieczeństwo źródeł:** migracja v2, wersje feedów, kanoniczne produkty/oferty, szyfrowane sekrety, walidacja SSRF.
2. **B2 — parsery i ingest:** XML/CSV/TSV/JSON, ścieżki rekordów i pól, normalizacja, walidacja kompletności, wsadowy upsert katalogu.
3. **B3 — kreator i katalog:** REST test/sample/ingest, kreator React, filtrowany katalog z paginacją.
4. **C — kategorie i selekcja:** matcher z uzasadnieniem, decyzje mapowania, trwałe selekcje i propozycje powiązań wielu dostawców.
5. **D — ceny, dry-run i WooCommerce:** kalkulator reguł, niezmienny plan, CRUD produktów prostych/wariantowych, obrazy, idempotencja i retry.
6. **E — synchronizacja:** Action Scheduler, harmonogramy, blokady, backoff, kompletność feedów i ochrona pól ręcznych.
7. **F — panel, hardening i release:** wszystkie działające widoki, testy bezpieczeństwa/wydajności, dokumentacja, uninstall/retencja, ZIP i test instalacji.

## Bramy jakości

Po każdej jednostce: PHP lint, testy właściwe dla modułu, ESLint/TypeScript/build dla zmian UI, `git diff --check`, aktualizacja checkpointów. Po każdym etapie: pełny test integracyjny w Dockerze i dokumentacja REST.

## Zakazy

Bez merge, push, publikacji, danych produkcyjnych, automatycznego usuwania produktów/kategorii i bez oznaczania nieobecności na podstawie niepełnego feedu.
