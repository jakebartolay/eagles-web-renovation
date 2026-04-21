<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

if (!function_exists('admin_magna_status_to_active')) {
    function admin_magna_status_to_active(mixed $status): int
    {
        return strtolower(trim((string) $status)) === 'published' ? 1 : 0;
    }
}

try {
    $db = api_db();
    $admin = api_require_admin($db);

    if (!api_table_exists($db, 'magna_carta_items')) {
        api_json([
            'ok' => false,
            'message' => 'Magna Carta table is not available.',
        ], 500);
    }

    $payload = api_request_data();
    $title = trim((string) ($payload['title'] ?? ''));
    $subtitle = trim((string) ($payload['subtitle'] ?? ''));
    $description = trim((string) ($payload['description'] ?? $payload['content'] ?? ''));
    $isActive = admin_magna_status_to_active($payload['status'] ?? $payload['is_active'] ?? 'Draft');

    if ($title === '' || $description === '') {
        api_json([
            'ok' => false,
            'message' => 'Title and description are required.',
        ], 422);
    }

    $imageUpload = $_FILES['image'] ?? null;
    $storedImage = null;

    if (is_array($imageUpload) && (int) ($imageUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $storedImage = api_store_uploaded_file_as(
            $imageUpload,
            'magna_carta',
            'magna_carta_' . date('Ymd'),
            api_image_extensions()
        );
    }

    api_execute($db, '
        INSERT INTO magna_carta_items (
            title,
            subtitle,
            description,
            image_path,
            is_active
        ) VALUES (
            :title,
            :subtitle,
            :description,
            :image_path,
            :is_active
        )
    ', [
        ':title' => $title,
        ':subtitle' => $subtitle !== '' ? $subtitle : null,
        ':description' => $description,
        ':image_path' => $storedImage['filename'] ?? null,
        ':is_active' => $isActive,
    ]);

    $newId = (int) $db->lastInsertId();
    api_log_admin_action($db, $admin, 'CREATE', 'Created Magna Carta "' . $title . '"');

    $item = null;
    foreach (api_magna_carta_list($db, false) as $row) {
        if ((int) ($row['id'] ?? 0) === $newId) {
            $item = $row;
            break;
        }
    }

    api_json([
        'ok' => true,
        'message' => 'Magna Carta item created successfully.',
        'data' => $item,
    ], 201);
} catch (Throwable $error) {
    error_log('Admin magna carta create API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to create Magna Carta item right now.',
    ], 500);
}

