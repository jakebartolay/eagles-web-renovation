<?php

declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    $payload = api_request_data();

    $username = trim((string) ($payload['username'] ?? ''));
    $password = (string) ($payload['password'] ?? '');

    if ($username === '' || $password === '') {
        api_error('Please enter your admin username and password.');
    }

    if (!api_table_exists($db, 'users')) {
        api_error('Admin users table not found.', 500);
    }

    $user = api_fetch_one($db, '
        SELECT id, name, username, password_hash, role_id
        FROM users
        WHERE username = :username
        LIMIT 1
    ', [':username' => $username]);

    $roleId = (int) ($user['role_id'] ?? 0);
    $isValidRole = in_array($roleId, [1, 2], true);
    $passwordHash = (string) ($user['password_hash'] ?? '');

    if (
        $user === null
        || !$isValidRole
        || $passwordHash === ''
        || !password_verify($password, $passwordHash)
    ) {
        api_error('Invalid admin credentials.', 401);
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) ($user['id'] ?? 0);
    $_SESSION['user_name'] = (string) ($user['name'] ?? '');
    $_SESSION['username'] = (string) ($user['username'] ?? '');
    $_SESSION['role_id'] = $roleId;

    $admin = api_current_admin($db);
    if ($admin === null) {
        api_error('Admin session could not be created.', 500);
    }

    api_log_admin_action($db, $admin, 'LOGIN', 'Signed in to the React admin dashboard.');

    api_json([
        'authenticated' => true,
        'message' => 'Signed in successfully.',
        'user' => admin_api_user_payload($admin),
    ]);
} catch (Throwable $error) {
    error_log('Admin login API error: ' . $error->getMessage());

    api_json([
        'success' => false,
        'message' => 'Unable to sign in right now.',
    ], 500);
}
