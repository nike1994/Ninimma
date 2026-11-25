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
];
