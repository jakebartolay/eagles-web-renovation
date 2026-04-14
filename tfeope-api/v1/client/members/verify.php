<?php
require_once '../../../bootstrap.php';
api_start();

$db = api_db();
$memberId = strtoupper(trim((string) ($_GET['id'] ?? '')));
$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    api_error('Please sign in to verify membership.', 401, [
        'authenticated' => false,
    ]);
}

if ($memberId === '' || !preg_match('/^TFOEPE[0-9]{8}$/', $memberId)) {
    api_error('ID is invalid.');
}

$row = api_fetch_one($db, '
    SELECT
        eagles_id,
        eagles_status,
        eagles_firstName,
        eagles_lastName,
        eagles_position,
        eagles_club,
        eagles_region,
        eagles_pic
    FROM user_info
    WHERE eagles_id = :id
    LIMIT 1
', [':id' => $memberId]);

if (!$row) {
    api_error('ID not found.', 404);
}

$rawStatus = strtolower(trim((string) ($row['eagles_status'] ?? '')));
$normalizedStatus = 'other';
$statusLabel = strtoupper($rawStatus !== '' ? $rawStatus : 'unknown');

if ($rawStatus === 'active') {
    $normalizedStatus = 'active';
    $statusLabel = 'ACTIVE';
} elseif (in_array($rawStatus, ['for renewal', 'renewal', 'renew', 'for_renewal', 'for-renewal'], true)) {
    $normalizedStatus = 'renewal';
    $statusLabel = 'FOR RENEWAL';
}

api_json([
    'success' => true,
    'authenticated' => true,
    'data' => [
        'id' => (string) ($row['eagles_id'] ?? ''),
        'status' => (string) ($row['eagles_status'] ?? ''),
        'statusLabel' => $statusLabel,
        'statusType' => $normalizedStatus,
        'firstName' => (string) ($row['eagles_firstName'] ?? ''),
        'lastName' => (string) ($row['eagles_lastName'] ?? ''),
        'fullName' => trim(((string) ($row['eagles_firstName'] ?? '')) . ' ' . ((string) ($row['eagles_lastName'] ?? ''))),
        'position' => (string) ($row['eagles_position'] ?? ''),
        'club' => (string) ($row['eagles_club'] ?? ''),
        'region' => (string) ($row['eagles_region'] ?? ''),
        'picUrl' => api_media_url('media', basename((string) ($row['eagles_pic'] ?? ''))),
        'showCertifiedStamp' => $normalizedStatus === 'active',
    ],
]);
