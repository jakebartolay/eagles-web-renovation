<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    $admin = api_require_admin($db);

    $payload = api_request_data();
    $appointedId = (int) ($payload['id'] ?? $payload['appointed_id'] ?? 0);

    if ($appointedId <= 0) {
        api_json([
            'success' => false,
            'message' => 'A valid appointed officer ID is required.',
        ], 422);
    }

    $existing = api_appointed_by_id($db, $appointedId);
    if ($existing === null) {
        api_json([
            'success' => false,
            'message' => 'Appointed officer not found.',
        ], 404);
    }

    api_execute($db, 'DELETE FROM appointed WHERE id = :id', [':id' => $appointedId]);
    api_log_admin_action($db, $admin, 'DELETE', 'Deleted appointed officer "' . (string) ($existing['name'] ?? '') . '"');

    api_json([
        'message' => 'Appointed officer deleted successfully.',
        'data' => [
            'deletedId' => $appointedId,
        ],
    ]);
} catch (Throwable $error) {
    error_log('Admin appointed delete API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to delete appointed officer right now.',
    ], 500);
}

