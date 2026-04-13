<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method(['DELETE', 'POST']);

try {
    $db = api_db();
    $admin = api_require_admin($db);

    if (!api_table_exists($db, 'news_info')) {
        api_json([
            'ok' => false,
            'message' => 'News table is not available.',
        ], 500);
    }

    $payload = api_request_data();
    $newsId = (int) (
        $payload['id']
        ?? $payload['news_id']
        ?? $_GET['id']
        ?? $_GET['news_id']
        ?? 0
    );

    if ($newsId <= 0) {
        api_json([
            'ok' => false,
            'message' => 'A valid news ID is required.',
        ], 422);
    }

    $newsRow = api_fetch_one($db, '
        SELECT news_id, news_title, news_image
        FROM news_info
        WHERE news_id = :news_id
        LIMIT 1
    ', [':news_id' => $newsId]);

    if ($newsRow === null) {
        api_json([
            'ok' => false,
            'message' => 'News item not found.',
        ], 404);
    }

    $filesToDelete = array_unique(array_filter(array_merge(
        [trim((string) ($newsRow['news_image'] ?? ''))],
        api_news_media_files($db, $newsId)
    )));

    $db->beginTransaction();

    try {
        if (api_table_exists($db, 'news_media')) {
            api_execute($db, '
                DELETE FROM news_media
                WHERE news_id = :news_id
            ', [':news_id' => $newsId]);
        }

        api_execute($db, '
            DELETE FROM news_info
            WHERE news_id = :news_id
        ', [':news_id' => $newsId]);

        api_log_admin_action(
            $db,
            $admin,
            'news_delete',
            'Deleted news "' . (string) ($newsRow['news_title'] ?? '') . '"'
        );

        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }

    foreach ($filesToDelete as $filename) {
        api_delete_uploaded_file('news', $filename);
    }

    api_json([
        'ok' => true,
        'message' => 'News deleted successfully.',
        'deletedId' => $newsId,
    ]);
} catch (Throwable $error) {
    error_log('Admin news delete API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to delete news right now.',
    ], 500);
}
