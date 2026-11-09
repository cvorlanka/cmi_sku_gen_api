<?php

return [
    // Database connection (from config/database.php) used for SKU generation.
    'connection' => env('SKUGEN_DB_CONNECTION', 'sqlsrv'),

    // Max seconds a single /skugen run may hold the lock.
    'lock_seconds' => (int) env('SKUGEN_LOCK_SECONDS', 600),

    // Seconds a request waits for a running job before returning 409.
    'lock_wait_seconds' => (int) env('SKUGEN_LOCK_WAIT_SECONDS', 120),
];
