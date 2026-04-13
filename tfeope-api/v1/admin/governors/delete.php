<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    $admin = api_require_admin($db);

    $payload = api_request_data();
    $governorId = (int) ($payload['id'] ?? $payload['governor_id'] ?? 0);

    if ($governorId <= 0) {
        api_json([
            'success' => false,
            'message' => 'A valid governor ID is required.',
        ], 422);
    }

    $map = api_governor_field_map($db);
    $selectImage = api_has_column($db, 'governors', 'governor_image') ? ', governor_image' : ', NULL AS governor_image';
    $row = api_fetch_one(
        $db,
        'SELECT governor_id, ' . api_quote_identifier($map['governor']) . ' AS governor_name' . $selectImage . '
         FROM governors
         WHERE governor_id = :governor_id
         LIMIT 1',
        [':governor_id' => $governorId]
    );

    if ($row === null) {
        api_json([
            'success' => false,
            'message' => 'Governor not found.',
        ], 404);
    }

    api_execute($db, 'DELETE FROM governors WHERE governor_id = :governor_id', [':governor_id' => $governorId]);

    api_delete_uploaded_file('media', (string) ($row['governor_image'] ?? ''));
    api_log_admin_action($db, $admin, 'DELETE', 'Deleted governor "' . (string) ($row['governor_name'] ?? '') . '"');

    api_json([
        'message' => 'Governor deleted successfully.',
        'data' => [
            'deletedId' => $governorId,
        ],
    ]);
} catch (Throwable $error) {
    error_log('Admin governor delete API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to delete governor right now.',
    ], 500);
}
