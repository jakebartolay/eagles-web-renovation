<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/_helpers.php';

api_start();
api_require_method('GET');

try {
    $db = api_db();

    if (!api_table_exists($db, 'forum_categories')) {
        api_json([
            'ok' => true,
            'data' => [],
        ]);
    }

    $rows = api_fetch_all($db, "
        SELECT
            fc.id,
            fc.name,
            fc.slug,
            fc.description,
            fc.icon,
            fc.sort_order,
            fc.thread_count,
            COALESCE(
                (SELECT SUM(ft.reply_count)
                 FROM forum_threads ft
                 WHERE ft.category_id = fc.id),
                0
            ) AS post_count,
            COALESCE(
                (SELECT COUNT(DISTINCT ft2.user_id)
                 FROM forum_threads ft2
                 WHERE ft2.category_id = fc.id),
                0
            ) AS member_count,
            (SELECT MAX(COALESCE(ft3.last_reply_at, ft3.created_at))
             FROM forum_threads ft3
             WHERE ft3.category_id = fc.id) AS last_activity_at,
            (SELECT u.username
             FROM forum_threads ft4
             INNER JOIN users u ON u.id = COALESCE(ft4.last_reply_user_id, ft4.user_id)
             WHERE ft4.category_id = fc.id
             ORDER BY COALESCE(ft4.last_reply_at, ft4.created_at) DESC
             LIMIT 1) AS last_poster
        FROM forum_categories fc
        WHERE fc.is_private = 0
        ORDER BY fc.sort_order ASC, fc.id ASC
    ");

    api_json([
        'ok' => true,
        'data' => array_map(static fn (array $row): array => [
            'id'             => (int)($row['id'] ?? 0),
            'name'           => (string)($row['name'] ?? ''),
            'slug'           => (string)($row['slug'] ?? ''),
            'description'    => (string)($row['description'] ?? ''),
            'icon'           => (string)($row['icon'] ?? '💬'),
            'sortOrder'      => (int)($row['sort_order'] ?? 0),
            'threadCount'    => (int)($row['thread_count'] ?? 0),
            'postCount'      => (int)($row['post_count'] ?? 0),
            'memberCount'    => (int)($row['member_count'] ?? 0),
            'lastActivityAt' => $row['last_activity_at'] ?? null,
            'lastPoster'     => $row['last_poster'] ?? null,
        ], $rows),
    ]);
} catch (Throwable $error) {
    error_log('Forum categories API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to load forum categories right now.',
    ], 500);
}
