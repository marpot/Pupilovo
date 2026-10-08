# Architecture decisions

## ADR-001 — zachowanie warstw Etapu A

Domain nie zależy od WordPress transportu; Application orkiestruje przypadki użycia; Infrastructure integruje bazę/pliki/HTTP/WooCommerce; REST jest cienkim adapterem; Admin ładuje React.

## ADR-002 — ewolucja schematu zamiast wymiany

Tabele v1 pozostają kompatybilne. Model SupplierProduct/SupplierOffer będzie dodany lub rozdzielony migracją v2; istniejące `catalog_products` będą migrowalne bez utraty danych.

## ADR-003 — sekrety poza konfiguracją publiczną

REST profilu nie przyjmuje sekretów. Dedykowany magazyn szyfruje wartości kluczem aplikacyjnym wyprowadzonym z soli WordPress i kontekstu instalacji; ciphertext nigdy nie trafia do DTO/logów.

## ADR-004 — bezpieczeństwo przed pobraniem

Każdy URL i każde przekierowanie przechodzi walidację schematu, hosta i wszystkich rozstrzygniętych adresów IP. Prywatne/lokalne/rezerwowane zakresy są blokowane, chyba że administrator świadomie włączy jawnie oznaczony tryb HTTP; tryb ten nie znosi blokady prywatnych IP.

## ADR-005 — feed run jako granica kompletności

Każde pobranie tworzy wersjonowany przebieg z licznikami, checksumą i statusem kompletności. Oznaczanie ofert jako nieobecnych jest możliwe tylko po zatwierdzonym pełnym przebiegu.

## ADR-006 — dry-run jako niezmienna migawka

Plan zapisuje wersję/checksum produktu katalogowego oraz hash reguł cenowych. Zmiana katalogu, reguł lub ręczna zmiana produktu WooCommerce po podglądzie blokuje wykonanie i wymaga nowego dry-run.

## ADR-007 — długie operacje przez Action Scheduler

Import produktów, synchronizacja i pobieranie katalogu są zadaniami. Import przetwarza partie po 10 pozycji, utrzymuje heartbeat/status, retry do trzech prób i wykładnicze opóźnienie. Endpoint REST tylko zatwierdza i kolejkuje pracę.

## ADR-008 — pola zarządzane są jawne

Synchronizacja aktualizuje wyłącznie zaznaczone, powiązane produkty i wybrane pola. Brak produktu w feedzie nigdy nie usuwa produktu WooCommerce.
