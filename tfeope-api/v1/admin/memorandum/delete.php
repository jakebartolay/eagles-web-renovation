<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    $admin = api_require_admin($db);

    $payload = api_request_data();
    $memoId = (int) ($payload['id'] ?? $payload['memo_id'] ?? 0);

    if ($memoId <= 0) {
        api_json([
            'success' => false,
            'message' => 'A valid memorandum ID is required.',
        ], 422);
    }

    $map = api_memorandum_field_map($db);
    $pageMap = api_memorandum_page_field_map($db);

    $memoRow = api_fetch_one(
        $db,
        'SELECT memo_id, ' . api_quote_identifier($map['title']) . ' AS api_title
         FROM memorandum
         WHERE memo_id = :memo_id
         LIMIT 1',
        [':memo_id' => $memoId]
    );

    if ($memoRow === null) {
        api_json([
            'success' => false,
            'message' => 'Memorandum not found.',
        ], 404);
    }

    $pageRows = api_fetch_all(
        $db,
        'SELECT ' . api_quote_identifier($pageMap['file']) . ' AS api_file
         FROM memorandum_pages
         WHERE memo_id = :memo_id',
        [':memo_id' => $memoId]
    );

    $filenames = array_values(array_filter(array_map(
        static fn (array $row): string => trim((string) ($row['api_file'] ?? '')),
        $pageRows
    )));

    $db->beginTransaction();

    try {
        api_execute($db, 'DELETE FROM memorandum_pages WHERE memo_id = :memo_id', [':memo_id' => $memoId]);
        api_execute($db, 'DELETE FROM memorandum WHERE memo_id = :memo_id', [':memo_id' => $memoId]);

        api_log_admin_action($db, $admin, 'DELETE', 'Deleted memorandum "' . (string) ($memoRow['api_title'] ?? '') . '"');

        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }

    foreach ($filenames as $filename) {
        api_delete_uploaded_file('media', $filename);
    }

    api_json([
        'message' => 'Memorandum deleted successfully.',
        'data' => [
            'deletedId' => $memoId,
        ],
    ]);
} catch (Throwable $error) {
    error_log('Admin memorandum delete API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to delete memorandum right now.',
    ], 500);
}
