<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method(['GET', 'POST']);

try {
    $db = api_db();
    api_require_admin($db);

    if (!api_table_exists($db, 'news_info')) {
        api_json([
            'ok' => true,
            'data' => [],
        ]);
    }

    api_json([
        'ok' => true,
        'data' => api_news_list($db, false),
    ]);
} catch (Throwable $error) {
    error_log('Admin news list API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to load admin news right now.',
    ], 500);
}
