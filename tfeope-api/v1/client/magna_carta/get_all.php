<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('GET');

try {
    $db = api_db();

    api_json([
        'data' => api_magna_carta_list($db),
    ]);
} catch (Throwable $error) {
    error_log('Client magna carta API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to load magna carta items right now.',
    ], 500);
}
