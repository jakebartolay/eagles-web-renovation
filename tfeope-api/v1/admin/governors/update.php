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

    $existing = api_fetch_one(
        $db,
        'SELECT governor_id, ' . api_quote_identifier($map['governor']) . ' AS governor_name' . $selectImage . '
         FROM governors
         WHERE governor_id = :governor_id
         LIMIT 1',
        [':governor_id' => $governorId]
    );

    if ($existing === null) {
        api_json([
            'success' => false,
            'message' => 'Governor not found.',
        ], 404);
    }

    $name = trim((string) ($payload['name'] ?? $payload['governor_name'] ?? $existing['governor_name'] ?? ''));
    if ($name === '') {
        api_json([
            'success' => false,
            'message' => 'Governor name is required.',
        ], 422);
    }

    $imageUpload = $_FILES['image'] ?? $_FILES['governor_image'] ?? null;
    $storedImage = null;

    if (is_array($imageUpload) && (int) ($imageUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $storedImage = api_store_uploaded_file($imageUpload, 'media', api_image_extensions());
    }

    $updates = [api_quote_identifier($map['governor']) . ' = :name'];
    $params = [
        ':name' => $name,
        ':governor_id' => $governorId,
    ];

    if ($storedImage !== null && api_has_column($db, 'governors', 'governor_image')) {
        $updates[] = '`governor_image` = :governor_image';
        $params[':governor_image'] = $storedImage['filename'];
    }

    try {
        api_execute(
            $db,
            'UPDATE governors SET ' . implode(', ', $updates) . ' WHERE governor_id = :governor_id',
            $params
        );

        if ($storedImage !== null) {
            api_delete_uploaded_file('media', (string) ($existing['governor_image'] ?? ''));
        }

        api_log_admin_action($db, $admin, 'UPDATE', 'Updated governor "' . $name . '"');

        api_json([
            'message' => 'Governor updated successfully.',
            'data' => api_governor_by_id($db, $governorId),
        ]);
    } catch (Throwable $error) {
        api_delete_uploaded_file('media', $storedImage['filename'] ?? null);
        throw $error;
    }
} catch (Throwable $error) {
    error_log('Admin governor update API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to update governor right now.',
    ], 500);
}
