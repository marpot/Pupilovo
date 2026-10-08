# Dropshipping fulfillment — Etap 6A

Etap 6A przygotowuje wewnętrzny, niezmienny podział pozycji zamówienia WooCommerce. Nie wysyła zamówień do dostawców i nie zmienia statusu zamówienia klienta.

## Przypisanie dostawcy

1. Metadana `_pupilovo_supplier_id` produktu lub wariantu jest uznawana tylko wtedy, gdy potwierdza ją aktywne `product_links`.
2. Następnie używane jest pojedyncze powiązanie oznaczone `is_primary`.
3. Przy dokładnie jednym powiązaniu jest ono wybierane.
4. Brak, konflikt lub nieaktualna metadana tworzy grupę `manual` z `requires_manual_decision=1`.

Snapshot zawiera wyłącznie dane pozycji potrzebne do realizacji: identyfikatory, nazwę/SKU, ilość, kwoty pozycji, walutę, dostawcę, powiązanie i zewnętrzny identyfikator. Nie zawiera danych klienta ani sekretów integracji.

Checkout klasyczny i Store API są przechwytywane po utworzeniu zamówienia. Ogólny hook `woocommerce_new_order` dodaje dodatkowe, opóźnione zadanie Action Scheduler; dzięki idempotencji jest ono bezpieczne również wtedy, gdy checkout został już przetworzony.

## Migracja schematu v3

Powtarzalna migracja `dbDelta` dodaje `psh_fulfillment_groups`, `psh_fulfillment_items` i `psh_fulfillment_history`. Nie modyfikuje ani nie usuwa zamówień WooCommerce. Unikalne klucze `order_group`, `order_item` i `event_key` stanowią bazową ochronę przed duplikatami.

## Statusy

`pending`, `ready`, `manually_approved`, `sent`, `acknowledged`, `shipped`, `delivered`, `failed`, `cancelled`.

Każda zmiana jest dopisywana do historii. Etap 6A nie udostępnia endpointu modyfikacji statusu i nie realizuje wysyłki.

## REST tylko do odczytu

- `GET /wp-json/pupilovo-supplier-hub/v1/fulfillment/orders`
- `GET /wp-json/pupilovo-supplier-hub/v1/fulfillment/orders/{wc_order_id}`

Wymagane jest `manage_pupilovo_supplier_hub` albo `manage_woocommerce`. Lista przyjmuje `page`, `per_page`, `supplier_id`, `status` i `manual`.

## Panel administracyjny — Etap 6B

Widok `Zamówienia dropshippingowe` korzysta wyłącznie z powyższego API do odczytu. Udostępnia filtrowaną i stronicowaną listę grup, snapshoty pozycji oraz historię statusów i błędów. Nie prezentuje danych klienta, nie wysyła zamówień do dostawców i nie zmienia statusów WooCommerce.
