<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    if ($userId <= 0) {
        api_error('Please sign in before updating your account.', 401, [
            'authenticated' => false,
        ]);
    }

    $payload = api_request_data();
    $currentPassword = (string) ($payload['currentPassword'] ?? $payload['current_password'] ?? '');
    $newName = trim((string) ($payload['name'] ?? $payload['displayName'] ?? $payload['display_name'] ?? ''));
    $newUsername = trim((string) ($payload['username'] ?? $payload['newUsername'] ?? $payload['new_username'] ?? ''));
    $newPassword = (string) ($payload['password'] ?? $payload['newPassword'] ?? $payload['new_password'] ?? '');
    $passwordConfirm = (string) ($payload['passwordConfirm'] ?? $payload['password_confirm'] ?? '');

    if ($currentPassword === '') {
        api_error('Current password is required.', 422);
    }

    if ($newName === '' && $newUsername === '' && $newPassword === '') {
        api_error('Enter a new display name, username, or password.', 422);
    }

    $user = api_fetch_one($db, '
        SELECT id, name, username, eagles_id, password_hash, role_id
        FROM users
        WHERE id = :id
        LIMIT 1
    ', [':id' => $userId]);

    if (!$user) {
        api_error('Account not found.', 404);
    }

    $currentPasswordHash = (string) ($user['password_hash'] ?? '');
    if (!password_verify($currentPassword, $currentPasswordHash)) {
        api_error('Current password is incorrect.', 401);
    }

    $updates = [];
    $params = [':id' => $userId];
    $nameChanged = false;
    $usernameChanged = false;
    $passwordChanged = false;

    if ($newName !== '') {
        $nameLength = mb_strlen($newName);
        if ($nameLength < 2 || $nameLength > 150) {
            api_error('Display name must be 2-150 characters.', 422);
        }

        if ($newName !== (string) ($user['name'] ?? '')) {
            $updates[] = 'name = :name';
            $params[':name'] = $newName;
            $nameChanged = true;
        }
    }

    if ($newUsername !== '') {
        if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $newUsername)) {
            api_error('Username must be 4-20 characters using letters, numbers, or underscore.', 422);
        }

        if ($newUsername !== (string) ($user['username'] ?? '')) {
            $duplicate = api_fetch_one($db, '
                SELECT id
                FROM users
                WHERE username = :username
                  AND id <> :id
                LIMIT 1
            ', [
                ':username' => $newUsername,
                ':id' => $userId,
            ]);

            if ($duplicate) {
                api_error('Username is already taken.', 409);
            }

            $updates[] = 'username = :username';
            $params[':username'] = $newUsername;
            $usernameChanged = true;
        }
    }

    if ($newPassword !== '') {
        if (strlen($newPassword) < 8) {
            api_error('New password must be at least 8 characters.', 422);
        }

        if ($newPassword !== $passwordConfirm) {
            api_error('New passwords do not match.', 422);
        }

        if (password_verify($newPassword, $currentPasswordHash)) {
            api_error('New password must be different from your current password.', 422);
        }

        $updates[] = 'password_hash = :password_hash';
        $params[':password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $passwordChanged = true;
    }

    if ($updates === []) {
        api_error('No account changes were made.', 422);
    }

    api_execute(
        $db,
        'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :id',
        $params
    );

    $updatedUser = api_fetch_one($db, '
        SELECT id, name, username, eagles_id, role_id
        FROM users
        WHERE id = :id
        LIMIT 1
    ', [':id' => $userId]);

    if (!$updatedUser) {
        api_error('Unable to reload the updated account.', 500);
    }

    $_SESSION['user_name'] = (string) ($updatedUser['name'] ?? '');
    $_SESSION['username'] = (string) ($updatedUser['username'] ?? '');
    session_regenerate_id(true);

    $message = $nameChanged && !$usernameChanged && !$passwordChanged
        ? 'Display name updated successfully.'
        : ($usernameChanged && !$nameChanged && !$passwordChanged
            ? 'Username updated successfully.'
            : (!$nameChanged && !$usernameChanged && $passwordChanged
                ? 'Password updated successfully.'
                : 'Account updated successfully.'));

    api_json([
        'success' => true,
        'authenticated' => true,
        'message' => $message,
        'data' => [
            'id' => (int) ($updatedUser['id'] ?? 0),
            'name' => (string) ($updatedUser['name'] ?? ''),
            'username' => (string) ($updatedUser['username'] ?? ''),
            'eaglesId' => (string) ($updatedUser['eagles_id'] ?? ''),
            'roleId' => (int) ($updatedUser['role_id'] ?? 0),
        ],
    ]);
} catch (Throwable $error) {
    api_handle_exception($error, 'Forum account update API error', 'Unable to update your account right now.');
}
