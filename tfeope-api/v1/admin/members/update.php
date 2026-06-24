<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    $admin = api_require_admin($db);
    $regionalPositionColumn = api_member_regional_position_column($db);
    $regionalPositionSql = api_quote_identifier((string) $regionalPositionColumn);

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

    $firstName = strtoupper(trim((string) api_payload_value($payload, ['first_name', 'firstName', 'First Name', 'eagles_firstName'], $current['eagles_firstName'] ?? '')));
    $lastName = strtoupper(trim((string) api_payload_value($payload, ['last_name', 'lastName', 'Last Name', 'eagles_lastName'], $current['eagles_lastName'] ?? '')));
    $position = strtoupper(trim((string) api_payload_value($payload, ['position', 'Position', 'eagles_position'], $current['eagles_position'] ?? '')));
    $currentRegionalPosition = api_member_regional_position_value($current);
    $regionalPosition = strtoupper(trim((string) api_payload_value($payload, [
        'regional_position',
        'regionalPosition',
        'regional position',
        'REGIONAL POSITION',
        'regional_postion',
        'regional postion',
        'eagles_regional_position',
    ], $currentRegionalPosition)));
    $clubSelection = trim((string) api_payload_value($payload, ['club', 'Club', 'club_name', 'eagles_club'], $current['eagles_club'] ?? ''));
    $clubNew = strtoupper(trim((string) api_payload_value($payload, ['club_new', 'clubNew'], '')));
    $club = $clubSelection === '__NEW__' ? $clubNew : strtoupper($clubSelection);
    $regionSelection = trim((string) api_payload_value($payload, ['region', 'Region', 'region_name', 'eagles_region'], $current['eagles_region'] ?? ''));
    $regionNew = strtoupper(trim((string) api_payload_value($payload, ['region_new', 'regionNew'], '')));
    $region = $regionSelection === '__NEW__' ? $regionNew : strtoupper($regionSelection);
    $status = strtoupper(trim((string) api_payload_value($payload, ['status', 'Status', 'eagles_status'], $current['eagles_status'] ?? 'ACTIVE')));

    if ($status === '') {
        $status = 'ACTIVE';
    }

    if (!in_array($status, ['ACTIVE', 'RENEWAL'], true)) {
        api_json([
            'ok' => false,
            'message' => 'Status must be either ACTIVE or RENEWAL.',
        ], 422);
    }

    if ($firstName === '' || $lastName === '' || $position === '' || $club === '' || $region === '' || $regionalPosition === '') {
        api_json([
            'ok' => false,
            'message' => 'Please complete all required member fields.',
        ], 422);
    }

    try {
        $catalogResult = api_ensure_region_club_catalog($db, $region, $club);
        if (($catalogResult['ok'] ?? false) !== true && ($catalogResult['reason'] ?? '') !== '') {
            error_log('Member update catalog notice: ' . $catalogResult['reason'] . ' Region=' . $region . ' Club=' . $club);
        }
    } catch (Throwable $catalogError) {
        error_log('Member update catalog notice: ' . $catalogError->getMessage());
    }

    $photoUpload = $_FILES['photo'] ?? $_FILES['eagles_pic'] ?? null;
    $currentPhotoFile = basename(trim((string) ($current['eagles_pic'] ?? '')));
    $currentPhotoAsset = $currentPhotoFile !== ''
        ? api_member_photo_asset($currentPhotoFile)
        : null;
    $storedPhoto = null;

    if (is_array($photoUpload) && (int) ($photoUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $storedPhoto = api_store_uploaded_file_as(
            $photoUpload,
            'members',
            $memberId,
            api_image_extensions(),
            true
        );
    }

    $nextPhoto = $storedPhoto['filename'] ?? $currentPhotoFile;

    try {
        api_execute($db, '
            UPDATE user_info
            SET eagles_firstName = :first_name,
                eagles_lastName = :last_name,
                eagles_position = :position,
                eagles_club = :club,
                eagles_region = :region,
                ' . $regionalPositionSql . ' = :regional_position,
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
            ':regional_position' => $regionalPosition,
            ':status' => $status !== '' ? $status : 'ACTIVE',
            ':pic' => $nextPhoto !== '' ? $nextPhoto : null,
        ]);
        $regionalPositionSave = api_save_member_regional_position($db, $memberId, $regionalPosition);
    } catch (Throwable $error) {
        if ($storedPhoto !== null) {
            $replacedSamePhoto = $currentPhotoAsset !== null
                && (string) ($currentPhotoAsset['group'] ?? '') === 'members'
                && $currentPhotoFile === basename((string) ($storedPhoto['filename'] ?? ''));

            if (!$replacedSamePhoto) {
                api_delete_uploaded_file('members', $storedPhoto['filename'] ?? null);
            }
        }
        throw $error;
    }

    if ($storedPhoto !== null && $currentPhotoAsset !== null) {
        $replacedSamePhoto = (string) ($currentPhotoAsset['group'] ?? '') === 'members'
            && $currentPhotoFile === basename((string) ($storedPhoto['filename'] ?? ''));

        if (!$replacedSamePhoto) {
            api_delete_uploaded_file((string) $currentPhotoAsset['group'], $currentPhotoFile);
        }
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

    $photoFile = basename(trim((string) ($row['eagles_pic'] ?? $nextPhoto ?? '')));
    $photoAsset = $photoFile !== ''
        ? api_member_photo_asset($photoFile)
        : null;

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
    ]);
} catch (Throwable $error) {
    error_log('Admin member update API error: ' . $error->getMessage());
    $message = str_contains($error->getMessage(), 'Regional position')
        ? $error->getMessage()
        : 'Unable to update member right now.';
    api_json([
        'ok' => false,
        'message' => $message,
    ], 500);
}
