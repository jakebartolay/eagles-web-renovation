<?php

declare(strict_types=1);

const UPSTREAM_API_ORIGIN = 'https://api.tfoepe-inc.com.ph';

function proxy_add_uploaded_files(array &$fields, array $files, string $prefix = ''): void
{
    foreach ($files as $key => $file) {
        $fieldName = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';

        if (is_array($file['name'] ?? null)) {
            $count = count($file['name']);
            for ($index = 0; $index < $count; $index += 1) {
                $nested = [
                    'name' => $file['name'][$index] ?? '',
                    'type' => $file['type'][$index] ?? '',
                    'tmp_name' => $file['tmp_name'][$index] ?? '',
                    'error' => $file['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $file['size'][$index] ?? 0,
                ];
                proxy_add_uploaded_files($fields, [$index => $nested], $fieldName);
            }
            continue;
        }

        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            continue;
        }

        $fields[$fieldName] = curl_file_create(
            $tmpName,
            (string) ($file['type'] ?? 'application/octet-stream'),
            (string) ($file['name'] ?? basename($tmpName))
        );
    }
}

$path = trim((string) ($_GET['__proxy_path'] ?? ''), '/');
unset($_GET['__proxy_path']);

if ($path === '') {
    $requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $clientApiMarker = '/client-api/';
    $markerPosition = strpos($requestPath, $clientApiMarker);
    if ($markerPosition !== false) {
        $path = trim(substr($requestPath, $markerPosition + strlen($clientApiMarker)), '/');
    }
}

if ($path === '' || str_contains($path, '..')) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid proxy path.',
    ]);
    exit;
}

$query = http_build_query($_GET);
$targetUrl = UPSTREAM_API_ORIGIN . '/' . $path . ($query !== '' ? '?' . $query : '');
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$requestBody = file_get_contents('php://input');
$contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
$isMultipart = stripos($contentType, 'multipart/form-data') !== false;

$forwardHeaders = [];
if ($contentType !== '' && !$isMultipart) {
    $forwardHeaders[] = 'Content-Type: ' . $contentType;
}

$accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
if ($accept !== '') {
    $forwardHeaders[] = 'Accept: ' . $accept;
}

$requestedWith = (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
if ($requestedWith !== '') {
    $forwardHeaders[] = 'X-Requested-With: ' . $requestedWith;
}

$authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
if ($authorization !== '') {
    $forwardHeaders[] = 'Authorization: ' . $authorization;
}

$cookie = (string) ($_SERVER['HTTP_COOKIE'] ?? '');
if ($cookie !== '') {
    $forwardHeaders[] = 'Cookie: ' . $cookie;
}

$responseHeaders = [];
$statusCode = 502;
$responseBody = '';

if (function_exists('curl_init')) {
    $ch = curl_init($targetUrl);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => $forwardHeaders,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$responseHeaders): int {
            $trimmed = trim($headerLine);
            if ($trimmed !== '') {
                $responseHeaders[] = $trimmed;
            }

            return strlen($headerLine);
        },
    ]);

    if (!in_array($method, ['GET', 'HEAD'], true)) {
        if ($isMultipart) {
            $multipartFields = $_POST;
            proxy_add_uploaded_files($multipartFields, $_FILES);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $multipartFields);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody === false ? '' : $requestBody);
        }
    }

    $responseBody = curl_exec($ch);
    if ($responseBody === false) {
        $responseBody = json_encode([
            'success' => false,
            'message' => 'Unable to reach API server.',
        ]);
    }

    $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
}

http_response_code($statusCode > 0 ? $statusCode : 502);

foreach ($responseHeaders as $headerLine) {
    if (stripos($headerLine, 'HTTP/') === 0) {
        continue;
    }

    $headerName = strtolower(strtok($headerLine, ':') ?: '');
    if (in_array($headerName, ['connection', 'content-length', 'transfer-encoding', 'content-encoding'], true)) {
        continue;
    }

    header($headerLine, false);
}

header('X-Content-Type-Options: nosniff');
echo (string) $responseBody;
