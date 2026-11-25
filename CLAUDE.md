# Ninimma — notatki dla asystenta

- Specyfikacja produktu: `docs/specyfikacja.pdf`; stan etapów w `README.md`.
- Laravel 13, PHP 8.3, MySQL na hostingu współdzielonym: bez Node w produkcji, bez Redisa, bez stałych procesów. Kolejka w bazie obsługiwana przez cron (`routes/console.php`).
- Frontend: Blade + czysty CSS/JS w `public/` (bez kroku budowania).
- Kod i nazwy w bazie po angielsku, komentarze i UI po polsku; każdy tekst UI przez `lang/pl` i `lang/en`.
- Izolacja klientów: zawsze `Ticket::visibleTo($user)` / policies; klient nigdy nie widzi `is_internal`.
- Testy: `php artisan test` (sqlite w pamięci).
