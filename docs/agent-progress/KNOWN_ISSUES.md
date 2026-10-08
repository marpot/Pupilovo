# Known issues

## Dziewięć błędów ESLint wykrytych i naprawionych 2026-10-08

Pierwszy przebieg `npm run lint` po dodaniu widoków Etapu F zgłosił dokładnie 9 błędów:

1. `admin-app/src/App.tsx:412` — parser `'}' expected`; brakowało zamknięcia wyrażenia JSX dla asynchronicznego callbacku `onSelection` w wierszu katalogu.
2. `admin-app/src/OperationalViews.tsx:20` — `react-hooks/set-state-in-effect`; synchroniczne ustawienie domyślnej hurtowni w `CategoriesView`.
3. `admin-app/src/OperationalViews.tsx:22` — `react-hooks/set-state-in-effect`; wywołanie `load()` z synchronicznym `setLoading` bez odroczenia w efekcie kategorii.
4. `admin-app/src/OperationalViews.tsx:32` — ten sam powód w `SelectedView`.
5. `admin-app/src/OperationalViews.tsx:39` — synchroniczne ustawienie domyślnej hurtowni w `ImportView`.
6. `admin-app/src/OperationalViews.tsx:51` — synchroniczne ustawienie hurtowni i stanu harmonogramu w `SyncView`.
7. `admin-app/src/OperationalViews.tsx:60` — synchroniczne ustawienie domyślnej hurtowni w `PricingView`.
8. `admin-app/src/OperationalViews.tsx:61` — bezpośrednie wywołanie loadera z efektu cen.
9. `admin-app/src/OperationalViews.tsx:69` — bezpośrednie wywołanie loadera z efektu historii.

Naprawa: domyślne ID jest teraz wartością pochodną (`supplierId || suppliers[0]?.id || 0`), loadery są odraczane przez `Promise.resolve().then(load)`, a JSX ma kompletne zamknięcie. Ponowny ESLint przechodzi; te pozycje są zachowane jako historia defektu, nie jako aktywne błędy.

## Aktywne ograniczenia

- Brak ręcznej wizualnej i klawiaturowej walidacji nowych widoków React w przeglądarce.
- Najnowszy `CatalogImportManager` ma lint pass, ale nie ma jeszcze dedykowanego testu integracyjnego upload/URL → Action Scheduler → katalog → cleanup.
- Pełna regresja wszystkich wcześniejszych testów nie została wykonana po ostatniej serii zmian.
- Build panelu zawiera React i ma 208.33 KiB gzip; przed release potrzebny jest code splitting lub świadoma akceptacja rozmiaru.
- Udane pobranie prawdziwego publicznego obrazu nie było testowane; przetestowano blokadę niebezpiecznego URL i bezpieczny błąd częściowy.
- Brak wyników wymaganych testów wydajności 100 / 2 000 / 10 000 produktów.
- Brak testu instalacji na niezależnym WordPressie, finalnego ZIP, kompletnej dokumentacji administratora/developera i zweryfikowanej polityki uninstall.
- Widoki działają funkcjonalnie na poziomie kodu/builda, ale wymagają integracyjnego QA REST; nie należy deklarować gotowości produkcyjnej.
