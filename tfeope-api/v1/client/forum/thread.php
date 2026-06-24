<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/_helpers.php';
api_start();
api_require_method('GET');

$db = api_db();
user_ensure_avatar_columns($db);
$userId = (int)($_SESSION['user_id'] ?? 0); // 0 = guest, still allowed to read

$threadId = (int)($_GET['id'] ?? 0);
if ($threadId === 0) { api_error('Thread ID required.', 400); }

$hasImageColumn = api_has_column($db, 'forum_threads', 'image');
$imageSelect = $hasImageColumn ? 't.image,' : '';

forum_ensure_thread_reactions($db);

$thread = api_fetch_one($db, "
    SELECT
        t.id, t.category_id, t.title, t.body, $imageSelect t.is_pinned, t.is_locked,
        t.views, t.reply_count, t.created_at,
        u.id AS author_id, u.username AS author_username, u.name AS author_name,
        u.eagles_id AS author_eagles_id, u.role_id AS author_role_id,
        u.avatar_seed AS author_avatar_seed, u.avatar_style AS author_avatar_style,
        ui.eagles_firstName, ui.eagles_lastName, ui.eagles_club, ui.eagles_position,
        c.name AS category_name, c.slug AS category_slug,
        (SELECT COUNT(*) FROM forum_thread_reactions tr WHERE tr.thread_id = t.id AND tr.type = 'approve') AS approve_count,
        (SELECT COUNT(*) FROM forum_thread_reactions tr WHERE tr.thread_id = t.id AND tr.type = 'disapprove') AS disapprove_count,
        (SELECT tr.type FROM forum_thread_reactions tr WHERE tr.thread_id = t.id AND tr.user_id = :uid LIMIT 1) AS my_reaction
    FROM forum_threads t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN user_info ui ON u.eagles_id = ui.eagles_id
    JOIN forum_categories c ON t.category_id = c.id
    WHERE t.id = :id LIMIT 1
", [':id' => $threadId, ':uid' => $userId]);

if (!$thread) { api_error('Thread not found.', 404); }

// Increment views
api_execute($db, "UPDATE forum_threads SET views = views + 1 WHERE id = :id", [':id' => $threadId]);

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Use PHP interpolation for LIMIT/OFFSET — avoids PDO string-binding issue with integer LIMIT
$posts = api_fetch_all($db, "
    SELECT
        p.id, p.body, p.parent_post_id, p.like_count, p.created_at, p.updated_at, p.is_deleted,
        u.id AS author_id, u.username AS author_username, u.name AS author_name,
        u.eagles_id AS author_eagles_id, u.role_id AS author_role_id,
        u.avatar_seed AS author_avatar_seed, u.avatar_style AS author_avatar_style,
        ui.eagles_firstName, ui.eagles_lastName, ui.eagles_club, ui.eagles_position, ui.eagles_pic,
        (SELECT COUNT(*) FROM forum_reactions r WHERE r.post_id = p.id AND r.user_id = :uid) AS user_liked
    FROM forum_posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN user_info ui ON u.eagles_id = ui.eagles_id
    WHERE p.thread_id = :tid
    ORDER BY p.created_at ASC
    LIMIT $limit OFFSET $offset
", [':tid' => $threadId, ':uid' => $userId]);

$total = (int)(api_fetch_one($db, "
    SELECT COUNT(*) AS cnt FROM forum_posts WHERE thread_id = :tid
", [':tid' => $threadId])['cnt'] ?? 0);

$postData = array_map(function($row) {
    $body = $row['is_deleted'] ? '[This post has been deleted.]' : $row['body'];
    return [
        'id'           => (int)$row['id'],
        'body'         => $body,
        'isDeleted'    => (bool)$row['is_deleted'],
        'parentPostId' => $row['parent_post_id'] ? (int)$row['parent_post_id'] : null,
        'likeCount'    => (int)$row['like_count'],
        'userLiked'    => (bool)$row['user_liked'],
        'createdAt'    => $row['created_at'],
        'author' => [
            'id'       => (int)$row['author_id'],
            'username' => $row['author_username'],
            'name'     => $row['author_name'],
            'roleId'   => (int)($row['author_role_id'] ?? 0),
            'avatarSeed' => user_avatar_seed([
                'id' => $row['author_id'] ?? 0,
                'username' => $row['author_username'] ?? '',
                'avatar_seed' => $row['author_avatar_seed'] ?? '',
            ]),
            'avatarStyle' => user_avatar_style($row['author_avatar_style'] ?? null),
            'isMember' => trim((string)($row['author_eagles_id'] ?? '')) !== ''
                || trim((string)($row['eagles_club'] ?? '')) !== '',
            'club'     => $row['eagles_club'] ?? null,
            'position' => $row['eagles_position'] ?? null,
        ],
    ];
}, $posts);

api_json([
    'success' => true,
    'thread'  => [
        'id'           => (int)$thread['id'],
        'categoryId'   => (int)$thread['category_id'],
        'categoryName' => $thread['category_name'],
        'categorySlug' => $thread['category_slug'],
        'title'        => $thread['title'],
        'body'         => $thread['body'],
        'image'        => (!empty($thread['image'])) ? api_media_url('media', (string)$thread['image']) : null,
        'isPinned'     => (bool)$thread['is_pinned'],
        'isLocked'     => (bool)$thread['is_locked'],
        'views'        => (int)$thread['views'],
        'replyCount'   => (int)$thread['reply_count'],
        'approveCount'    => (int)($thread['approve_count'] ?? 0),
        'disapproveCount' => (int)($thread['disapprove_count'] ?? 0),
        'myReaction'      => (!empty($thread['my_reaction'])) ? (string)$thread['my_reaction'] : null,
        'createdAt'    => $thread['created_at'],
        'author' => [
            'id'       => (int)$thread['author_id'],
            'username' => $thread['author_username'],
            'name'     => $thread['author_name'],
            'roleId'   => (int)($thread['author_role_id'] ?? 0),
            'avatarSeed' => user_avatar_seed([
                'id' => $thread['author_id'] ?? 0,
                'username' => $thread['author_username'] ?? '',
                'avatar_seed' => $thread['author_avatar_seed'] ?? '',
            ]),
            'avatarStyle' => user_avatar_style($thread['author_avatar_style'] ?? null),
            'isMember' => trim((string)($thread['author_eagles_id'] ?? '')) !== ''
                || trim((string)($thread['eagles_club'] ?? '')) !== '',
            'club'     => $thread['eagles_club'] ?? null,
            'position' => $thread['eagles_position'] ?? null,
        ],
    ],
    'posts' => $postData,
    'total' => $total,
    'page'  => $page,
    'pages' => (int)ceil($total / $limit),
]);
