<?php

return [
    /*
    |--------------------------------------------------------------------------
    | EMIS Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Pengaturan sinkronisasi batch data kesiswaan dari ekstensi Chrome EMIS.
    |
    */

    'chunk_size' => (int) env('EMIS_SYNC_CHUNK_SIZE', 50),

    'store_raw_payload' => (bool) env('EMIS_STORE_RAW_PAYLOAD', false),

    'nik_hash_key' => env('EMIS_NIK_HASH_KEY', 'sis-emis-secret-hash-key-2026'),
];
