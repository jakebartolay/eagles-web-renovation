<?php

declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

api_start();
api_require_method('GET');

try {
    $db = api_db();
    $admin = api_current_admin($db);

    if ($admin === null) {
        admin_api_forget_session();

        api_json([
            'authenticated' => false,
            'user' => null,
        ]);
    }

    api_json([
        'authenticated' => true,
        'user' => admin_api_user_payload($admin),
    ]);
} catch (Throwable $error) {
    error_log('Admin session API error: ' . $error->getMessage());

    api_json([
        'success' => false,
        'message' => 'Unable to validate the admin session right now.',
        'authenticated' => false,
        'user' => null,
    ], 500);
}
