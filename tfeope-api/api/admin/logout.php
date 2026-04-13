<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

api_start();
api_require_method('POST');

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', '', false, true);
}

session_destroy();

api_json([
    'ok' => true,
    'message' => 'Logged out successfully.',
]);
