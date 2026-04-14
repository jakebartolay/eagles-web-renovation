<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

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
    $title = trim((string) ($payload['title'] ?? $payload['news_title'] ?? ''));
    $content = trim((string) ($payload['content'] ?? $payload['news_content'] ?? ''));
    $status = api_normalize_news_status($payload['status'] ?? $payload['news_status'] ?? 'Draft');

    if ($title === '' || $content === '') {
        api_json([
            'ok' => false,
            'message' => 'Title and content are required.',
        ], 422);
    }

    $uploadFile = $_FILES['image'] ?? $_FILES['news_image'] ?? null;
    $storedUpload = null;

    if (is_array($uploadFile) && (int) ($uploadFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $storedUpload = api_store_uploaded_file_as(
            $uploadFile,
            'news',
            'news_' . date('Ymd'),
            api_image_extensions()
        );
    }

    $db->beginTransaction();

    try {
        api_execute($db, '
            INSERT INTO news_info (
                news_title,
                news_content,
                news_status,
                news_image
            ) VALUES (
                :news_title,
                :news_content,
                :news_status,
                :news_image
            )
        ', [
            ':news_title' => $title,
            ':news_content' => $content,
            ':news_status' => $status,
            ':news_image' => $storedUpload['filename'] ?? null,
        ]);

        $newsId = (int) $db->lastInsertId();

        if ($newsId > 0 && $storedUpload !== null && api_table_exists($db, 'news_media')) {
            api_execute($db, '
                INSERT INTO news_media (
                    news_id,
                    file_name,
                    file_type
                ) VALUES (
                    :news_id,
                    :file_name,
                    :file_type
                )
            ', [
                ':news_id' => $newsId,
                ':file_name' => $storedUpload['filename'],
                ':file_type' => 'image',
            ]);
        }

        api_log_admin_action(
            $db,
            $admin,
            'news_create',
            'Created news "' . $title . '"'
        );

        $db->commit();

        $news = api_news_by_id($db, $newsId);

        api_json([
            'ok' => true,
            'message' => 'News created successfully.',
            'data' => $news,
        ], 201);
    } catch (Throwable $error) {
        $db->rollBack();

        if ($storedUpload !== null) {
            api_delete_uploaded_file('news', $storedUpload['filename'] ?? null);
        }

        throw $error;
    }
} catch (Throwable $error) {
    error_log('Admin news create API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to create news right now.',
    ], 500);
}
