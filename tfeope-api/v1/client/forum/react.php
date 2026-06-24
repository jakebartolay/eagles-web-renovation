<?php
declare(strict_types=1);
require_once '../../../bootstrap.php';
api_start();
api_require_method('POST');

$db = api_db();
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId === 0) { api_error('Unauthorized.', 401); }

$payload = api_request_data();
$postId  = (int)($payload['postId'] ?? 0);
if ($postId === 0) { api_error('Post ID required.', 400); }

$existing = api_fetch_one($db, "
    SELECT id FROM forum_reactions WHERE post_id = :pid AND user_id = :uid LIMIT 1
", [':pid' => $postId, ':uid' => $userId]);

if ($existing) {
    // Toggle off
    api_execute($db, "DELETE FROM forum_reactions WHERE post_id = :pid AND user_id = :uid", [':pid' => $postId, ':uid' => $userId]);
    api_execute($db, "UPDATE forum_posts SET like_count = GREATEST(0, like_count - 1) WHERE id = :id", [':id' => $postId]);
    api_json(['success' => true, 'liked' => false]);
} else {
    api_execute($db, "INSERT INTO forum_reactions (post_id, user_id, type) VALUES (:pid, :uid, 'like')", [':pid' => $postId, ':uid' => $userId]);
    api_execute($db, "UPDATE forum_posts SET like_count = like_count + 1 WHERE id = :id", [':id' => $postId]);
    api_json(['success' => true, 'liked' => true]);
}
