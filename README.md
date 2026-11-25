<p align="center"><img src="public/img/logo-full.png" width="240" alt="Ninimma"></p>

# Ninimma — panel agencji

Samodzielna aplikacja w PHP 8.3 + MySQL (Laravel 13) do obsługi klientów agencji: zlecenia z rozmową,
statusem i osią czasu, a w kolejnych etapach tablice, plan publikacji social media, influencerzy,
kampanie oraz strony/SEO. Działa na zwykłym hostingu współdzielonym obok WordPressa (osobna subdomena),
bez Node, Redisa, WebSocketów i stałych procesów. Pełna specyfikacja: `docs/specyfikacja.pdf`.

## Stan prac

| Etap | Zakres | Stan |
|---|---|---|
| 1. Rdzeń | konta, role, zaproszenia, 2FA, klienci, zlecenia z rozmową i notatkami, statusy i oś czasu, pliki w kawałkach, e-maile, wyszukiwarka, dziennik systemu, kopie zapasowe | ✅ gotowy do testów |
| 2. Tablice i wyceny | Excalidraw, wersje, wyceny z limitem, czas pracy, odbiór, regulamin | ⏳ |
| 3. Plan publikacji | treści i publikacje (YT, TikTok, FB, IG, Pinterest, LinkedIn), kalendarz, statystyki | ✅ gotowy do testów |
| 4. Influencerzy i kampanie | osoby, współprace z etapami, przesyłki, materiały z licencjami, raporty | ⏳ |
| 5. Strony, administracja, SEO | terminy, przeglądy z listą kontrolną, pakiety godzin, frazy, plan treści | ⏳ |
| 6. Porządki | archiwizacja po roku, paczki offline, kartoteka z szyfrowanymi dostępami | ⏳ |

### Co działa w etapie 1

- **Role:** administrator widzi wszystko; klient tylko zlecenia swojej firmy, bez notatek wewnętrznych.
- **Konta:** zaproszenia z linkiem aktywacyjnym (ważne 7 dni), reset hasła, obowiązkowe 2FA (TOTP + kody awaryjne) dla administratorów, dezaktywacja kont.
- **Zlecenia:** typ, status i strona, po której jest piłka (`u nas`, `czeka na klienta`, `wymaga decyzji klienta`, `zablokowane` z powodem, `zakończone`), powiązanie „wynikło z”, oś czasu budowana automatycznie.
- **Rozmowa:** wiadomości + notatki wewnętrzne, odświeżanie co kilka sekund (oszczędne: rzadziej przy bezczynności, wcale przy ukrytej karcie), potwierdzenie przeczytania, kropki przy nowościach, Ctrl+Enter.
- **Zgłaszanie:** wklejanie zrzutu ze schowka, przeciąganie plików, nagrywanie ekranu z przeglądarki (limit dla klienta), działa na telefonie.
- **Sygnał zastoju:** powiadomienie od razu, przypomnienie po dniu (jeśli nieprzeczytane), kolejne po tygodniu → lista zastojów na pulpicie; przycisk „Przypomnij”.
- **Pliki:** wysyłka w kawałkach (omija limity uploadu hostingu), kompresja obrazów w przeglądarce, deduplikacja po SHA-256, pliki poza katalogiem publicznym wydawane po sprawdzeniu uprawnień.
- **Powiadomienia e-mail** (SMTP, kolejka w bazie): każda wiadomość albo dzienne podsumowanie — wybór użytkownika.
- **Szablony i zlecenia cykliczne** (np. comiesięczna opieka nad stroną, `{month}` w tytule).
- **Wyszukiwarka** po tytułach i treści wiadomości, także w archiwum.
- **Dziennik systemu:** ostatnie uruchomienie crona, nieudane e-maile/zadania, błędy uploadu, kopie zapasowe.
- **Języki:** polski i angielski, wybierane na użytkownika (także e-maile).
- **Planowanie i czas pracy** (część etapu 2): priorytet (normalny / wysoki / pilne), termin, osoba prowadząca i wpisy czasu pracy przy zleceniu.
- **Pulpit administratora:** wskaźniki z trendem względem poprzedniego okresu (wiadomości, otwarte zadania, na dziś, do akceptacji, czas pracy), aktywne rozmowy z liczbą nieprzeczytanych, wykres aktywności zespołu, tablica zadań (do zrobienia / w trakcie / do akceptacji / zakończone), priorytety, nadchodzące terminy, zespół i przypomnienie o terminie na dziś.

### Co działa w etapie 3 (Social media)

- **Profile:** konta social media klientów (Instagram, Facebook, TikTok, YouTube, LinkedIn, Pinterest).
- **Treści:** zapisane raz (tytuł, opis, hashtagi, typ, format, grafiki) z lejkiem: pomysł → w przygotowaniu → gotowe.
- **Publikacje:** każda platforma ma własną publikację treści z datą, statusem, linkiem, opcjonalnym opisem pod platformę i promocją (budżet).
- **Statystyki:** kolejka „do sprawdzenia” po 1, 7 i 30 dniach i jeden ekran do wpisania liczb dla wszystkich zaległych publikacji; każdy pomiar zapisany z datą, bez nadpisywania.
- **Pulpit social media:** wskaźniki z trendem (posty, zasięg, zaangażowanie, kliknięcia), filtr marek, lejek treści, kalendarz tygodnia z miniaturami, wyniki według marek i platform, trend zaangażowania, zadania i publikacje czekające na klienta.
- **Kalendarz miesięczny** z filtrem kont.
- **Klient:** udostępnione publikacje z podglądem grafik i opisów (bez pól roboczych), akceptacja lub prośba o zmiany z komentarzem; kalendarz udostępnionych postów.

## Instalacja na hostingu

1. Sprawdź wersję PHP (wymagane **8.3+**) i utwórz bazę MySQL.
2. Lokalnie: `composer install --no-dev --optimize-autoloader`, potem wgraj cały katalog (razem z `vendor/`) na hosting, np. do `~/panel`.
3. Ustaw katalog główny subdomeny (np. `panel.twojadomena.pl`) na `~/panel/public`.
4. Skopiuj `.env.example` do `.env`, uzupełnij `APP_URL`, dane bazy i SMTP (domena z SPF/DKIM), ustaw `APP_ENV=production`, `APP_DEBUG=false`.
5. Przez SSH:
   ```bash
   php artisan key:generate
   php artisan migrate --force
   php artisan agency:create-admin ty@agencja.pl "Imię Nazwisko"
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
6. Dodaj jeden wpis crona (co minutę):
   ```
   * * * * * cd ~/panel && php artisan schedule:run >> /dev/null 2>&1
   ```
   Cron obsługuje kolejkę e-maili, przypomnienia, podsumowania, zlecenia cykliczne i kopie zapasowe.
7. Zaloguj się — przy pierwszym logowaniu panel poprosi o skonfigurowanie 2FA.

### Kopie zapasowe (homelab)

Codziennie o 2:15 powstaje zrzut bazy w `storage/app/backups` (ostatnie 7). Homelab może go pobrać:
```bash
curl -H "Authorization: Bearer $AGENCY_BACKUP_TOKEN" https://panel.twojadomena.pl/backup/latest -o db.jsonl.gz
```
Pliki z `storage/app/store` najlepiej synchronizować przez SFTP/rsync.

## Praca lokalna

```bash
composer install
cp .env.example .env   # ustaw DB_CONNECTION=sqlite dla szybkiego startu
php artisan key:generate
php artisan migrate --seed --seeder=DemoSeeder   # dane demo, wypisuje sekret 2FA
php artisan serve
php artisan test
```

## Otwarte decyzje (ze specyfikacji)

- [ ] Wersja PHP na hostingu (Laravel 13 wymaga 8.3) i możliwość wskazania katalogu subdomeny.
- [ ] Dokładne limity dla klienta (obecnie 100 MB / 5 min nagrania — `.env`).
- [ ] Próg zajętości dysku dla przenoszenia offline.
- [ ] Domyślny okres przechowywania danych.
- [ ] Treść regulaminu współpracy i zapisy RODO (checkbox przy aktywacji już jest).
- [ ] Pierwszy klient testowy.

## Struktura

- `app/Services/TicketService.php` — logika zleceń (statusy, wiadomości, przypomnienia, odczyty)
- `app/Services/FileStore.php` — magazyn plików
- `app/Services/Totp.php` — 2FA bez zewnętrznych zależności
- `app/Console/Commands` — zadania crona (`agency:*`)
- `public/js/live.js` — jedyne miejsce wymiany zdarzeń z serwerem (łatwa podmiana na WebSocket)
- `lang/pl`, `lang/en` — tłumaczenia interfejsu i e-maili
