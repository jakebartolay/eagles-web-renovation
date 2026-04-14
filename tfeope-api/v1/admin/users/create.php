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
            'message' => 'Only super admins can add users.',
        ], 403);
    }

    if (!api_table_exists($db, 'users')) {
        api_json([
            'ok' => false,
            'message' => 'Users table is not available.',
        ], 500);
    }

    $payload = api_request_data();
    $name = trim((string) ($payload['name'] ?? ''));
    $username = trim((string) ($payload['username'] ?? ''));
    $password = (string) ($payload['password'] ?? '');
    $roleId = (int) ($payload['role_id'] ?? $payload['roleId'] ?? 4);
    $eaglesId = trim((string) ($payload['eagles_id'] ?? $payload['eaglesId'] ?? ''));

    if ($name === '' || $username === '' || $password === '') {
        api_json([
            'ok' => false,
            'message' => 'Name, username, and password are required.',
        ], 422);
    }

    if ($roleId < 1 || $roleId > 4) {
        api_json([
            'ok' => false,
            'message' => 'A valid role is required.',
        ], 422);
    }

    $duplicate = api_fetch_one($db, '
        SELECT id
        FROM users
        WHERE username = :username
        LIMIT 1
    ', [':username' => $username]);

    if ($duplicate !== null) {
        api_json([
            'ok' => false,
            'message' => 'Username already exists.',
        ], 409);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    api_execute($db, '
        INSERT INTO users (
            name,
            username,
            password_hash,
            role_id,
            eagles_id
        ) VALUES (
            :name,
            :username,
            :password_hash,
            :role_id,
            :eagles_id
        )
    ', [
        ':name' => $name,
        ':username' => $username,
        ':password_hash' => $passwordHash,
        ':role_id' => $roleId,
        ':eagles_id' => $eaglesId !== '' ? $eaglesId : null,
    ]);

    $userId = (int) $db->lastInsertId();
    $row = api_fetch_one($db, '
        SELECT id, name, username, role_id, created_at, eagles_id
        FROM users
        WHERE id = :id
        LIMIT 1
    ', [':id' => $userId]);

    api_log_admin_action(
        $db,
        $admin,
        'CREATE',
        'Created user "' . $username . '"'
    );

    api_json([
        'ok' => true,
        'message' => 'User added successfully.',
        'data' => [
            'id' => (int) ($row['id'] ?? $userId),
            'name' => (string) ($row['name'] ?? $name),
            'username' => (string) ($row['username'] ?? $username),
            'roleId' => (int) ($row['role_id'] ?? $roleId),
            'roleLabel' => api_admin_role_label((int) ($row['role_id'] ?? $roleId)),
            'eaglesId' => (string) ($row['eagles_id'] ?? $eaglesId),
            'createdAt' => (string) ($row['created_at'] ?? ''),
        ],
    ], 201);
} catch (Throwable $error) {
    error_log('Admin user create API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to add user right now.',
    ], 500);
}
