<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    $admin = api_require_admin($db);

    if (!api_table_exists($db, 'memorandum') || !api_table_exists($db, 'memorandum_pages')) {
        api_json([
            'ok' => false,
            'message' => 'Memorandum tables are not available.',
        ], 500);
    }

    $payload = api_request_data();
    $memoId = (int) ($payload['id'] ?? $payload['memo_id'] ?? 0);

    if ($memoId <= 0) {
        api_json([
            'ok' => false,
            'message' => 'A valid memorandum ID is required.',
        ], 422);
    }

    $map = api_memorandum_field_map($db);
    $pageMap = api_memorandum_page_field_map($db);

    $current = api_fetch_one(
        $db,
        'SELECT memo_id, ' . api_quote_identifier($map['title']) . ' AS api_title,
                ' . api_quote_identifier($map['status']) . ' AS api_status,
                ' . ($map['description'] !== null ? api_quote_identifier($map['description']) . ' AS api_description' : '"" AS api_description') . '
         FROM memorandum
         WHERE memo_id = :memo_id
         LIMIT 1',
        [':memo_id' => $memoId]
    );

    if ($current === null) {
        api_json([
            'ok' => false,
            'message' => 'Memorandum not found.',
        ], 404);
    }

    $title = trim((string) ($payload['title'] ?? $payload['memo_title'] ?? $current['api_title'] ?? ''));
    $description = trim((string) ($payload['description'] ?? $payload['memo_description'] ?? $current['api_description'] ?? ''));
    $status = api_normalize_publication_status($payload['status'] ?? $payload['memo_status'] ?? $current['api_status'] ?? 'Draft');

    if ($title === '') {
        api_json([
            'ok' => false,
            'message' => 'Memorandum title is required.',
        ], 422);
    }

    $pageUpload = $_FILES['pages'] ?? $_FILES['page_files'] ?? $_FILES['files'] ?? null;
    $uploadedFiles = is_array($pageUpload)
        ? array_values(array_filter(
            api_normalize_uploaded_files($pageUpload),
            static fn (array $file): bool => (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
        ))
        : [];

    $oldPageRows = api_fetch_all(
        $db,
        'SELECT page_id, ' . api_quote_identifier($pageMap['file']) . ' AS api_file
         FROM memorandum_pages
         WHERE memo_id = :memo_id
         ORDER BY page_id ASC',
        [':memo_id' => $memoId]
    );

    $oldFilenames = array_values(array_filter(array_map(
        static fn (array $row): string => trim((string) ($row['api_file'] ?? '')),
        $oldPageRows
    )));

    $storedPages = [];

    foreach ($uploadedFiles as $file) {
        $storedFile = api_store_uploaded_image_as_jpeg(
            $file,
            'memorandum',
            'memo_' . date('Ymd')
        );
        $storedPages[] = $storedFile['filename'];
    }

    $db->beginTransaction();

    try {
        $updateFields = [
            api_quote_identifier($map['title']) . ' = :memo_title',
            api_quote_identifier($map['status']) . ' = :memo_status',
        ];

        $params = [
            ':memo_id' => $memoId,
            ':memo_title' => $title,
            ':memo_status' => $status,
        ];

        if ($map['description'] !== null) {
            $updateFields[] = api_quote_identifier($map['description']) . ' = :memo_description';
            $params[':memo_description'] = $description;
        }

        api_execute(
            $db,
            'UPDATE memorandum SET ' . implode(', ', $updateFields) . ' WHERE memo_id = :memo_id',
            $params
        );

        if ($storedPages !== []) {
            api_execute($db, 'DELETE FROM memorandum_pages WHERE memo_id = :memo_id', [':memo_id' => $memoId]);

            foreach ($storedPages as $index => $filename) {
                $pageFields = ['memo_id', $pageMap['file']];
                $pagePlaceholders = [':memo_id', ':page_file'];
                $pageParams = [
                    ':memo_id' => $memoId,
                    ':page_file' => $filename,
                ];

                if (api_has_column($db, 'memorandum_pages', 'page_number')) {
                    $pageFields[] = 'page_number';
                    $pagePlaceholders[] = ':page_number';
                    $pageParams[':page_number'] = $index + 1;
                }

                api_execute(
                    $db,
                    'INSERT INTO memorandum_pages (' . implode(', ', array_map('api_quote_identifier', $pageFields)) . ')
                     VALUES (' . implode(', ', $pagePlaceholders) . ')',
                    $pageParams
                );
            }
        }

        api_log_admin_action($db, $admin, 'UPDATE', 'Updated memorandum "' . $title . '"');

        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();

        foreach ($storedPages as $filename) {
            api_delete_uploaded_file('memorandum', $filename);
        }

        throw $error;
    }

    if ($storedPages !== []) {
        foreach ($oldFilenames as $filename) {
            api_delete_uploaded_file('memorandum', $filename);
        }
    }

    api_json([
        'ok' => true,
        'message' => 'Memorandum updated successfully.',
        'data' => api_memorandum_by_id($db, $memoId),
    ]);
} catch (Throwable $error) {
    error_log('Admin memorandum update API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to update memorandum right now.',
    ], 500);
}
