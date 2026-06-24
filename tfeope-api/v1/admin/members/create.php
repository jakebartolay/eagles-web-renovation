<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    $admin = api_require_admin($db);

    if (!api_table_exists($db, 'user_info')) {
        api_json([
            'success' => false,
            'message' => 'Members table is not available.',
        ], 500);
    }
    $regionalPositionColumn = api_member_regional_position_column($db);
    $regionalPositionSql = api_quote_identifier((string) $regionalPositionColumn);

    $payload = api_request_data();

    $memberId = trim((string) api_payload_value($payload, ['id', 'ID', 'eagles_id', 'member_id'], ''));
    if ($memberId === '') {
        $memberId = 'EAG_' . strtoupper(substr(str_replace('.', '', uniqid('', true)), -12));
    }

    $duplicateMember = api_fetch_one($db, '
        SELECT eagles_id
        FROM user_info
        WHERE eagles_id = :eagles_id
        LIMIT 1
    ', [':eagles_id' => $memberId]);

    if ($duplicateMember !== null) {
        api_json([
            'success' => false,
            'message' => 'Duplicate Eagles ID. Please use a different member ID.',
        ], 409);
    }

    $firstName = strtoupper(trim((string) api_payload_value($payload, ['first_name', 'firstName', 'First Name', 'eagles_firstName'], '')));
    $lastName = strtoupper(trim((string) api_payload_value($payload, ['last_name', 'lastName', 'Last Name', 'eagles_lastName'], '')));
    $position = strtoupper(trim((string) api_payload_value($payload, ['position', 'Position', 'eagles_position'], '')));
    $regionalPosition = strtoupper(trim((string) api_payload_value($payload, [
        'regional_position',
        'regionalPosition',
        'regional position',
        'REGIONAL POSITION',
        'regional_postion',
        'regional postion',
        'eagles_regional_position',
    ], '')));

    $clubSelection = trim((string) api_payload_value($payload, ['club', 'Club', 'club_name', 'eagles_club'], ''));
    $clubNew = strtoupper(trim((string) api_payload_value($payload, ['club_new', 'clubNew'], '')));
    $club = $clubSelection === '__NEW__' ? $clubNew : strtoupper($clubSelection);

    $regionSelection = trim((string) api_payload_value($payload, ['region', 'Region', 'region_name', 'eagles_region'], ''));
    $regionNew = strtoupper(trim((string) api_payload_value($payload, ['region_new', 'regionNew'], '')));
    $region = $regionSelection === '__NEW__' ? $regionNew : strtoupper($regionSelection);

    $status = strtoupper(trim((string) api_payload_value($payload, ['status', 'Status', 'eagles_status'], 'ACTIVE')));
    if ($status === '') {
        $status = 'ACTIVE';
    }

    if (!in_array($status, ['ACTIVE', 'RENEWAL'], true)) {
        api_json([
            'success' => false,
            'message' => 'Status must be either ACTIVE or RENEWAL.',
        ], 422);
    }

    if ($firstName === '' || $lastName === '' || $position === '' || $club === '' || $region === '' || $regionalPosition === '') {
        api_json([
            'success' => false,
            'message' => 'Please complete all required member fields.',
        ], 422);
    }

    try {
        $catalogResult = api_ensure_region_club_catalog($db, $region, $club);
        if (($catalogResult['ok'] ?? false) !== true && ($catalogResult['reason'] ?? '') !== '') {
            error_log('Member create catalog notice: ' . $catalogResult['reason'] . ' Region=' . $region . ' Club=' . $club);
        }
    } catch (Throwable $catalogError) {
        error_log('Member create catalog notice: ' . $catalogError->getMessage());
    }

    $photoUpload = $_FILES['photo'] ?? $_FILES['eagles_pic'] ?? null;
    $storedPhoto = null;

    if (is_array($photoUpload) && (int) ($photoUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $storedPhoto = api_store_uploaded_file_as($photoUpload, 'members', $memberId, api_image_extensions(), true);
    }

    $nextPhoto = $storedPhoto['filename'] ?? '';

    try {
        api_execute($db, '
            INSERT INTO user_info (
                eagles_id,
                eagles_firstName,
                eagles_lastName,
                eagles_position,
                ' . $regionalPositionSql . ',
                eagles_club,
                eagles_region,
                eagles_status,
                eagles_pic
            ) VALUES (
                :eagles_id,
                :eagles_firstName,
                :eagles_lastName,
                :eagles_position,
                :regional_position,
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
            ':regional_position' => $regionalPosition,
            ':eagles_status' => $status,
            ':eagles_pic' => $nextPhoto,
        ]);
        $regionalPositionSave = api_save_member_regional_position($db, $memberId, $regionalPosition);
    } catch (Throwable $error) {
        if ($storedPhoto !== null) {
            api_delete_uploaded_file('members', $storedPhoto['filename'] ?? null);
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
            ' . api_member_regional_position_select($db) . ',
            eagles_pic,
            eagles_dateAdded
        FROM user_info
        WHERE eagles_id = :eagles_id
        LIMIT 1
    ', [':eagles_id' => $memberId]);

    $photoFile = basename(trim((string) ($row['eagles_pic'] ?? $nextPhoto ?? '')));
    $photoAsset = $photoFile !== ''
        ? api_member_photo_asset($photoFile)
        : null;

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
            'regionalPosition' => api_member_regional_position_value($row ?? []) ?: $regionalPosition,
            'regional_position' => api_member_regional_position_value($row ?? []) ?: $regionalPosition,
            'regionalPositionColumn' => $regionalPositionSave['column'] ?? $regionalPositionColumn,
            'regionalPositionSaved' => api_member_regional_position_value($row ?? []),
            'club' => (string) ($row['eagles_club'] ?? $club),
            'region' => (string) ($row['eagles_region'] ?? $region),
            'picUrl' => $photoAsset['url'] ?? null,
            'photoFilename' => $photoFile !== '' ? $photoFile : null,
            'photoLink' => api_member_photo_link($photoFile),
            'dateAdded' => (string) ($row['eagles_dateAdded'] ?? ''),
        ],
    ], 201);
} catch (Throwable $error) {
    error_log('Admin member create API error: ' . $error->getMessage());
    $message = str_contains($error->getMessage(), 'Regional position')
        ? $error->getMessage()
        : 'Unable to add member right now.';
    api_json([
        'success' => false,
        'message' => $message,
    ], 500);
}
