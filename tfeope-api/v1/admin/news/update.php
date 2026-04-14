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
    $newsId = (int) ($payload['id'] ?? $payload['news_id'] ?? 0);

    if ($newsId <= 0) {
        api_json([
            'ok' => false,
            'message' => 'A valid news ID is required.',
        ], 422);
    }

    $currentRow = api_fetch_one($db, '
        SELECT news_id, news_title, news_content, news_status, news_image
        FROM news_info
        WHERE news_id = :news_id
        LIMIT 1
    ', [':news_id' => $newsId]);

    if ($currentRow === null) {
        api_json([
            'ok' => false,
            'message' => 'News item not found.',
        ], 404);
    }

    $title = trim((string) ($payload['title'] ?? $payload['news_title'] ?? $currentRow['news_title'] ?? ''));
    $content = trim((string) ($payload['content'] ?? $payload['news_content'] ?? $currentRow['news_content'] ?? ''));
    $status = api_normalize_news_status($payload['status'] ?? $payload['news_status'] ?? $currentRow['news_status'] ?? 'Draft');

    if ($title === '' || $content === '') {
        api_json([
            'ok' => false,
            'message' => 'Title and content are required.',
        ], 422);
    }

    $existingImageMedia = null;
    if (api_table_exists($db, 'news_media')) {
        $existingImageMedia = api_fetch_one($db, '
            SELECT media_id, file_name
            FROM news_media
            WHERE news_id = :news_id
              AND (
                  file_type = :file_type
                  OR file_type IS NULL
                  OR TRIM(file_type) = ""
              )
            ORDER BY media_id ASC
            LIMIT 1
        ', [
            ':news_id' => $newsId,
            ':file_type' => 'image',
        ]);
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

    $nextImageFilename = $storedUpload['filename'] ?? trim((string) ($currentRow['news_image'] ?? ''));
    $oldFiles = array_unique(array_filter([
        trim((string) ($currentRow['news_image'] ?? '')),
        trim((string) ($existingImageMedia['file_name'] ?? '')),
    ]));

    $db->beginTransaction();

    try {
        api_execute($db, '
            UPDATE news_info
            SET news_title = :news_title,
                news_content = :news_content,
                news_status = :news_status,
                news_image = :news_image
            WHERE news_id = :news_id
        ', [
            ':news_id' => $newsId,
            ':news_title' => $title,
            ':news_content' => $content,
            ':news_status' => $status,
            ':news_image' => $nextImageFilename !== '' ? $nextImageFilename : null,
        ]);

        if ($storedUpload !== null && api_table_exists($db, 'news_media')) {
            if ($existingImageMedia !== null) {
                api_execute($db, '
                    UPDATE news_media
                    SET file_name = :file_name,
                        file_type = :file_type
                    WHERE media_id = :media_id
                ', [
                    ':media_id' => (int) ($existingImageMedia['media_id'] ?? 0),
                    ':file_name' => $storedUpload['filename'],
                    ':file_type' => 'image',
                ]);
            } else {
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
        }

        api_log_admin_action(
            $db,
            $admin,
            'news_update',
            'Updated news "' . $title . '"'
        );

        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();

        if ($storedUpload !== null) {
            api_delete_uploaded_file('news', $storedUpload['filename'] ?? null);
        }

        throw $error;
    }

    if ($storedUpload !== null) {
        foreach ($oldFiles as $filename) {
            if ($filename === $storedUpload['filename']) {
                continue;
            }

            $asset = api_locate_media_file(api_news_media_groups(), $filename);
            if ($asset !== null) {
                api_delete_uploaded_file((string) ($asset['group'] ?? 'news'), $filename);
            }
        }
    }

    api_json([
        'ok' => true,
        'message' => 'News updated successfully.',
        'data' => api_news_by_id($db, $newsId),
    ]);
} catch (Throwable $error) {
    error_log('Admin news update API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to update news right now.',
    ], 500);
}
