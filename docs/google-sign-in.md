# Google Sign-In

Integracja korzysta z Google Identity Services (GIS), bez Client Secret.

## Konfiguracja lokalna

1. W Google Cloud / Google Auth Platform skonfiguruj branding, odbiorców i klienta OAuth typu **Web application**. W trybie testowym dodaj konta testowe, jeśli wymaga tego wybrana konfiguracja odbiorców.
2. Dodaj Authorized JavaScript origins: `http://localhost` i `http://localhost:5173`. Na produkcji dodaj dokładny origin HTTPS frontendu. Callback JS z popupem nie wymaga redirect URI backendu.
3. Utwórz `.env` w katalogu głównym repozytorium na podstawie `.env.example`. Obie zmienne muszą zawierać identyczny Client ID:

   ```dotenv
   VITE_GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
   PUPILOVO_GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
   ```

4. Zastosuj środowisko: `docker compose up -d --force-recreate frontend wordpress`. Zachowuje istniejące wolumeny. Frontend budowany poza Compose musi otrzymać `VITE_GOOGLE_CLIENT_ID` podczas builda (np. `frontend/.env.local`).
5. Otwórz konto w `http://localhost:5173`. Sprawdź nowe konto, istniejący e-mail, ponowne logowanie, odświeżenie, wylogowanie i klasyczny login.

Brak Client ID ukrywa przycisk; backend zwraca kontrolowany błąd 503. Client ID jest publicznym identyfikatorem, nie sekretem. Nie ustawiaj Client Secret w React.

## Przepływ i zabezpieczenia

Jeden przycisk GIS obsługuje obie zakładki konta. Backend sprawdza podpis RS256 przez OpenSSL z kluczami PEM pobranymi z ustalonego adresu Google przez HTTPS. Cache kluczy respektuje max-age i Age. Nieznany kid jest odrzucany; odświeżenie cache następuje po jego wygaśnięciu. Tokeninfo nie jest używany.

Sprawdzane są issuer, audience, opcjonalne azp, expiration, issued-at, email, ścisłe boolean email_verified i sub. Losowy nonce przekazywany do GIS i zapisywany w cookie SameSite=Strict wiąże podpisany token z przeglądarką. Cookie wygasa po godzinie; po dłuższym oczekiwaniu lub rozpoczęciu logowania w innej karcie może być konieczne odświeżenie.

Powiązanie po sub ma pierwszeństwo przed e-mailem. Łączenie dotyczy wyłącznie roli customer i nie nadpisuje istniejącego innego sub. Dla istniejącego konta z adresem spoza Gmail/Workspace wymagane jest potwierdzenie aktualnym hasłem Pupilovo (przez wp_authenticate). Frontend wyświetla formularz potwierdzenia; token pozostaje tylko w pamięci komponentu. Wcześniej powiązane sub nie wymagają ponownego hasła. Blokada MySQL serializuje operacje Google tworzenia/powiązania. Sesja używa standardowego cookie WooCommerce, następnie frontend wywołuje /auth/me po nonce REST.

## Testy i ograniczenia produkcyjne

- `docker compose exec wordpress php /var/www/html/wp-content/plugins/pupilovo-auth/tests/google-token.php` — podpisane lokalnie tokeny, przypadki negatywne oraz adapter pamięciowy tworzenia/powiązania klientów. Bez modyfikowania bazy i bez rzeczywistych danych Google.
- Build TypeScript/Vite, PHP lint i kontrola diff.
- Wymagany ręczny test prawdziwego GIS po konfiguracji klienta, w tym sesji i nonce przez proxy produkcyjne.
- Produkcja: HTTPS, odpowiednie origins, polityka CSP dopuszczająca GIS, dostęp backendu do www.googleapis.com, ograniczenie liczby prób logowania na reverse proxy. Zweryfikuj cookie/proxy/cache dla /wp-json/pupilovo/v1/auth/*; odpowiedzi auth nie powinny być cache'owane.
- Potwierdzenie hasłem dla adresów spoza Gmail/Workspace jest wdrożone. Klient bez dostępu do obecnego hasła musi najpierw odzyskać dostęp przez WordPress/WooCommerce; osobny formularz resetowania hasła w React pozostaje poza zakresem tej integracji.
- Weryfikator RS256 jest lokalną implementacją z testami, nie biblioteką Google; przed produkcją wskazany przegląd bezpieczeństwa i rozważenie utrzymywanej biblioteki JWT przy wprowadzeniu Composera.

Dokumentacja: [weryfikacja tokenów](https://developers.google.com/identity/gsi/web/guides/verify-google-id-token), [GIS JavaScript API](https://developers.google.com/identity/gsi/web/reference/js-reference).

## Weryfikacja integracyjna (11.09.2026)

`docker compose exec wordpress php /var/www/html/wp-content/plugins/pupilovo-auth/tests/auth-integration.php`

Test uruchamia tymczasowy serwer HTTP wyłącznie na loopback kontenera, z rzeczywistymi WordPress/WooCommerce i cookies. Używa lokalnego klucza RSA wyłącznie we własnym procesie testowym, bez zmieniania konfiguracji lub cache kluczy działającej aplikacji. Blokuje wysyłkę e-maili. Tworzy klientów z unikalnymi adresami testowymi, a w finally usuwa wyłącznie te konta i wyłącza serwer.

51 sprawdzeń obejmuje: rejestrację, poprawne/błędne hasło, bootstrap i odświeżanie sesji, odrzucenie błędnego nonce wylogowania, poprawne wylogowanie, potwierdzenie hasłem zewnętrznego adresu, automatyczne powiązanie Gmail, ponowny login po sub, utworzenie klienta Google i zachowanie klasycznego hasła. Powiązanie zachowuje ID klienta; nie tworzy duplikatu. Test nie składa zamówień. Prawdziwy popup Google został osobno sprawdzony ręcznie przez użytkownika.

W badanym środowisku odczyty /me zwykle trwały około 0,08 s, ale operacje tworzenia kont i sesji okresowo trwały kilka–kilkadziesiąt sekund. W tym samym czasie /proc/pressure/io wskazywało około 60–70% oczekiwania na I/O, przy niskim użyciu CPU i wolnym miejscu na dysku. To wskazówka dotycząca wydajności hosta, nie dowód konkretnej przyczyny historycznego opóźnienia. Nie zmieniono ustawień trwałości MySQL. Wcześniejszy błąd google_busy odpowiadał równoległej próbie podczas trwającego tworzenia konta.
