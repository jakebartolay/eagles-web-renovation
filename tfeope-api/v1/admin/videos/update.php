<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    $admin = api_require_admin($db);

    $payload = api_request_data();
    $videoId = (int) ($payload['id'] ?? $payload['video_id'] ?? 0);

    if ($videoId <= 0) {
        api_json([
            'ok' => false,
            'message' => 'A valid video ID is required.',
        ], 422);
    }

    $current = api_fetch_one($db, '
        SELECT *
        FROM video_info
        WHERE video_id = :video_id
        LIMIT 1
    ', [':video_id' => $videoId]);

    if ($current === null) {
        api_json([
            'ok' => false,
            'message' => 'Video not found.',
        ], 404);
    }

    $title = trim((string) ($payload['title'] ?? $payload['video_title'] ?? $current['video_title'] ?? ''));
    $description = trim((string) ($payload['description'] ?? $payload['video_description'] ?? $current['video_description'] ?? ''));
    $status = api_normalize_publication_status($payload['status'] ?? $payload['video_status'] ?? $current['video_status'] ?? 'Draft');

    if ($title === '') {
        api_json([
            'ok' => false,
            'message' => 'Video title is required.',
        ], 422);
    }

    $videoUpload = $_FILES['video'] ?? $_FILES['video_file'] ?? null;
    $thumbnailUpload = $_FILES['thumbnail'] ?? $_FILES['video_thumbnail'] ?? null;

    $storedVideo = null;
    $storedThumbnail = null;

    if (is_array($videoUpload) && (int) ($videoUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $storedVideo = api_store_uploaded_file($videoUpload, 'videos', api_video_extensions());
    }

    if (is_array($thumbnailUpload) && (int) ($thumbnailUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $storedThumbnail = api_store_uploaded_file($thumbnailUpload, 'media', api_image_extensions());
    }

    try {
        api_execute($db, '
            UPDATE video_info
            SET video_title = :video_title,
                video_description = :video_description,
                video_file = :video_file,
                video_thumbnail = :video_thumbnail,
                video_status = :video_status
            WHERE video_id = :video_id
        ', [
            ':video_id' => $videoId,
            ':video_title' => $title,
            ':video_description' => $description !== '' ? $description : null,
            ':video_file' => $storedVideo['filename'] ?? (string) ($current['video_file'] ?? ''),
            ':video_thumbnail' => $storedThumbnail['filename'] ?? (string) ($current['video_thumbnail'] ?? ''),
            ':video_status' => $status,
        ]);
    } catch (Throwable $error) {
        api_delete_uploaded_file('videos', $storedVideo['filename'] ?? null);
        api_delete_uploaded_file('media', $storedThumbnail['filename'] ?? null);
        throw $error;
    }

    if ($storedVideo !== null) {
        api_delete_uploaded_file('videos', (string) ($current['video_file'] ?? ''));
    }

    if ($storedThumbnail !== null) {
        api_delete_uploaded_file('media', (string) ($current['video_thumbnail'] ?? ''));
    }

    api_log_admin_action($db, $admin, 'UPDATE', 'Updated video "' . $title . '"');

    api_json([
        'ok' => true,
        'message' => 'Video updated successfully.',
        'data' => api_video_by_id($db, $videoId),
    ]);
} catch (Throwable $error) {
    error_log('Admin video update API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to update video right now.',
    ], 500);
}
