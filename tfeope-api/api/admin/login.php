<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

api_start();
api_require_method('POST');

try {
    $payload = api_request_data();
    $username = trim((string) ($payload['username'] ?? ''));
    $password = (string) ($payload['password'] ?? '');

    if ($username === '' || $password === '') {
        api_json([
            'ok' => false,
            'message' => 'Username and password are required.',
        ], 422);
    }

    $db = api_db();
    $user = api_fetch_one($db, '
        SELECT id, name, username, password_hash, role_id
        FROM users
        WHERE username = :username
        LIMIT 1
    ', [':username' => $username]);

    if (
        $user === null
        || !password_verify($password, (string) ($user['password_hash'] ?? ''))
    ) {
        api_json([
            'ok' => false,
            'message' => 'Invalid username or password.',
        ], 401);
    }

    $roleId = (int) ($user['role_id'] ?? 0);
    if (!in_array($roleId, [1, 2], true)) {
        api_json([
            'ok' => false,
            'message' => 'This account does not have admin access.',
        ], 403);
    }

    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = (string) ($user['username'] ?? '');
    $_SESSION['role_id'] = $roleId;
    $_SESSION['id'] = (int) ($user['id'] ?? 0);
    $_SESSION['user_id'] = (int) ($user['id'] ?? 0);

    api_json([
        'ok' => true,
        'message' => 'Login successful.',
        'user' => [
            'id' => (int) ($user['id'] ?? 0),
            'name' => (string) ($user['name'] ?? $user['username'] ?? ''),
            'username' => (string) ($user['username'] ?? ''),
            'roleId' => $roleId,
            'roleLabel' => $roleId === 1 ? 'Super Admin' : 'Admin',
        ],
    ]);
} catch (Throwable $error) {
    error_log('Admin login API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to login right now.',
    ], 500);
}
