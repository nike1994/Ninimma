=== Ninimma Connect ===
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT

Łączy stronę klienta z panelem agencji Ninimma.

== Funkcje ==
* Wspólne logowanie: zalogowany w WordPressie redaktor/administrator wchodzi do panelu bez hasła
  (link podpisany HMAC-SHA256, ważny 5 minut, jednorazowy). Konto klienta w panelu musi mieć ten sam e-mail.
* Przycisk „Zgłoś problem” w pasku administratora i shortcode [ninimma_zglos] — otwiera nowe zgłoszenie
  z adresem strony, na której był użytkownik.

== Instalacja ==
1. Wgraj katalog ninimma-connect do wp-content/plugins i włącz wtyczkę.
2. W panelu agencji: Strony i SEO → strona → Wtyczka WordPress → Wygeneruj sekret.
3. W WordPressie: Ustawienia → Ninimma Connect — wpisz adres panelu i sekret.

Domena strony w panelu musi odpowiadać domenie WordPressa (bez www).
Uprawnienie do zgłaszania można zmienić filtrem: add_filter('ninimma_connect_capability', fn() => 'read');
