<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (!function_exists('api_start')) {
    function api_start(): void
    {
        static $started = false;

        if ($started) {
            return;
        }

        $started = true;

        api_apply_cors_headers();

        if (api_request_method() === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('TFEOPESESSID');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => api_is_https(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }
}

if (!function_exists('api_request_method')) {
    function api_request_method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }
}

if (!function_exists('api_ini_size_to_bytes')) {
    function api_ini_size_to_bytes(string $value): int
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return 0;
        }

        $unit = strtolower(substr($trimmed, -1));
        $number = (float) $trimmed;

        return match ($unit) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
    }
}

if (!function_exists('api_post_too_large')) {
    function api_post_too_large(): bool
    {
        if (api_request_method() !== 'POST') {
            return false;
        }

        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $postMaxSize = api_ini_size_to_bytes((string) ini_get('post_max_size'));

        return $contentLength > 0 && $postMaxSize > 0 && $contentLength > $postMaxSize;
    }
}

if (!function_exists('api_require_method')) {
    function api_require_method(string|array $methods): void
    {
        $allowedMethods = is_array($methods) ? $methods : [$methods];
        $allowedMethods = array_map(
            static fn (string $method): string => strtoupper($method),
            $allowedMethods
        );

        if (!in_array(api_request_method(), $allowedMethods, true)) {
            api_json([
                'ok' => false,
                'message' => 'Method not allowed.',
                'allowedMethods' => $allowedMethods,
            ], 405);
        }
    }
}

if (!function_exists('api_json')) {
    function api_json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        $success = (bool) ($payload['success'] ?? $payload['ok'] ?? ($status < 400));
        $payload['success'] = $success;
        $payload['ok'] = $success;

        echo json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        exit;
    }
}

if (!function_exists('api_error')) {
    function api_error(string $message, int $status = 400, array $extra = []): never
    {
        api_json(array_merge([
            'ok' => false,
            'message' => $message,
        ], $extra), $status);
    }
}

if (!function_exists('api_is_database_error')) {
    function api_is_database_error(Throwable $error): bool
    {
        $current = $error;
        $markers = [
            'sqlstate',
            'mysql',
            'mariadb',
            'unknown database',
            'access denied for user',
            'connection refused',
            'could not find driver',
            'server has gone away',
        ];

        while ($current instanceof Throwable) {
            if ($current instanceof PDOException) {
                return true;
            }

            $message = strtolower($current->getMessage());
            foreach ($markers as $marker) {
                if ($message !== '' && str_contains($message, $marker)) {
                    return true;
                }
            }

            $current = $current->getPrevious();
        }

        return false;
    }
}

if (!function_exists('api_public_exception_details')) {
    function api_public_exception_details(
        Throwable $error,
        string $fallbackMessage,
        int $fallbackStatus = 500
    ): array {
        if (api_is_database_error($error)) {
            return [
                'status' => 503,
                'payload' => [
                    'success' => false,
                    'message' => 'Server unavailable right now. Please try again later.',
                    'code' => 'DATABASE_UNAVAILABLE',
                ],
            ];
        }

        return [
            'status' => $fallbackStatus,
            'payload' => [
                'success' => false,
                'message' => $fallbackMessage,
            ],
        ];
    }
}

if (!function_exists('api_handle_exception')) {
    function api_handle_exception(
        Throwable $error,
        string $logContext,
        string $fallbackMessage,
        int $fallbackStatus = 500,
        array $extraPayload = []
    ): never {
        error_log($logContext . ': ' . $error->getMessage());

        $details = api_public_exception_details($error, $fallbackMessage, $fallbackStatus);

        api_json(
            array_merge($details['payload'], $extraPayload),
            (int) ($details['status'] ?? $fallbackStatus)
        );
    }
}

if (!function_exists('api_db')) {
    function api_db(): PDO
    {
        return api_pdo();
    }
}

if (!function_exists('api_read_json')) {
    function api_read_json(bool $allowEmpty = true): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return $allowEmpty ? [] : api_error('JSON payload is required.', 400);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            api_error('Invalid JSON payload.', 400);
        }

        return $decoded;
    }
}

if (!function_exists('api_request_data')) {
    function api_request_data(): array
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            return api_read_json();
        }

        if (!empty($_POST) && is_array($_POST)) {
            return $_POST;
        }

        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        parse_str($raw, $parsed);
        return is_array($parsed) ? $parsed : [];
    }
}

if (!function_exists('api_fetch_one')) {
    function api_fetch_one(PDO $db, string $sql, array $params = []): ?array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('api_fetch_all')) {
    function api_fetch_all(PDO $db, string $sql, array $params = []): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('api_execute')) {
    function api_execute(PDO $db, string $sql, array $params = []): bool
    {
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }
}

if (!function_exists('api_quote_identifier')) {
    function api_quote_identifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}

if (!function_exists('api_table_columns')) {
    function api_table_columns(PDO $db, string $table): array
    {
        static $cache = [];
        $cacheKey = spl_object_id($db) . ':' . $table;

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        if (!api_table_exists($db, $table)) {
            $cache[$cacheKey] = [];
            return [];
        }

        $rows = api_fetch_all($db, 'DESCRIBE ' . api_quote_identifier($table));
        $cache[$cacheKey] = array_values(array_map(
            static fn (array $row): string => (string) ($row['Field'] ?? ''),
            $rows
        ));

        return $cache[$cacheKey];
    }
}

if (!function_exists('api_has_column')) {
    function api_has_column(PDO $db, string $table, string $column): bool
    {
        return in_array($column, api_table_columns($db, $table), true);
    }
}

if (!function_exists('api_first_column')) {
    function api_first_column(PDO $db, string $table, array $candidates): ?string
    {
        $columns = api_table_columns($db, $table);

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }
}

if (!function_exists('api_table_exists')) {
    function api_table_exists(PDO $db, string $table): bool
    {
        $row = api_fetch_one($db, '
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = :table
            LIMIT 1
        ', [':table' => $table]);

        return $row !== null;
    }
}

if (!function_exists('api_base_path')) {
    function api_base_path(): string
    {
        static $basePath = null;

        if (is_string($basePath)) {
            return $basePath;
        }

        $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: '';
        $apiRoot = realpath(__DIR__) ?: __DIR__;

        $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
        $apiRoot = rtrim(str_replace('\\', '/', $apiRoot), '/');

        if ($documentRoot !== '' && str_starts_with($apiRoot, $documentRoot)) {
            $relative = substr($apiRoot, strlen($documentRoot));
            $basePath = $relative === false || $relative === '' ? '' : $relative;

            return $basePath;
        }

        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $marker = '/tfeope-api/';
        $position = strpos($scriptName, $marker);

        if ($position !== false) {
            $basePath = '/tfeope-api';
            return $basePath;
        }

        $basePath = '/tfeope-api';
        return $basePath;
    }
}

if (!function_exists('api_base_url')) {
    function api_base_url(): string
    {
        $config = api_config();
        $configuredBaseUrl = rtrim(trim((string) ($config['base_url'] ?? '')), '/');
        if ($configuredBaseUrl !== '') {
            return $configuredBaseUrl;
        }

        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return api_base_path();
        }

        $scheme = api_is_https() ? 'https://' : 'http://';
        return $scheme . $host . api_base_path();
    }
}

if (!function_exists('api_upload_root')) {
    function api_upload_root(): string
    {
        $config = api_config();
        return rtrim(str_replace('\\', '/', $config['uploads_root']), '/');
    }
}

if (!function_exists('api_legacy_storage_root')) {
    function api_legacy_storage_root(): string
    {
        $config = api_config();
        return rtrim(str_replace('\\', '/', $config['legacy_storage_root']), '/');
    }
}

if (!function_exists('api_upload_directories')) {
    function api_upload_directories(): array
    {
        $legacy = api_legacy_storage_root();
        $uploadsBase = $legacy . '/uploads';
        $root = $uploadsBase;

        return [
            'news' => $uploadsBase . '/news',
            'members' => $uploadsBase . '/members',
            'videos' => $uploadsBase . '/videos',
            'memorandum' => $uploadsBase . '/memorandum',
            'national-officers' => $uploadsBase . '/national-officers',
            'media'  => $root . '/media',    // ← string lang, hindi array!
            'uploads' => $uploadsBase,
        ];
    }
}

if (!function_exists('api_storage_groups')) {
    function api_storage_groups(): array
    {
        $uploads = api_upload_directories();
        $root = api_upload_root();
        $legacy = api_legacy_storage_root();

        return [
            'news' => [
                $uploads['news'],
                $uploads['uploads'],
                $legacy . '/news_images',
            ],
            'videos' => [
                $uploads['videos'],
                $root . '/videos',
                $legacy . '/videos',
            ],
            'members' => [
                $uploads['members'],
            ],
            'national-officers' => [
                $uploads['national-officers'],
            ],
            'media' => [
                $uploads['media'],
                $uploads['members'],
                $uploads['national-officers'],
                $legacy . '/memorandum',
                $legacy . '/event_media',
                $legacy . '/uploads',
                $legacy . '/videos_thumbnail',
                $legacy . '/officers',    // ← dito dapat!
            ],
            'news_images' => [
                $uploads['news'],
                $uploads['uploads'],
                $legacy . '/news_images',
            ],
            'memorandum' => [
                $uploads['memorandum'],
                $uploads['media'],
            ],
            'event_media' => [
                $uploads['media'],
                $legacy . '/event_media',
            ],
            'uploads' => [
                $uploads['uploads'],
                $uploads['news'],
                $legacy . '/news_images',
            ],
            'videos_thumbnail' => [
                $uploads['media'],
                $legacy . '/videos_thumbnail',
            ],
        ];
    }
}

if (!function_exists('api_ensure_directory')) {
    function api_ensure_directory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to create directory: ' . $path);
        }
    }
}

if (!function_exists('api_upload_dir')) {
    function api_upload_dir(string $group): string
    {
        $directories = api_upload_directories();

        if (!isset($directories[$group])) {
            throw new InvalidArgumentException('Invalid upload group: ' . $group);
        }

        api_ensure_directory($directories[$group]);

        return $directories[$group];
    }
}

if (!function_exists('api_media_url')) {
    function api_media_url(string $group, string $filename): ?string
    {
        $file = basename(trim($filename));
        if ($file === '' || !isset(api_storage_groups()[$group])) {
            return null;
        }

        return api_base_url() . '/media.php?group=' . rawurlencode($group) . '&file=' . rawurlencode($file);
    }
}

if (!function_exists('api_storage_file_path')) {
    function api_storage_file_path(string $group, string $filename): ?string
    {
        $groups = api_storage_groups();
        if (!isset($groups[$group])) {
            return null;
        }

        $file = basename(trim($filename));
        if ($file === '' || $file === '.' || $file === '..') {
            return null;
        }

        foreach ($groups[$group] as $directory) {
            $fullPath = rtrim(str_replace('\\', '/', $directory), '/') . '/' . $file;
            if (is_file($fullPath)) {
                return $fullPath;
            }
        }

        return null;
    }
}

if (!function_exists('api_locate_media_file')) {
    function api_locate_media_file(string|array $groups, ?string $filename): ?array
    {
        $file = basename(trim((string) $filename));
        if ($file === '') {
            return null;
        }

        foreach ((array) $groups as $group) {
            $path = api_storage_file_path($group, $file);
            if ($path !== null) {
                return [
                    'group' => $group,
                    'filename' => $file,
                    'path' => $path,
                    'url' => api_media_url($group, $file),
                ];
            }
        }

        return null;
    }
}

if (!function_exists('api_asset_url_or_fallback')) {
    function api_asset_url_or_fallback(?array $asset, string $group, ?string $filename): ?string
    {
        if (is_array($asset) && !empty($asset['url'])) {
            return (string) $asset['url'];
        }

        $file = basename(trim((string) $filename));
        if ($file === '') {
            return null;
        }

        return api_media_url($group, $file);
    }
}

if (!function_exists('api_path_is_within')) {
    function api_path_is_within(string $path, string $root): bool
    {
        $realPath = realpath($path);
        $realRoot = realpath($root);

        if ($realPath === false || $realRoot === false) {
            return false;
        }

        $normalizedPath = rtrim(str_replace('\\', '/', $realPath), '/');
        $normalizedRoot = rtrim(str_replace('\\', '/', $realRoot), '/');

        return $normalizedPath === $normalizedRoot
            || str_starts_with($normalizedPath, $normalizedRoot . '/');
    }
}

if (!function_exists('api_upload_error_message')) {
    function api_upload_error_message(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded file is too large.',
            UPLOAD_ERR_PARTIAL => 'Uploaded file was only partially received.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary upload folder.',
            UPLOAD_ERR_CANT_WRITE => 'Unable to write uploaded file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
            default => 'Unknown file upload error.',
        };
    }
}

if (!function_exists('api_image_extensions')) {
    function api_image_extensions(): array
    {
        return ['jpeg', 'jpg', 'png', 'gif', 'webp', 'jfif'];
    }
}

if (!function_exists('api_video_extensions')) {
    function api_video_extensions(): array
    {
        return ['mp4', 'webm'];
    }
}

if (!function_exists('api_allowed_extensions_for_group')) {
    function api_allowed_extensions_for_group(string $group): array
    {
        return match ($group) {
            'news' => api_image_extensions(),
            'videos' => api_video_extensions(),
            'memorandum' => api_image_extensions(),
            'media' => array_values(array_unique(array_merge(
                api_image_extensions(),
                api_video_extensions()
            ))),
            default => api_image_extensions(),
        };
    }
}

if (!function_exists('api_normalize_uploaded_files')) {
    function api_normalize_uploaded_files(array $files): array
    {
        if (!isset($files['name']) || !is_array($files['name'])) {
            return [$files];
        }

        $normalized = [];
        foreach (array_keys($files['name']) as $index) {
            $normalized[] = [
                'name' => $files['name'][$index] ?? '',
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            ];
        }

        return $normalized;
    }
}

if (!function_exists('api_request_ip')) {
    function api_request_ip(): string
    {
        $candidates = [
            (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''),
            (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            $parts = preg_split('/\s*,\s*/', $candidate);
            $ip = trim((string) ($parts[0] ?? ''));
            if ($ip !== '') {
                return substr($ip, 0, 60);
            }
        }

        return 'unknown';
    }
}

if (!function_exists('api_store_uploaded_file')) {
    function api_store_uploaded_file(array $file, string $group, ?array $allowedExtensions = null): array
    {
        $directories = api_upload_directories();
        if (!isset($directories[$group])) {
            throw new InvalidArgumentException('Invalid upload group: ' . $group);
        }

        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new RuntimeException(api_upload_error_message($errorCode));
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Uploaded file is missing.');
        }

        $targetDir = api_upload_dir($group);
        $originalName = (string) ($file['name'] ?? 'file');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = $allowedExtensions ?? api_allowed_extensions_for_group($group);

        if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException('Unsupported file type.');
        }

        $filename = str_replace('.', '', uniqid('', true));
        if ($extension !== '') {
            $filename .= '.' . $extension;
        }

        $targetPath = $targetDir . '/' . $filename;
        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new RuntimeException('Failed to save uploaded file.');
        }

        return [
            'filename' => $filename,
            'path' => $targetPath,
            'url' => api_media_url($group, $filename),
        ];
    }
}

if (!function_exists('api_store_uploaded_file_as')) {
    function api_store_uploaded_file_as(
        array $file,
        string $group,
        string $baseName,
        ?array $allowedExtensions = null,
        bool $overwrite = false
    ): array {
        $directories = api_upload_directories();
        if (!isset($directories[$group])) {
            throw new InvalidArgumentException('Invalid upload group: ' . $group);
        }

        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new RuntimeException(api_upload_error_message($errorCode));
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Uploaded file is missing.');
        }

        $targetDir = api_upload_dir($group);
        $originalName = (string) ($file['name'] ?? 'file');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = $allowedExtensions ?? api_allowed_extensions_for_group($group);

        if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException('Unsupported file type.');
        }

        $safeBaseName = trim((string) preg_replace('/[^a-zA-Z0-9_-]+/', '_', $baseName), '_');
        if ($safeBaseName === '') {
            $safeBaseName = 'file';
        }

        $filename = $safeBaseName . '.' . $extension;
        if (!$overwrite) {
            $counter = 2;

            while (is_file($targetDir . '/' . $filename)) {
                $filename = $safeBaseName . '_' . $counter . '.' . $extension;
                $counter++;
            }
        }

        $targetPath = $targetDir . '/' . $filename;
        if ($overwrite && is_file($targetPath)) {
            @unlink($targetPath);
        }

        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new RuntimeException('Failed to save uploaded file.');
        }

        return [
            'filename' => $filename,
            'path' => $targetPath,
            'url' => api_media_url($group, $filename),
        ];
    }
}

if (!function_exists('api_store_uploaded_image_as_jpeg')) {
    function api_store_uploaded_image_as_jpeg(
        array $file,
        string $group,
        string $baseName,
        int $quality = 90
    ): array {
            $directories = api_upload_directories();
            if (!isset($directories[$group])) {
                throw new InvalidArgumentException('Invalid upload group: ' . $group);
            }

            $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($errorCode !== UPLOAD_ERR_OK) {
                throw new RuntimeException(api_upload_error_message($errorCode));
            }

            $tmpName = (string) ($file['tmp_name'] ?? '');
            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                throw new RuntimeException('Uploaded file is missing.');
            }

            $originalName = (string) ($file['name'] ?? 'image');
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if ($extension === '' || !in_array($extension, api_image_extensions(), true)) {
                throw new RuntimeException('Unsupported file type.');
            }

            $imageData = file_get_contents($tmpName);
            if ($imageData === false) {
                throw new RuntimeException('Unable to read uploaded image.');
            }

            $sourceImage = @imagecreatefromstring($imageData);
            if ($sourceImage === false) {
                throw new RuntimeException('Unable to process uploaded image.');
            }

            $width = imagesx($sourceImage);
            $height = imagesy($sourceImage);
            $canvas = imagecreatetruecolor($width, $height);

            if ($canvas === false) {
                imagedestroy($sourceImage);
                throw new RuntimeException('Unable to prepare uploaded image.');
            }

            $background = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $background);
            imagecopy($canvas, $sourceImage, 0, 0, 0, 0, $width, $height);
            imagedestroy($sourceImage);

            $targetDir = api_upload_dir($group);
            $safeBaseName = trim((string) preg_replace('/[^a-zA-Z0-9_-]+/', '_', $baseName), '_');
            if ($safeBaseName === '') {
                $safeBaseName = 'image';
            }

            $filename = $safeBaseName . '.jpg';
            $counter = 2;

            while (is_file($targetDir . '/' . $filename)) {
                $filename = $safeBaseName . '_' . $counter . '.jpg';
                $counter++;
            }

            $targetPath = $targetDir . '/' . $filename;
            if (!imagejpeg($canvas, $targetPath, max(0, min(100, $quality)))) {
                imagedestroy($canvas);
                throw new RuntimeException('Failed to save uploaded image.');
            }

            imagedestroy($canvas);

            return [
                'filename' => $filename,
                'path' => $targetPath,
                'url' => api_media_url($group, $filename),
            ];
    }
}

if (!function_exists('api_delete_uploaded_file')) {
    function api_delete_uploaded_file(string $group, ?string $filename): void
    {
        $file = trim((string) $filename);
        if ($file === '') {
            return;
        }

        $fullPath = api_storage_file_path($group, $file);
        $allowedRoots = api_storage_groups()[$group] ?? [];
        if ($fullPath === null || $allowedRoots === []) {
            return;
        }

        foreach ($allowedRoots as $root) {
            if (!api_path_is_within($fullPath, $root)) {
                continue;
            }

            @unlink($fullPath);
            return;
        }
    }
}

if (!function_exists('api_excerpt')) {
    function api_excerpt(?string $value, int $limit = 180): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        if (function_exists('mb_strimwidth')) {
            return rtrim(mb_strimwidth($text, 0, $limit, '...'));
        }

        if (strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(substr($text, 0, $limit - 3)) . '...';
    }
}

if (!function_exists('api_media_type')) {
    function api_media_type(?string $filename): string
    {
        $extension = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
        if (in_array($extension, ['mp4', 'mov', 'avi', 'webm'], true)) {
            return 'video';
        }

        return 'image';
    }
}

if (!function_exists('api_current_admin')) {
    function api_current_admin(PDO $db): ?array
    {
        $userId = 0;
        if (!empty($_SESSION['user_id'])) {
            $userId = (int) $_SESSION['user_id'];
        } elseif (!empty($_SESSION['id'])) {
            $userId = (int) $_SESSION['id'];
        }

        if ($userId <= 0) {
            return null;
        }

        $user = api_fetch_one($db, '
            SELECT id, name, username, role_id
            FROM users
            WHERE id = :id
            LIMIT 1
        ', [':id' => $userId]);

        if ($user === null) {
            return null;
        }

        $roleId = (int) ($user['role_id'] ?? 0);
        if (!in_array($roleId, [1, 2], true)) {
            return null;
        }

        $user['role_label'] = $roleId === 1 ? 'Super Admin' : 'Admin';

        return $user;
    }
}

if (!function_exists('api_require_admin')) {
    function api_require_admin(PDO $db): array
    {
        $user = api_current_admin($db);
        if ($user === null) {
            api_json([
                'ok' => false,
                'message' => 'Unauthorized.',
                'authenticated' => false,
            ], 401);
        }

        return $user;
    }
}

if (!function_exists('api_log_admin_action')) {
    function api_log_admin_action(PDO $db, array $admin, string $actionType, string $description): void
    {
        if (!api_table_exists($db, 'admin_action_logs')) {
            return;
        }

        try {
            $columns = api_table_columns($db, 'admin_action_logs');
            $fieldNames = ['admin_user_id', 'admin_username', 'action_type', 'action_desc'];
            $placeholders = [':admin_user_id', ':admin_username', ':action_type', ':action_desc'];
            $params = [
                ':admin_user_id' => (int) ($admin['id'] ?? 0),
                ':admin_username' => (string) ($admin['username'] ?? ''),
                ':action_type' => strtoupper($actionType),
                ':action_desc' => $description,
            ];

            if (in_array('ip_address', $columns, true)) {
                $fieldNames[] = 'ip_address';
                $placeholders[] = ':ip_address';
                $params[':ip_address'] = api_request_ip();
            }

            api_execute($db, '
                INSERT INTO admin_action_logs (
                    ' . implode(', ', array_map('api_quote_identifier', $fieldNames)) . '
                ) VALUES (
                    ' . implode(', ', $placeholders) . '
                )
            ', $params);
        } catch (Throwable $error) {
            error_log('Unable to write admin log: ' . $error->getMessage());
        }
    }
}

if (!function_exists('api_normalize_news_status')) {
    function api_normalize_news_status(?string $status): string
    {
        $status = trim((string) $status);
        return $status === 'Published' ? 'Published' : 'Draft';
    }
}

if (!function_exists('api_normalize_publication_status')) {
    function api_normalize_publication_status(?string $status): string
    {
        return api_normalize_news_status($status);
    }
}

if (!function_exists('api_normalize_event_type')) {
    function api_normalize_event_type(?string $type): string
    {
        $type = strtolower(trim((string) $type));
        return $type === 'past' ? 'past' : 'upcoming';
    }
}

if (!function_exists('api_news_media_groups')) {
    function api_news_media_groups(): array
    {
        return ['news', 'uploads', 'news_images'];
    }
}

if (!function_exists('api_news_media_url')) {
    function api_news_media_url(?string $filename): ?string
    {
        $file = basename(trim((string) $filename));
        if ($file === '') {
            return null;
        }

        $asset = api_locate_media_file(api_news_media_groups(), $file);
        if ($asset !== null && !empty($asset['url'])) {
            return (string) $asset['url'];
        }

        return api_media_url('news', $file);
    }
}

if (!function_exists('api_member_media_groups')) {
    function api_member_media_groups(): array
    {
        return ['members', 'media'];
    }
}

if (!function_exists('api_member_photo_asset')) {
    function api_member_photo_asset(?string $filename): ?array
    {
        return api_locate_media_file(api_member_media_groups(), $filename);
    }
}

if (!function_exists('api_member_photo_link')) {
    function api_member_photo_link(?string $filename): ?string
    {
        $file = basename(trim((string) $filename));
        if ($file === '') {
            return null;
        }

        return api_media_url('members', $file);
    }
}

if (!function_exists('api_resolve_news_image_filename')) {
    function api_resolve_news_image_filename(PDO $db, array $newsRow): ?string
    {
        $imageFile = trim((string) ($newsRow['news_image'] ?? ''));
        if ($imageFile !== '' && api_locate_media_file(api_news_media_groups(), $imageFile) !== null) {
            return $imageFile;
        }

        if (!api_table_exists($db, 'news_media')) {
            return null;
        }

        $media = api_fetch_one($db, '
            SELECT file_name
            FROM news_media
            WHERE news_id = :news_id
              AND (
                  file_type = :file_type
                  OR file_type IS NULL
                  OR TRIM(file_type) = ""
              )
            ORDER BY media_id ASC
            LIMIT 1
        ', [
            ':news_id' => (int) ($newsRow['news_id'] ?? 0),
            ':file_type' => 'image',
        ]);

        $fallback = trim((string) ($media['file_name'] ?? ''));
        if ($fallback === '' || api_locate_media_file(api_news_media_groups(), $fallback) === null) {
            return null;
        }

        return $fallback;
    }
}

if (!function_exists('api_news_media_files')) {
    function api_news_media_files(PDO $db, int $newsId): array
    {
        if (!api_table_exists($db, 'news_media')) {
            return [];
        }

        $rows = api_fetch_all($db, '
            SELECT file_name
            FROM news_media
            WHERE news_id = :news_id
        ', [':news_id' => $newsId]);

        return array_values(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['file_name'] ?? '')),
            $rows
        )));
    }
}

if (!function_exists('api_news_media_items')) {
    function api_news_media_items(PDO $db, int $newsId): array
    {
        if (!api_table_exists($db, 'news_media')) {
            return [];
        }

        $rows = api_fetch_all($db, '
            SELECT media_id, file_name, file_type, created_at
            FROM news_media
            WHERE news_id = :news_id
            ORDER BY media_id ASC
        ', [':news_id' => $newsId]);

        $items = [];
        foreach ($rows as $row) {
            $filename = trim((string) ($row['file_name'] ?? ''));

            $items[] = [
                'id' => (int) ($row['media_id'] ?? 0),
                'filename' => $filename !== '' ? $filename : null,
                'fileType' => (string) ($row['file_type'] ?? api_media_type($filename)),
                'createdAt' => (string) ($row['created_at'] ?? ''),
                'url' => api_news_media_url($filename),
            ];
        }

        return $items;
    }
}

if (!function_exists('api_news_payload')) {
    function api_news_payload(PDO $db, array $newsRow): array
    {
        $newsId = (int) ($newsRow['news_id'] ?? 0);
        $imageFile = api_resolve_news_image_filename($db, $newsRow);
        $mediaItems = api_news_media_items($db, $newsId);

        return [
            'id' => $newsId,
            'title' => (string) ($newsRow['news_title'] ?? ''),
            'content' => (string) ($newsRow['news_content'] ?? ''),
            'excerpt' => api_excerpt((string) ($newsRow['news_content'] ?? ''), 180),
            'status' => (string) ($newsRow['news_status'] ?? 'Draft'),
            'createdAt' => (string) ($newsRow['created_at'] ?? ''),
            'imageFilename' => $imageFile,
            'imageUrl' => api_news_media_url($imageFile),
            'media' => $mediaItems,
        ];
    }
}

if (!function_exists('api_news_list')) {
    function api_news_list(PDO $db, bool $publishedOnly = false): array
    {
        $sql = '
            SELECT news_id, news_title, news_content, news_status, news_image, created_at
            FROM news_info
        ';

        $params = [];
        if ($publishedOnly) {
            $sql .= ' WHERE news_status = :news_status';
            $params[':news_status'] = 'Published';
        }

        $sql .= ' ORDER BY created_at DESC, news_id DESC';

        $rows = api_fetch_all($db, $sql, $params);

        return array_map(
            static fn (array $row): array => api_news_payload($db, $row),
            $rows
        );
    }
}

if (!function_exists('api_news_by_id')) {
    function api_news_by_id(PDO $db, int $newsId): ?array
    {
        $row = api_fetch_one($db, '
            SELECT news_id, news_title, news_content, news_status, news_image, created_at
            FROM news_info
            WHERE news_id = :news_id
            LIMIT 1
        ', [':news_id' => $newsId]);

        return $row !== null ? api_news_payload($db, $row) : null;
    }
}

if (!function_exists('api_video_payload')) {
    function api_video_payload(array $row): array
    {
        $videoFile = trim((string) ($row['video_file'] ?? ''));
        $thumbnailFile = trim((string) ($row['video_thumbnail'] ?? ''));
        $videoAsset = api_locate_media_file('videos', $videoFile);
        $thumbnailAsset = api_locate_media_file('media', $thumbnailFile);

        return [
            'id' => (int) ($row['video_id'] ?? 0),
            'title' => (string) ($row['video_title'] ?? ''),
            'description' => (string) ($row['video_description'] ?? ''),
            'excerpt' => api_excerpt((string) ($row['video_description'] ?? ''), 180),
            'status' => (string) ($row['video_status'] ?? 'Draft'),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'videoFilename' => $videoFile !== '' ? $videoFile : null,
            'videoUrl' => api_asset_url_or_fallback($videoAsset, 'videos', $videoFile),
            'thumbnailFilename' => $thumbnailFile !== '' ? $thumbnailFile : null,
            'thumbnailUrl' => api_asset_url_or_fallback($thumbnailAsset, 'media', $thumbnailFile),
        ];
    }
}

if (!function_exists('api_video_list')) {
    function api_video_list(PDO $db, bool $publishedOnly = false): array
    {
        if (!api_table_exists($db, 'video_info')) {
            return [];
        }

        $sql = '
            SELECT video_id, video_title, video_description, video_file, video_thumbnail, video_status, created_at
            FROM video_info
        ';
        $params = [];

        if ($publishedOnly) {
            $sql .= ' WHERE video_status = :video_status';
            $params[':video_status'] = 'Published';
        }

        $sql .= ' ORDER BY video_id DESC';

        $rows = api_fetch_all($db, $sql, $params);

        return array_map(static fn (array $row): array => api_video_payload($row), $rows);
    }
}

if (!function_exists('api_video_by_id')) {
    function api_video_by_id(PDO $db, int $videoId): ?array
    {
        if (!api_table_exists($db, 'video_info')) {
            return null;
        }

        $row = api_fetch_one($db, '
            SELECT video_id, video_title, video_description, video_file, video_thumbnail, video_status, created_at
            FROM video_info
            WHERE video_id = :video_id
            LIMIT 1
        ', [':video_id' => $videoId]);

        return $row !== null ? api_video_payload($row) : null;
    }
}

if (!function_exists('api_event_payload')) {
    function api_event_payload(array $row): array
    {
        $mediaFile = trim((string) ($row['event_media'] ?? ''));
        $mediaAsset = api_locate_media_file('media', $mediaFile);

        return [
            'id' => (int) ($row['event_id'] ?? 0),
            'title' => (string) ($row['event_title'] ?? ''),
            'description' => (string) ($row['event_description'] ?? ''),
            'date' => (string) ($row['event_date'] ?? ''),
            'type' => (string) ($row['event_type'] ?? ''),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'mediaFilename' => $mediaFile !== '' ? $mediaFile : null,
            'mediaUrl' => api_asset_url_or_fallback($mediaAsset, 'media', $mediaFile),
            'mediaType' => api_media_type($mediaFile),
        ];
    }
}

if (!function_exists('api_events_list')) {
    function api_events_list(PDO $db, ?string $type = null): array
    {
        if (!api_table_exists($db, 'events')) {
            return [];
        }

        $sql = '
            SELECT event_id, event_title, event_description, event_date, event_type, event_media, created_at
            FROM events
        ';
        $params = [];

        if ($type !== null) {
            $sql .= ' WHERE event_type = :event_type';
            $params[':event_type'] = $type;
        }

        $sql .= ' ORDER BY event_date DESC, event_id DESC';

        $rows = api_fetch_all($db, $sql, $params);

        return array_map(static fn (array $row): array => api_event_payload($row), $rows);
    }
}

if (!function_exists('api_event_by_id')) {
    function api_event_by_id(PDO $db, int $eventId): ?array
    {
        if (!api_table_exists($db, 'events')) {
            return null;
        }

        $row = api_fetch_one($db, '
            SELECT event_id, event_title, event_description, event_date, event_type, event_media, created_at
            FROM events
            WHERE event_id = :event_id
            LIMIT 1
        ', [':event_id' => $eventId]);

        return $row !== null ? api_event_payload($row) : null;
    }
}

if (!function_exists('api_memorandum_field_map')) {
    function api_memorandum_field_map(PDO $db): array
    {
        return [
            'title' => api_first_column($db, 'memorandum', ['memo_title', 'title']) ?? 'memo_title',
            'status' => api_first_column($db, 'memorandum', ['memo_status', 'status']) ?? 'memo_status',
            'description' => api_first_column($db, 'memorandum', ['memo_description', 'description']),
        ];
    }
}

if (!function_exists('api_memorandum_page_field_map')) {
    function api_memorandum_page_field_map(PDO $db): array
    {
        return [
            'file' => api_first_column($db, 'memorandum_pages', ['page_image', 'file_name']) ?? 'page_image',
            'order' => api_first_column($db, 'memorandum_pages', ['page_number', 'page_id']) ?? 'page_id',
        ];
    }
}

if (!function_exists('api_memorandum_payload')) {
    function api_memorandum_payload(PDO $db, array $memoRow): array
    {
        $pageMap = api_memorandum_page_field_map($db);
        $orderColumn = api_quote_identifier($pageMap['order']);
        $fileColumn = api_quote_identifier($pageMap['file']);

        $pageRows = api_fetch_all($db, '
            SELECT page_id, ' . $fileColumn . ' AS api_file, ' . $orderColumn . ' AS api_order
            FROM memorandum_pages
            WHERE memo_id = :memo_id
            ORDER BY ' . $orderColumn . ' ASC, page_id ASC
        ', [':memo_id' => (int) ($memoRow['memo_id'] ?? 0)]);

        $pages = [];
        foreach ($pageRows as $pageRow) {
            $filename = trim((string) ($pageRow['api_file'] ?? ''));
            $asset = api_locate_media_file(['memorandum', 'media'], $filename);

            $pages[] = [
                'id' => (int) ($pageRow['page_id'] ?? 0),
                'filename' => $filename !== '' ? $filename : null,
                'pageNumber' => (int) ($pageRow['api_order'] ?? 0),
                'url' => api_asset_url_or_fallback($asset, 'memorandum', $filename),
            ];
        }

        return [
            'id' => (int) ($memoRow['memo_id'] ?? 0),
            'title' => (string) ($memoRow['api_title'] ?? ''),
            'description' => (string) ($memoRow['api_description'] ?? ''),
            'status' => (string) ($memoRow['api_status'] ?? 'Draft'),
            'createdAt' => (string) ($memoRow['created_at'] ?? ''),
            'coverUrl' => $pages[0]['url'] ?? null,
            'pages' => $pages,
            'pageUrls' => array_values(array_filter(array_map(
                static fn (array $page): ?string => $page['url'] ?? null,
                $pages
            ))),
        ];
    }
}

if (!function_exists('api_memorandum_list')) {
    function api_memorandum_list(PDO $db, bool $publishedOnly = false): array
    {
        if (!api_table_exists($db, 'memorandum') || !api_table_exists($db, 'memorandum_pages')) {
            return [];
        }

        $map = api_memorandum_field_map($db);
        $titleColumn = api_quote_identifier($map['title']);
        $statusColumn = api_quote_identifier($map['status']);
        $descriptionSelect = $map['description'] !== null
            ? api_quote_identifier($map['description']) . ' AS api_description'
            : '"" AS api_description';

        $sql = '
            SELECT memo_id,
                   ' . $titleColumn . ' AS api_title,
                   ' . $statusColumn . ' AS api_status,
                   ' . $descriptionSelect . ',
                   created_at
            FROM memorandum
        ';
        $params = [];

        if ($publishedOnly) {
            $sql .= ' WHERE ' . $statusColumn . ' = :memo_status';
            $params[':memo_status'] = 'Published';
        }

        $sql .= ' ORDER BY memo_id DESC';

        $rows = api_fetch_all($db, $sql, $params);

        return array_map(static fn (array $row): array => api_memorandum_payload($db, $row), $rows);
    }
}

if (!function_exists('api_memorandum_by_id')) {
    function api_memorandum_by_id(PDO $db, int $memoId): ?array
    {
        if (!api_table_exists($db, 'memorandum')) {
            return null;
        }

        $map = api_memorandum_field_map($db);
        $titleColumn = api_quote_identifier($map['title']);
        $statusColumn = api_quote_identifier($map['status']);
        $descriptionSelect = $map['description'] !== null
            ? api_quote_identifier($map['description']) . ' AS api_description'
            : '"" AS api_description';

        $row = api_fetch_one($db, '
            SELECT memo_id,
                   ' . $titleColumn . ' AS api_title,
                   ' . $statusColumn . ' AS api_status,
                   ' . $descriptionSelect . ',
                   created_at
            FROM memorandum
            WHERE memo_id = :memo_id
            LIMIT 1
        ', [':memo_id' => $memoId]);

        return $row !== null ? api_memorandum_payload($db, $row) : null;
    }
}

if (!function_exists('api_officer_payload')) {
    function api_officer_payload(array $row): array
    {
        $imageFile = trim((string) ($row['image'] ?? ''));
        $speechImageFile = trim((string) ($row['speech_image'] ?? ''));
        $imageAsset = api_locate_media_file(['national-officers', 'media'], $imageFile);
        $speechImageAsset = api_locate_media_file(['national-officers', 'media'], $speechImageFile);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'position' => (string) ($row['position'] ?? ''),
            'fullPosition' => (string) ($row['full_position'] ?? $row['position'] ?? ''),
            'category' => (string) ($row['category'] ?? ''),
            'speech' => (string) ($row['speech'] ?? ''),
            'imageFilename' => $imageFile !== '' ? $imageFile : null,
            'imageUrl' => api_asset_url_or_fallback($imageAsset, 'national-officers', $imageFile),
            'speechImageFilename' => $speechImageFile !== '' ? $speechImageFile : null,
            'speechImageUrl' => api_asset_url_or_fallback($speechImageAsset, 'national-officers', $speechImageFile),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}

if (!function_exists('api_officer_list')) {
    function api_officer_list(PDO $db): array
    {
        if (!api_table_exists($db, 'officers')) {
            return [];
        }

        $rows = api_fetch_all($db, '
            SELECT id, name, position, full_position, category, image, speech_image, speech, created_at, updated_at
            FROM officers
            ORDER BY created_at DESC, id DESC
        ');

        return array_map(static fn (array $row): array => api_officer_payload($row), $rows);
    }
}

if (!function_exists('api_officer_by_id')) {
    function api_officer_by_id(PDO $db, int $officerId): ?array
    {
        if (!api_table_exists($db, 'officers')) {
            return null;
        }

        $row = api_fetch_one($db, '
            SELECT id, name, position, full_position, category, image, speech_image, speech, created_at, updated_at
            FROM officers
            WHERE id = :id
            LIMIT 1
        ', [':id' => $officerId]);

        return $row !== null ? api_officer_payload($row) : null;
    }
}

if (!function_exists('api_governor_field_map')) {
    function api_governor_field_map(PDO $db): array
    {
        return [
            'governor' => api_first_column($db, 'governors', ['governor_name', 'name']) ?? 'governor_name',
            'region' => api_first_column($db, 'regions', ['region_name', 'name']) ?? 'region_name',
            'club' => api_first_column($db, 'clubs', ['club_name', 'name']) ?? 'club_name',
            'president' => api_first_column($db, 'presidents', ['president_name', 'name']) ?? 'president_name',
        ];
    }
}

if (!function_exists('api_governor_list')) {
    function api_governor_list(PDO $db): array
    {
        if (!api_table_exists($db, 'governors')) {
            return [];
        }

        $map = api_governor_field_map($db);
        $governorName = api_quote_identifier($map['governor']);
        $regionName = api_quote_identifier($map['region']);
        $clubName = api_quote_identifier($map['club']);
        $presidentName = api_quote_identifier($map['president']);
        $governorImageSelect = api_has_column($db, 'governors', 'governor_image')
            ? ', governor_image'
            : ', NULL AS governor_image';

        $governors = api_fetch_all($db, '
            SELECT governor_id, ' . $governorName . ' AS governor_name' . $governorImageSelect . '
            FROM governors
            ORDER BY ' . $governorName . ' ASC
        ');
        $regions = api_table_exists($db, 'regions')
            ? api_fetch_all($db, '
                SELECT region_id, governor_id, ' . $regionName . ' AS region_name
                FROM regions
                ORDER BY ' . $regionName . ' ASC
            ')
            : [];
        $clubs = api_table_exists($db, 'clubs')
            ? api_fetch_all($db, '
                SELECT club_id, region_id, governor_id, ' . $clubName . ' AS club_name
                FROM clubs
                ORDER BY ' . $clubName . ' ASC
            ')
            : [];
        $presidents = api_table_exists($db, 'presidents')
            ? api_fetch_all($db, '
                SELECT president_id, club_id, governor_id, ' . $presidentName . ' AS president_name
                FROM presidents
                ORDER BY ' . $presidentName . ' ASC
            ')
            : [];

        $regionsByGovernor = [];
        foreach ($regions as $region) {
            $regionsByGovernor[(int) ($region['governor_id'] ?? 0)][] = $region;
        }

        $clubsByRegion = [];
        foreach ($clubs as $club) {
            $clubsByRegion[(int) ($club['region_id'] ?? 0)][] = $club;
        }

        $presidentsByClub = [];
        foreach ($presidents as $president) {
            $presidentsByClub[(int) ($president['club_id'] ?? 0)][] = $president;
        }

        $items = [];
        foreach ($governors as $governor) {
            $governorId = (int) ($governor['governor_id'] ?? 0);
            $imageFile = trim((string) ($governor['governor_image'] ?? ''));
            $imageAsset = api_locate_media_file('media', $imageFile);

            $regionItems = [];
            foreach ($regionsByGovernor[$governorId] ?? [] as $region) {
                $regionId = (int) ($region['region_id'] ?? 0);
                $clubItems = [];

                foreach ($clubsByRegion[$regionId] ?? [] as $club) {
                    $clubId = (int) ($club['club_id'] ?? 0);
                    $clubItems[] = [
                        'id' => $clubId,
                        'name' => (string) ($club['club_name'] ?? ''),
                        'presidents' => array_map(
                            static fn (array $president): array => [
                                'id' => (int) ($president['president_id'] ?? 0),
                                'name' => (string) ($president['president_name'] ?? ''),
                            ],
                            $presidentsByClub[$clubId] ?? []
                        ),
                    ];
                }

                $regionItems[] = [
                    'id' => $regionId,
                    'name' => (string) ($region['region_name'] ?? ''),
                    'clubs' => $clubItems,
                ];
            }

            $items[] = [
                'id' => $governorId,
                'name' => (string) ($governor['governor_name'] ?? ''),
                'imageFilename' => $imageFile !== '' ? $imageFile : null,
                'imageUrl' => $imageAsset['url'] ?? null,
                'regions' => $regionItems,
            ];
        }

        return $items;
    }
}

if (!function_exists('api_governor_by_id')) {
    function api_governor_by_id(PDO $db, int $governorId): ?array
    {
        foreach (api_governor_list($db) as $governor) {
            if ((int) ($governor['id'] ?? 0) === $governorId) {
                return $governor;
            }
        }

        return null;
    }
}

if (!function_exists('api_magna_carta_list')) {
    function api_magna_carta_list(PDO $db): array
    {
        if (!api_table_exists($db, 'magna_carta_items')) {
            return [];
        }

        $selects = ['id'];
        foreach (['title', 'subtitle', 'description', 'content', 'image_path', 'created_at', 'updated_at', 'is_active'] as $column) {
            if (api_has_column($db, 'magna_carta_items', $column)) {
                $selects[] = $column;
            }
        }

        $sql = 'SELECT ' . implode(', ', array_map('api_quote_identifier', $selects)) . ' FROM magna_carta_items';
        if (api_has_column($db, 'magna_carta_items', 'is_active')) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= api_has_column($db, 'magna_carta_items', 'created_at')
            ? ' ORDER BY created_at ASC, id ASC'
            : ' ORDER BY id ASC';

        $rows = api_fetch_all($db, $sql);

        return array_map(static function (array $row): array {
            $imageFile = trim((string) ($row['image_path'] ?? ''));
            $imageAsset = api_locate_media_file('media', $imageFile);

            return [
                'id' => (int) ($row['id'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
                'subtitle' => (string) ($row['subtitle'] ?? ''),
                'content' => (string) ($row['content'] ?? $row['description'] ?? ''),
                'description' => (string) ($row['description'] ?? $row['content'] ?? ''),
                'imageFilename' => $imageFile !== '' ? $imageFile : null,
                'imageUrl' => $imageAsset['url'] ?? null,
                'createdAt' => (string) ($row['created_at'] ?? ''),
                'updatedAt' => (string) ($row['updated_at'] ?? ''),
            ];
        }, $rows);
    }
}
