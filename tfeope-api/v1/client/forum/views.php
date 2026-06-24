<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/_helpers.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    forum_require_tables($db);
    $payload = api_request_data();
    $threadId = (int) ($payload['threadId'] ?? $payload['thread_id'] ?? 0);

    if ($threadId <= 0) {
        api_error('Thread is required.', 422);
    }

    $thread = forum_fetch_thread($db, $threadId);
    if (!$thread) {
        api_error('Thread not found.', 404);
    }

    api_execute($db, '
        UPDATE forum_threads
        SET views = views + 1
        WHERE id = :thread_id
    ', [':thread_id' => $threadId]);

    $userId = forum_current_user_id();
    if ($userId > 0 && api_table_exists($db, 'forum_thread_views')) {
        api_execute($db, '
            INSERT INTO forum_thread_views (thread_id, user_id, last_viewed_at)
            VALUES (:thread_id, :user_id, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE last_viewed_at = CURRENT_TIMESTAMP
        ', [
            ':thread_id' => $threadId,
            ':user_id' => $userId,
        ]);
    }

    api_json([
        'ok' => true,
        'message' => 'Thread view recorded.',
    ]);
} catch (Throwable $error) {
    error_log('Forum view API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to record thread view right now.',
    ], 500);
}
