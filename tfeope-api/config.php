<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

if (!function_exists('api_config')) {
    function api_config(): array
    {
        static $config = null;

        if (is_array($config)) {
            return $config;
        }

        $config = [
            'db' => [
                'host' => 'localhost',
                'user' => 'root',
                'pass' => '',
                'name' => 'tfoepeinc_data',
                'port' => 3306,
                'charset' => 'utf8mb4',
            ],
            'base_url' => 'http://localhost/tfeope-api',
            'uploads_root' => __DIR__ . '/uploads',
            'legacy_storage_root' => __DIR__ . '/storage',
            'allowed_origins' => [
                'http://localhost',
                'http://127.0.0.1',
                'http://localhost:3000',
                'http://127.0.0.1:3000',
                'http://localhost:4173',
                'http://127.0.0.1:4173',
                'http://localhost:5173',
                'http://127.0.0.1:5173',
                'http://localhost:5174',   // ← dagdag (admin)
                'http://127.0.0.1:5174',   // ← dagdag (admin)
                'http://localhost:5175',   // ← dagdag (admin)
                'http://127.0.0.1:5175',   // ← dagdag (admin)
            ],
        ];

        return $config;
    }
}

if (!function_exists('api_is_https')) {
    function api_is_https(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https === 'on' || $https === '1') {
            return true;
        }

        return (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }
}

if (!function_exists('api_allowed_origin')) {
    function api_allowed_origin(?string $origin = null): ?string
    {
        $origin = trim((string) ($origin ?? ($_SERVER['HTTP_ORIGIN'] ?? '')));
        if ($origin === '') {
            return null;
        }

        $parsedOrigin = parse_url($origin);
        if (
            !is_array($parsedOrigin)
            || empty($parsedOrigin['scheme'])
            || empty($parsedOrigin['host'])
        ) {
            return null;
        }

        $config = api_config();
        $allowedOrigins = $config['allowed_origins'] ?? [];
        if (in_array($origin, $allowedOrigins, true)) {
            return $origin;
        }

        $originHost = strtolower((string) $parsedOrigin['host']);
        $serverHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $serverHost = preg_replace('/:\d+$/', '', $serverHost ?? '') ?? '';

        if ($serverHost !== '' && $originHost === $serverHost) {
            return $origin;
        }

        if (in_array($originHost, ['localhost', '127.0.0.1'], true)) {
            return $origin;
        }

        return null;
    }
}

if (!function_exists('api_apply_cors_headers')) {
    function api_apply_cors_headers(): void
    {
        $allowedOrigin = api_allowed_origin();
        if ($allowedOrigin !== null) {
            header('Access-Control-Allow-Origin: ' . $allowedOrigin);
            header('Access-Control-Allow-Credentials: true');
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');
        header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    }
}

if (!function_exists('api_pdo')) {
    function api_pdo(): PDO
    {
        static $pdo = null;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $config = api_config();
        $db = $config['db'];

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $db['host'],
            (int) $db['port'],
            $db['name'],
            $db['charset']
        );

        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $pdo;
    }
}

return api_config();
