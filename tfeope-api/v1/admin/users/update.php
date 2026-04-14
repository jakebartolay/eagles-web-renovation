<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

function api_admin_role_label(int $roleId): string
{
    return match ($roleId) {
        1 => 'Super Admin',
        2 => 'Admin',
        3 => 'Maintenance',
        default => 'User',
    };
}

try {
    $db = api_db();
    $admin = api_require_admin($db);

    if ((int) ($admin['role_id'] ?? 0) !== 1) {
        api_json([
            'ok' => false,
            'message' => 'Only super admins can edit users.',
        ], 403);
    }

    $payload = api_request_data();
    $userId = (int) ($payload['id'] ?? $payload['user_id'] ?? 0);

    if ($userId <= 0) {
        api_json([
            'ok' => false,
            'message' => 'A valid user ID is required.',
        ], 422);
    }

    $current = api_fetch_one($db, '
        SELECT id, name, username, role_id, created_at, eagles_id
        FROM users
        WHERE id = :id
        LIMIT 1
    ', [':id' => $userId]);

    if ($current === null) {
        api_json([
            'ok' => false,
            'message' => 'User not found.',
        ], 404);
    }

    $name = trim((string) ($payload['name'] ?? $current['name'] ?? ''));
    $username = trim((string) ($payload['username'] ?? $current['username'] ?? ''));
    $password = (string) ($payload['password'] ?? '');
    $roleId = (int) ($payload['role_id'] ?? $payload['roleId'] ?? $current['role_id'] ?? 4);
    $eaglesId = trim((string) ($payload['eagles_id'] ?? $payload['eaglesId'] ?? $current['eagles_id'] ?? ''));

    if ($name === '' || $username === '') {
        api_json([
            'ok' => false,
            'message' => 'Name and username are required.',
        ], 422);
    }

    if ($roleId < 1 || $roleId > 4) {
        api_json([
            'ok' => false,
            'message' => 'A valid role is required.',
        ], 422);
    }

    if ($userId === (int) ($admin['id'] ?? 0)) {
        $roleId = (int) ($current['role_id'] ?? $roleId);
    }

    $duplicate = api_fetch_one($db, '
        SELECT id
        FROM users
        WHERE username = :username AND id <> :id
        LIMIT 1
    ', [
        ':username' => $username,
        ':id' => $userId,
    ]);

    if ($duplicate !== null) {
        api_json([
            'ok' => false,
            'message' => 'Username already exists.',
        ], 409);
    }

    $params = [
        ':id' => $userId,
        ':name' => $name,
        ':username' => $username,
        ':role_id' => $roleId,
        ':eagles_id' => $eaglesId !== '' ? $eaglesId : null,
    ];

    $set = [
        'name = :name',
        'username = :username',
        'role_id = :role_id',
        'eagles_id = :eagles_id',
    ];

    if ($password !== '') {
        $set[] = 'password_hash = :password_hash';
        $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }

    api_execute(
        $db,
        'UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id',
        $params
    );

    $row = api_fetch_one($db, '
        SELECT id, name, username, role_id, created_at, eagles_id
        FROM users
        WHERE id = :id
        LIMIT 1
    ', [':id' => $userId]);

    api_log_admin_action(
        $db,
        $admin,
        'UPDATE',
        'Updated user "' . $username . '"'
    );

    api_json([
        'ok' => true,
        'message' => 'User updated successfully.',
        'data' => [
            'id' => (int) ($row['id'] ?? $userId),
            'name' => (string) ($row['name'] ?? $name),
            'username' => (string) ($row['username'] ?? $username),
            'roleId' => (int) ($row['role_id'] ?? $roleId),
            'roleLabel' => api_admin_role_label((int) ($row['role_id'] ?? $roleId)),
            'eaglesId' => (string) ($row['eagles_id'] ?? $eaglesId),
            'createdAt' => (string) ($row['created_at'] ?? ''),
        ],
    ]);
} catch (Throwable $error) {
    error_log('Admin user update API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to update user right now.',
    ], 500);
}
