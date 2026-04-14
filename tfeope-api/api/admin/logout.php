<?php

declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

api_start();
api_require_method(['POST', 'GET']);

try {
    $db = api_db();
    $admin = api_current_admin($db);

    if ($admin !== null) {
        api_log_admin_action($db, $admin, 'LOGOUT', 'Signed out from the React admin dashboard.');
    }

    admin_api_forget_session();

    api_json([
        'authenticated' => false,
        'message' => 'Signed out successfully.',
        'user' => null,
    ]);
} catch (Throwable $error) {
    error_log('Admin logout API error: ' . $error->getMessage());

    api_json([
        'success' => false,
        'message' => 'Unable to sign out right now.',
    ], 500);
}
