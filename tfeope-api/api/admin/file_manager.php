<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

api_start();

const FILE_MANAGER_MAX_EDIT_BYTES = 1048576;
const FILE_MANAGER_MAX_UPLOAD_BYTES = 52428800;

function file_manager_json_error(string $message, int $status = 400): never
{
    api_json([
        'ok' => false,
        'message' => $message,
    ], $status);
}

function file_manager_is_super_admin(PDO $db): array
{
    $admin = api_require_admin($db);
    if ((int) ($admin['role_id'] ?? 0) !== 1) {
        file_manager_json_error('Only super admins can access file management.', 403);
    }

    return $admin;
}

function file_manager_slash_path(string $path): string
{
    return rtrim(str_replace('\\', '/', $path), '/');
}

function file_manager_root_entries(): array
{
    $apiRoot = realpath(__DIR__ . '/../..') ?: (__DIR__ . '/../..');
    $workspaceRoot = dirname($apiRoot);
    $config = api_config();

    $candidates = [
        'api' => [
            'label' => 'API Project',
            'path' => $apiRoot,
        ],
        'uploads' => [
            'label' => 'Uploads Storage',
            'path' => (string) ($config['uploads_root'] ?? ($apiRoot . '/storage/uploads')),
        ],
        'public-site' => [
            'label' => 'Public Site',
            'path' => $workspaceRoot . '/tfeope',
        ],
        'admin-site' => [
            'label' => 'Admin Site',
            'path' => $workspaceRoot . '/tfeope-admin',
        ],
    ];

    $cpanelDomains = [
        'dev.tfoepe-inc.com.ph' => 'Dev Domain',
        'api.tfoepe-inc.com.ph' => 'API Domain',
        'admin.tfoepe-inc.com.ph' => 'Admin Domain',
        'public_html' => 'Main public_html',
    ];
    foreach ($cpanelDomains as $folder => $label) {
        $candidates['domain-' . preg_replace('/[^a-z0-9]+/i', '-', $folder)] = [
            'label' => $label . ' (' . $folder . ')',
            'path' => $workspaceRoot . '/' . $folder,
        ];
    }

    foreach (scandir($workspaceRoot) ?: [] as $folder) {
        if ($folder === '.' || $folder === '..') {
            continue;
        }

        if (!preg_match('/tfoepe.*\.ph$/i', $folder)) {
            continue;
        }

        $id = 'domain-' . trim((string) preg_replace('/[^a-z0-9]+/i', '-', strtolower($folder)), '-');
        if (!isset($candidates[$id])) {
            $candidates[$id] = [
                'label' => 'Domain (' . $folder . ')',
                'path' => $workspaceRoot . '/' . $folder,
            ];
        }
    }

    $extraRoots = trim((string) getenv('TFEOPE_FILE_MANAGER_ROOTS'));
    if ($extraRoots !== '') {
        foreach (preg_split('/[|;]/', $extraRoots) ?: [] as $index => $rootPath) {
            $rootPath = trim((string) $rootPath);
            if ($rootPath !== '') {
                $candidates['custom-' . ($index + 1)] = [
                    'label' => 'Custom Root ' . ($index + 1),
                    'path' => $rootPath,
                ];
            }
        }
    }

    $roots = [];
    $seenPaths = [];
    foreach ($candidates as $id => $candidate) {
        $realPath = realpath((string) $candidate['path']);
        if ($realPath === false || !is_dir($realPath)) {
            continue;
        }

        $normalizedRealPath = file_manager_slash_path($realPath);
        if (isset($seenPaths[$normalizedRealPath])) {
            continue;
        }

        $seenPaths[$normalizedRealPath] = true;
        $roots[$id] = [
            'id' => $id,
            'label' => (string) $candidate['label'],
            'path' => $normalizedRealPath,
        ];
    }

    return $roots;
}

function file_manager_root(string $rootId): array
{
    $roots = file_manager_root_entries();
    if (empty($roots)) {
        file_manager_json_error('No readable file roots are configured.', 500);
    }

    if ($rootId === '') {
        return reset($roots);
    }

    if (!isset($roots[$rootId])) {
        file_manager_json_error('Selected file root is not available.', 404);
    }

    return $roots[$rootId];
}

function file_manager_clean_relative_path(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    $path = preg_replace('#/+#', '/', $path) ?? '';
    $path = trim($path, '/');

    if ($path === '') {
        return '';
    }

    $parts = [];
    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }

        if ($part === '..') {
            file_manager_json_error('Invalid path.', 422);
        }

        $parts[] = $part;
    }

    return implode('/', $parts);
}

function file_manager_absolute_path(array $root, string $relativePath, bool $mustExist = true): string
{
    $base = (string) $root['path'];
    $relativePath = file_manager_clean_relative_path($relativePath);
    $candidate = $relativePath === '' ? $base : $base . '/' . $relativePath;

    if ($mustExist) {
        $realPath = realpath($candidate);
        if ($realPath === false) {
            file_manager_json_error('File or folder was not found.', 404);
        }
    } else {
        $parent = dirname($candidate);
        $parentReal = realpath($parent);
        if ($parentReal === false || !is_dir($parentReal)) {
            file_manager_json_error('Target folder was not found.', 404);
        }

        $realPath = file_manager_slash_path($parentReal) . '/' . basename($candidate);
    }

    $realPath = file_manager_slash_path($realPath);
    if ($realPath !== $base && !str_starts_with($realPath, $base . '/')) {
        file_manager_json_error('Path is outside the allowed file root.', 403);
    }

    return $realPath;
}

function file_manager_relative_from_root(array $root, string $absolutePath): string
{
    $base = (string) $root['path'];
    $absolutePath = file_manager_slash_path($absolutePath);
    if ($absolutePath === $base) {
        return '';
    }

    return ltrim(substr($absolutePath, strlen($base)), '/');
}

function file_manager_is_sensitive_name(string $name): bool
{
    $name = strtolower(trim($name));
    return in_array($name, [
        '.env',
        '.htaccess',
        '.user.ini',
        'config.php',
        'composer.lock',
        'package-lock.json',
    ], true);
}

function file_manager_extension(string $path): string
{
    return strtolower(pathinfo($path, PATHINFO_EXTENSION));
}

function file_manager_is_executable_extension(string $path): bool
{
    return in_array(file_manager_extension($path), [
        'php',
        'php3',
        'php4',
        'php5',
        'phtml',
        'phar',
        'cgi',
        'pl',
        'py',
        'rb',
        'sh',
        'bash',
        'bat',
        'cmd',
        'ps1',
        'exe',
        'dll',
    ], true);
}

function file_manager_is_editable(string $path): bool
{
    return in_array(file_manager_extension($path), [
        'txt',
        'md',
        'csv',
        'json',
        'js',
        'jsx',
        'css',
        'html',
        'htm',
        'xml',
        'svg',
        'sql',
        'log',
        'ini',
        'yml',
        'yaml',
    ], true);
}

function file_manager_public_url(array $root, string $absolutePath): string
{
    $relativePath = file_manager_relative_from_root($root, $absolutePath);
    $rootId = (string) ($root['id'] ?? '');

    if ($rootId === 'uploads' && $relativePath !== '') {
        $parts = explode('/', $relativePath, 2);
        if (count($parts) === 2) {
            return api_media_url($parts[0], $parts[1]);
        }
    }

    return '';
}

function file_manager_entry_payload(array $root, string $path): array
{
    $name = basename($path);
    $isDir = is_dir($path);
    $relativePath = file_manager_relative_from_root($root, $path);

    return [
        'name' => $name,
        'path' => $relativePath,
        'type' => $isDir ? 'folder' : 'file',
        'extension' => $isDir ? '' : file_manager_extension($path),
        'size' => $isDir ? null : (int) (filesize($path) ?: 0),
        'modifiedAt' => date('Y-m-d H:i:s', (int) (filemtime($path) ?: time())),
        'readable' => is_readable($path),
        'writable' => is_writable($path),
        'editable' => !$isDir && file_manager_is_editable($path) && !file_manager_is_sensitive_name($name),
        'downloadUrl' => !$isDir && !file_manager_is_sensitive_name($name)
            ? file_manager_download_url((string) $root['id'], $relativePath)
            : '',
        'publicUrl' => !$isDir ? file_manager_public_url($root, $path) : '',
    ];
}

function file_manager_download_url(string $rootId, string $path): string
{
    $script = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '/api/admin/file_manager.php';
    $scheme = api_is_https() ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');

    return $scheme . '://' . $host . $script . '?action=download&root=' . rawurlencode($rootId) . '&path=' . rawurlencode($path);
}

function file_manager_list(array $root, string $path): array
{
    $directory = file_manager_absolute_path($root, $path);
    if (!is_dir($directory)) {
        file_manager_json_error('Selected path is not a folder.', 422);
    }

    $items = [];
    foreach (scandir($directory) ?: [] as $name) {
        if ($name === '.' || $name === '..' || file_manager_is_sensitive_name($name)) {
            continue;
        }

        $itemPath = $directory . '/' . $name;
        if (!is_readable($itemPath)) {
            continue;
        }

        $items[] = file_manager_entry_payload($root, $itemPath);
    }

    usort($items, static function (array $first, array $second): int {
        if ($first['type'] !== $second['type']) {
            return $first['type'] === 'folder' ? -1 : 1;
        }

        return strcasecmp((string) $first['name'], (string) $second['name']);
    });

    return [
        'root' => [
            'id' => $root['id'],
            'label' => $root['label'],
        ],
        'path' => file_manager_relative_from_root($root, $directory),
        'parentPath' => $directory === (string) $root['path']
            ? ''
            : file_manager_relative_from_root($root, dirname($directory)),
        'items' => $items,
    ];
}

function file_manager_safe_name(string $name): string
{
    $name = trim(str_replace(['\\', '/'], '', $name));
    if ($name === '' || $name === '.' || $name === '..') {
        file_manager_json_error('A valid file or folder name is required.', 422);
    }

    if (file_manager_is_sensitive_name($name)) {
        file_manager_json_error('That filename is protected.', 422);
    }

    return $name;
}

function file_manager_delete_path(string $path): void
{
    if (is_dir($path)) {
        foreach (scandir($path) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            file_manager_delete_path($path . '/' . $name);
        }

        if (!rmdir($path)) {
            throw new RuntimeException('Unable to delete folder.');
        }

        return;
    }

    if (!unlink($path)) {
        throw new RuntimeException('Unable to delete file.');
    }
}

function file_manager_copy_path(string $source, string $destination): void
{
    if (is_dir($source)) {
        if (!mkdir($destination, 0755, true) && !is_dir($destination)) {
            throw new RuntimeException('Unable to prepare destination folder.');
        }

        foreach (scandir($source) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            file_manager_copy_path($source . '/' . $name, $destination . '/' . $name);
        }

        return;
    }

    if (!copy($source, $destination)) {
        throw new RuntimeException('Unable to copy item to destination.');
    }
}

function file_manager_move_path(string $source, string $destination): void
{
    if (@rename($source, $destination)) {
        return;
    }

    file_manager_copy_path($source, $destination);
    file_manager_delete_path($source);
}

function file_manager_handle_upload(PDO $db, array $admin, array $root, string $path): never
{
    $directory = file_manager_absolute_path($root, $path);
    if (!is_dir($directory) || !is_writable($directory)) {
        file_manager_json_error('Selected folder is not writable.', 422);
    }

    $files = api_normalize_uploaded_files($_FILES['files'] ?? $_FILES['file'] ?? []);
    if (empty($files)) {
        file_manager_json_error('No file was uploaded.', 422);
    }

    $uploaded = [];
    foreach ($files as $file) {
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            file_manager_json_error(api_upload_error_message($errorCode), 422);
        }

        $size = (int) ($file['size'] ?? 0);
        $originalName = file_manager_safe_name((string) ($file['name'] ?? 'file'));
        if ($size > FILE_MANAGER_MAX_UPLOAD_BYTES) {
            file_manager_json_error('Upload is too large. Maximum is 50MB per file.', 413);
        }

        if (file_manager_is_executable_extension($originalName)) {
            file_manager_json_error('Server-executable files are blocked in file manager uploads.', 422);
        }

        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeBase = trim((string) preg_replace('/[^a-zA-Z0-9_-]+/', '_', $base), '_');
        if ($safeBase === '') {
            $safeBase = 'file';
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            file_manager_json_error('Uploaded file is missing.', 422);
        }

        try {
            $storedFile = api_save_uploaded_file_to_path($tmpName, $directory, $safeBase, $extension, 'uploads');
        } catch (Throwable) {
            file_manager_json_error('Unable to save uploaded file.', 500);
        }

        $uploaded[] = file_manager_entry_payload($root, (string) $storedFile['path']);
    }

    api_log_admin_action($db, $admin, 'UPLOAD', 'Uploaded ' . count($uploaded) . ' file(s) through File Manager');

    api_json([
        'ok' => true,
        'message' => 'Upload completed.',
        'data' => $uploaded,
    ]);
}

try {
    $db = api_db();
    $admin = file_manager_is_super_admin($db);
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $payload = $method === 'POST' ? api_request_data() : $_GET;
    $action = trim((string) ($payload['action'] ?? 'list'));
    $root = file_manager_root(trim((string) ($payload['root'] ?? '')));
    $path = (string) ($payload['path'] ?? '');

    if ($method === 'GET' && $action === 'roots') {
        api_json([
            'ok' => true,
            'data' => array_values(array_map(static fn (array $item): array => [
                'id' => $item['id'],
                'label' => $item['label'],
            ], file_manager_root_entries())),
        ]);
    }

    if ($method === 'GET' && $action === 'list') {
        api_json([
            'ok' => true,
            'data' => file_manager_list($root, $path),
        ]);
    }

    if ($method === 'GET' && $action === 'read') {
        $file = file_manager_absolute_path($root, $path);
        if (!is_file($file) || file_manager_is_sensitive_name(basename($file)) || !file_manager_is_editable($file)) {
            file_manager_json_error('This file cannot be edited from File Manager.', 422);
        }

        if ((int) (filesize($file) ?: 0) > FILE_MANAGER_MAX_EDIT_BYTES) {
            file_manager_json_error('File is too large to edit here.', 413);
        }

        api_json([
            'ok' => true,
            'data' => [
                'path' => file_manager_relative_from_root($root, $file),
                'name' => basename($file),
                'content' => (string) file_get_contents($file),
                'modifiedAt' => date('Y-m-d H:i:s', (int) (filemtime($file) ?: time())),
            ],
        ]);
    }

    if ($method === 'GET' && $action === 'download') {
        $file = file_manager_absolute_path($root, $path);
        if (!is_file($file) || file_manager_is_sensitive_name(basename($file))) {
            file_manager_json_error('File cannot be downloaded from File Manager.', 422);
        }

        header('Content-Type: application/octet-stream');
        $downloadName = str_replace(['"', "\r", "\n"], '_', basename($file));
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . (string) (filesize($file) ?: 0));
        readfile($file);
        exit;
    }

    if ($method !== 'POST') {
        api_require_method('POST');
    }

    if ($action === 'upload') {
        file_manager_handle_upload($db, $admin, $root, $path);
    }

    if ($action === 'mkdir') {
        $directory = file_manager_absolute_path($root, $path);
        $name = file_manager_safe_name((string) ($payload['name'] ?? ''));
        $target = file_manager_absolute_path($root, trim(file_manager_relative_from_root($root, $directory) . '/' . $name, '/'), false);
        if (file_exists($target)) {
            file_manager_json_error('A file or folder with that name already exists.', 409);
        }

        if (!mkdir($target, 0755, true)) {
            file_manager_json_error('Unable to create folder.', 500);
        }

        api_log_admin_action($db, $admin, 'CREATE', 'Created folder "' . $name . '" through File Manager');
        api_json(['ok' => true, 'message' => 'Folder created.', 'data' => file_manager_entry_payload($root, $target)]);
    }

    if ($action === 'save') {
        $file = file_manager_absolute_path($root, $path);
        if (!is_file($file) || !is_writable($file) || file_manager_is_sensitive_name(basename($file)) || !file_manager_is_editable($file)) {
            file_manager_json_error('This file cannot be saved from File Manager.', 422);
        }

        $content = (string) ($payload['content'] ?? '');
        if (strlen($content) > FILE_MANAGER_MAX_EDIT_BYTES) {
            file_manager_json_error('Content is too large to save here.', 413);
        }

        if (file_put_contents($file, $content, LOCK_EX) === false) {
            file_manager_json_error('Unable to save file.', 500);
        }

        api_log_admin_action($db, $admin, 'UPDATE', 'Edited file "' . file_manager_relative_from_root($root, $file) . '" through File Manager');
        api_json(['ok' => true, 'message' => 'File saved.', 'data' => file_manager_entry_payload($root, $file)]);
    }

    if ($action === 'rename') {
        $target = file_manager_absolute_path($root, $path);
        if (file_manager_is_sensitive_name(basename($target))) {
            file_manager_json_error('Protected files cannot be renamed.', 422);
        }

        $newName = file_manager_safe_name((string) ($payload['name'] ?? ''));
        if (file_manager_is_executable_extension($newName)) {
            file_manager_json_error('Server-executable filenames are blocked.', 422);
        }

        $nextPath = dirname($target) . '/' . $newName;
        if (file_exists($nextPath)) {
            file_manager_json_error('A file or folder with that name already exists.', 409);
        }

        if (!rename($target, $nextPath)) {
            file_manager_json_error('Unable to rename item.', 500);
        }

        api_log_admin_action($db, $admin, 'UPDATE', 'Renamed file manager item to "' . $newName . '"');
        api_json(['ok' => true, 'message' => 'Item renamed.', 'data' => file_manager_entry_payload($root, $nextPath)]);
    }

    if ($action === 'move') {
        $requestedPaths = $payload['paths'] ?? null;
        if (is_array($requestedPaths)) {
            $paths = array_values(array_filter(array_map(static fn ($item): string => (string) $item, $requestedPaths)));
        } else {
            $paths = [$path];
        }

        if (empty($paths)) {
            file_manager_json_error('Select at least one item to move.', 422);
        }

        $destinationRoot = file_manager_root(trim((string) ($payload['destinationRoot'] ?? $payload['destination_root'] ?? $root['id'])));
        $destinationPath = (string) ($payload['destinationPath'] ?? $payload['destination_path'] ?? '');
        $destinationDirectory = file_manager_absolute_path($destinationRoot, $destinationPath);
        if (!is_dir($destinationDirectory)) {
            file_manager_json_error('Destination must be a folder.', 422);
        }

        if (!is_writable($destinationDirectory)) {
            file_manager_json_error('Destination folder is not writable.', 422);
        }

        $moves = [];
        foreach ($paths as $sourcePath) {
            $source = file_manager_absolute_path($root, $sourcePath);
            if ($source === (string) $root['path']) {
                file_manager_json_error('The root folder cannot be moved.', 422);
            }

            if (file_manager_is_sensitive_name(basename($source))) {
                file_manager_json_error('Protected files cannot be moved.', 422);
            }

            if (!is_writable(dirname($source))) {
                file_manager_json_error('Source folder is not writable.', 422);
            }

            if (is_dir($source) && ($destinationDirectory === $source || str_starts_with($destinationDirectory, $source . '/'))) {
                file_manager_json_error('A folder cannot be moved into itself.', 422);
            }

            $destination = $destinationDirectory . '/' . basename($source);
            if ($destination === $source) {
                file_manager_json_error('One or more selected items are already in that folder.', 409);
            }

            if (file_exists($destination)) {
                file_manager_json_error('A file or folder named "' . basename($source) . '" already exists in the destination.', 409);
            }

            $moves[] = [
                'source' => $source,
                'sourcePath' => $sourcePath,
                'destination' => $destination,
            ];
        }

        $moved = [];
        foreach ($moves as $move) {
            file_manager_move_path($move['source'], $move['destination']);
            $moved[] = file_manager_entry_payload($destinationRoot, $move['destination']);
        }

        api_log_admin_action(
            $db,
            $admin,
            'MOVE',
            'Moved ' . count($moved) . ' item(s) to "' . file_manager_relative_from_root($destinationRoot, $destinationDirectory) . '" through File Manager'
        );
        api_json([
            'ok' => true,
            'message' => count($moved) === 1 ? 'Item moved.' : 'Selected items moved.',
            'data' => count($moved) === 1 ? $moved[0] : $moved,
        ]);
    }

    if ($action === 'delete') {
        $target = file_manager_absolute_path($root, $path);
        if ($target === (string) $root['path']) {
            file_manager_json_error('The root folder cannot be deleted.', 422);
        }

        if (file_manager_is_sensitive_name(basename($target))) {
            file_manager_json_error('Protected files cannot be deleted.', 422);
        }

        file_manager_delete_path($target);
        api_log_admin_action($db, $admin, 'DELETE', 'Deleted "' . $path . '" through File Manager');
        api_json(['ok' => true, 'message' => 'Item deleted.']);
    }

    file_manager_json_error('Unknown file manager action.', 400);
} catch (Throwable $error) {
    error_log('Admin file manager API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to process file manager request right now.',
    ], 500);
}
