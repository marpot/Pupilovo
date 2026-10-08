# Resume instructions

## Ograniczenie czasowe

Nie wznawiać rozwijania nowych funkcji przed **2026-10-08 16:43 CEST**. Do tego czasu stan repozytorium pozostawić bez zmian. Po tej godzinie rozpocząć od walidacji, nie od nowych funkcji.

1. `cd ~/Desktop/Projekty/Pupilovo`
2. Sprawdź `git status --short --branch`; wymagana gałąź: `feature/universal-supplier-import`.
3. Przeczytaj wszystkie pliki w `docs/agent-progress/`.
4. Przeczytaj `wordpress/plugins/pupilovo-dropshipping/docs/architecture.md` i `rest-api.md`.
5. Uruchom bazę: `docker compose up -d wordpress mysql`.
6. Sprawdź brak osieroconych testów: `docker compose exec -T wordpress ps aux`.
7. Uruchom pełną regresję: `make supplier-hub-test`. Jeśli Makefile nie obejmuje nowych plików, uruchom kolejno wszystkie skrypty `tests/*.php` w kontenerze.
8. Uruchom panel: `cd wordpress/plugins/pupilovo-dropshipping/admin-app && npm run lint && npm run build`.
9. Uruchom PHP lint całego `src/` i `git diff --check`.
10. Dodaj dedykowany test `CatalogImportManager`: kolejka uploadu, wykonanie, trwały katalog, cleanup pliku tymczasowego i bezpieczna porażka.
11. Wykonaj ręczny QA panelu w WordPress: wszystkie 10 sekcji, focus/klawiatura, błędy, loading, potwierdzenia i brak martwych akcji.
12. Dopiero po zaliczeniu powyższego kontynuuj pierwsze niezakończone zadanie z `PENDING_TASKS.md`.

Nie cofaj niezacommitowanych zmian, nie wykonuj merge/push i nie używaj danych produkcyjnych.
