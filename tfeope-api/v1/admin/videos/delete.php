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
            'success' => false,
            'message' => 'A valid video ID is required.',
        ], 422);
    }

    $videoRow = api_fetch_one($db, '
        SELECT video_id, video_title, video_file, video_thumbnail
        FROM video_info
        WHERE video_id = :video_id
        LIMIT 1
    ', [':video_id' => $videoId]);

    if ($videoRow === null) {
        api_json([
            'success' => false,
            'message' => 'Video not found.',
        ], 404);
    }

    api_execute($db, '
        DELETE FROM video_info
        WHERE video_id = :video_id
    ', [':video_id' => $videoId]);

    api_delete_uploaded_file('videos', (string) ($videoRow['video_file'] ?? ''));
    api_delete_uploaded_file('media', (string) ($videoRow['video_thumbnail'] ?? ''));
    api_log_admin_action($db, $admin, 'DELETE', 'Deleted video "' . (string) ($videoRow['video_title'] ?? '') . '"');

    api_json([
        'message' => 'Video deleted successfully.',
        'data' => [
            'deletedId' => $videoId,
        ],
    ]);
} catch (Throwable $error) {
    error_log('Admin video delete API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to delete video right now.',
    ], 500);
}
