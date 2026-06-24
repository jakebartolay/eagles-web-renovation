<?php
require_once '../../../bootstrap.php';
require_once __DIR__ . '/../users/_avatar_helpers.php';
api_start();

$db = api_db();
user_ensure_avatar_columns($db);
$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    api_json([
        'success' => true,
        'authenticated' => false,
        'data' => null,
    ]);
}

$user = api_fetch_one($db, '
    SELECT id, name, username, eagles_id, role_id, avatar_seed, avatar_style
    FROM users
    WHERE id = :id
    LIMIT 1
', [':id' => $userId]);

if (!$user) {
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['username'], $_SESSION['role_id']);

    api_json([
        'success' => true,
        'authenticated' => false,
        'data' => null,
    ]);
}

// Touch last_seen for online tracking (ignore if column not yet added)
try {
    api_execute($db, 'UPDATE users SET last_seen = NOW() WHERE id = :id', [':id' => $userId]);
} catch (Throwable $_) {}

api_json([
    'success' => true,
    'authenticated' => true,
    'data' => [
        'id' => (int) ($user['id'] ?? 0),
        'name' => (string) ($user['name'] ?? ''),
        'username' => (string) ($user['username'] ?? ''),
        'eaglesId' => (string) ($user['eagles_id'] ?? ''),
        'roleId' => (int) ($user['role_id'] ?? 0),
        'avatarSeed' => user_avatar_seed($user),
        'avatarStyle' => user_avatar_style($user['avatar_style'] ?? null),
    ],
]);
