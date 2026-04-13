<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    $admin = api_require_admin($db);

    $payload = api_request_data();
    $officerId = (int) ($payload['id'] ?? $payload['officer_id'] ?? 0);

    if ($officerId <= 0) {
        api_json([
            'success' => false,
            'message' => 'A valid officer ID is required.',
        ], 422);
    }

    $row = api_fetch_one($db, '
        SELECT id, name, image, speech_image
        FROM officers
        WHERE id = :id
        LIMIT 1
    ', [':id' => $officerId]);

    if ($row === null) {
        api_json([
            'success' => false,
            'message' => 'Officer not found.',
        ], 404);
    }

    api_execute($db, 'DELETE FROM officers WHERE id = :id', [':id' => $officerId]);

    api_delete_uploaded_file('media', (string) ($row['image'] ?? ''));
    api_delete_uploaded_file('media', (string) ($row['speech_image'] ?? ''));
    api_log_admin_action($db, $admin, 'DELETE', 'Deleted officer "' . (string) ($row['name'] ?? '') . '"');

    api_json([
        'message' => 'Officer deleted successfully.',
        'data' => [
            'deletedId' => $officerId,
        ],
    ]);
} catch (Throwable $error) {
    error_log('Admin officer delete API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to delete officer right now.',
    ], 500);
}
