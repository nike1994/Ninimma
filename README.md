<p align="center"><img src="public/img/logo-full.png" width="240" alt="Ninimma"></p>

# Ninimma — panel agencji

Samodzielna aplikacja w PHP 8.3 + MySQL (Laravel 13) dla agencji: zlecenia z rozmową, tablice, wyceny,
social media, influencerzy, kampanie, strony i SEO. Działa na zwykłym hostingu współdzielonym obok WordPressa
(osobna subdomena), bez Node, Redisa, WebSocketów i stałych procesów — zadania w tle wykonuje cron.
Opcjonalna wtyczka WordPress łączy panel ze stronami klientów. Specyfikacja: `docs/specyfikacja.pdf`.

## Stan prac

| Etap | Zakres | Stan |
|---|---|---|
| 1. Rdzeń | konta, role, zaproszenia, 2FA, klienci, zlecenia z rozmową, notatki, statusy, oś czasu, pliki, e-maile, wyszukiwarka, dziennik, kopie | ✅ |
| 2. Tablice i wyceny | Excalidraw z wersjami, kropką i listą zmian, wyceny z limitem, zmiana zakresu, odbiór, czas pracy, regulamin, rozliczenia | ✅ |
| 3. Plan publikacji | profile, treści i publikacje na platformach, kalendarze, kolejka statystyk, akceptacja klienta, kampanie, pulpit social media | ✅ |
| 4. Influencerzy i kampanie | osoby, współprace z 6 etapami, przesyłki, materiały z licencjami, przypomnienia, raport kampanii | ✅ |
| 5. Strony, administracja i SEO | terminy, dziennik, przeglądy z listą kontrolną, pakiety godzin, frazy, plan treści, wspólny kalendarz, raport miesięczny | ✅ |
| 6. Porządki i wygoda | archiwizacja po roku, paczki eksportu, offline, przechowywanie, zajętość, kartoteka z szyfrowanymi dostępami | ✅ |
| Wtyczka WordPress | wspólne logowanie klienta i przycisk „Zgłoś problem” | ✅ |

## Najważniejsze funkcje

**Zlecenia (wszystko jest zleceniem).** Rozmowa z notatkami wewnętrznymi, status i strona, po której jest piłka,
oś czasu tworzona automatycznie, „wynikło z”, potwierdzenia przeczytania, odświeżanie co kilka sekund,
wklejanie zrzutów, nagrywanie ekranu, pliki w kawałkach (deduplikacja SHA-256, wydawane po sprawdzeniu uprawnień),
sygnał zastoju z przypomnieniami (od razu, po dniu, po tygodniu), szablony i zlecenia cykliczne, priorytet,
termin, osoba prowadząca, czas pracy.

**Tablice (Excalidraw, MIT).** Przy zleceniu; synchronizacja przez odpytywanie co 2–3 s (tylko zmienione elementy,
łączone po id i wersji), blokada elementów drugiej strony, obecność, obrazy w magazynie, osadzanie linków
(YouTube, Canva, Facebook…), wersje świadome i po sesji, podgląd i przywracanie, kropka i lista zmian, podgląd na telefonie.

**Wyceny i rozliczenia.** Wycena jako limit pracy (godziny/kwota) z opcjami (pełna naprawa, obejście, stop),
płatna diagnoza, historia wersji, ostrzeżenie przy 80% limitu, odbiór jednym kliknięciem, faktury z KSeF jako PDF +
status płatności, regulamin współpracy z wersjami i ponowną akceptacją.

**Social media.** Profile (IG, FB, TikTok, YouTube, LinkedIn, Pinterest), treść zapisana raz + publikacja na każdej
platformie, lejek treści, kalendarz tygodnia i miesiąca, kolejka statystyk po 1/7/30 dniach (pomiary bez nadpisywania),
pulpit z KPI i trendami, wyniki według marek i platform, kampanie (ROAS/leady/zasięg), zadania i notatki,
akceptacja postów przez klienta.

**Kampanie marketingowe (Social media → Kampanie).** Jedna strona z KPI (aktywne kampanie, łączny zasięg,
kliknięcia, wydatki, ROAS) i trendem 7 dni do 7 poprzednich, filtrami po kliencie i statusie (aktywne / zaplanowane /
zakończone), listą lub siatką kart oraz szczegółem kampanii w zakładkach: podsumowanie (okładka, wyniki, wykres zasięgu
i kliknięć w czasie, budżet i harmonogram, platformy, cel), ustawienia, kreacje, grupy odbiorców i wyniki. Pod spodem
lejek (zasięg → wejścia na stronę → dodania do koszyka → zakup/lead; dane z wyników reklam), demografia (płeć, wiek),
najlepsze kreacje według CTR, lokalizacje (top miasta na mapie Polski / top kraje) i ranking najskuteczniejszych
kampanii (ROAS, konwersje). Struktura odbiorców wpisywana ręcznie albo importowana z CSV menedżera reklam
(kolumny wiek/płeć/region/kraj + zasięg lub wyświetlenia).

**Marketing płatny (reklamy i promocje).** Promowane posty (budżet i okres przy publikacji tworzą wpis reklamy)
oraz samodzielne kampanie reklamowe (Meta, Google, TikTok, LinkedIn, Pinterest, YouTube) z celem, budżetem planowanym
i okresem. Wyniki płatne narastająco z datą, bez nadpisywania (wydatek, wyświetlenia, zasięg, kliknięcia, konwersje,
leady, przychód) — ręcznie albo importem CSV z menedżera reklam (dopasowanie po nazwie kampanii). Wskaźniki: CTR, CPC,
CPM, koszt wyniku, ROAS. Lista promocji z filtrami i paskiem wykorzystania budżetu, alert przed końcem, start i koniec
według dat (cron). Budżet kampanii: wydane vs planowane. Generator linków UTM. Raport: wyniki płatne osobno od organicznych.

**Planowanie seryjne.** Social media → Treści → „Planowanie seryjne”: klient, konta z godzinami, hashtagi, zestawy
hashtagów i akceptacja klienta ustawiane raz; przeciągnięte grafiki/filmy stają się osobnymi postami (kolejność wg nazw,
„+” dokłada pliki do karuzeli), „Rozłóż daty” przydziela kolejne wybrane dni tygodnia. Ustawienia zapamiętują się jako
domyślne klienta i podpowiadają się też w zwykłym formularzu posta. Każdą treść można zduplikować.

**Podgląd posta i limity.** Konta mają zdjęcie profilu (wgrane w Social media → Profile albo pobrane z platformy
przy łączeniu z API). W formularzu treści obok pól jest podgląd na żywo w wyglądzie aplikacji — zdjęcie profilu,
nazwa, „Sponsorowane” przy promocji, przyciski (Instagram: serce, komentarz, wyślij, zapisz; Facebook: „Lubię to!”,
„Komentarz”, „Udostępnij”, pole komentarza; TikTok: pionowy kadr z prawym paskiem) — dla kont wybranych w publikacjach
(Instagram, Facebook, TikTok, LinkedIn): kadr (Instagram/Facebook przycinają do 4:5–1.91:1, film jako rolka 9:16),
ucięcie opisu na „…więcej” / „Wyświetl więcej”, hashtagi. Liczniki znaków i hashtagów pokazują najostrzejszy limit
z wybranych platform (Instagram i TikTok 2200 znaków, Instagram 30 hashtagów, LinkedIn 3000, Pinterest 500);
przekroczenie blokuje zapis tej publikacji z podpowiedzią (np. krótszy opis pod platformę).

**Planowanie w kalendarzu.** Przeciąganie postów na inny dzień (kalendarz miesięczny) albo dzień i godzinę (tydzień
na pulpicie social media) — z tymi samymi zasadami co formularz (licencje, okres promocji przesuwa się razem z postem,
automatyczna publikacja dostaje nową szansę). „+” w dniu i dwuklik w godzinę tygodnia zakładają post z tym terminem
(i kontem z filtra). Po najechaniu — podgląd: grafika, opis, hashtagi, status, akceptacja klienta, licencja.

**Strażnik licencji (influencerzy / UGC).** Każdy materiał ma licencję od–do, kanały użycia (posty, reklamy, strona,
e-mail, druk) i zasadę „zdjąć po końcu” — domyślnie przejmowane z umowy współpracy. Materiał podpina się do treści
(a przez nią do publikacji) i do reklam. Panel wykrywa konflikty: publikacja po końcu / przed startem licencji, reklama
dłuższa niż licencja lub trwająca po końcu, kanał nieobjęty licencją, opublikowany post do zdjęcia. Ekran „Licencje”:
liczniki-pierścienie, oś czasu z dziś, postami i reklamami, przedłużenia z kosztem (wchodzi do kosztów kampanii)
i historią. Ostrzeżenia w formularzu treści i reklamy, w kalendarzach, na pulpitach i w karcie współpracy; klient widzi
termin i kanały swoich materiałów. Blokady: po wygaśnięciu licencji (albo przed jej startem) nie da się zaplanować
ani opublikować nowego posta z materiałem lub jego fragmentem, promować posta ani utworzyć/wznowić reklamy z nim;
promocja musi mieć datę końca w okresie licencji. Wygasłe materiały są zablokowane w wyborze, a cron nie uruchomi takiej reklamy.
Od materiału do posta: przy materiale (karta współpracy, Licencje, Biblioteka) „Utwórz post” otwiera nową treść
z klientem, kampanią, tytułem, formatem i zaznaczonym materiałem; plik materiału dołącza się do treści sam (także
przy ręcznym zaznaczeniu materiału w poście). Zaznaczenie materiału w poście to jedno powiązanie dla strażnika
licencji, raportu kampanii i wyników influencera; przy materiale widać listę postów, w których go użyto.
Dodawanie: w karcie współpracy przeciąga się filmy/zdjęcia (kilka naraz) — każdy plik to materiał z licencją z umowy.
Wszędzie widać miniaturę z kółkiem profilu influencera: kadr filmu (JPEG) robi przeglądarka przy wgrywaniu, więc działa
w każdej przeglądarce, także dla .mov z iPhone'a (bez ffmpeg na hostingu); dla starszych filmów kadr dorabia się przy
pierwszym podglądzie przez zespół. Po najechaniu film gra bez dźwięku.

**Ochrona materiałów przed klientem.** Filmy i zdjęcia materiałów oraz projekty postów (do publikacji) klient widzi
tylko w trybie chronionym: zdjęcia jako pomniejszona kopia ze znakiem wodnym (nazwa klienta + data, GD), filmy tylko
w odtwarzaczu panelu (bez pobierania, bez menu, z nakładką znaku wodnego; bezpośredni link i `?download=1` → 403).
Po wygaśnięciu licencji podgląd jest zablokowany. Oryginał klient pobierze dopiero po włączeniu „Klient może pobrać
oryginał” przy materiale. Pobrania i zablokowane próby trafiają do dziennika (widoczny przy licencji materiału).
Uwaga: nagrania ekranu nie da się technicznie zablokować — znak wodny wskazuje, od kogo pochodzi wyciek. E-mail do zespołu 30/14/7/1/0 dni przed końcem (cron 7:40, każdy próg raz).

**Influencerzy.** Karty, etapy, tabela z filtrami („komu paczka nie dotarła”, „kto nie oddał materiałów”),
współprace (umowa → brief → przesyłka → materiały → publikacja → rozliczenie), materiały z licencją i powiązaniem
z publikacją, przypomnienia, raport kampanii (koszt na materiał i interakcję, porównanie osób i formatów),
wersja dla klienta bez stawek i danych osobowych.

**Strony i SEO.** Terminy (domena, SSL, hosting, licencje) z odnowieniem, dziennik zmian, przeglądy cykliczne
tworzone przez cron jako zlecenia z listą kontrolną, problem z przeglądu → osobne zlecenie, pakiety godzin,
frazy z pomiarami (import CSV), plan treści jako zlecenia, zadania techniczne, linki zewnętrzne,
wspólny kalendarz, raport miesięczny dla klienta.

**Porządki.** Archiwizacja rok po zamknięciu (zostaje wersja końcowa i opisane wersje tablic), paczki ZIP
(zlecenie, klient/RODO), przenoszenie offline do homelabu i przywracanie z paczki, licznik zajętości, próg,
okres przechowywania, usunięcie na wniosek, kartoteka klienta z szyfrowanymi dostępami (odsłonięcia logowane).

**Pulpity.** Administrator: KPI z trendem, rozmowy, wykres aktywności, tablica zadań, priorytety, terminy,
przypomnienia i licencje. Klient: zlecenia, pliki, wiadomości, publikacje, strony i SEO, materiały, raporty, rozliczenia.

Interfejs i e-maile po polsku i angielsku (wybór na użytkownika).

## Instalacja na hostingu

1. PHP **8.3+**, MySQL/MariaDB, możliwość ustawienia katalogu subdomeny na `public/`.
2. Lokalnie: `composer install --no-dev --optimize-autoloader`, wgraj cały katalog (z `vendor/` i `public/vendor/`) np. do `~/panel`.
3. Katalog główny subdomeny (np. `panel.agencja.pl`) → `~/panel/public`.
4. `.env` z `.env.example`: `APP_URL`, baza, SMTP (domena z SPF/DKIM), `APP_ENV=production`, `APP_DEBUG=false`, `AGENCY_BACKUP_TOKEN`.
5. Przez SSH:
   ```bash
   php artisan key:generate
   php artisan migrate --force
   php artisan agency:create-admin ty@agencja.pl "Imię Nazwisko"
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
   **Zachowaj `APP_KEY`** — szyfruje dostępy w kartotece i sekret 2FA. Bez niego nie da się ich odczytać.
6. Cron (co minutę):
   ```
   * * * * * cd ~/panel && php artisan schedule:run >> /dev/null 2>&1
   ```
   Obsługuje: kolejkę e-maili, przypomnienia o zastojach (co godz.), podsumowania (7:30), zlecenia cykliczne (6:00),
   przeglądy stron (6:10), kopię bazy (2:15), archiwizację i okres przechowywania (3:10), pocztę zgłoszeń (co minutę, gdy skonfigurowana), statusy reklam (0:20), alerty licencji (7:40), sprzątanie wysyłek.
7. Pierwsze logowanie — konfiguracja 2FA. Potem: Ustawienia → Regulamin współpracy, Archiwum → stawka godzinowa.

### Homelab (kopie i paczki)

```bash
curl -H "Authorization: Bearer $TOKEN" https://panel.agencja.pl/backup/latest -o db.jsonl.gz   # zrzut bazy
curl -H "Authorization: Bearer $TOKEN" https://panel.agencja.pl/backup/exports                   # lista paczek (JSON)
curl -H "Authorization: Bearer $TOKEN" https://panel.agencja.pl/backup/exports/NAZWA.zip -O      # paczka
```
Pliki z `storage/app/store` najlepiej synchronizować przez SFTP/rsync. Paczki offline muszą mieć kopię w homelabie —
z hostingu znikają z nich ciężkie pliki.

### Integracje z platformami (Meta, TikTok) — włączane

Administracja → Integracje: osobny włącznik dla Mety (Facebook + Instagram) i TikToka oraz dla pobierania statystyk.
Wyłączone = wszystko działa ręcznie, jak dotąd. Po włączeniu i wpisaniu kluczy aplikacji konto łączy się w Social media
→ Profile („Połącz z API”); przy publikacji pojawia się „Publikuj automatycznie o tej godzinie”. Cron co minutę publikuje
zaplanowane posty: Facebook (tekst, zdjęcie, kilka zdjęć, film), Instagram (zdjęcie, karuzela, rolka — media pobierane
przez Metę z podpisanego linku ważnego 2 h), TikTok (film wysyłany w kawałkach z serwera). Publikacja jest wstrzymywana,
gdy licencja materiału nie pozwala, post czeka na akceptację klienta albo konto jest odłączone; błędy platformy —
3 próby, potem e-mail do zespołu; ponad dobę po terminie panel nie publikuje sam. Statystyki 1/7/30 dni pobierają się
co godzinę zamiast ręcznego wpisywania. Tokeny są szyfrowane w bazie. Wymagane: weryfikacja aplikacji (App Review)
u Mety oraz audyt Content Posting API u TikToka (przed audytem posty TikTok tylko prywatne).

### Zgłoszenia przez e-mail

Klient (i zespół) może po prostu odpowiedzieć na powiadomienie — odpowiedź dopisuje się do zlecenia razem
z załącznikami, bez cytatu i podpisu. Nowy e-mail od klienta na adres zgłoszeń zakłada zlecenie (klient dostaje
potwierdzenie z numerem). Przyjmowane są tylko wiadomości od aktywnych użytkowników panelu; odpowiedź musi mieć
podpisany znacznik zlecenia (adres `zgloszenia+t12-…@`, nagłówek wątku albo „Ref:” w stopce) lub `[#12]` w temacie
zlecenia własnej firmy. Pomijane: automatyczne odpowiedzi, zwroty, listy mailingowe, e-maile z nieudaną weryfikacją
SPF/DMARC; limit 30 wiadomości na godzinę na nadawcę. Dziennik: Administracja → System.

1. Załóż skrzynkę, np. `zgloszenia@twojadomena.pl` (najlepiej osobną).
2. W `.env` ustaw `AGENCY_INBOUND_ADDRESS` oraz dane IMAP (`AGENCY_IMAP_HOST`, `…_USER`, `…_PASSWORD`; zwykle port 993, `ssl`).
   Cron, który już działa, sprawdza skrzynkę co minutę. Przetworzone wiadomości są oznaczane jako przeczytane
   (albo przenoszone do folderu z `AGENCY_IMAP_DONE_FOLDER`).
3. Gdy skrzynka nie obsługuje aliasów z „+”, ustaw `AGENCY_INBOUND_PLUS=false` (znacznik pójdzie w wątku i stopce).
4. Zamiast IMAP można w panelu hostingu (cPanel → Przekierowania → „Przekaż do programu”) przekierować pocztę do:
   `php /ścieżka/do/panelu/artisan agency:mail-in` — wtedy zostaw puste `AGENCY_IMAP_HOST`.

### Wtyczka WordPress

Katalog `wordpress-plugin/ninimma-connect` (instrukcja w `readme.txt`). W panelu: Strony i SEO → strona →
Wtyczka WordPress → Wygeneruj sekret; w WordPressie: Ustawienia → Ninimma Connect.

### Tablice (Excalidraw)

Gotowa paczka jest w `public/vendor/excalidraw` — na hostingu nie trzeba Node. Odbudowa (tylko lokalnie):
`cd resources/excalidraw && npm ci && npm run build`.

## Praca lokalna

```bash
composer install
cp .env.example .env          # DB_CONNECTION=sqlite dla szybkiego startu
php artisan key:generate
php artisan migrate --seed --seeder=DemoSeeder   # dane demo; wypisuje sekret 2FA administratora
php artisan serve
php artisan test               # 42 testy
```
Konta demo: `admin@example.com` / `anna@example.com`, hasło `demo-haslo-123`.

## Świadomie odłożone (ze specyfikacji)

Edycja tablic w pełni na żywo (WebSocket), API YouTube / LinkedIn / Pinterest,
własne fakturowanie i integracja z KSeF, role podwykonawców, wiele agencji
w jednej instalacji. Architektura zostawia na nie miejsce (np. `public/js/live.js` — jeden moduł transportu).

## Otwarte decyzje

- [x] Wersja PHP na hostingu — hosting ma najnowsze PHP (wymagane 8.3+).
- [ ] Katalog subdomeny ustawiony na `public/`.
- [ ] Limity dla klienta (teraz 100 MB / 5 min nagrania — `.env`).
- [ ] Próg zajętości dla offline i domyślny okres przechowywania (Archiwum → Ustawienia).
- [ ] Treść regulaminu współpracy i zapisy RODO (konsultacja z prawnikiem).
- [ ] Pierwszy klient testowy.
