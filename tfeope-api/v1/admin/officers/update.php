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

    $existing = api_fetch_one($db, '
        SELECT id, name, position, full_position, category, speech, image, speech_image
        FROM officers
        WHERE id = :id
        LIMIT 1
    ', [':id' => $officerId]);

    if ($existing === null) {
        api_json([
            'success' => false,
            'message' => 'Officer not found.',
        ], 404);
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

    $fieldValues = [
        'name' => trim((string) ($payload['name'] ?? $existing['name'] ?? '')),
        'position' => trim((string) ($payload['position'] ?? $existing['position'] ?? '')),
        'full_position' => trim((string) ($payload['full_position'] ?? $payload['fullPosition'] ?? $existing['full_position'] ?? $payload['position'] ?? $existing['position'] ?? '')),
        'category' => trim((string) ($payload['category'] ?? $existing['category'] ?? '')) ?: 'general',
        'speech' => trim((string) ($payload['speech'] ?? $existing['speech'] ?? '')),
    ];

    if ($fieldValues['name'] === '' || $fieldValues['position'] === '') {
        api_json([
            'success' => false,
            'message' => 'Officer name and position are required.',
        ], 422);
    }

    $updates = [];
    $params = [':id' => $officerId];

    foreach ($fieldValues as $column => $value) {
        if (!api_has_column($db, 'officers', $column)) {
            continue;
        }

        $updates[] = api_quote_identifier($column) . ' = :' . $column;
        $params[':' . $column] = $value;
    }

    if ($storedImage !== null && api_has_column($db, 'officers', 'image')) {
        $updates[] = '`image` = :image';
        $params[':image'] = $storedImage['filename'];
    }

    if ($storedSpeechImage !== null && api_has_column($db, 'officers', 'speech_image')) {
        $updates[] = '`speech_image` = :speech_image';
        $params[':speech_image'] = $storedSpeechImage['filename'];
    }

    if (api_has_column($db, 'officers', 'updated_at')) {
        $updates[] = '`updated_at` = CURRENT_TIMESTAMP';
    }

    if ($updates === []) {
        api_json([
            'success' => false,
            'message' => 'No officer changes were provided.',
        ], 422);
    }

    try {
        api_execute(
            $db,
            'UPDATE officers SET ' . implode(', ', $updates) . ' WHERE id = :id',
            $params
        );

        if ($storedImage !== null) {
            api_delete_uploaded_file('media', (string) ($existing['image'] ?? ''));
        }

        if ($storedSpeechImage !== null) {
            api_delete_uploaded_file('media', (string) ($existing['speech_image'] ?? ''));
        }

        api_log_admin_action($db, $admin, 'UPDATE', 'Updated officer "' . $fieldValues['name'] . '"');

        api_json([
            'message' => 'Officer updated successfully.',
            'data' => api_officer_by_id($db, $officerId),
        ]);
    } catch (Throwable $error) {
        api_delete_uploaded_file('media', $storedImage['filename'] ?? null);
        api_delete_uploaded_file('media', $storedSpeechImage['filename'] ?? null);
        throw $error;
    }
} catch (Throwable $error) {
    error_log('Admin officer update API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to update officer right now.',
    ], 500);
}
