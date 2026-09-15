# Adresy klienta

- `GET /wp-json/pupilovo/v1/account/addresses` zwraca `{ billing, shipping }`.
- `POST /wp-json/pupilovo/v1/account/addresses/billing` i `/shipping` aktualizują wyłącznie wskazany adres. JSON zawiera pola bez prefiksu, np. `first_name`, `address_1`, `country`.
- Obie operacje wymagają sesji WordPress i `X-WP-Nonce` pobranego przez istniejące `/auth/me`. ID pochodzi wyłącznie z `get_current_user_id()`. Dodatkowe pola i parametry zapisu (w tym `customer_id`) są odrzucane. Parametry odczytu nie wybierają klienta.
- Dane są odczytywane i zapisywane przez `WC_Customer`. Brakujące pola częściowego zapisu zachowują obecną wartość; cały wynik podlega walidacji przed zapisaniem. Obsługiwane są wymagane pola WooCommerce, kraj/region, kod pocztowy, telefon i e-mail oraz sanitizacja tekstu. Odpowiedzi mają `Cache-Control: private, no-store`.
- Natywny model WooCommerce ma `shipping_phone`, ale nie ma `shipping_email`. E-mail rozliczeniowy nie zmienia e-maila logowania.

Interfejs konta pozwala osobno edytować, anulować i zapisywać oba adresy. Obsługuje ładowanie, błędy, ponowienie odczytu i potwierdzenie zapisu. Kraj i region wpisuje się jako kody (np. `PL`, a dla USA `US` i `CA`).

Checkout pobiera zapisane adresy tylko dla zalogowanego klienta. Pozwala na osobny adres dostawy, zachowuje firmę, drugi wiersz adresu, kraj, region i telefon. Jeśli brak adresu dostawy, używa danych rozliczeniowych. Gość wypełnia formularz jak wcześniej. Nie ma kopii adresów w localStorage ani sessionStorage. Zmiana adresu wymaga ponownego obliczenia dostawy; pola są zablokowane podczas obliczania i składania zamówienia. Błąd odczytu adresów pozwala wpisać je ręcznie.

## Testy

```bash
docker compose exec -T wordpress php /var/www/html/wp-content/plugins/pupilovo-auth/tests/addresses-integration.php
npm --prefix frontend run lint
npm --prefix frontend run build
git diff --check
```

Test adresów używa istniejącego WordPress/WooCommerce, tymczasowego serwera loopback oraz własnych klientów usuwanych w `finally`. Sprawdza sesje i nonce, oba zapisy i trwałość, izolację klientów, próby podania ID, walidację oraz brak częściowego zapisu błędnych danych. Regresję checkoutu gościa, klienta i Google obejmuje istniejący `customer-cart-session-integration.php`.

Do ręcznej kontroli w przeglądarce: edycja/anulowanie/zapis i odświeżenie konta, oba różne adresy w checkout, przeliczenie dostawy po zmianie, zakup gościnny, widok mobilny i nawigacja klawiaturą.
