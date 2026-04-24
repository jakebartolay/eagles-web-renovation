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
            'message' => 'Only super admins can create regions.',
        ], 403);
    }

    if (!api_table_exists($db, 'regions')) {
        api_json([
            'success' => false,
            'message' => 'Regions table is not available.',
        ], 500);
    }

    $payload = api_request_data();
    $regionIdColumn = api_first_column($db, 'regions', ['region_id', 'id']) ?? 'region_id';
    $regionNameColumn = api_first_column($db, 'regions', ['region_name', 'name']) ?? 'region_name';
    $regionGovernorIdColumn = api_first_column($db, 'regions', ['governor_id']);

    $regionName = trim((string) ($payload['name'] ?? $payload['region_name'] ?? ''));
    if ($regionName === '') {
        api_json([
            'success' => false,
            'message' => 'Region name is required.',
        ], 422);
    }

    $governorId = (int) ($payload['governor_id'] ?? $payload['governorId'] ?? 0);

    if ($regionGovernorIdColumn !== null && $governorId <= 0) {
        api_json([
            'success' => false,
            'message' => 'Governor ID is required to create a region.',
        ], 422);
    }

    if ($regionGovernorIdColumn !== null && $governorId > 0 && api_table_exists($db, 'governors')) {
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

    $regionIdSql = api_quote_identifier($regionIdColumn);
    $regionNameSql = api_quote_identifier($regionNameColumn);
    $regionGovernorSql = $regionGovernorIdColumn !== null
        ? api_quote_identifier($regionGovernorIdColumn)
        : null;

    $existing = api_fetch_one(
        $db,
        'SELECT ' . $regionIdSql . ' AS api_region_id,
                ' . $regionNameSql . ' AS api_region_name,
                ' . ($regionGovernorSql !== null ? $regionGovernorSql : 'NULL') . ' AS api_governor_id
         FROM regions
         WHERE UPPER(' . $regionNameSql . ') = UPPER(:region_name)
         LIMIT 1',
        [':region_name' => $regionName]
    );

    if ($existing !== null) {
        $existingGovernorId = (int) ($existing['api_governor_id'] ?? 0);

        if ($regionGovernorSql !== null && $governorId > 0 && $existingGovernorId > 0 && $existingGovernorId !== $governorId) {
            api_json([
                'success' => false,
                'message' => 'Region name already exists under a different governor.',
            ], 409);
        }

        api_json([
            'message' => 'Region already exists.',
            'data' => [
                'id' => (int) ($existing['api_region_id'] ?? 0),
                'name' => (string) ($existing['api_region_name'] ?? $regionName),
                'governorId' => $regionGovernorSql !== null
                    ? (int) ($existing['api_governor_id'] ?? $governorId)
                    : null,
            ],
        ]);
    }

    $insertFields = [$regionNameSql];
    $insertValues = [':region_name'];
    $insertParams = [':region_name' => $regionName];

    if ($regionGovernorSql !== null) {
        $insertFields[] = $regionGovernorSql;
        $insertValues[] = ':governor_id';
        $insertParams[':governor_id'] = $governorId;
    }

    api_execute(
        $db,
        'INSERT INTO regions (' . implode(', ', $insertFields) . ')
         VALUES (' . implode(', ', $insertValues) . ')',
        $insertParams
    );

    $createdId = (int) $db->lastInsertId();
    $created = $createdId > 0
        ? api_fetch_one(
            $db,
            'SELECT ' . $regionIdSql . ' AS api_region_id,
                    ' . $regionNameSql . ' AS api_region_name,
                    ' . ($regionGovernorSql !== null ? $regionGovernorSql : 'NULL') . ' AS api_governor_id
             FROM regions
             WHERE ' . $regionIdSql . ' = :region_id
             LIMIT 1',
            [':region_id' => $createdId]
        )
        : null;

    api_log_admin_action($db, $admin, 'CREATE', 'Created region "' . $regionName . '"');

    api_json([
        'message' => 'Region created successfully.',
        'data' => [
            'id' => (int) ($created['api_region_id'] ?? $createdId),
            'name' => (string) ($created['api_region_name'] ?? $regionName),
            'governorId' => $regionGovernorSql !== null
                ? (int) ($created['api_governor_id'] ?? $governorId)
                : null,
        ],
    ], 201);
} catch (Throwable $error) {
    error_log('Admin region create API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to create region right now.',
    ], 500);
}
