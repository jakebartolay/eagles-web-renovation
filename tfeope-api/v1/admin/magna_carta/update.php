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
    $id = (int) ($payload['id'] ?? 0);

    if ($id <= 0) {
        api_json([
            'ok' => false,
            'message' => 'A valid Magna Carta ID is required.',
        ], 422);
    }

    $current = api_fetch_one($db, '
        SELECT id, title, subtitle, description, image_path, is_active
        FROM magna_carta_items
        WHERE id = :id
        LIMIT 1
    ', [':id' => $id]);

    if ($current === null) {
        api_json([
            'ok' => false,
            'message' => 'Magna Carta item not found.',
        ], 404);
    }

    $title = trim((string) ($payload['title'] ?? $current['title'] ?? ''));
    $subtitle = trim((string) ($payload['subtitle'] ?? $current['subtitle'] ?? ''));
    $description = trim((string) ($payload['description'] ?? $payload['content'] ?? $current['description'] ?? ''));
    $isActive = admin_magna_status_to_active($payload['status'] ?? $payload['is_active'] ?? $current['is_active'] ?? 1);

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

    $nextImage = $storedImage['filename'] ?? trim((string) ($current['image_path'] ?? ''));

    try {
        api_execute($db, '
            UPDATE magna_carta_items
            SET title = :title,
                subtitle = :subtitle,
                description = :description,
                image_path = :image_path,
                is_active = :is_active
            WHERE id = :id
        ', [
            ':id' => $id,
            ':title' => $title,
            ':subtitle' => $subtitle !== '' ? $subtitle : null,
            ':description' => $description,
            ':image_path' => $nextImage !== '' ? $nextImage : null,
            ':is_active' => $isActive,
        ]);
    } catch (Throwable $error) {
        api_delete_uploaded_file('magna_carta', $storedImage['filename'] ?? null);
        throw $error;
    }

    if ($storedImage !== null) {
        $oldImage = trim((string) ($current['image_path'] ?? ''));
        if ($oldImage !== '' && $oldImage !== $storedImage['filename']) {
            api_delete_uploaded_file('magna_carta', $oldImage);
        }
    }

    api_log_admin_action($db, $admin, 'UPDATE', 'Updated Magna Carta "' . $title . '"');

    $item = null;
    foreach (api_magna_carta_list($db, false) as $row) {
        if ((int) ($row['id'] ?? 0) === $id) {
            $item = $row;
            break;
        }
    }

    api_json([
        'ok' => true,
        'message' => 'Magna Carta item updated successfully.',
        'data' => $item,
    ]);
} catch (Throwable $error) {
    error_log('Admin magna carta update API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to update Magna Carta item right now.',
    ], 500);
}

