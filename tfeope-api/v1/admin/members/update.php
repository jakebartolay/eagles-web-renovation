<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    $admin = api_require_admin($db);

    if ((int) ($admin['role_id'] ?? 0) !== 1) {
        api_json([
            'ok' => false,
            'message' => 'Only super admins can edit members.',
        ], 403);
    }

    $payload = api_request_data();
    $memberId = trim((string) ($payload['id'] ?? $payload['eagles_id'] ?? ''));

    if ($memberId === '') {
        api_json([
            'ok' => false,
            'message' => 'A valid member ID is required.',
        ], 422);
    }

    $current = api_fetch_one($db, '
        SELECT *
        FROM user_info
        WHERE eagles_id = :eagles_id
        LIMIT 1
    ', [':eagles_id' => $memberId]);

    if ($current === null) {
        api_json([
            'ok' => false,
            'message' => 'Member not found.',
        ], 404);
    }

    $firstName = strtoupper(trim((string) ($payload['first_name'] ?? $payload['eagles_firstName'] ?? $current['eagles_firstName'] ?? '')));
    $lastName = strtoupper(trim((string) ($payload['last_name'] ?? $payload['eagles_lastName'] ?? $current['eagles_lastName'] ?? '')));
    $position = strtoupper(trim((string) ($payload['position'] ?? $payload['eagles_position'] ?? $current['eagles_position'] ?? '')));
    $clubSelection = trim((string) ($payload['club'] ?? $payload['eagles_club'] ?? $current['eagles_club'] ?? ''));
    $clubNew = strtoupper(trim((string) ($payload['club_new'] ?? '')));
    $club = $clubSelection === '__NEW__' ? $clubNew : strtoupper($clubSelection);
    $regionSelection = trim((string) ($payload['region'] ?? $payload['eagles_region'] ?? $current['eagles_region'] ?? ''));
    $regionNew = strtoupper(trim((string) ($payload['region_new'] ?? '')));
    $region = $regionSelection === '__NEW__' ? $regionNew : strtoupper($regionSelection);
    $status = strtoupper(trim((string) ($payload['status'] ?? $payload['eagles_status'] ?? $current['eagles_status'] ?? 'ACTIVE')));

    if ($firstName === '' || $lastName === '' || $position === '' || $club === '' || $region === '') {
        api_json([
            'ok' => false,
            'message' => 'Please complete all required member fields.',
        ], 422);
    }

    $photoUpload = $_FILES['photo'] ?? $_FILES['eagles_pic'] ?? null;
    $storedPhoto = null;

    if (is_array($photoUpload) && (int) ($photoUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $storedPhoto = api_store_uploaded_file($photoUpload, 'media', api_image_extensions());
    }

    $nextPhoto = $storedPhoto['filename'] ?? trim((string) ($current['eagles_pic'] ?? ''));

    try {
        api_execute($db, '
            UPDATE user_info
            SET eagles_firstName = :first_name,
                eagles_lastName = :last_name,
                eagles_position = :position,
                eagles_club = :club,
                eagles_region = :region,
                eagles_status = :status,
                eagles_pic = :pic
            WHERE eagles_id = :eagles_id
        ', [
            ':eagles_id' => $memberId,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':position' => $position,
            ':club' => $club,
            ':region' => $region,
            ':status' => $status !== '' ? $status : 'ACTIVE',
            ':pic' => $nextPhoto !== '' ? $nextPhoto : null,
        ]);
    } catch (Throwable $error) {
        if ($storedPhoto !== null) {
            api_delete_uploaded_file('media', $storedPhoto['filename'] ?? null);
        }
        throw $error;
    }

    if ($storedPhoto !== null) {
        api_delete_uploaded_file('media', basename((string) ($current['eagles_pic'] ?? '')));
    }

    api_log_admin_action(
        $db,
        $admin,
        'UPDATE',
        'Updated member "' . trim($firstName . ' ' . $lastName) . '" (' . $memberId . ')'
    );

    $row = api_fetch_one($db, '
        SELECT *
        FROM user_info
        WHERE eagles_id = :eagles_id
        LIMIT 1
    ', [':eagles_id' => $memberId]);

    api_json([
        'ok' => true,
        'message' => 'Member updated successfully.',
        'data' => [
            'id' => (string) ($row['eagles_id'] ?? $memberId),
            'status' => (string) ($row['eagles_status'] ?? $status),
            'firstName' => (string) ($row['eagles_firstName'] ?? $firstName),
            'lastName' => (string) ($row['eagles_lastName'] ?? $lastName),
            'fullName' => trim((string) (($row['eagles_firstName'] ?? $firstName) . ' ' . ($row['eagles_lastName'] ?? $lastName))),
            'position' => (string) ($row['eagles_position'] ?? $position),
            'club' => (string) ($row['eagles_club'] ?? $club),
            'region' => (string) ($row['eagles_region'] ?? $region),
            'picUrl' => isset($row['eagles_pic']) && trim((string) $row['eagles_pic']) !== ''
                ? api_media_url('media', basename((string) $row['eagles_pic']))
                : null,
            'dateAdded' => (string) ($row['eagles_dateAdded'] ?? ''),
        ],
    ]);
} catch (Throwable $error) {
    error_log('Admin member update API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to update member right now.',
    ], 500);
}
