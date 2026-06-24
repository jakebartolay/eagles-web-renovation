<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/_helpers.php';
api_start();
api_require_method('POST');

$db = api_db();
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId === 0) { api_error('Please sign in to react.', 401); }

$payload  = api_request_data();
$threadId = (int)($payload['threadId'] ?? 0);
$type     = strtolower(trim((string)($payload['type'] ?? '')));

if ($threadId === 0) { api_error('Thread ID required.', 400); }
if (!in_array($type, ['approve', 'disapprove'], true)) { api_error('Invalid reaction.', 400); }

$thread = api_fetch_one($db, "SELECT id, user_id FROM forum_threads WHERE id = :id LIMIT 1", [':id' => $threadId]);
if (!$thread) { api_error('Thread not found.', 404); }

forum_ensure_thread_reactions($db);

$current = api_fetch_one($db, "
    SELECT type FROM forum_thread_reactions WHERE thread_id = :tid AND user_id = :uid LIMIT 1
", [':tid' => $threadId, ':uid' => $userId]);

$myReaction = $type;

if ($current && (string)$current['type'] === $type) {
    // Same reaction tapped again → remove it.
    api_execute($db, "DELETE FROM forum_thread_reactions WHERE thread_id = :tid AND user_id = :uid",
        [':tid' => $threadId, ':uid' => $userId]);
    $myReaction = null;
} elseif ($current) {
    // Switch from approve <-> disapprove.
    api_execute($db, "UPDATE forum_thread_reactions SET type = :type, created_at = NOW() WHERE thread_id = :tid AND user_id = :uid",
        [':type' => $type, ':tid' => $threadId, ':uid' => $userId]);
} else {
    api_execute($db, "INSERT INTO forum_thread_reactions (thread_id, user_id, type) VALUES (:tid, :uid, :type)",
        [':tid' => $threadId, ':uid' => $userId, ':type' => $type]);
}

$notificationKey = 'thread-reaction:' . $threadId . ':' . $userId;
$threadOwnerId = (int)($thread['user_id'] ?? 0);
if ($myReaction === null) {
    forum_delete_notification($db, $notificationKey);
} else {
    forum_upsert_notification(
        $db,
        $threadOwnerId,
        $userId,
        $threadId,
        null,
        $notificationKey,
        $myReaction
    );
}

$approve = (int)(api_fetch_one($db, "
    SELECT COUNT(*) AS c FROM forum_thread_reactions WHERE thread_id = :tid AND type = 'approve'
", [':tid' => $threadId])['c'] ?? 0);

$disapprove = (int)(api_fetch_one($db, "
    SELECT COUNT(*) AS c FROM forum_thread_reactions WHERE thread_id = :tid AND type = 'disapprove'
", [':tid' => $threadId])['c'] ?? 0);

api_json([
    'success'         => true,
    'approveCount'    => $approve,
    'disapproveCount' => $disapprove,
    'myReaction'      => $myReaction,
]);
