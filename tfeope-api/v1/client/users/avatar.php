<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/_avatar_helpers.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    user_ensure_avatar_columns($db);

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        api_error('Please sign in to update your avatar.', 401);
    }

    $payload = api_request_data();
    $seed = trim((string) ($payload['seed'] ?? $payload['avatarSeed'] ?? ''));
    $style = user_avatar_style($payload['style'] ?? $payload['avatarStyle'] ?? null);

    if ($seed === '') {
        $seed = 'user-' . $userId . '-' . bin2hex(random_bytes(8));
    }

    $seed = preg_replace('/[^a-zA-Z0-9_.:-]+/', '-', $seed) ?? '';
    $seed = substr(trim($seed, '-'), 0, 120);

    if ($seed === '') {
        $seed = 'user-' . $userId . '-' . bin2hex(random_bytes(6));
    }

    api_execute($db, '
        UPDATE users
        SET avatar_seed = :seed,
            avatar_style = :style
        WHERE id = :id
    ', [
        ':seed' => $seed,
        ':style' => $style,
        ':id' => $userId,
    ]);

    api_json([
        'success' => true,
        'message' => 'Avatar updated.',
        'data' => [
            'avatarSeed' => $seed,
            'avatarStyle' => $style,
        ],
    ]);
} catch (Throwable $error) {
    error_log('User avatar update API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to update avatar right now.',
    ], 500);
}

