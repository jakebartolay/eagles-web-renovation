<?php
require_once '../../../bootstrap.php';
require_once __DIR__ . '/../users/_avatar_helpers.php';
api_start();
api_require_method('POST');

$db = api_db();
user_ensure_avatar_columns($db);
$payload = api_request_data();

$credential = trim((string) (
    $payload['username']
    ?? $payload['eaglesId']
    ?? $payload['memberId']
    ?? $payload['id']
    ?? ''
));
$password = (string) ($payload['password'] ?? '');

if ($credential === '' || $password === '') {
    api_error('Please enter your username and password.');
}

$eaglesId = strtoupper($credential);

$user = api_fetch_one($db, '
    SELECT id, name, username, eagles_id, password_hash, role_id, avatar_seed, avatar_style
    FROM users
    WHERE username = :credential
       OR eagles_id = :eagles_id
    LIMIT 1
', [
    ':credential' => $credential,
    ':eagles_id' => $eaglesId,
]);

if (!$user || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
    api_error('Invalid username or password.', 401);
}

$roleId = (int) ($user['role_id'] ?? 0);

session_regenerate_id(true);

$_SESSION['user_id'] = (int) ($user['id'] ?? 0);
$_SESSION['user_name'] = (string) ($user['name'] ?? '');
$_SESSION['username'] = (string) ($user['username'] ?? '');
$_SESSION['eagles_id'] = (string) ($user['eagles_id'] ?? '');
$_SESSION['role_id'] = $roleId;

api_json([
    'success' => true,
    'message' => 'Signed in successfully.',
    'data' => [
        'id' => (int) ($user['id'] ?? 0),
        'name' => (string) ($user['name'] ?? ''),
        'username' => (string) ($user['username'] ?? ''),
        'eaglesId' => (string) ($user['eagles_id'] ?? ''),
        'roleId' => $roleId,
        'avatarSeed' => user_avatar_seed($user),
        'avatarStyle' => user_avatar_style($user['avatar_style'] ?? null),
    ],
]);
