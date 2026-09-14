# Sesja klienta, koszyk i checkout (#16)

Logowanie, rejestracja i Google Sign-In przyjmują Cart-Token koszyka gościa. Po udanym uwierzytelnieniu plugin przenosi jego sesję do klienta i zwraca nowy token. Frontend zapisuje token, a przy wylogowaniu go usuwa. Koszyk i checkout pobierają aktualny nonce przez /auth/me i przekazują X-WP-Nonce razem z Cart-Token. Wymaga to dodatkowego odczytu /auth/me przed każdym żądaniem koszyka/checkoutu.

Tożsamość zamówienia pochodzi z uwierzytelnionego użytkownika WordPressa, nie z adresu e-mail rozliczeniowego. Cart-Token identyfikuje koszyk; samo jego wystawienie dla ID klienta nie jest równoznaczne z ustawieniem bieżącego użytkownika WordPressa.

## Testy

```bash
docker compose exec -T wordpress php wp-content/plugins/pupilovo-auth/tests/customer-cart-session-integration.php
docker compose exec -T wordpress php wp-content/plugins/pupilovo-auth/tests/auth-integration.php
docker compose exec -T wordpress php wp-content/plugins/pupilovo-auth/tests/orders-integration.php
docker compose exec -T wordpress php wp-content/plugins/pupilovo-auth/tests/google-token.php
docker compose exec -T frontend sh -lc 'cd frontend && npm run lint && npm run build'
git diff --check
```

Test koszyka obejmuje checkout po logowaniu klasycznym i istniejącym flow Google (podpisany testowy token, bez zewnętrznego popupu), unieważnienie sesji WordPressa, zachowanie zawartości i rotację tokenu po logowaniu, przypisanie zamówienia klientowi, zakup gościnny z e-mailem istniejącego klienta oraz wylogowanie i zmianę konta. Tworzy własne dane i usuwa je w finally, blokuje wysyłkę maili i korzysta z lokalnego checkoutu za pobraniem.

Serwer testowy musi ustawić `$GLOBALS['wp']->query_vars['rest_route']` przed inicjalizacją REST. Bez tego WooCommerce nie uruchamia obsługi uwierzytelniania Store API, a standardowe uwierzytelnianie cookie WordPressa bez X-WP-Nonce zeruje bieżącego użytkownika. /auth/me osobno odtwarza go z cookie. Pierwotny test pomijał tę inicjalizację, więc jego wynik nie był dowodem identycznego błędu w normalnie routowanym Store API.

## Wolne lokalne środowisko

Podczas diagnostyki 2026-09-14 pierwszy request rejestracji trwał 75,67 s: samo wp-load.php 33,37 s, a callback REST rozpoczął się dopiero około 59,5 s od startu. Jednocześnie /proc/pressure/io wskazywało wysokie oczekiwanie na dysk przy niskim obciążeniu CPU. Z limitem 180 s wszystkie 51 asercji testu logowania przeszło; większość dalszych żądań trwała 0,1–0,3 s. To wskazuje na opóźnienia środowiska, nie błąd samej rejestracji.

Domyślny limit obu testów integracyjnych pozostaje 60 s. Na takim hoście można jawnie zwiększyć go tylko dla testu:

```bash
docker compose exec -T -e PUPILOVO_TEST_HTTP_TIMEOUT=180 wordpress php wp-content/plugins/pupilovo-auth/tests/auth-integration.php
```

Tę samą zmienną obsługuje customer-cart-session-integration.php.

Nie zmienia to timeoutów aplikacji, uwierzytelniania ani asercji testu.

## Wygaśnięcie i zmiana konta

Frontend zapamiętuje ID ostatniej potwierdzonej sesji w sessionStorage. Gdy /auth/me zgłasza gościa albo innego klienta, usuwa poprzedni token i nonce, zeruje licznik koszyka i przechodzi do /account?session=expired. Pełna nawigacja usuwa poprzednie dane z komponentów konta, koszyka i checkoutu. Żądanie checkoutu nie jest wtedy wysyłane. Odpowiedzi 401/403 przy odczycie sesji/konta i operacjach koszyka/checkoutu prowadzą do tego samego stanu. Zwykły gość zachowuje własny koszyk.

```bash
docker compose exec -T frontend sh -lc 'cd frontend && node tests/customer-session.mjs'
```

Test frontendu sprawdza 21 przypadków: gość i klient, wygaśnięcie również po odświeżeniu strony, zmianę konta, 401/403, login/rejestrację/Google oraz czyszczenie licznika przy wylogowaniu.
