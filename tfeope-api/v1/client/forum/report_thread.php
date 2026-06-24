<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/_helpers.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    forum_require_tables($db);
    $userId = forum_require_user();

    if (!forum_ensure_thread_reports($db)) {
        api_error('Unable to create report right now.', 500);
    }

    $payload = api_request_data();
    $threadId = (int) ($payload['threadId'] ?? $payload['thread_id'] ?? 0);
    $reason = trim((string) ($payload['reason'] ?? 'Reported from post menu.'));

    if ($threadId <= 0) {
        api_error('Thread ID is required.', 422);
    }

    if (mb_strlen($reason) > 255) {
        $reason = mb_substr($reason, 0, 255);
    }

    $thread = api_fetch_one($db, '
        SELECT id
        FROM forum_threads
        WHERE id = :id
        LIMIT 1
    ', [':id' => $threadId]);

    if (!$thread) {
        api_error('Thread not found.', 404);
    }

    api_execute($db, '
        INSERT INTO forum_thread_reports (
            thread_id,
            reporter_user_id,
            reason,
            status,
            created_at
        ) VALUES (
            :thread_id,
            :reporter_user_id,
            :reason,
            "pending",
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            reason = VALUES(reason),
            status = "pending",
            updated_at = NOW()
    ', [
        ':thread_id' => $threadId,
        ':reporter_user_id' => $userId,
        ':reason' => $reason !== '' ? $reason : 'Reported from post menu.',
    ]);

    api_json([
        'ok' => true,
        'message' => 'Report submitted.',
    ]);
} catch (Throwable $error) {
    error_log('Forum report thread API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to submit report right now.',
    ], 500);
}

