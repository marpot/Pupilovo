# Pupilovo Supplier Hub

Wtyczka WordPress/WooCommerce do integracji wielu hurtowni dropshippingowych. Wersja 0.3.0. Projekt w fazie testów przedwdrożeniowych.

## Zaimplementowane

- Profile dostawców, źródła danych i zabezpieczone dane uwierzytelniające.
- Odczyt XML, CSV, TSV, JSON, mapowanie pól i walidacja źródeł URL.
- Katalog ofert dostawców, historia zmian i zabezpieczenie przed niepełnymi feedami.
- Dopasowywanie kategorii z ręcznym zatwierdzaniem.
- Wybór konkretnych produktów do importu.
- Podgląd i zatwierdzanie importu, produkty proste i wariantowe jako szkice WooCommerce.
- Reguły cenowe, aktualizacja bez duplikatów i ograniczenia nadpisywania.
- Action Scheduler, harmonogram synchronizacji, historia i błędy.
- Panel React, TypeScript, Vite i SCSS.
- Wewnętrzny, idempotentny podział pozycji zamówienia według dostawcy z niezmiennym snapshotem i grupą ręcznych decyzji.
- Statusy i historia realizacji dostawcy oraz administracyjne API tylko do odczytu. Wysyłka do dostawców nie jest jeszcze zaimplementowana.

## Uruchomienie

Z katalogu głównego repozytorium: docker compose up -d mysql wordpress.
Aktywuj Pupilovo Supplier Hub w panelu WordPress.

## Testy

Zbuduj panel poleceniami npm run build i npm run lint w katalogu admin-app.
Testy PHP znajdują się w tests/. Każdy można uruchomić w kontenerze wordpress poleceniem php /var/www/html/wp-content/plugins/pupilovo-dropshipping/tests/NAZWA.php.

8 października 2026: wszystkie 10 zestawów testowych przeszło poprawnie, łącznie 118 kontroli PASS, na lokalnym MySQL na SSD.

## Pozostało przed produkcją

- Rzeczywisty test UX panelu w przeglądarce.
- Import przykładowego feedu prawdziwej hurtowni i ocena mapowania kategorii.
- Testy błędów sieci, ponawiania zadań i harmonogramu.
- Przegląd bezpieczeństwa, migracji schematu, obrazów i instalacji na czystym WordPressie.
- Przygotowanie paczki instalacyjnej ZIP.

Dokumentacja techniczna: docs/architecture.md, docs/rest-api.md, docs/fulfillment.md i docs/implementation-plan.md.
