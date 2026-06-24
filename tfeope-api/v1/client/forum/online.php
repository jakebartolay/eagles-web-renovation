<?php
declare(strict_types=1);
require_once '../../../bootstrap.php';
api_start();
api_require_method('GET');

$db = api_db();

// Count members only — exclude staff/admin roles (1 = Super Admin, 2 = Admin, 3 = Maintenance).
$totalMembers = api_has_column($db, 'users', 'role_id')
    ? (int)(api_fetch_one($db, 'SELECT COUNT(*) AS cnt FROM users WHERE role_id NOT IN (1, 2, 3)')['cnt'] ?? 0)
    : (int)(api_fetch_one($db, 'SELECT COUNT(*) AS cnt FROM users')['cnt'] ?? 0);
$totalPosts   = (int)(api_fetch_one($db, 'SELECT COUNT(*) AS cnt FROM forum_posts WHERE is_deleted = 0')['cnt'] ?? 0);
$totalThreads = (int)(api_fetch_one($db, 'SELECT COUNT(*) AS cnt FROM forum_threads')['cnt'] ?? 0);

// Users active in the last 10 minutes
$onlineRows = [];
try {
    $onlineRows = api_fetch_all($db, "
        SELECT u.id, u.username, u.name, ui.eagles_club
        FROM users u
        LEFT JOIN user_info ui ON u.eagles_id = ui.eagles_id
        WHERE u.last_seen > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ORDER BY u.last_seen DESC
        LIMIT 30
    ");
} catch (Throwable $_) {
    // last_seen column may not exist yet — return empty list
}

api_json([
    'success'      => true,
    'onlineCount'  => count($onlineRows),
    'online'       => array_map(fn($u) => [
        'id'       => (int)$u['id'],
        'username' => (string)$u['username'],
        'name'     => (string)$u['name'],
        'club'     => $u['eagles_club'] ?? null,
    ], $onlineRows),
    'totalMembers' => $totalMembers,
    'totalPosts'   => $totalPosts,
    'totalThreads' => $totalThreads,
]);
