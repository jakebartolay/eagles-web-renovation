<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    $admin = api_require_admin($db);

    if (!api_table_exists($db, 'governors')) {
        api_json([
            'success' => false,
            'message' => 'Governors table is not available.',
        ], 500);
    }

    $map = api_governor_field_map($db);
    $payload = api_request_data();
    $name = trim((string) ($payload['name'] ?? $payload['governor_name'] ?? ''));

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

    $fields = [$map['governor']];
    $placeholders = [':name'];
    $params = [':name' => $name];

    if (api_has_column($db, 'governors', 'governor_image')) {
        $fields[] = 'governor_image';
        $placeholders[] = ':governor_image';
        $params[':governor_image'] = $storedImage['filename'] ?? null;
    }

    try {
        api_execute(
            $db,
            'INSERT INTO governors (' . implode(', ', array_map('api_quote_identifier', $fields)) . ')
             VALUES (' . implode(', ', $placeholders) . ')',
            $params
        );

        $governorId = (int) $db->lastInsertId();

        api_log_admin_action($db, $admin, 'CREATE', 'Created governor "' . $name . '"');

        api_json([
            'message' => 'Governor created successfully.',
            'data' => api_governor_by_id($db, $governorId),
        ], 201);
    } catch (Throwable $error) {
        api_delete_uploaded_file('media', $storedImage['filename'] ?? null);
        throw $error;
    }
} catch (Throwable $error) {
    error_log('Admin governor create API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to create governor right now.',
    ], 500);
}
