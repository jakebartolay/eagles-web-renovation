<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    $admin = api_require_admin($db);

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

    $clubName = trim((string) ($payload['name'] ?? $payload['club_name'] ?? ''));
    if ($clubName === '') {
        api_json([
            'success' => false,
            'message' => 'Club name is required.',
        ], 422);
    }

    $regionId = (int) ($payload['region_id'] ?? $payload['regionId'] ?? 0);
    $governorId = (int) ($payload['governor_id'] ?? $payload['governorId'] ?? 0);

    if ($clubRegionIdColumn !== null && $regionId <= 0) {
        api_json([
            'success' => false,
            'message' => 'Region ID is required to create a club.',
        ], 422);
    }

    $regionIdSql = $clubRegionIdColumn !== null ? api_quote_identifier($clubRegionIdColumn) : null;
    $governorIdSql = $clubGovernorIdColumn !== null ? api_quote_identifier($clubGovernorIdColumn) : null;
    $regionGovernorId = 0;

    if ($regionIdSql !== null && $regionId > 0 && api_table_exists($db, 'regions')) {
        $regionIdColumn = api_first_column($db, 'regions', ['region_id', 'id']) ?? 'region_id';
        $regionNameColumn = api_first_column($db, 'regions', ['region_name', 'name']) ?? 'region_name';
        $regionGovernorColumn = api_first_column($db, 'regions', ['governor_id']);
        $regionIdQuoted = api_quote_identifier($regionIdColumn);
        $regionGovernorQuoted = $regionGovernorColumn !== null ? api_quote_identifier($regionGovernorColumn) : null;

        $regionRow = api_fetch_one(
            $db,
            'SELECT ' . $regionIdQuoted . ' AS api_region_id,
                    ' . api_quote_identifier($regionNameColumn) . ' AS api_region_name,
                    ' . ($regionGovernorQuoted !== null ? $regionGovernorQuoted : 'NULL') . ' AS api_governor_id
             FROM regions
             WHERE ' . $regionIdQuoted . ' = :region_id
             LIMIT 1',
            [':region_id' => $regionId]
        );

        if ($regionRow === null) {
            api_json([
                'success' => false,
                'message' => 'Selected region does not exist.',
            ], 422);
        }

        $regionGovernorId = (int) ($regionRow['api_governor_id'] ?? 0);
    }

    if ($governorId <= 0 && $regionGovernorId > 0) {
        $governorId = $regionGovernorId;
    }

    if ($governorIdSql !== null && $governorId <= 0) {
        api_json([
            'success' => false,
            'message' => 'Governor ID is required to create a club.',
        ], 422);
    }

    if ($governorIdSql !== null && $governorId > 0 && api_table_exists($db, 'governors')) {
        $governorExists = api_fetch_one(
            $db,
            'SELECT governor_id
             FROM governors
             WHERE governor_id = :governor_id
             LIMIT 1',
            [':governor_id' => $governorId]
        );

        if ($governorExists === null) {
            api_json([
                'success' => false,
                'message' => 'Selected governor does not exist.',
            ], 422);
        }
    }

    $clubIdSql = api_quote_identifier($clubIdColumn);
    $clubNameSql = api_quote_identifier($clubNameColumn);

    $existingWhere = 'UPPER(' . $clubNameSql . ') = UPPER(:club_name)';
    $existingParams = [':club_name' => $clubName];

    if ($regionIdSql !== null) {
        $existingWhere .= ' AND ' . $regionIdSql . ' = :region_id';
        $existingParams[':region_id'] = $regionId;
    }

    $existing = api_fetch_one(
        $db,
        'SELECT ' . $clubIdSql . ' AS api_club_id,
                ' . $clubNameSql . ' AS api_club_name,
                ' . ($regionIdSql !== null ? $regionIdSql : 'NULL') . ' AS api_region_id,
                ' . ($governorIdSql !== null ? $governorIdSql : 'NULL') . ' AS api_governor_id
         FROM clubs
         WHERE ' . $existingWhere . '
         LIMIT 1',
        $existingParams
    );

    if ($existing !== null) {
        $existingGovernorId = (int) ($existing['api_governor_id'] ?? 0);

        if ($governorIdSql !== null && $governorId > 0 && $existingGovernorId > 0 && $existingGovernorId !== $governorId) {
            api_json([
                'success' => false,
                'message' => 'Club name already exists under a different governor.',
            ], 409);
        }

        api_json([
            'message' => 'Club already exists.',
            'data' => [
                'id' => (int) ($existing['api_club_id'] ?? 0),
                'name' => (string) ($existing['api_club_name'] ?? $clubName),
                'regionId' => $regionIdSql !== null
                    ? (int) ($existing['api_region_id'] ?? $regionId)
                    : null,
                'governorId' => $governorIdSql !== null
                    ? (int) ($existing['api_governor_id'] ?? $governorId)
                    : null,
            ],
        ]);
    }

    $insertFields = [$clubNameSql];
    $insertValues = [':club_name'];
    $insertParams = [':club_name' => $clubName];

    if ($regionIdSql !== null) {
        $insertFields[] = $regionIdSql;
        $insertValues[] = ':region_id';
        $insertParams[':region_id'] = $regionId;
    }

    if ($governorIdSql !== null) {
        $insertFields[] = $governorIdSql;
        $insertValues[] = ':governor_id';
        $insertParams[':governor_id'] = $governorId;
    }

    api_execute(
        $db,
        'INSERT INTO clubs (' . implode(', ', $insertFields) . ')
         VALUES (' . implode(', ', $insertValues) . ')',
        $insertParams
    );

    $createdId = (int) $db->lastInsertId();
    $created = $createdId > 0
        ? api_fetch_one(
            $db,
            'SELECT ' . $clubIdSql . ' AS api_club_id,
                    ' . $clubNameSql . ' AS api_club_name,
                    ' . ($regionIdSql !== null ? $regionIdSql : 'NULL') . ' AS api_region_id,
                    ' . ($governorIdSql !== null ? $governorIdSql : 'NULL') . ' AS api_governor_id
             FROM clubs
             WHERE ' . $clubIdSql . ' = :club_id
             LIMIT 1',
            [':club_id' => $createdId]
        )
        : null;

    api_log_admin_action($db, $admin, 'CREATE', 'Created club "' . $clubName . '"');

    api_json([
        'message' => 'Club created successfully.',
        'data' => [
            'id' => (int) ($created['api_club_id'] ?? $createdId),
            'name' => (string) ($created['api_club_name'] ?? $clubName),
            'regionId' => $regionIdSql !== null
                ? (int) ($created['api_region_id'] ?? $regionId)
                : null,
            'governorId' => $governorIdSql !== null
                ? (int) ($created['api_governor_id'] ?? $governorId)
                : null,
        ],
    ], 201);
} catch (Throwable $error) {
    error_log('Admin club create API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to create club right now.',
    ], 500);
}
