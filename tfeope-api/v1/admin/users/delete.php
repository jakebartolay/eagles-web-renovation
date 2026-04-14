<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method(['POST', 'DELETE']);

try {
    $db = api_db();
    $admin = api_require_admin($db);

    if ((int) ($admin['role_id'] ?? 0) !== 1) {
        api_json([
            'ok' => false,
            'message' => 'Only super admins can delete users.',
        ], 403);
    }

    $payload = api_request_data();
    $userId = (int) ($payload['id'] ?? $payload['user_id'] ?? $_GET['id'] ?? 0);

    if ($userId <= 0) {
        api_json([
            'ok' => false,
            'message' => 'A valid user ID is required.',
        ], 422);
    }

    if ($userId === (int) ($admin['id'] ?? 0)) {
        api_json([
            'ok' => false,
            'message' => 'You cannot delete the current signed-in user.',
        ], 422);
    }

    $current = api_fetch_one($db, '
        SELECT id, username
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

    api_execute($db, '
        DELETE FROM users
        WHERE id = :id
    ', [':id' => $userId]);

    api_log_admin_action(
        $db,
        $admin,
        'DELETE',
        'Deleted user "' . (string) ($current['username'] ?? '') . '"'
    );

    api_json([
        'ok' => true,
        'message' => 'User deleted successfully.',
        'data' => [
            'deletedId' => $userId,
        ],
    ]);
} catch (Throwable $error) {
    error_log('Admin user delete API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to delete user right now.',
    ], 500);
}
