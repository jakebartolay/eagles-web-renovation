<?php

declare(strict_types=1);

require_once __DIR__ . '/../users/_avatar_helpers.php';

function forum_require_tables(PDO $db): void
{
    foreach (['forum_categories', 'forum_threads', 'forum_posts'] as $table) {
        if (!api_table_exists($db, $table)) {
            api_error('Forum database tables are not available.', 500);
        }
    }
}

function forum_current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function forum_require_user(): int
{
    $userId = forum_current_user_id();
    if ($userId <= 0) {
        api_error('Please sign in to use the forum.', 401, [
            'authenticated' => false,
        ]);
    }

    return $userId;
}

function forum_category_is_eagles_news(array $category): bool
{
    $slug = strtolower(trim((string) ($category['slug'] ?? '')));
    $name = preg_replace('/\s+/', ' ', strtolower(trim((string) ($category['name'] ?? '')))) ?? '';

    return $slug === 'eagles-news' || $name === 'eagles news';
}

function forum_user_can_post_eagles_news(PDO $db, int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }

    $user = api_fetch_one($db, '
        SELECT role_id
        FROM users
        WHERE id = :id
        LIMIT 1
    ', [':id' => $userId]);

    $roleId = (int) ($user['role_id'] ?? 0);
    return in_array($roleId, [1, 2, 3], true);
}

function forum_guard_thread_category_permission(PDO $db, int $userId, array $category): void
{
    if (!forum_category_is_eagles_news($category)) {
        return;
    }

    if (!forum_user_can_post_eagles_news($db, $userId)) {
        api_error('Only administrators and Brotherhood Officers can post in Eagles News.', 403);
    }
}

function forum_slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    return $slug !== '' ? substr($slug, 0, 220) : 'topic';
}

function forum_unique_thread_slug(PDO $db, int $categoryId, string $title): string
{
    $baseSlug = forum_slugify($title);
    $slug = $baseSlug;
    $suffix = 2;

    while (api_fetch_one($db, '
        SELECT id
        FROM forum_threads
        WHERE category_id = :category_id
          AND slug = :slug
        LIMIT 1
    ', [
        ':category_id' => $categoryId,
        ':slug' => $slug,
    ])) {
        $slug = substr($baseSlug, 0, 210) . '-' . $suffix;
        $suffix += 1;
    }

    return $slug;
}

function forum_ensure_thread_image_column(PDO $db): bool
{
    if (api_has_column($db, 'forum_threads', 'image')) {
        return true;
    }

    try {
        $db->exec('ALTER TABLE forum_threads ADD COLUMN image VARCHAR(255) NULL AFTER body');
        return true;
    } catch (Throwable $error) {
        error_log('Forum image column add failed: ' . $error->getMessage());
        return false;
    }
}

function forum_ensure_thread_reactions(PDO $db): void
{
    if (api_table_exists($db, 'forum_thread_reactions')) {
        return;
    }

    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS forum_thread_reactions (
                id INT(11) NOT NULL AUTO_INCREMENT,
                thread_id INT(11) NOT NULL,
                user_id INT(11) NOT NULL,
                type ENUM('approve','disapprove') NOT NULL DEFAULT 'approve',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_thread_user (thread_id, user_id),
                KEY idx_thread_type (thread_id, type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    } catch (Throwable $error) {
        error_log('Create forum_thread_reactions failed: ' . $error->getMessage());
    }
}

function forum_ensure_notifications(PDO $db): bool
{
    if (api_table_exists($db, 'forum_notifications')) {
        try {
            $typeColumn = api_fetch_one($db, "SHOW COLUMNS FROM forum_notifications LIKE 'type'");
            if ($typeColumn && stripos((string)($typeColumn['Type'] ?? ''), "'reply'") === false) {
                $db->exec("
                    ALTER TABLE forum_notifications
                    MODIFY type ENUM('approve','disapprove','comment','reply') NOT NULL
                ");
            }
        } catch (Throwable $error) {
            error_log('Update forum notification types failed: ' . $error->getMessage());
        }
        return true;
    }

    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS forum_notifications (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT NOT NULL,
                actor_user_id INT NOT NULL,
                thread_id INT NOT NULL,
                post_id INT NULL,
                event_key VARCHAR(100) NOT NULL,
                type ENUM('approve','disapprove','comment','reply') NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_forum_notification_event (event_key),
                KEY idx_forum_notifications_user_read (user_id, is_read, created_at),
                KEY idx_forum_notifications_thread (thread_id),
                KEY idx_forum_notifications_actor (actor_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        return true;
    } catch (Throwable $error) {
        error_log('Create forum_notifications failed: ' . $error->getMessage());
        return false;
    }
}

function forum_ensure_thread_reports(PDO $db): bool
{
    if (api_table_exists($db, 'forum_thread_reports')) {
        return true;
    }

    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS forum_thread_reports (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                thread_id INT NOT NULL,
                reporter_user_id INT NOT NULL,
                reason VARCHAR(255) NULL,
                status ENUM('pending','reviewed','dismissed') NOT NULL DEFAULT 'pending',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_forum_thread_reporter (thread_id, reporter_user_id),
                KEY idx_forum_thread_reports_thread (thread_id),
                KEY idx_forum_thread_reports_status (status, created_at),
                KEY idx_forum_thread_reports_reporter (reporter_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        return true;
    } catch (Throwable $error) {
        error_log('Create forum_thread_reports failed: ' . $error->getMessage());
        return false;
    }
}

function forum_upsert_notification(
    PDO $db,
    int $recipientUserId,
    int $actorUserId,
    int $threadId,
    ?int $postId,
    string $eventKey,
    string $type
): void {
    if ($recipientUserId <= 0 || $actorUserId <= 0 || $recipientUserId === $actorUserId) {
        return;
    }

    if (!forum_ensure_notifications($db)) {
        return;
    }

    api_execute($db, '
        INSERT INTO forum_notifications (
            user_id,
            actor_user_id,
            thread_id,
            post_id,
            event_key,
            type,
            is_read,
            created_at
        ) VALUES (
            :user_id,
            :actor_user_id,
            :thread_id,
            :post_id,
            :event_key,
            :type,
            0,
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            type = VALUES(type),
            post_id = VALUES(post_id),
            is_read = 0,
            created_at = NOW()
    ', [
        ':user_id' => $recipientUserId,
        ':actor_user_id' => $actorUserId,
        ':thread_id' => $threadId,
        ':post_id' => $postId,
        ':event_key' => $eventKey,
        ':type' => $type,
    ]);
}

function forum_delete_notification(PDO $db, string $eventKey): void
{
    if ($eventKey === '' || !forum_ensure_notifications($db)) {
        return;
    }

    api_execute($db, 'DELETE FROM forum_notifications WHERE event_key = :event_key', [
        ':event_key' => $eventKey,
    ]);
}

function forum_user_name(array $row): string
{
    return trim((string) ($row['author_name'] ?? ''))
        ?: trim((string) ($row['author_username'] ?? ''))
        ?: 'Member';
}

function forum_user_is_member(array $row): bool
{
    return trim((string) ($row['author_eagles_id'] ?? '')) !== ''
        || trim((string) ($row['author_club'] ?? '')) !== '';
}

function forum_format_post(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'threadId' => (int) ($row['thread_id'] ?? 0),
        'authorId' => (int) ($row['user_id'] ?? 0),
        'author' => forum_user_name($row),
        'authorUsername' => (string) ($row['author_username'] ?? ''),
        'authorRoleId' => (int) ($row['author_role_id'] ?? 0),
        'authorIsMember' => forum_user_is_member($row),
        'authorAvatarSeed' => user_avatar_seed([
            'id' => $row['user_id'] ?? 0,
            'username' => $row['author_username'] ?? '',
            'avatar_seed' => $row['author_avatar_seed'] ?? '',
        ]),
        'authorAvatarStyle' => user_avatar_style($row['author_avatar_style'] ?? null),
        'body' => (string) ($row['body'] ?? ''),
        'parentPostId' => isset($row['parent_post_id']) ? (int) $row['parent_post_id'] : null,
        'createdAt' => (string) ($row['created_at'] ?? ''),
        'likes' => (int) ($row['reaction_count'] ?? 0),
    ];
}

function forum_format_thread(array $row, array $replies = []): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'categoryId' => (int) ($row['category_id'] ?? 0),
        'category' => (string) ($row['category_slug'] ?? ''),
        'categoryName' => (string) ($row['category_name'] ?? ''),
        'title' => (string) ($row['title'] ?? ''),
        'slug' => (string) ($row['slug'] ?? ''),
        'authorId' => (int) ($row['user_id'] ?? 0),
        'author' => forum_user_name($row),
        'authorUsername' => (string) ($row['author_username'] ?? ''),
        'authorRoleId' => (int) ($row['author_role_id'] ?? 0),
        'authorIsMember' => forum_user_is_member($row),
        'authorClub' => trim((string) ($row['author_club'] ?? '')) !== '' ? (string) $row['author_club'] : null,
        'authorAvatarSeed' => user_avatar_seed([
            'id' => $row['user_id'] ?? 0,
            'username' => $row['author_username'] ?? '',
            'avatar_seed' => $row['author_avatar_seed'] ?? '',
        ]),
        'authorAvatarStyle' => user_avatar_style($row['author_avatar_style'] ?? null),
        'body' => (string) ($row['body'] ?? ''),
        'image' => (isset($row['image']) && (string) $row['image'] !== '')
            ? api_media_url('media', (string) $row['image'])
            : null,
        'pinned' => (bool) ((int) ($row['is_pinned'] ?? 0)),
        'locked' => (bool) ((int) ($row['is_locked'] ?? 0)),
        'views' => (int) ($row['views'] ?? 0),
        'replyCount' => (int) ($row['reply_count'] ?? count($replies)),
        'approveCount' => (int) ($row['approve_count'] ?? 0),
        'disapproveCount' => (int) ($row['disapprove_count'] ?? 0),
        'myReaction' => (isset($row['my_reaction']) && (string) $row['my_reaction'] !== '')
            ? (string) $row['my_reaction']
            : null,
        'createdAt' => (string) ($row['created_at'] ?? ''),
        'updatedAt' => (string) ($row['updated_at'] ?? ''),
        'lastReplyAt' => $row['last_reply_at'] ?? null,
        'lastReplyUserId' => isset($row['last_reply_user_id']) ? (int) $row['last_reply_user_id'] : null,
        'replies' => $replies,
    ];
}

function forum_fetch_thread(PDO $db, int $threadId): ?array
{
    return api_fetch_one($db, '
        SELECT
            t.*,
            c.name AS category_name,
            c.slug AS category_slug,
            u.name AS author_name,
            u.username AS author_username,
            u.eagles_id AS author_eagles_id,
            u.role_id AS author_role_id,
            u.avatar_seed AS author_avatar_seed,
            u.avatar_style AS author_avatar_style,
            ui.eagles_club AS author_club
        FROM forum_threads t
        INNER JOIN forum_categories c ON c.id = t.category_id
        INNER JOIN users u ON u.id = t.user_id
        LEFT JOIN user_info ui ON u.eagles_id = ui.eagles_id
        WHERE t.id = :id
        LIMIT 1
    ', [':id' => $threadId]);
}
