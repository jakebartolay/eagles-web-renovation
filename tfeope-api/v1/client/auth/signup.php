<?php
require_once '../../../bootstrap.php';
api_start();
api_require_method('POST');

$db = api_db();
$payload = api_request_data();

$name = trim((string) ($payload['name'] ?? ''));
$username = trim((string) ($payload['username'] ?? ''));
$password = (string) ($payload['password'] ?? '');
$passwordConfirm = (string) ($payload['passwordConfirm'] ?? '');

if ($name === '' || $username === '' || $password === '' || $passwordConfirm === '') {
    api_error('Please complete all fields.');
}

if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $username)) {
    api_error('Username must be 4-20 characters using letters, numbers, or underscore.');
}

if ($password !== $passwordConfirm) {
    api_error('Passwords do not match.');
}

if (strlen($password) < 8) {
    api_error('Password must be at least 8 characters.');
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

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

api_execute($db, '
    INSERT INTO users (name, username, eagles_id, password_hash, role_id)
    VALUES (:name, :username, :eagles_id, :password_hash, :role_id)
', [
    ':name' => $name,
    ':username' => $username,
    ':eagles_id' => null,
    ':password_hash' => $passwordHash,
    ':role_id' => 4,
]);

api_json([
    'success' => true,
    'message' => 'Account created. You can sign in now.',
], 201);
