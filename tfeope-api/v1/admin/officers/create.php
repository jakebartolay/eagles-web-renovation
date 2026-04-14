<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    $admin = api_require_admin($db);

    if (!api_table_exists($db, 'officers')) {
        api_json([
            'success' => false,
            'message' => 'Officers table is not available.',
        ], 500);
    }

    $payload = api_request_data();
    $name = trim((string) ($payload['name'] ?? ''));
    $position = trim((string) ($payload['position'] ?? ''));
    $fullPosition = trim((string) ($payload['full_position'] ?? $payload['fullPosition'] ?? $position));
    $category = trim((string) ($payload['category'] ?? '')) ?: 'general';
    $speech = trim((string) ($payload['speech'] ?? ''));

    if ($name === '' || $position === '') {
        api_json([
            'success' => false,
            'message' => 'Officer name and position are required.',
        ], 422);
    }

    $imageUpload = $_FILES['image'] ?? null;
    $speechImageUpload = $_FILES['speech_image'] ?? $_FILES['speechImage'] ?? null;
    $storedImage = null;
    $storedSpeechImage = null;

    if (is_array($imageUpload) && (int) ($imageUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $storedImage = api_store_uploaded_file($imageUpload, 'media', api_image_extensions());
    }

    if (is_array($speechImageUpload) && (int) ($speechImageUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $storedSpeechImage = api_store_uploaded_file($speechImageUpload, 'media', api_image_extensions());
    }

    $fields = ['name', 'position'];
    $placeholders = [':name', ':position'];
    $params = [
        ':name' => $name,
        ':position' => $position,
    ];

    foreach ([
        'full_position' => $fullPosition,
        'category' => $category,
        'speech' => $speech,
        'image' => $storedImage['filename'] ?? null,
        'speech_image' => $storedSpeechImage['filename'] ?? null,
    ] as $column => $value) {
        if (!api_has_column($db, 'officers', $column)) {
            continue;
        }

        $fields[] = $column;
        $placeholders[] = ':' . $column;
        $params[':' . $column] = $value;
    }

    try {
        api_execute(
            $db,
            'INSERT INTO officers (' . implode(', ', array_map('api_quote_identifier', $fields)) . ')
             VALUES (' . implode(', ', $placeholders) . ')',
            $params
        );

        $officerId = (int) $db->lastInsertId();

        api_log_admin_action($db, $admin, 'CREATE', 'Created officer "' . $name . '"');

        api_json([
            'message' => 'Officer created successfully.',
            'data' => api_officer_by_id($db, $officerId),
        ], 201);
    } catch (Throwable $error) {
        api_delete_uploaded_file('media', $storedImage['filename'] ?? null);
        api_delete_uploaded_file('media', $storedSpeechImage['filename'] ?? null);
        throw $error;
    }
} catch (Throwable $error) {
    error_log('Admin officer create API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to create officer right now.',
    ], 500);
}
