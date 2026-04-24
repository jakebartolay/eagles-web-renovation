<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('GET');

try {
    $db = api_db();
    api_require_admin($db);

    api_json([
        'data' => api_appointed_list($db),
    ]);
} catch (Throwable $error) {
    error_log('Admin appointed list API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to load appointed officers right now.',
    ], 500);
}

