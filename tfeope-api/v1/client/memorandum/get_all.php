<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('GET');

try {
    $db = api_db();

    api_json([
        'data' => api_memorandum_list($db, true),
    ]);
} catch (Throwable $error) {
    error_log('Client memorandum list API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to load memorandum right now.',
    ], 500);
}
