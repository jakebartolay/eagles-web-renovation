<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

api_apply_cors_headers();

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($requestMethod === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$group = (string) ($_GET['group'] ?? '');
$file = (string) ($_GET['file'] ?? '');
$fullPath = api_storage_file_path($group, $file);

if ($fullPath === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'File not found.';
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = $finfo ? (string) finfo_file($finfo, $fullPath) : 'application/octet-stream';
if ($finfo) {
    finfo_close($finfo);
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($fullPath));
header('Cache-Control: public, max-age=86400');
readfile($fullPath);
exit;
