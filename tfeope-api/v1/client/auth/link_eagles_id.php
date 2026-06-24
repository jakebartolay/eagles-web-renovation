<?php
require_once '../../../bootstrap.php';
api_start();
api_require_method('POST');

$db = api_db();
$payload = api_request_data();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$eaglesId = strtoupper(trim((string) ($payload['eaglesId'] ?? $payload['eagles_id'] ?? $payload['memberId'] ?? '')));

if ($userId <= 0) {
    api_error('Please sign in before linking an Eagles ID.', 401, [
        'authenticated' => false,
    ]);
}

if ($eaglesId === '' || !preg_match('/^TFOEPE[0-9]{8}$/', $eaglesId)) {
    api_error('ID is invalid.');
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
    api_error('Eagles ID not found. Please contact your chapter officer.', 404);
}

$duplicate = api_fetch_one($db, '
    SELECT id
    FROM users
    WHERE eagles_id = :eagles_id
      AND id <> :id
    LIMIT 1
', [
    ':eagles_id' => $eaglesId,
    ':id' => $userId,
]);

if ($duplicate) {
    api_error('This Eagles ID is already linked to another account.', 409);
}

api_execute($db, '
    UPDATE users
    SET eagles_id = :eagles_id
    WHERE id = :id
', [
    ':eagles_id' => $eaglesId,
    ':id' => $userId,
]);

$user = api_fetch_one($db, '
    SELECT id, name, username, eagles_id, role_id
    FROM users
    WHERE id = :id
    LIMIT 1
', [':id' => $userId]);

if (!$user) {
    api_error('Unable to reload your account after linking Eagles ID.', 500);
}

$_SESSION['eagles_id'] = (string) ($user['eagles_id'] ?? '');

api_json([
    'success' => true,
    'message' => 'Eagles ID linked successfully.',
    'authenticated' => true,
    'data' => [
        'id' => (int) ($user['id'] ?? 0),
        'name' => (string) ($user['name'] ?? ''),
        'username' => (string) ($user['username'] ?? ''),
        'eaglesId' => (string) ($user['eagles_id'] ?? ''),
        'roleId' => (int) ($user['role_id'] ?? 0),
    ],
]);
