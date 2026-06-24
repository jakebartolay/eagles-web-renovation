<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/_helpers.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    $userId = forum_require_user();
    $payload = api_request_data();
    $postId = (int)($payload['postId'] ?? $payload['id'] ?? 0);

    if ($postId <= 0) {
        api_error('Comment ID is required.', 422);
    }

    $post = api_fetch_one($db, '
        SELECT id, thread_id, user_id, is_deleted
        FROM forum_posts
        WHERE id = :post_id
        LIMIT 1
    ', [':post_id' => $postId]);

    if (!$post) {
        api_error('Comment not found.', 404);
    }

    if ((int)($post['user_id'] ?? 0) !== $userId) {
        api_error('You can only delete your own comment.', 403);
    }

    if ((int)($post['is_deleted'] ?? 0) === 0) {
        api_execute($db, '
            UPDATE forum_posts
            SET is_deleted = 1,
                body = \'\',
                updated_at = NOW()
            WHERE id = :post_id
        ', [':post_id' => $postId]);

        api_execute($db, 'DELETE FROM forum_reactions WHERE post_id = :post_id', [
            ':post_id' => $postId,
        ]);

        if (forum_ensure_notifications($db)) {
            api_execute($db, 'DELETE FROM forum_notifications WHERE post_id = :post_id', [
                ':post_id' => $postId,
            ]);
        }

        $threadId = (int)($post['thread_id'] ?? 0);
        api_execute($db, '
            UPDATE forum_threads
            SET reply_count = (
                    SELECT COUNT(*)
                    FROM forum_posts
                    WHERE thread_id = :count_thread_id
                      AND is_deleted = 0
                ),
                last_reply_at = (
                    SELECT MAX(created_at)
                    FROM forum_posts
                    WHERE thread_id = :date_thread_id
                      AND is_deleted = 0
                ),
                last_reply_user_id = (
                    SELECT user_id
                    FROM forum_posts
                    WHERE thread_id = :user_thread_id
                      AND is_deleted = 0
                    ORDER BY created_at DESC, id DESC
                    LIMIT 1
                )
            WHERE id = :thread_id
        ', [
            ':count_thread_id' => $threadId,
            ':date_thread_id' => $threadId,
            ':user_thread_id' => $threadId,
            ':thread_id' => $threadId,
        ]);
    }

    api_json([
        'success' => true,
        'postId' => $postId,
        'message' => 'Comment deleted.',
    ]);
} catch (Throwable $error) {
    error_log('Forum comment delete error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to delete this comment right now.',
    ], 500);
}
