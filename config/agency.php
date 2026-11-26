<?php

return [
    // Limity wysyłki dla klientów (administrator nie ma limitów).
    'client_max_file_mb' => (int) env('AGENCY_CLIENT_MAX_FILE_MB', 100),
    'client_max_recording_seconds' => (int) env('AGENCY_CLIENT_MAX_RECORDING_SECONDS', 300),
    'chunk_size_kb' => (int) env('AGENCY_CHUNK_SIZE_KB', 1024),

    // Sygnał zastoju: przypomnienie po 1 dniu, kolejne po 7 dniach.
    'stall_first_reminder_hours' => 24,
    'stall_second_reminder_days' => 7,

    // Odpytywanie serwera (ms): aktywna praca / bezczynność.
    'poll_active_ms' => 3000,
    'poll_idle_ms' => 15000,

    'invitation_valid_days' => 7,

    'locales' => ['pl', 'en'],

    // Token dla homelabu pobierającego kopie zapasowe (puste = wyłączone).
    'backup_token' => env('AGENCY_BACKUP_TOKEN'),
    'backup_keep' => (int) env('AGENCY_BACKUP_KEEP', 7),

    // Zgłoszenia i odpowiedzi przez e-mail. Adres skrzynki (Reply-To w powiadomieniach); puste = wyłączone.
    'inbound' => [
        'address' => env('AGENCY_INBOUND_ADDRESS'),
        // Adres z plusem (zgloszenia+t12-abc@…), gdy skrzynka obsługuje aliasy z „+”.
        'plus_addressing' => (bool) env('AGENCY_INBOUND_PLUS', true),
        // Odbiór: „imap” (cron co minutę) albo „pipe” (przekierowanie poczty do programu na hostingu).
        'imap_host' => env('AGENCY_IMAP_HOST'),
        'imap_port' => (int) env('AGENCY_IMAP_PORT', 993),
        'imap_encryption' => env('AGENCY_IMAP_ENCRYPTION', 'ssl'), // ssl | tls
        'imap_user' => env('AGENCY_IMAP_USER'),
        'imap_password' => env('AGENCY_IMAP_PASSWORD'),
        'imap_folder' => env('AGENCY_IMAP_FOLDER', 'INBOX'),
        'imap_done_folder' => env('AGENCY_IMAP_DONE_FOLDER'), // np. „Ninimma” — przetworzone; puste = tylko oznacz jako przeczytane
        'max_per_hour' => (int) env('AGENCY_INBOUND_MAX_PER_HOUR', 30), // na nadawcę
        'allow_new_tickets' => (bool) env('AGENCY_INBOUND_NEW_TICKETS', true),
    ],
];
