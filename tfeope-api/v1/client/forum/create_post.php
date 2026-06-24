<?php
declare(strict_types=1);
require_once '../../../bootstrap.php';
require_once __DIR__ . '/_helpers.php';
api_start();
api_require_method('POST');

$db = api_db();
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId === 0) { api_error('Unauthorized.', 401); }

$payload      = api_request_data();
$threadId     = (int)($payload['threadId'] ?? 0);
$body         = trim((string)($payload['body'] ?? ''));
$parentPostId = isset($payload['parentPostId']) ? (int)$payload['parentPostId'] : null;

if ($threadId === 0) { api_error('Thread ID is required.', 400); }
if ($body === '')    { api_error('Post body is required.', 400); }

$thread = api_fetch_one($db, "
    SELECT id, user_id, is_locked FROM forum_threads WHERE id = :id LIMIT 1
", [':id' => $threadId]);
if (!$thread)             { api_error('Thread not found.', 404); }
if ($thread['is_locked']) { api_error('This thread is locked and no longer accepts replies.', 403); }

$parentPost = null;
if ($parentPostId !== null && $parentPostId > 0) {
    $parentPost = api_fetch_one($db, "
        SELECT id, user_id, is_deleted
        FROM forum_posts
        WHERE id = :post_id
          AND thread_id = :thread_id
        LIMIT 1
    ", [
        ':post_id' => $parentPostId,
        ':thread_id' => $threadId,
    ]);

    if (!$parentPost || (int)($parentPost['is_deleted'] ?? 0) === 1) {
        api_error('The comment you are replying to is no longer available.', 422);
    }
}

api_execute($db, "
    INSERT INTO forum_posts (thread_id, user_id, body, parent_post_id)
    VALUES (:tid, :uid, :body, :pid)
", [':tid' => $threadId, ':uid' => $userId, ':body' => $body, ':pid' => $parentPostId]);

$postId = (int)$db->lastInsertId();

// Update thread reply count + last_reply_at
api_execute($db, "
    UPDATE forum_threads
    SET reply_count = reply_count + 1, last_reply_at = NOW(), last_reply_user_id = :uid
    WHERE id = :tid
", [':uid' => $userId, ':tid' => $threadId]);

forum_upsert_notification(
    $db,
    (int)($thread['user_id'] ?? 0),
    $userId,
    $threadId,
    $postId,
    'thread-comment:' . $postId,
    'comment'
);

$parentAuthorId = (int)($parentPost['user_id'] ?? 0);
$threadOwnerId = (int)($thread['user_id'] ?? 0);
if ($parentAuthorId > 0 && $parentAuthorId !== $threadOwnerId) {
    forum_upsert_notification(
        $db,
        $parentAuthorId,
        $userId,
        $threadId,
        $postId,
        'comment-reply:' . $postId . ':' . $parentAuthorId,
        'reply'
    );
}

api_json(['success' => true, 'postId' => $postId], 201);
