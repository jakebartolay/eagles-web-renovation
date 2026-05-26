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
            'message' => 'Only super admins can update clubs.',
        ], 403);
    }

    if (!api_table_exists($db, 'clubs')) {
        api_json([
            'success' => false,
            'message' => 'Clubs table is not available.',
        ], 500);
    }

    $payload = api_request_data();

    $clubIdColumn = api_first_column($db, 'clubs', ['club_id', 'id']) ?? 'club_id';
    $clubNameColumn = api_first_column($db, 'clubs', ['club_name', 'name']) ?? 'club_name';
    $clubRegionIdColumn = api_first_column($db, 'clubs', ['region_id']);
    $clubGovernorIdColumn = api_first_column($db, 'clubs', ['governor_id']);

    $clubId = (int) ($payload['id'] ?? $payload['club_id'] ?? 0);
    if ($clubId <= 0) {
        api_json([
            'success' => false,
            'message' => 'A valid club ID is required.',
        ], 422);
    }

    $clubIdSql = api_quote_identifier($clubIdColumn);
    $clubNameSql = api_quote_identifier($clubNameColumn);
    $clubRegionSql = $clubRegionIdColumn !== null ? api_quote_identifier($clubRegionIdColumn) : null;
    $clubGovernorSql = $clubGovernorIdColumn !== null ? api_quote_identifier($clubGovernorIdColumn) : null;

    $existing = api_fetch_one(
        $db,
        'SELECT ' . $clubIdSql . ' AS api_club_id,
                ' . $clubNameSql . ' AS api_club_name,
                ' . ($clubRegionSql !== null ? $clubRegionSql : 'NULL') . ' AS api_region_id,
                ' . ($clubGovernorSql !== null ? $clubGovernorSql : 'NULL') . ' AS api_governor_id
         FROM clubs
         WHERE ' . $clubIdSql . ' = :club_id
         LIMIT 1',
        [':club_id' => $clubId]
    );

    if ($existing === null) {
        api_json([
            'success' => false,
            'message' => 'Club not found.',
        ], 404);
    }

    $existingClubName = trim((string) ($existing['api_club_name'] ?? ''));
    $nextClubName = trim((string) ($payload['name'] ?? $payload['club_name'] ?? $existingClubName));

    if ($nextClubName === '') {
        api_json([
            'success' => false,
            'message' => 'Club name is required.',
        ], 422);
    }

    $regionId = (int) (
        $payload['region_id']
        ?? $payload['regionId']
        ?? $existing['api_region_id']
        ?? 0
    );
    $governorId = (int) (
        $payload['governor_id']
        ?? $payload['governorId']
        ?? $existing['api_governor_id']
        ?? 0
    );

    $duplicateWhere = 'UPPER(' . $clubNameSql . ') = UPPER(:club_name) AND ' . $clubIdSql . ' <> :club_id';
    $duplicateParams = [
        ':club_name' => $nextClubName,
        ':club_id' => $clubId,
    ];

    if ($clubRegionSql !== null && $regionId > 0) {
        $duplicateWhere .= ' AND ' . $clubRegionSql . ' = :region_id';
        $duplicateParams[':region_id'] = $regionId;
    }

    $duplicate = api_fetch_one(
        $db,
        'SELECT ' . $clubIdSql . ' AS api_club_id
         FROM clubs
         WHERE ' . $duplicateWhere . '
         LIMIT 1',
        $duplicateParams
    );

    if ($duplicate !== null) {
        api_json([
            'success' => false,
            'message' => 'Club name already exists under this region.',
        ], 409);
    }

    $updateFields = [$clubNameSql . ' = :club_name'];
    $updateParams = [
        ':club_name' => $nextClubName,
        ':club_id' => $clubId,
    ];

    if ($clubRegionSql !== null && $regionId > 0) {
        $updateFields[] = $clubRegionSql . ' = :region_id';
        $updateParams[':region_id'] = $regionId;
    }

    if ($clubGovernorSql !== null && $governorId > 0) {
        $updateFields[] = $clubGovernorSql . ' = :governor_id';
        $updateParams[':governor_id'] = $governorId;
    }

    api_execute(
        $db,
        'UPDATE clubs
         SET ' . implode(', ', $updateFields) . '
         WHERE ' . $clubIdSql . ' = :club_id',
        $updateParams
    );

    $updated = api_fetch_one(
        $db,
        'SELECT ' . $clubIdSql . ' AS api_club_id,
                ' . $clubNameSql . ' AS api_club_name,
                ' . ($clubRegionSql !== null ? $clubRegionSql : 'NULL') . ' AS api_region_id,
                ' . ($clubGovernorSql !== null ? $clubGovernorSql : 'NULL') . ' AS api_governor_id
         FROM clubs
         WHERE ' . $clubIdSql . ' = :club_id
         LIMIT 1',
        [':club_id' => $clubId]
    );

    api_log_admin_action(
        $db,
        $admin,
        'UPDATE',
        'Updated club "' . ($existingClubName !== '' ? $existingClubName : ('#' . $clubId)) . '" to "' . $nextClubName . '"'
    );

    api_json([
        'message' => 'Club updated successfully.',
        'data' => [
            'id' => (int) ($updated['api_club_id'] ?? $clubId),
            'name' => (string) ($updated['api_club_name'] ?? $nextClubName),
            'regionId' => $clubRegionSql !== null
                ? (int) ($updated['api_region_id'] ?? $regionId)
                : null,
            'governorId' => $clubGovernorSql !== null
                ? (int) ($updated['api_governor_id'] ?? $governorId)
                : null,
        ],
    ]);
} catch (Throwable $error) {
    error_log('Admin club update API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to update club right now.',
    ], 500);
}
