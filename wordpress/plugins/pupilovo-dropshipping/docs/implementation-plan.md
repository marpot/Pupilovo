# Plan implementacji

## Etap A — fundament

- [x] audyt istniejącego parsera i formularza;
- [x] modularny bootstrap oraz wersjonowany schemat danych;
- [x] REST dashboardu i profili hurtowni;
- [x] aplikacja React/TypeScript/SCSS z pełną nawigacją;
- [x] realny dashboard, stany ładowania/błędu/pusty;
- [x] zachowany bezpieczny podgląd lokalnych XML/CSV;
- [x] dokumentacja i test integracyjny fundamentu.

## Etap B — hurtownie i katalog

Bezpieczny klient URL, szyfrowany magazyn sekretów, rejestr adapterów, streaming parserów z mapowaniem ścieżek/atrybutów, walidacja rekordów, przebiegi feedu, trwały katalog, serwerowe filtry i paginacja. Dopiero tutaj pojawi się pełny kreator kroków 1–3.

## Etap C — kategorie i selekcja

Import drzewa kategorii dostawcy, normalizacja językowa, dopasowanie ścieżek/hierarchii, punktacja podobieństwa, jawne decyzje administratora oraz selekcje jednostkowe/kategorii/wyniku filtra bez ładowania całości do przeglądarki.

## Etap D — import WooCommerce

ProductMatcher (supplier+external ID, link, SKU, EAN), konflikty bez dopasowania po samej nazwie, reguły cenowe, dry-run, idempotentne zadania Action Scheduler, produkty jako szkice, kontrolowane zdjęcia, raport i ograniczony rollback zmian wykonanych przez konkretne zadanie.

## Etap E — synchronizacja

Harmonogramy per dostawca, blokady współbieżności, ceny/stany/dostępność per oferta, pełne vs częściowe feedy, progi kompletności, ręczne zatwierdzanie ryzykownych zmian i obsługa produktów wycofanych bez automatycznego kasowania.

## Etap F — produkcja

Testy dużych feedów i awarii, profilowanie zapytań, retencja logów, audyt SSRF/XXE/CSRF/capabilities, WCAG, dokumentacja operatora, migracje kompatybilności i pakowanie wydania.

## Bramy jakości

Każdy etap kończy się lintem i buildem panelu, składnią PHP, testami integracyjnymi w Dockerze oraz ręczną weryfikacją ekranów. Następny etap nie rozpoczyna się bez podsumowania zakresu i znanych ograniczeń.
