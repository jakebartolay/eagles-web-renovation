<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    $admin = api_require_admin($db);

    if (!api_table_exists($db, 'video_info')) {
        api_json([
            'success' => false,
            'message' => 'Video table is not available.',
        ], 500);
    }

    $payload = api_request_data();
    $title = trim((string) ($payload['title'] ?? $payload['video_title'] ?? ''));
    $description = trim((string) ($payload['description'] ?? $payload['video_description'] ?? ''));
    $status = api_normalize_publication_status($payload['status'] ?? $payload['video_status'] ?? 'Draft');

    if ($title === '') {
        api_json([
            'success' => false,
            'message' => 'Video title is required.',
        ], 422);
    }

    $videoUpload = $_FILES['video'] ?? $_FILES['video_file'] ?? null;
    if (!is_array($videoUpload) || (int) ($videoUpload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        api_json([
            'success' => false,
            'message' => 'A video file is required.',
        ], 422);
    }

    $thumbnailUpload = $_FILES['thumbnail'] ?? $_FILES['video_thumbnail'] ?? null;
    $storedVideo = api_store_uploaded_file($videoUpload, 'videos', api_video_extensions());
    $storedThumbnail = null;

    if (is_array($thumbnailUpload) && (int) ($thumbnailUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $storedThumbnail = api_store_uploaded_file($thumbnailUpload, 'media', api_image_extensions());
    }

    $db->beginTransaction();

    try {
        api_execute($db, '
            INSERT INTO video_info (
                video_title,
                video_description,
                video_file,
                video_thumbnail,
                video_status
            ) VALUES (
                :video_title,
                :video_description,
                :video_file,
                :video_thumbnail,
                :video_status
            )
        ', [
            ':video_title' => $title,
            ':video_description' => $description !== '' ? $description : null,
            ':video_file' => $storedVideo['filename'],
            ':video_thumbnail' => $storedThumbnail['filename'] ?? null,
            ':video_status' => $status,
        ]);

        $videoId = (int) $db->lastInsertId();

        api_log_admin_action($db, $admin, 'CREATE', 'Created video "' . $title . '"');

        $db->commit();

        api_json([
            'message' => 'Video created successfully.',
            'data' => api_video_by_id($db, $videoId),
        ], 201);
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        api_delete_uploaded_file('videos', $storedVideo['filename'] ?? null);
        api_delete_uploaded_file('media', $storedThumbnail['filename'] ?? null);
        throw $error;
    }
} catch (Throwable $error) {
    error_log('Admin video create API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to create video right now.',
    ], 500);
}
