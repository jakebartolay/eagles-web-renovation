<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

api_start();
api_require_method('GET');

try {
    $db = api_db();
    $user = api_current_admin($db);

    if ($user === null) {
        api_json([
            'ok' => true,
            'authenticated' => false,
            'user' => null,
        ]);
    }

    api_json([
        'ok' => true,
        'authenticated' => true,
        'user' => [
            'id' => (int) ($user['id'] ?? 0),
            'name' => (string) ($user['name'] ?? $user['username'] ?? ''),
            'username' => (string) ($user['username'] ?? ''),
            'roleId' => (int) ($user['role_id'] ?? 0),
            'roleLabel' => (string) ($user['role_label'] ?? ''),
        ],
    ]);
} catch (Throwable $error) {
    error_log('Admin session API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to verify admin session right now.',
    ], 500);
}
