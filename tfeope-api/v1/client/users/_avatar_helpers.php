<?php

declare(strict_types=1);

function user_ensure_avatar_columns(PDO $db): void
{
    if (!api_has_column($db, 'users', 'avatar_seed')) {
        try {
            $db->exec('ALTER TABLE users ADD COLUMN avatar_seed VARCHAR(120) NULL AFTER role_id');
        } catch (Throwable $error) {
            error_log('Add users.avatar_seed failed: ' . $error->getMessage());
        }
    }

    if (!api_has_column($db, 'users', 'avatar_style')) {
        try {
            $db->exec('ALTER TABLE users ADD COLUMN avatar_style VARCHAR(60) NULL AFTER avatar_seed');
        } catch (Throwable $error) {
            error_log('Add users.avatar_style failed: ' . $error->getMessage());
        }
    }
}

function user_avatar_allowed_styles(): array
{
    return [
        'adventurer-neutral',
        'avataaars',
        'bottts-neutral',
        'lorelei',
        'notionists',
        'pixel-art',
    ];
}

function user_avatar_style(?string $style): string
{
    $style = strtolower(trim((string) $style));
    return in_array($style, user_avatar_allowed_styles(), true) ? $style : 'adventurer-neutral';
}

function user_avatar_seed(array $user): string
{
    $seed = trim((string) ($user['avatar_seed'] ?? ''));
    if ($seed !== '') {
        return $seed;
    }

    $id = (int) ($user['id'] ?? 0);
    $username = trim((string) ($user['username'] ?? 'user'));
    return 'user-' . ($id > 0 ? $id : md5($username)) . '-' . $username;
}

function user_avatar_payload(array $user): array
{
    return [
        'avatarSeed' => user_avatar_seed($user),
        'avatarStyle' => user_avatar_style($user['avatar_style'] ?? null),
    ];
}

