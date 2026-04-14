<?php
require_once '../../../bootstrap.php';
api_start();
api_require_method('POST');

$db = api_db();
$payload = api_request_data();

$username = trim((string) ($payload['username'] ?? ''));
$password = (string) ($payload['password'] ?? '');

if ($username === '' || $password === '') {
    api_error('Please enter your username and password.');
}

$user = api_fetch_one($db, '
    SELECT id, name, username, password_hash, role_id
    FROM users
    WHERE username = :username
    LIMIT 1
', [':username' => $username]);

if (!$user || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
    api_error('Invalid username or password.', 401);
}

$_SESSION['user_id'] = (int) ($user['id'] ?? 0);
$_SESSION['user_name'] = (string) ($user['name'] ?? '');
$_SESSION['username'] = (string) ($user['username'] ?? '');
$_SESSION['role_id'] = (int) ($user['role_id'] ?? 0);

api_json([
    'success' => true,
    'message' => 'Signed in successfully.',
    'data' => [
        'id' => (int) ($user['id'] ?? 0),
        'name' => (string) ($user['name'] ?? ''),
        'username' => (string) ($user['username'] ?? ''),
        'roleId' => (int) ($user['role_id'] ?? 0),
    ],
]);
