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
        'data' => admin_api_dashboard_data($db, $admin),
    ]);
} catch (Throwable $error) {
    api_handle_exception(
        $error,
        'Admin dashboard API error',
        'Unable to load the admin dashboard right now.'
    );
}
