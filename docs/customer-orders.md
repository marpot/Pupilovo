# Historia zamówień klienta

W „Moje konto → Zamówienia” klient widzi numer, datę, status, łączną kwotę z walutą oraz rozwijaną listę produktów i ilości. Lista ma 10 zamówień na stronę, od najnowszego ID. Obsługuje pusty wynik, ładowanie, ponowienie po błędzie oraz wygaśnięcie sesji.

## Endpoint

`GET /wp-json/pupilovo/v1/account/orders?page=1`

Wymaga cookie zalogowanego WordPressa i prawidłowego `X-WP-Nonce`. Frontend wykorzystuje istniejący bootstrap `/auth/me`. Serwer pobiera ID klienta wyłącznie z sesji; parametry `customer` i `customer_id` przesłane przez klienta nie wpływają na zapytanie. Nie ma wyszukiwania po e-mailu.

Zapytanie używa `wc_get_orders`, obsługującego warstwę danych WooCommerce. Endpoint zwraca wyłącznie zamówienia typu `shop_order` z publicznymi statusami WooCommerce; pomija robocze koszyki `checkout-draft` i osobne rekordy zwrotów. Kwota to całkowita kwota zamówienia, nie saldo po zwrotach. Odpowiedź nie zawiera adresów, danych płatności ani klucza dostępu do zamówienia i ma nagłówek `Cache-Control: private, no-store, max-age=0`.

Historia obejmuje zamówienia przypisane do ID konta. Zakupy gościnne z tym samym e-mailem nie są automatycznie udostępniane. Powiązanie Google zachowujące ID istniejącego klienta zachowuje też jego historię.

## Weryfikacja

```bash
docker compose exec wordpress php /var/www/html/wp-content/plugins/pupilovo-auth/tests/orders-integration.php
docker compose exec wordpress php /var/www/html/wp-content/plugins/pupilovo-auth/tests/google-token.php
docker compose exec wordpress php -l /var/www/html/wp-content/plugins/pupilovo-auth/customer-orders.php
docker compose exec frontend sh -lc 'cd frontend && npm run build'
git diff --check
```

Test integracyjny używa rzeczywistego WordPress/WooCommerce i tymczasowego serwera HTTP na loopback kontenera. Tworzy własnych klientów i zamówienia, wyłącza wysyłkę e-maili, a w `finally` usuwa tylko dane utworzone przez ten test. Sprawdza brak sesji, brak/błędny nonce, wylogowanie, izolację klientów, wykluczenie zamówień gościa i draftów, paginację, walidację strony, kwoty i pustą historię. Nie uruchamia płatności ani nie zmienia istniejących zamówień.

Implementacja korzysta z [oficjalnego API zapytań WooCommerce](https://developer.woocommerce.com/docs/features/orders/wc-get-orders/). Przed wdrożeniem na instalację z innym trybem przechowywania zamówień uruchom testy na docelowym trybie HPOS/legacy.
