<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/_helpers.php';

api_start();
api_require_method(['GET', 'POST']);

try {
    $db = api_db();
    $userId = forum_require_user();

    if (!forum_ensure_notifications($db)) {
        api_error('Notifications are unavailable right now.', 500);
    }

    if (api_request_method() === 'POST') {
        $payload = api_request_data();
        $notificationId = (int)($payload['id'] ?? $payload['notificationId'] ?? 0);
        $markAll = (bool)($payload['all'] ?? false);

        if ($markAll) {
            api_execute($db, '
                UPDATE forum_notifications
                SET is_read = 1
                WHERE user_id = :user_id
                  AND is_read = 0
            ', [':user_id' => $userId]);
        } elseif ($notificationId > 0) {
            api_execute($db, '
                UPDATE forum_notifications
                SET is_read = 1
                WHERE id = :id
                  AND user_id = :user_id
            ', [
                ':id' => $notificationId,
                ':user_id' => $userId,
            ]);
        } else {
            api_error('Choose a notification to mark as read.', 422);
        }

        $unreadRow = api_fetch_one($db, '
            SELECT COUNT(*) AS total
            FROM forum_notifications
            WHERE user_id = :user_id
              AND is_read = 0
        ', [':user_id' => $userId]);

        api_json([
            'success' => true,
            'unreadCount' => (int)($unreadRow['total'] ?? 0),
        ]);
    }

    $rows = api_fetch_all($db, '
        SELECT
            n.id,
            n.type,
            n.thread_id,
            n.post_id,
            n.is_read,
            n.created_at,
            u.id AS actor_id,
            u.name AS actor_name,
            u.username AS actor_username,
            t.title AS thread_title
        FROM forum_notifications n
        INNER JOIN users u ON u.id = n.actor_user_id
        INNER JOIN forum_threads t ON t.id = n.thread_id
        WHERE n.user_id = :user_id
        ORDER BY n.created_at DESC, n.id DESC
        LIMIT 40
    ', [':user_id' => $userId]);

    $notifications = array_map(static function (array $row): array {
        $type = (string)($row['type'] ?? 'comment');
        $actorName = trim((string)($row['actor_name'] ?? ''))
            ?: trim((string)($row['actor_username'] ?? ''))
            ?: 'A forum member';
        $action = match ($type) {
            'approve' => 'approved your post',
            'disapprove' => 'disapproved your post',
            'reply' => 'replied to your comment',
            default => 'commented on your post',
        };
        $threadId = (int)($row['thread_id'] ?? 0);

        return [
            'id' => (int)($row['id'] ?? 0),
            'type' => $type,
            'threadId' => $threadId,
            'postId' => isset($row['post_id']) ? (int)$row['post_id'] : null,
            'actor' => [
                'id' => (int)($row['actor_id'] ?? 0),
                'name' => $actorName,
                'username' => (string)($row['actor_username'] ?? ''),
            ],
            'threadTitle' => (string)($row['thread_title'] ?? 'Forum post'),
            'message' => $actorName . ' ' . $action . '.',
            'isRead' => (bool)((int)($row['is_read'] ?? 0)),
            'createdAt' => (string)($row['created_at'] ?? ''),
            'url' => '/forum/thread/' . $threadId,
        ];
    }, $rows);

    $unreadRow = api_fetch_one($db, '
        SELECT COUNT(*) AS total
        FROM forum_notifications
        WHERE user_id = :user_id
          AND is_read = 0
    ', [':user_id' => $userId]);
    $unreadCount = (int)($unreadRow['total'] ?? 0);

    api_json([
        'success' => true,
        'unreadCount' => $unreadCount,
        'data' => $notifications,
    ]);
} catch (Throwable $error) {
    error_log('Forum notifications API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to load notifications right now.',
    ], 500);
}
