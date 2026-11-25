# Ninimma — notatki dla asystenta

- Specyfikacja produktu: `docs/specyfikacja.pdf`; stan etapów w `README.md`.
- Laravel 13, PHP 8.3, MySQL na hostingu współdzielonym: bez Node w produkcji, bez Redisa, bez stałych procesów. Kolejka w bazie obsługiwana przez cron (`routes/console.php`).
- Frontend: Blade + czysty CSS/JS w `public/` (bez kroku budowania).
- Kod i nazwy w bazie po angielsku, komentarze i UI po polsku; każdy tekst UI przez `lang/pl` i `lang/en`.
- Izolacja klientów: zawsze `Ticket::visibleTo($user)` / policies; klient nigdy nie widzi `is_internal`.
- Testy: `php artisan test` (sqlite w pamięci).
- Moduły: licencje materiałów (`LicenseGuard`), zlecenia (`TicketService`), tablice (`BoardService`, `public/js/board-page.js`, paczka Excalidraw w `resources/excalidraw`), wyceny (`QuoteService`), social (`SocialStats`), kampanie (`CampaignReport`), strony/SEO (`SiteService`), archiwum (`ArchiveService`).
- Wszystko jest zleceniem: zadania social, treści SEO, przeglądy stron i problemy z przeglądów to zlecenia (typ w `TicketType`).
- Pliki zawsze przez `FileStore` + `FileUsage` (morph map w `AppServiceProvider`), uprawnienia w `FileUsagePolicy`.
- Dane demo: `php artisan migrate:fresh --seed --seeder=DemoSeeder`.
