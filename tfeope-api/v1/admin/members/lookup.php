<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('GET');

try {
    $db    = api_db();
    $admin = api_require_admin($db);

    $eaglesId = strtoupper(trim((string) ($_GET['id'] ?? '')));

    if ($eaglesId === '') {
        api_json(['ok' => false, 'message' => 'Eagles ID is required.'], 422);
        return;
    }

    if (!api_table_exists($db, 'user_info')) {
        api_json(['ok' => false, 'message' => 'Member records table is unavailable.'], 500);
        return;
    }

    $member = api_fetch_one($db, '
        SELECT eagles_id, eagles_firstName, eagles_lastName, eagles_status, eagles_club, eagles_region
        FROM user_info
        WHERE eagles_id = :id
        LIMIT 1
    ', [':id' => $eaglesId]);

    if (!$member) {
        api_json(['ok' => false, 'found' => false, 'message' => 'Eagles ID not found in member records.'], 404);
        return;
    }

    // Check if already linked to a portal account
    $linked = null;
    if (api_table_exists($db, 'users')) {
        $linked = api_fetch_one($db, '
            SELECT id FROM users WHERE eagles_id = :id LIMIT 1
        ', [':id' => $eaglesId]);
    }

    $firstName = trim((string) ($member['eagles_firstName'] ?? ''));
    $lastName  = trim((string) ($member['eagles_lastName'] ?? ''));

    api_json([
        'ok'      => true,
        'found'   => true,
        'linked'  => $linked !== null,
        'data'    => [
            'eaglesId'  => (string) ($member['eagles_id'] ?? ''),
            'firstName' => $firstName,
            'lastName'  => $lastName,
            'fullName'  => trim($firstName . ' ' . $lastName),
            'status'    => (string) ($member['eagles_status'] ?? ''),
            'club'      => (string) ($member['eagles_club'] ?? ''),
            'region'    => (string) ($member['eagles_region'] ?? ''),
        ],
    ]);

} catch (Throwable $error) {
    error_log('Admin member lookup error: ' . $error->getMessage());
    api_json(['ok' => false, 'message' => 'Unable to look up member right now.'], 500);
}
