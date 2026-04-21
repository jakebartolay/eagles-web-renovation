<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('GET');

try {
    $db = api_db();
    api_require_admin($db);

    api_json([
        'ok' => true,
        'data' => api_magna_carta_list($db, false),
    ]);
} catch (Throwable $error) {
    error_log('Admin magna carta list API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to load Magna Carta items right now.',
    ], 500);
}

