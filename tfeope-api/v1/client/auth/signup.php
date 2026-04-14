<?php
require_once '../../../bootstrap.php';
api_start();
api_require_method('POST');

$db = api_db();
$payload = api_request_data();

$name = trim((string) ($payload['name'] ?? ''));
$username = trim((string) ($payload['username'] ?? ''));
$eaglesId = strtoupper(trim((string) ($payload['eaglesId'] ?? '')));
$password = (string) ($payload['password'] ?? '');
$passwordConfirm = (string) ($payload['passwordConfirm'] ?? '');

if ($name === '' || $username === '' || $eaglesId === '' || $password === '' || $passwordConfirm === '') {
    api_error('Please complete all fields.');
}

if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $username)) {
    api_error('Username must be 4-20 characters using letters, numbers, or underscore.');
}

if (!preg_match('/^TFOEPE[0-9]{8}$/', $eaglesId)) {
    api_error('ID is invalid.');
}

if ($password !== $passwordConfirm) {
    api_error('Passwords do not match.');
}

if (strlen($password) < 8) {
    api_error('Password must be at least 8 characters.');
}

if (!api_table_exists($db, 'user_info')) {
    api_error('Member records are unavailable right now.', 500);
}

$member = api_fetch_one($db, '
    SELECT eagles_id
    FROM user_info
    WHERE eagles_id = :eagles_id
    LIMIT 1
', [':eagles_id' => $eaglesId]);

if (!$member) {
    api_error('Eagles ID not found. Please contact your chapter officer.');
}

$existingUsername = api_fetch_one($db, '
    SELECT id
    FROM users
    WHERE username = :username
    LIMIT 1
', [':username' => $username]);

if ($existingUsername) {
    api_error('Username is already taken.');
}

$existingEaglesId = api_fetch_one($db, '
    SELECT id
    FROM users
    WHERE eagles_id = :eagles_id
    LIMIT 1
', [':eagles_id' => $eaglesId]);

if ($existingEaglesId) {
    api_error('This Eagles ID is already linked to an account.');
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

api_execute($db, '
    INSERT INTO users (name, username, eagles_id, password_hash, role_id)
    VALUES (:name, :username, :eagles_id, :password_hash, :role_id)
', [
    ':name' => $name,
    ':username' => $username,
    ':eagles_id' => $eaglesId,
    ':password_hash' => $passwordHash,
    ':role_id' => 4,
]);

api_json([
    'success' => true,
    'message' => 'Account created. You can sign in now.',
], 201);
