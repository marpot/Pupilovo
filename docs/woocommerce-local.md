# Lokalny WooCommerce

Backend korzysta z istniejących usług `wordpress` i `mysql` oraz wolumenów
`wordpress_data` i `mysql_data`. Nie tworzymy drugiej instalacji WordPressa.
Frontend React pozostaje osobnym serwisem.

## Wersje i adresy

- WordPress: 7.1, oficjalny obraz `wordpress:7.1-php8.3-apache`.
- WooCommerce: 11.1.0 (wtyczka w wolumenie WordPressa).
- Panel: http://localhost:8080/wp-admin/
- Frontend: http://localhost:5173/
- Katalog Store API: http://localhost:8080/wp-json/wc/store/v1/products

WooCommerce 11.1.0 wymaga co najmniej WordPressa 7.0. Wersję sprawdzono
w oficjalnym katalogu WordPress.org przed aktualizacją istniejącego WP 6.8.2.
Sam wybór nowego obrazu Dockera nie aktualizuje plików rdzenia w istniejącym
wolumenie: wykonano również `wp core update` oraz `wp core update-db`.

## Kopia przed zmianami

Kopie z 2026-09-11 znajdują się lokalnie w `.local-backups/`:

- `before-woocommerce-2026-09-11.sql` — baza przed aktualizacją i instalacją;
- `before-woocommerce-files-2026-09-11.tar.gz` — `wp-content` i `wp-config.php`.

Katalog jest wyłączony z Gita i kontekstu budowania Dockera. Zawiera dane
instalacji, więc nie należy go publikować. Nie używać `docker compose down -v`:
usuwa to dane utrzymywane przez wolumeny.

Odtworzenie kopii zastąpi obecny stan bazy i plików, dlatego powinno być
osobną, świadomą operacją po zabezpieczeniu nowszych danych. Wymaga też
przywrócenia zgodnej wersji rdzenia i obrazu WordPressa.

## Narzędzia administracyjne

Do konfiguracji użyto oficjalnego WP-CLI pobranego z
https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
z kontrolą sumy SHA512 z tego samego oficjalnego repozytorium.

WP-CLI jest tymczasowo dostępne w kontenerze jako `/tmp/pupilovo-wp-cli.phar`.
Po odtworzeniu kontenera należy ponownie skopiować zweryfikowane narzędzie.
Polecenia wykonujemy jako `www-data`, nie jako administrator systemu:

```bash
docker compose exec -T --user www-data wordpress php /tmp/pupilovo-wp-cli.phar core version
docker compose exec -T --user www-data wordpress php /tmp/pupilovo-wp-cli.phar plugin list
```

Na świeżej, zainstalowanej lokalnej instancji WordPressa:

```bash
docker compose exec -T --user www-data wordpress php /tmp/pupilovo-wp-cli.phar plugin install woocommerce --version=11.1.0 --activate
```

Nie uruchamiać instalacji ponownie z `--force` na działającym sklepie.
Wtyczka i jej ustawienia znajdują się w wolumenie/bazie, nie w repozytorium.

## Katalog demonstracyjny

```bash
docker compose cp scripts/woocommerce/seed-local.php wordpress:/tmp/pupilovo-seed-local.php
docker compose exec -T --user www-data wordpress php /tmp/pupilovo-wp-cli.phar eval-file /tmp/pupilovo-seed-local.php
```

Skrypt dopuszcza tylko instalację pod `localhost` lub `127.0.0.1`.
Tworzy brakujące kategorie Psy, Koty, Gryzonie, Ptaki oraz cztery produkty
z SKU `PUP-DEMO-001`–`PUP-DEMO-004`. Ponowne wykonanie nie nadpisuje istniejących
produktów ani ich stanów. Dane są wyłącznie demonstracyjne: opisy zawierają
odpowiednią informację, a produkty mają metadane `_pupilovo_demo=yes`.

Pierwsze wykonanie ustawia Polskę, PLN, kilogramy i centymetry oraz wyłącza
zgodę na telemetrię WooCommerce. Skrypt tworzy standardowe strony WooCommerce.
Nie ustawia danych firmy, stawek podatków, bramek płatności ani kosztów dostawy.
Nie przypisuje zastępczych zdjęć psów jako prawdziwych zdjęć produktów.

## Następny etap

React nadal używa dotychczasowych mocków. Kolejna gałąź ma podłączyć publiczny
katalog Store API z obsługą ładowania, błędów, wyszukiwania i kategorii.
Administracyjnych kluczy WooCommerce nie wolno umieszczać w kodzie React ani
zmiennych `VITE_*`. Logowanie, sesja koszyka i checkout wymagają osobnej integracji.

Konfiguracja jest lokalna i developerska. Uruchomienie komercyjnego sklepu
wymaga m.in. prawdziwego katalogu, dostawy, podatków, płatności oraz konfiguracji
produkcyjnego hostingu.
