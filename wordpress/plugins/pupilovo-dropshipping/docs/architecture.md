# Architektura Pupilovo Supplier Hub

## Granice modułów

1. **Domain** — typy źródeł, kontrakty adapterów oraz w kolejnych etapach polityki cenowe, kompletność feedu i dopasowanie produktów.
2. **Application** — przypadki użycia niezależne od transportu, np. dashboard, test źródła, przygotowanie podglądu i uruchamianie zadania.
3. **Infrastructure** — schemat i repozytoria WordPress, parsery XML/CSV, bezpieczny klient HTTP, magazyn sekretów i Action Scheduler.
4. **REST** — walidacja wejścia, uprawnienia, kody odpowiedzi i DTO bez sekretów.
5. **Admin** — ładowanie aplikacji React i przekazywanie wyłącznie REST URL, nonce oraz wersji.
6. **admin-app** — dostępny interfejs React/TypeScript. Nie odczytuje całego katalogu i nie podejmuje decyzji biznesowych za backend.

Nietypowe API rejestrują adapter przez filtr `pupilovo_supplier_hub_api_adapters` i implementują `APIAdapterInterface`. JSON będzie można dodać jako kolejny parser bez zmiany modelu katalogu.

## Model danych

Prefiks tabel uwzględnia `$wpdb->prefix`; poniżej użyto skrótu `psh_`.

| Tabela | Odpowiedzialność | Kluczowe indeksy |
|---|---|---|
| `psh_suppliers` | profil, typ źródła, niesekretna konfiguracja, mapowanie i harmonogram | UUID, slug, status, source type |
| `psh_catalog_products` | znormalizowane oferty dostawców i kontrolowany raw payload | supplier+external ID, supplier+SKU, supplier+EAN, availability |
| `psh_supplier_categories` | drzewa kategorii poszczególnych feedów | supplier+external ID, parent |
| `psh_category_mappings` | zatwierdzone/sugerowane decyzje mapowania do `product_cat` | supplier+category, WC term, decision |
| `psh_product_selections` | trwały wybór konkretnych ofert | supplier+catalog product |
| `psh_product_links` | relacja wiele ofert dostawców → jeden produkt WooCommerce | supplier+external ID, WC product |
| `psh_pricing_rules` | priorytetowe reguły per dostawca/kategoria/produkt | supplier+scope, active+priority |
| `psh_jobs` | wznawialne zadania importu/synchronizacji i kursory | UUID, supplier+status, type+status |
| `psh_job_items` | idempotentne operacje jednostkowe, wynik i ograniczone snapshoty | job+product+action, job+status |
| `psh_logs` | zdarzenia operacyjne bez sekretów | supplier/date, job/date, severity/date |

Celowo nie ma kluczy obcych SQL: instalacje WordPress różnią się silnikiem i sposobem aktualizacji przez `dbDelta`. Spójność będzie egzekwowana przez repozytoria i zadania czyszczące. Wszystkie daty w bazie są zapisywane w UTC.

## Bezpieczeństwo

- capability `manage_pupilovo_supplier_hub` otrzymują role administratora i shop managera;
- cookie REST WordPress wymaga `X-WP-Nonce`; każdy endpoint dodatkowo sprawdza capability;
- ogólny endpoint profilu odrzuca pola sekretów i nigdy ich nie serializuje;
- endpoint usuwania wymaga `confirm=true`, stosuje soft delete i wyłącza synchronizację;
- podgląd XML zabrania dostępu sieciowego i encji zewnętrznych;
- URL będą pobierane wyłącznie przez klienta opartego na `wp_safe_remote_get`, z ponowną walidacją przekierowań, limitami rozmiaru/czasu i blokadą prywatnych zakresów;
- logi będą otrzymywać przefiltrowany kontekst, a nie request headers ani konfigurację uwierzytelnienia.

## Przepływ docelowy

Połączenie i próbka → jawne mapowanie pól → import feedu do tabel katalogu → ocena kompletności → sugestie kategorii → trwała selekcja → reguły ceny → dry-run → zatwierdzone zadanie Action Scheduler → szkice WooCommerce → późniejsze synchronizacje wyłącznie powiązanych ofert.

Brak produktu może wpłynąć na dostępność dopiero, gdy pełny feed przeszedł walidację kompletności względem poprzednich przebiegów i progów bezpieczeństwa.
