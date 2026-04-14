<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('GET');

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
            'message' => 'Only super admins can manage users.',
        ], 403);
    }

    if (!api_table_exists($db, 'users')) {
        api_json([
            'ok' => true,
            'data' => [],
        ]);
    }

    $rows = api_fetch_all($db, '
        SELECT id, name, username, role_id, created_at, eagles_id
        FROM users
        ORDER BY created_at DESC, id DESC
    ');

    $items = array_map(static function (array $row): array {
        $roleId = (int) ($row['role_id'] ?? 0);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'username' => (string) ($row['username'] ?? ''),
            'roleId' => $roleId,
            'roleLabel' => api_admin_role_label($roleId),
            'eaglesId' => (string) ($row['eagles_id'] ?? ''),
            'createdAt' => (string) ($row['created_at'] ?? ''),
        ];
    }, $rows);

    api_json([
        'ok' => true,
        'data' => $items,
    ]);
} catch (Throwable $error) {
    error_log('Admin users list API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to load users right now.',
    ], 500);
}
