<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/_helpers.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    forum_require_tables($db);
    user_ensure_avatar_columns($db);
    $userId = forum_require_user();
    $payload = api_request_data();
    $threadId = (int) ($payload['threadId'] ?? $payload['thread_id'] ?? 0);
    $parentPostId = (int) ($payload['parentPostId'] ?? $payload['parent_post_id'] ?? 0);
    $body = trim((string) ($payload['body'] ?? ''));

    if ($threadId <= 0) {
        api_error('Thread is required.', 422);
    }

    if ($body === '') {
        api_error('Reply message is required.', 422);
    }

    $thread = forum_fetch_thread($db, $threadId);
    if (!$thread) {
        api_error('Thread not found.', 404);
    }

    if ((int) ($thread['is_locked'] ?? 0) === 1) {
        api_error('This discussion is locked.', 423);
    }

    if ($parentPostId > 0) {
        $parentPost = api_fetch_one($db, '
            SELECT id
            FROM forum_posts
            WHERE id = :id
              AND thread_id = :thread_id
            LIMIT 1
        ', [
            ':id' => $parentPostId,
            ':thread_id' => $threadId,
        ]);

        if (!$parentPost) {
            api_error('Parent reply not found.', 422);
        }
    } else {
        $parentPostId = null;
    }

    $db->beginTransaction();

    try {
        api_execute($db, '
            INSERT INTO forum_posts (
                thread_id,
                user_id,
                body,
                parent_post_id
            ) VALUES (
                :thread_id,
                :user_id,
                :body,
                :parent_post_id
            )
        ', [
            ':thread_id' => $threadId,
            ':user_id' => $userId,
            ':body' => $body,
            ':parent_post_id' => $parentPostId,
        ]);

        $postId = (int) $db->lastInsertId();

        api_execute($db, '
            UPDATE forum_threads
            SET
                reply_count = reply_count + 1,
                last_reply_at = NOW(),
                last_reply_user_id = :user_id
            WHERE id = :thread_id
        ', [
            ':user_id' => $userId,
            ':thread_id' => $threadId,
        ]);

        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }

    $post = api_fetch_one($db, '
        SELECT
            p.*,
            u.name AS author_name,
            u.username AS author_username,
            u.eagles_id AS author_eagles_id,
            u.role_id AS author_role_id,
            u.avatar_seed AS author_avatar_seed,
            u.avatar_style AS author_avatar_style,
            ui.eagles_club AS author_club,
            0 AS reaction_count
        FROM forum_posts p
        INNER JOIN users u ON u.id = p.user_id
        LEFT JOIN user_info ui ON u.eagles_id = ui.eagles_id
        WHERE p.id = :id
        LIMIT 1
    ', [':id' => $postId]);

    api_json([
        'ok' => true,
        'message' => 'Reply added.',
        'data' => $post ? forum_format_post($post) : null,
    ], 201);
} catch (Throwable $error) {
    error_log('Forum post create API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to add reply right now.',
    ], 500);
}
