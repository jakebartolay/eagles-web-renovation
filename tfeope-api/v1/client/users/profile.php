<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../forum/_helpers.php';
require_once __DIR__ . '/_avatar_helpers.php';

api_start();
api_require_method('GET');

try {
    $db = api_db();
    user_ensure_avatar_columns($db);
    forum_ensure_thread_reactions($db);
    $username = trim((string) ($_GET['username'] ?? ''));
    $viewerId = (int) ($_SESSION['user_id'] ?? 0);

    if ($username === '') {
        api_error('Username is required.', 422);
    }

    if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $username)) {
        api_error('Invalid username.', 422);
    }

    $regionalPositionSelect = api_member_regional_position_select($db);
    $lastSeenSelect = api_has_column($db, 'users', 'last_seen')
        ? 'u.last_seen'
        : 'NULL AS last_seen';
    $threadImageSelect = api_has_column($db, 'forum_threads', 'image')
        ? 't.image'
        : 'NULL AS image';

    $user = api_fetch_one($db, "
        SELECT
            u.id,
            u.name,
            u.username,
            u.eagles_id,
            u.role_id,
            u.avatar_seed,
            u.avatar_style,
            u.created_at,
            $lastSeenSelect,
            ui.eagles_status,
            ui.eagles_firstName,
            ui.eagles_lastName,
            ui.eagles_position,
            ui.eagles_club,
            ui.eagles_region,
            $regionalPositionSelect,
            ui.eagles_pic,
            ui.eagles_dateAdded
        FROM users u
        LEFT JOIN user_info ui ON u.eagles_id = ui.eagles_id
        WHERE u.username = :username
        LIMIT 1
    ", [':username' => $username]);

    if (!$user) {
        api_error('User not found.', 404);
    }

    $userId = (int) $user['id'];

    $stats = api_fetch_one($db, '
        SELECT
            (SELECT COUNT(*) FROM forum_threads WHERE user_id = :thread_user_id) AS thread_count,
            (SELECT COUNT(*) FROM forum_posts WHERE user_id = :comment_user_id AND is_deleted = 0) AS comment_count
    ', [
        ':thread_user_id' => $userId,
        ':comment_user_id' => $userId,
    ]) ?? [];

    $recentThreads = api_fetch_all($db, "
        SELECT
            t.id,
            t.title,
            t.body,
            $threadImageSelect,
            t.views,
            t.reply_count,
            t.created_at,
            c.name AS category_name,
            c.slug AS category_slug,
            (SELECT COUNT(*) FROM forum_thread_reactions tr WHERE tr.thread_id = t.id AND tr.type = 'approve') AS approve_count,
            (SELECT COUNT(*) FROM forum_thread_reactions tr WHERE tr.thread_id = t.id AND tr.type = 'disapprove') AS disapprove_count,
            (SELECT tr.type FROM forum_thread_reactions tr WHERE tr.thread_id = t.id AND tr.user_id = :viewer_id LIMIT 1) AS my_reaction
        FROM forum_threads t
        INNER JOIN forum_categories c ON c.id = t.category_id
        WHERE t.user_id = :user_id
        ORDER BY t.created_at DESC
        LIMIT 12
    ", [
        ':user_id' => $userId,
        ':viewer_id' => $viewerId,
    ]);

    $fullName = trim((string) ($user['eagles_firstName'] ?? '') . ' ' . (string) ($user['eagles_lastName'] ?? ''));
    if ($fullName === '') {
        $fullName = (string) ($user['name'] ?? $user['username']);
    }

    $photoFile = basename(trim((string) ($user['eagles_pic'] ?? '')));

    api_json([
        'success' => true,
        'data' => [
            'id' => $userId,
            'name' => (string) ($user['name'] ?? ''),
            'username' => (string) ($user['username'] ?? ''),
            'roleId' => (int) ($user['role_id'] ?? 0),
            'avatarSeed' => user_avatar_seed($user),
            'avatarStyle' => user_avatar_style($user['avatar_style'] ?? null),
            'displayName' => $fullName,
            'joinedAt' => (string) ($user['created_at'] ?? ''),
            'lastSeenAt' => $user['last_seen'] ?? null,
            'member' => [
                'linked' => trim((string) ($user['eagles_id'] ?? '')) !== ''
                    || trim((string) ($user['eagles_status'] ?? '')) !== ''
                    || trim((string) ($user['eagles_club'] ?? '')) !== '',
                'status' => (string) ($user['eagles_status'] ?? ''),
                'position' => (string) ($user['eagles_position'] ?? ''),
                'regionalPosition' => api_member_regional_position_value($user),
                'club' => (string) ($user['eagles_club'] ?? ''),
                'region' => (string) ($user['eagles_region'] ?? ''),
                'photoUrl' => $photoFile !== '' ? api_media_url('media', $photoFile) : null,
                'dateAdded' => (string) ($user['eagles_dateAdded'] ?? ''),
            ],
            'stats' => [
                'threadCount' => (int) ($stats['thread_count'] ?? 0),
                'commentCount' => (int) ($stats['comment_count'] ?? 0),
            ],
            'threads' => array_map(static fn (array $thread): array => [
                'id' => (int) ($thread['id'] ?? 0),
                'title' => (string) ($thread['title'] ?? ''),
                'body' => (string) ($thread['body'] ?? ''),
                'image' => trim((string) ($thread['image'] ?? '')) !== ''
                    ? api_media_url('media', (string) $thread['image'])
                    : null,
                'views' => (int) ($thread['views'] ?? 0),
                'replyCount' => (int) ($thread['reply_count'] ?? 0),
                'approveCount' => (int) ($thread['approve_count'] ?? 0),
                'disapproveCount' => (int) ($thread['disapprove_count'] ?? 0),
                'myReaction' => (isset($thread['my_reaction']) && (string) $thread['my_reaction'] !== '')
                    ? (string) $thread['my_reaction']
                    : null,
                'createdAt' => (string) ($thread['created_at'] ?? ''),
                'categoryName' => (string) ($thread['category_name'] ?? ''),
                'categorySlug' => (string) ($thread['category_slug'] ?? ''),
            ], $recentThreads),
        ],
    ]);
} catch (Throwable $error) {
    error_log('Public user profile API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to load this profile right now.',
    ], 500);
}
