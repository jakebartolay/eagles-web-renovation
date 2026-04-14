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
            'success' => false,
            'message' => 'Memorandum tables are not available.',
        ], 500);
    }

    $payload = api_request_data();
    $title = trim((string) ($payload['title'] ?? $payload['memo_title'] ?? ''));
    $description = trim((string) ($payload['description'] ?? $payload['memo_description'] ?? ''));
    $status = api_normalize_publication_status($payload['status'] ?? $payload['memo_status'] ?? 'Draft');

    if ($title === '') {
        api_json([
            'success' => false,
            'message' => 'Memorandum title is required.',
        ], 422);
    }

    $pageUpload = $_FILES['pages'] ?? $_FILES['page_files'] ?? $_FILES['files'] ?? null;
    if (!is_array($pageUpload)) {
        api_json([
            'success' => false,
            'message' => 'At least one memorandum page is required.',
        ], 422);
    }

    $uploadedFiles = array_values(array_filter(
        api_normalize_uploaded_files($pageUpload),
        static fn (array $file): bool => (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
    ));

    if ($uploadedFiles === []) {
        api_json([
            'success' => false,
            'message' => 'At least one memorandum page is required.',
        ], 422);
    }

    $map = api_memorandum_field_map($db);
    $pageMap = api_memorandum_page_field_map($db);
    $storedPages = [];

    $db->beginTransaction();

    try {
        $fieldNames = [$map['title'], $map['status']];
        $placeholders = [':memo_title', ':memo_status'];
        $params = [
            ':memo_title' => $title,
            ':memo_status' => $status,
        ];

        if ($map['description'] !== null) {
            $fieldNames[] = $map['description'];
            $placeholders[] = ':memo_description';
            $params[':memo_description'] = $description;
        }

        api_execute(
            $db,
            'INSERT INTO memorandum (' . implode(', ', array_map('api_quote_identifier', $fieldNames)) . ')
             VALUES (' . implode(', ', $placeholders) . ')',
            $params
        );

        $memoId = (int) $db->lastInsertId();

        foreach ($uploadedFiles as $index => $file) {
            $storedFile = api_store_uploaded_image_as_jpeg(
                $file,
                'memorandum',
                'memo_' . date('Ymd')
            );
            $storedPages[] = $storedFile['filename'];

            $pageFields = ['memo_id', $pageMap['file']];
            $pagePlaceholders = [':memo_id', ':page_file'];
            $pageParams = [
                ':memo_id' => $memoId,
                ':page_file' => $storedFile['filename'],
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

        api_log_admin_action($db, $admin, 'CREATE', 'Created memorandum "' . $title . '"');

        $db->commit();

        api_json([
            'message' => 'Memorandum created successfully.',
            'data' => api_memorandum_by_id($db, $memoId),
        ], 201);
    } catch (Throwable $error) {
        $db->rollBack();

        foreach ($storedPages as $filename) {
            api_delete_uploaded_file('memorandum', $filename);
        }

        throw $error;
    }
} catch (Throwable $error) {
    error_log('Admin memorandum create API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to create memorandum right now.',
    ], 500);
}
