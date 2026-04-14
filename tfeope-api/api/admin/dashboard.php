<?php

declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

api_start();
api_require_method('GET');

try {
    $db = api_db();
    $admin = api_require_admin($db);

    api_json([
        'authenticated' => true,
        'user' => admin_api_user_payload($admin),
        'data' => admin_api_dashboard_data($db),
    ]);
} catch (Throwable $error) {
    error_log('Admin dashboard API error: ' . $error->getMessage());

    api_json([
        'success' => false,
        'message' => 'Unable to load the admin dashboard right now.',
    ], 500);
}
