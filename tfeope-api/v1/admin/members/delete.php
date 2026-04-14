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
            'message' => 'Only super admins can delete members.',
        ], 403);
    }

    $payload = api_request_data();
    $memberId = trim((string) ($payload['id'] ?? $payload['eagles_id'] ?? $_GET['id'] ?? ''));

    if ($memberId === '') {
        api_json([
            'ok' => false,
            'message' => 'A valid member ID is required.',
        ], 422);
    }

    $current = api_fetch_one($db, '
        SELECT eagles_id, eagles_firstName, eagles_lastName, eagles_pic
        FROM user_info
        WHERE eagles_id = :eagles_id
        LIMIT 1
    ', [':eagles_id' => $memberId]);

    if ($current === null) {
        api_json([
            'ok' => false,
            'message' => 'Member not found.',
        ], 404);
    }

    api_execute($db, '
        DELETE FROM user_info
        WHERE eagles_id = :eagles_id
    ', [':eagles_id' => $memberId]);

    api_delete_uploaded_file('media', basename((string) ($current['eagles_pic'] ?? '')));

    api_log_admin_action(
        $db,
        $admin,
        'DELETE',
        'Deleted member "' . trim((string) (($current['eagles_firstName'] ?? '') . ' ' . ($current['eagles_lastName'] ?? ''))) . '" (' . $memberId . ')'
    );

    api_json([
        'ok' => true,
        'message' => 'Member deleted successfully.',
        'data' => [
            'deletedId' => $memberId,
        ],
    ]);
} catch (Throwable $error) {
    error_log('Admin member delete API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to delete member right now.',
    ], 500);
}
