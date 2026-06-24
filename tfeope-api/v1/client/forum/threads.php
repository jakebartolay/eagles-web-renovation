<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/_helpers.php';

api_start();
api_require_method(['GET', 'POST']);

try {
    $db = api_db();
    forum_require_tables($db);
    forum_ensure_thread_reactions($db);
    user_ensure_avatar_columns($db);

    if (api_request_method() === 'POST') {
        $userId = forum_require_user();
        $payload = api_request_data();
        $title = trim((string) ($payload['title'] ?? ''));
        $body = trim((string) ($payload['body'] ?? ''));
        $categoryId = (int) ($payload['categoryId'] ?? $payload['category_id'] ?? 0);
        $categorySlug = trim((string) ($payload['category'] ?? $payload['categorySlug'] ?? $payload['category_slug'] ?? ''));

        if ($title === '' || $body === '') {
            api_error('Title and message are required.', 422);
        }

        if (mb_strlen($title) > 255) {
            api_error('Title is too long.', 422);
        }

        if ($categoryId > 0) {
            $category = api_fetch_one($db, '
                SELECT id, name, slug
                FROM forum_categories
                WHERE id = :id
                LIMIT 1
            ', [':id' => $categoryId]);
        } else {
            $category = api_fetch_one($db, '
                SELECT id, name, slug
                FROM forum_categories
                WHERE slug = :slug
                LIMIT 1
            ', [':slug' => $categorySlug]);
        }

        if (!$category) {
            api_error('Please choose a valid forum category.', 422);
        }

        forum_guard_thread_category_permission($db, $userId, $category);

        $categoryId = (int) $category['id'];
        $slug = forum_unique_thread_slug($db, $categoryId, $title);

        api_execute($db, '
            INSERT INTO forum_threads (
                category_id,
                user_id,
                title,
                slug,
                body
            ) VALUES (
                :category_id,
                :user_id,
                :title,
                :slug,
                :body
            )
        ', [
            ':category_id' => $categoryId,
            ':user_id' => $userId,
            ':title' => $title,
            ':slug' => $slug,
            ':body' => $body,
        ]);

        $threadId = (int) $db->lastInsertId();
        $thread = forum_fetch_thread($db, $threadId);

        api_json([
            'ok' => true,
            'message' => 'Discussion posted.',
            'data' => $thread ? forum_format_thread($thread) : null,
        ], 201);
    }

    // Filter by category slug or id if provided
    $filterSlug = trim((string)($_GET['category'] ?? ''));
    $filterCatId = (int)($_GET['categoryId'] ?? 0);

    $categoryRow = null;
    $whereClause = '';
    $whereParams = [];

    if ($filterSlug !== '') {
        $categoryRow = api_fetch_one($db, 'SELECT * FROM forum_categories WHERE slug = :s LIMIT 1', [':s' => $filterSlug]);
        if ($categoryRow) {
            $whereClause = 'WHERE t.category_id = :cid';
            $whereParams[':cid'] = (int)$categoryRow['id'];
        }
    } elseif ($filterCatId > 0) {
        $categoryRow = api_fetch_one($db, 'SELECT * FROM forum_categories WHERE id = :id LIMIT 1', [':id' => $filterCatId]);
        if ($categoryRow) {
            $whereClause = 'WHERE t.category_id = :cid';
            $whereParams[':cid'] = $filterCatId;
        }
    }

    $viewerId = (int)($_SESSION['user_id'] ?? 0);

    $rows = api_fetch_all($db, "
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
            ui.eagles_club AS author_club,
            (SELECT COUNT(*) FROM forum_thread_reactions tr WHERE tr.thread_id = t.id AND tr.type = 'approve') AS approve_count,
            (SELECT COUNT(*) FROM forum_thread_reactions tr WHERE tr.thread_id = t.id AND tr.type = 'disapprove') AS disapprove_count,
            (SELECT tr.type FROM forum_thread_reactions tr WHERE tr.thread_id = t.id AND tr.user_id = :viewer LIMIT 1) AS my_reaction
        FROM forum_threads t
        INNER JOIN forum_categories c ON c.id = t.category_id
        INNER JOIN users u ON u.id = t.user_id
        LEFT JOIN user_info ui ON u.eagles_id = ui.eagles_id
        $whereClause
        ORDER BY
            t.is_pinned DESC,
            COALESCE(t.last_reply_at, t.created_at) DESC,
            t.created_at DESC
        LIMIT 100
    ", array_merge($whereParams, [':viewer' => $viewerId]));

    $formatted = array_map(static fn (array $row): array => forum_format_thread($row), $rows);

    api_json([
        'ok' => true,
        'category' => $categoryRow ? [
            'id'          => (int)$categoryRow['id'],
            'name'        => (string)$categoryRow['name'],
            'slug'        => (string)$categoryRow['slug'],
            'description' => (string)($categoryRow['description'] ?? ''),
        ] : null,
        'data' => $formatted,
    ]);
} catch (Throwable $error) {
    error_log('Forum threads API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to load forum discussions right now.',
    ], 500);
}
