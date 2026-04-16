<?php

declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

api_start();
api_require_method(['POST', 'GET']);

try {
    try {
        $db = api_db();
        $admin = api_current_admin($db);

        if ($admin !== null) {
            api_log_admin_action($db, $admin, 'LOGOUT', 'Signed out from the React admin dashboard.');
        }
    } catch (Throwable $error) {
        error_log('Admin logout activity log skipped: ' . $error->getMessage());
    }

    admin_api_forget_session();

    api_json([
        'authenticated' => false,
        'message' => 'Signed out successfully.',
        'user' => null,
    ]);
} catch (Throwable $error) {
    api_handle_exception($error, 'Admin logout API error', 'Unable to sign out right now.');
}
