# Completed tasks

## Etap A

- Audyt pierwotnego importera.
- Modularny bootstrap wtyczki.
- Schemat v1 i migracja starszych profili.
- Capability, REST CRUD profili i realny dashboard.
- React Admin z nawigacją, stanami i podglądem XML/CSV.
- Dokumentacja architektury, REST i etapów.
- Test integracyjny: 23 kontrole zaliczone w poprzedniej sesji.

## Bieżąca dyrektywa

- Ponowny audyt gałęzi, zmian, dokumentacji, schematu, REST i React wykonany.
- Utworzono system checkpointów `docs/agent-progress/`.

## Etap B1

- Migracja schematu v2 zgodna z v1.
- Model SupplierSource, feed run, CanonicalProduct, SupplierOffer i historia zmian.
- Szyfrowanie sekretów oraz repozytorium ciphertext.
- Polityka bezpiecznych nagłówków.
- Walidacja SSRF i klient HTTP z ręcznie walidowanymi przekierowaniami/limitami.
- 16 testów bezpieczeństwa oraz 23 testy regresji zaliczone.

## Etap B2 — backend

- Strumieniowe XML, CSV, TSV i JSON z limitami rekordów.
- Mapowanie zagnieżdżonych ścieżek, list i atrybutów XML.
- Normalizacja, walidacja wymaganych pól i GTIN.
- Feed runs, checksumy, historia zmian, produkty katalogowe i oddzielne oferty.
- Idempotentny ponowny ingest.
- Brama kompletności blokująca zmiany masowe dla feedów pustych, częściowych i z nagłym spadkiem liczby rekordów.
- Testy parserów 14/14 i katalogu 15/15.

## Etap B3

- REST profilu źródła, oddzielnych credentials i bezpiecznej inspekcji.
- Stronicowany katalog REST z wyszukiwaniem, ceną, dostępnością i sortowaniem.
- Kreator źródła React i rzeczywisty widok katalogu.
- REST integration 10/10, frontend lint/build pass.

## Etap C — backend

- Trwałe kategorie dostawców i mapowania per supplier.
- Punktowany matcher nazwy/ścieżki/hierarchii z uzasadnieniem.
- Jawne mapowanie, skip i potwierdzone tworzenie kategorii WooCommerce.
- Pojedyncze i masowe selekcje serwerowe, trwałe po re-ingest.
- Integration 8/8.

## Etap D — rdzeń importu

- Reguły cenowe per dostawca/kategoria/produkt z priorytetem i hashem konfiguracji.
- Matcher oparty o trwałe powiązanie, SKU i EAN; nazwa nie jest używana do automatycznego scalenia.
- Dry-run zapisany w `jobs`/`job_items`, z migawką katalogu, cen, kategorii i produktu WooCommerce.
- Jawne zatwierdzenie importu oraz kontrola nieaktualnego planu i ręcznych zmian.
- Import prostego produktu przez WooCommerce CRUD, domyślnie jako szkic, z ceną, stanem, kategorią, wymiarami, atrybutami i GTIN.
- Trwałe powiązanie i idempotentny ponowny import.
- Integration 11/11.
- Produkty wariantowe z trwałą tożsamością wariantu i cenami liczonymi z zatwierdzonej reguły.
- Obrazy tylko wybranych produktów: SSRF, HTTPS, limit 10 MB, walidacja MIME, deduplikacja checksumy i bezpieczny błąd częściowy.
- Action Scheduler: import partiami po 10, trwały status, wznawianie kolejnej partii i anulowanie oczekującej akcji.
- Ręczna synchronizacja wyłącznie zaznaczonych i powiązanych produktów, z konfigurowalnymi polami.
- Harmonogram synchronizacji per dostawca i blokada współbieżności.

## Etap E — stan checkpointu

- Retry pozycji do trzech prób z wykładniczym opóźnieniem w kolejce.
- Zredagowane logi audytowe oraz REST historii zadań/logów/diagnostyki.
- Test kolejki, anulowania i cyklicznego harmonogramu: 6/6.
- Kolejkowany ingest katalogu z pliku lub bezpiecznie pobranego URL; kod ma lint pass, test integracyjny pozostaje do wykonania.

## Etap F — rozpoczęty, nieukończony

- Usunięto placeholdery nawigacji i podłączono realne widoki React dla kategorii, selekcji, importu, synchronizacji, cen, historii i diagnostyki.
- Dodano wybór produktu bezpośrednio w katalogu.
- Naprawiono 9 błędów ESLint wykrytych podczas pierwszego przebiegu nowych widoków.
- ESLint, TypeScript i produkcyjny build Vite przechodzą.
