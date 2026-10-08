# REST API

Namespace: `/wp-json/pupilovo-supplier-hub/v1`

Każdy endpoint wymaga zalogowanego użytkownika z `manage_pupilovo_supplier_hub` lub `manage_woocommerce`. Żądania z panelu używają cookie WordPress i nagłówka `X-WP-Nonce`.

## Etap A

| Metoda | Ścieżka | Znaczenie |
|---|---|---|
| `GET` | `/dashboard` | realne liczniki i data ostatniej synchronizacji |
| `GET` | `/system` | wersje, dostępność WooCommerce/Action Scheduler, typy źródeł i adaptery |
| `GET` | `/suppliers` | stronicowana lista; parametry `page`, `per_page`, `search`, `status` |
| `POST` | `/suppliers` | utworzenie profilu jako niesekretnej konfiguracji |
| `GET` | `/suppliers/{id}` | szczegóły profilu bez danych dostępowych |
| `PUT/PATCH` | `/suppliers/{id}` | częściowa aktualizacja profilu |
| `DELETE` | `/suppliers/{id}?confirm=true` | soft delete, bez usuwania produktów WooCommerce |
| `POST` | `/suppliers/{id}/preview` | multipart `feed_file`, maks. 20 rekordów, bez persystencji |

Przykładowe utworzenie profilu:

```json
{
  "name": "Hurtownia przykładowa",
  "sourceType": "file_xml",
  "status": "draft",
  "sourceConfig": { "record_element": "product" },
  "fieldMapping": { "external_id": "id", "sku": "sku", "name": "name" }
}
```

Pola `credentials`, `secret`, `password`, `token` i `apiKey` są odrzucane. Dedykowany kontrakt sekretów zostanie dodany razem z szyfrowanym magazynem.

## Planowane kontrakty

- `/suppliers/{id}/connection-test`, `/sample`, `/mapping`;
- `/catalog` z serwerowymi filtrami, sortowaniem i paginacją;
- `/categories/tree`, `/category-mappings`, `/category-suggestions`;
- `/selections` wraz z operacjami na wynikach filtra realizowanymi backendowo;
- `/import-preview`, `/jobs`, `/jobs/{id}`, `/jobs/{id}/resume`;
- `/sync-runs`, `/pricing-rules`, `/logs`.

Mutacje katalogu i WooCommerce otrzymają klucze idempotencji oraz jawne operacje `dryRun`/`confirm`.
