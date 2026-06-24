<?php

declare(strict_types=1);

require_once '../../../bootstrap.php';

api_start();
api_require_method('POST');

if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION = [];

    if ((bool) ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path']     ?? '/',
            'domain'   => $params['domain']   ?? '',
            'secure'   => (bool) ($params['secure']   ?? false),
            'httponly' => (bool) ($params['httponly']  ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

api_json([
    'success' => true,
    'message' => 'Signed out successfully.',
]);
