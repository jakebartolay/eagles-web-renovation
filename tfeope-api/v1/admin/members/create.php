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
            'success' => false,
            'message' => 'Only super admins can add members.',
        ], 403);
    }

    if (!api_table_exists($db, 'user_info')) {
        api_json([
            'success' => false,
            'message' => 'Members table is not available.',
        ], 500);
    }

    $payload = api_request_data();

    $memberId = trim((string) ($payload['id'] ?? $payload['eagles_id'] ?? ''));
    if ($memberId === '') {
        $memberId = 'EAG_' . strtoupper(substr(str_replace('.', '', uniqid('', true)), -12));
    }

    $firstName = strtoupper(trim((string) ($payload['first_name'] ?? $payload['eagles_firstName'] ?? '')));
    $lastName = strtoupper(trim((string) ($payload['last_name'] ?? $payload['eagles_lastName'] ?? '')));
    $position = strtoupper(trim((string) ($payload['position'] ?? $payload['eagles_position'] ?? '')));

    $clubSelection = trim((string) ($payload['club'] ?? $payload['eagles_club'] ?? ''));
    $clubNew = strtoupper(trim((string) ($payload['club_new'] ?? '')));
    $club = $clubSelection === '__NEW__' ? $clubNew : strtoupper($clubSelection);

    $regionSelection = trim((string) ($payload['region'] ?? $payload['eagles_region'] ?? ''));
    $regionNew = strtoupper(trim((string) ($payload['region_new'] ?? '')));
    $region = $regionSelection === '__NEW__' ? $regionNew : strtoupper($regionSelection);

    $status = strtoupper(trim((string) ($payload['status'] ?? $payload['eagles_status'] ?? 'ACTIVE')));
    if ($status === '') {
        $status = 'ACTIVE';
    }

    if ($firstName === '' || $lastName === '' || $position === '' || $club === '' || $region === '') {
        api_json([
            'success' => false,
            'message' => 'Please complete all required member fields.',
        ], 422);
    }

    $photoUpload = $_FILES['photo'] ?? $_FILES['eagles_pic'] ?? null;
    $storedPhoto = null;

    if (is_array($photoUpload) && (int) ($photoUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $storedPhoto = api_store_uploaded_file($photoUpload, 'media', api_image_extensions());
    }

    try {
        api_execute($db, '
            INSERT INTO user_info (
                eagles_id,
                eagles_firstName,
                eagles_lastName,
                eagles_position,
                eagles_club,
                eagles_region,
                eagles_status,
                eagles_pic
            ) VALUES (
                :eagles_id,
                :eagles_firstName,
                :eagles_lastName,
                :eagles_position,
                :eagles_club,
                :eagles_region,
                :eagles_status,
                :eagles_pic
            )
        ', [
            ':eagles_id' => $memberId,
            ':eagles_firstName' => $firstName,
            ':eagles_lastName' => $lastName,
            ':eagles_position' => $position,
            ':eagles_club' => $club,
            ':eagles_region' => $region,
            ':eagles_status' => $status,
            ':eagles_pic' => $storedPhoto['filename'] ?? null,
        ]);
    } catch (Throwable $error) {
        if ($storedPhoto !== null) {
            api_delete_uploaded_file('media', $storedPhoto['filename'] ?? null);
        }

        $message = $error->getMessage();
        $duplicate = str_contains($message, '1062') || str_contains(strtolower($message), 'duplicate');

        if ($duplicate) {
            api_json([
                'success' => false,
                'message' => 'Duplicate Eagles ID. Please use a different member ID.',
            ], 409);
        }

        throw $error;
    }

    api_log_admin_action(
        $db,
        $admin,
        'CREATE',
        'Added member "' . trim($firstName . ' ' . $lastName) . '" (' . $memberId . ')'
    );

    $row = api_fetch_one($db, '
        SELECT
            eagles_id,
            eagles_status,
            eagles_firstName,
            eagles_lastName,
            eagles_position,
            eagles_club,
            eagles_region,
            eagles_pic,
            eagles_dateAdded
        FROM user_info
        WHERE eagles_id = :eagles_id
        LIMIT 1
    ', [':eagles_id' => $memberId]);

    api_json([
        'success' => true,
        'message' => 'Member added successfully.',
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
    ], 201);
} catch (Throwable $error) {
    error_log('Admin member create API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to add member right now.',
    ], 500);
}
