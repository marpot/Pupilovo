# Current state

## Checkpoint 2026-10-08 13:34 CEST — praca wstrzymana na polecenie użytkownika

- Gałąź: `feature/universal-supplier-import`.
- Etap A istnieje jako niezacommitowane zmiany i nie został nadpisany.
- Plugin 0.2.0 ma warstwy Domain/Application/Infrastructure/REST/Admin.
- Migracja v2 zachowuje 10 tabel v1 i dodaje źródła, sekrety, feed runs, produkty kanoniczne, oferty i historię zmian.
- REST v1: dashboard, system, CRUD supplier, lokalny preview XML/CSV.
- React Admin: dashboard, profile i kreator hurtowni, katalog, kategorie, selekcja, dry-run/import, synchronizacja, ceny, historia oraz diagnostyka są podłączone do rzeczywistych endpointów. Nowe widoki nie przeszły jeszcze ręcznego QA w przeglądarce.
- `SecretCipher`, `SecretRepository`, `SourceUrlGuard`, `HeaderPolicy` i `SourceHttpClient` tworzą fundament bezpiecznych źródeł.
- Testy: security 16/16, regresja Etapu A 23/23, PHP lint pass.
- Parsery strumieniowe XML/CSV/TSV/JSON, zagnieżdżone ścieżki/atrybuty i normalizacja: 14/14 testów.
- Wersjonowany ingest katalogu, SupplierOffer, historia, idempotencja i brama kompletności: 15/15 testów.
- Dodano stronicowane, filtrowane zapytanie katalogu oraz `GET /catalog`; składnia PHP pass, integracja REST jest następnym testem.
- REST źródła/credentials/inspect oraz katalog: 10/10 testów.
- React: kreator połączenie → test → mapowanie oraz realny filtrowany katalog; lint/TypeScript/build pass.
- Kategorie dostawcy, punktowany matcher z uzasadnieniem, jawne decyzje i tworzenie kategorii za potwierdzeniem.
- Selekcje pojedyncze i masowe po filtrze pozostają trwałe po re-ingest; Etap C integration 8/8.
- Etap D rozpoczęty: kalkulator netto/brutto, narzut/marża, koszty, VAT, waluta, rounding i stan decision_required; 8/8 testów.
- Etap D: repozytorium reguł cenowych, bezpieczny matcher SKU/EAN/powiązań, wersjonowany dry-run oraz jawnie zatwierdzany import przez WooCommerce CRUD.
- Plan importu blokuje konflikty z niepowiązanym produktem, wymusza ponowny dry-run po zmianie katalogu/reguł i chroni ręczne zmiany po podglądzie.
- Nowe produkty są szkicami; ponowienie korzysta z trwałego powiązania i nie tworzy duplikatu. Integracja D: 11/11.
- Produkty wariantowe, wariantowe ceny i idempotencja oraz bezpieczne pobieranie obrazów z SSRF/type/size/checksum: 6/6.
- Rozszerzona integracja import/sync: 12/12, w tym synchronizacja wyłącznie powiązanego produktu oraz tylko pól cena/stan.
- Etap E: wsadowy worker Action Scheduler, kolejka, anulowanie, retry/backoff, harmonogram per dostawca, blokada współbieżnej synchronizacji, audyt i diagnostyka. Test kolejki/harmonogramu: 6/6.
- Dodano kolejkowane pobieranie katalogu z uploadu lub URL (`CatalogImportManager`), ale ten najnowszy przepływ nie ma jeszcze dedykowanego testu integracyjnego.
- Ostatnia stabilizacja frontendu: początkowo ESLint zgłosił 9 błędów (szczegóły w `KNOWN_ISSUES.md`); wszystkie zostały poprawione. Aktualnie ESLint pass, TypeScript/Vite build pass.
- Wszystkie pliki PHP w `src/`: lint pass. `git diff --check`: pass.
- Nie wykonano merge, push ani operacji na danych produkcyjnych. Wszystkie zmiany pozostają niezacommitowane na `feature/universal-supplier-import`.
- Lokalne kontenery były uruchomione w poprzedniej sesji, ale dostęp Docker wymaga zatwierdzenia środowiska.

## Następna bezpieczna jednostka

Nie wznawiać nowych funkcji przed 2026-10-08 16:43 CEST. Po wznowieniu najpierw wykonać pełny zestaw regresji i test integracyjny `CatalogImportManager`, potem ręczny QA panelu. Nie przechodzić od razu do nowych funkcji.
