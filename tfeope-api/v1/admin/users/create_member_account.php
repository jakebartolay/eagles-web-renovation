<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

try {
    $db  = api_db();
    $admin = api_require_admin($db);

    // Any admin (role 1 or 2) can create member portal accounts
    $payload  = api_request_data();
    $eaglesId = strtoupper(trim((string) ($payload['eagles_id'] ?? $payload['eaglesId'] ?? '')));
    $username = trim((string) ($payload['username'] ?? ''));
    $password = (string) ($payload['password'] ?? '');
    $passwordConfirm = (string) ($payload['password_confirm'] ?? $payload['passwordConfirm'] ?? '');

    // ── Validate inputs ──────────────────────────────────────────────
    if ($eaglesId === '' || $username === '' || $password === '') {
        api_json(['ok' => false, 'message' => 'Eagles ID, username, and password are required.'], 422);
        return;
    }

    if (!preg_match('/^TFOEPE[0-9]{8}$/', $eaglesId)) {
        api_json(['ok' => false, 'message' => 'Eagles ID format is invalid. Expected: TFOEPE + 8 digits.'], 422);
        return;
    }

    if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $username)) {
        api_json(['ok' => false, 'message' => 'Username must be 4–20 characters (letters, numbers, underscore).'], 422);
        return;
    }

    if (strlen($password) < 8) {
        api_json(['ok' => false, 'message' => 'Password must be at least 8 characters.'], 422);
        return;
    }

    if ($passwordConfirm !== '' && $password !== $passwordConfirm) {
        api_json(['ok' => false, 'message' => 'Passwords do not match.'], 422);
        return;
    }

    // ── Verify Eagles ID exists in user_info ─────────────────────────
    if (!api_table_exists($db, 'user_info')) {
        api_json(['ok' => false, 'message' => 'Member records table is unavailable.'], 500);
        return;
    }

    $member = api_fetch_one($db, '
        SELECT eagles_id, eagles_firstName, eagles_lastName, eagles_status
        FROM user_info
        WHERE eagles_id = :id
        LIMIT 1
    ', [':id' => $eaglesId]);

    if (!$member) {
        api_json(['ok' => false, 'message' => 'Eagles ID not found in member records.'], 404);
        return;
    }

    // ── Check for duplicate username ─────────────────────────────────
    if (!api_table_exists($db, 'users')) {
        api_json(['ok' => false, 'message' => 'Users table is unavailable.'], 500);
        return;
    }

    $dupUsername = api_fetch_one($db, '
        SELECT id FROM users WHERE username = :username LIMIT 1
    ', [':username' => $username]);

    if ($dupUsername) {
        api_json(['ok' => false, 'message' => 'Username is already taken.'], 409);
        return;
    }

    // ── Check Eagles ID not already linked ───────────────────────────
    $dupEaglesId = api_fetch_one($db, '
        SELECT id FROM users WHERE eagles_id = :eagles_id LIMIT 1
    ', [':eagles_id' => $eaglesId]);

    if ($dupEaglesId) {
        api_json(['ok' => false, 'message' => 'This Eagles ID is already linked to a portal account.'], 409);
        return;
    }

    // ── Create account ───────────────────────────────────────────────
    $firstName = trim((string) ($member['eagles_firstName'] ?? ''));
    $lastName  = trim((string) ($member['eagles_lastName'] ?? ''));
    $fullName  = trim($firstName . ' ' . $lastName);
    if ($fullName === '') {
        $fullName = $eaglesId;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    api_execute($db, '
        INSERT INTO users (name, username, password_hash, role_id, eagles_id)
        VALUES (:name, :username, :password_hash, :role_id, :eagles_id)
    ', [
        ':name'          => $fullName,
        ':username'      => $username,
        ':password_hash' => $passwordHash,
        ':role_id'       => 0,
        ':eagles_id'     => $eaglesId,
    ]);

    $newId = (int) $db->lastInsertId();

    api_log_admin_action(
        $db,
        $admin,
        'CREATE',
        sprintf('Created member portal account for "%s" (%s)', $fullName, $eaglesId)
    );

    api_json([
        'ok'      => true,
        'message' => 'Member portal account created successfully.',
        'data'    => [
            'id'       => $newId,
            'name'     => $fullName,
            'username' => $username,
            'eaglesId' => $eaglesId,
            'roleId'   => 0,
            'roleLabel' => 'Member',
        ],
    ], 201);

} catch (Throwable $error) {
    error_log('Admin create member account error: ' . $error->getMessage());
    api_json(['ok' => false, 'message' => 'Unable to create member account right now.'], 500);
}
