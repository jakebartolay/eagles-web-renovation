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
            'message' => 'Only super admins can update regions.',
        ], 403);
    }

    if (!api_table_exists($db, 'regions')) {
        api_json([
            'success' => false,
            'message' => 'Regions table is not available.',
        ], 500);
    }

    $payload = api_request_data();

    $regionId = (int) ($payload['id'] ?? $payload['region_id'] ?? 0);
    if ($regionId <= 0) {
        api_json([
            'success' => false,
            'message' => 'A valid region ID is required.',
        ], 422);
    }

    $regionIdColumn = api_first_column($db, 'regions', ['region_id', 'id']) ?? 'region_id';
    $regionNameColumn = api_first_column($db, 'regions', ['region_name', 'name']) ?? 'region_name';
    $regionGovernorIdColumn = api_first_column($db, 'regions', ['governor_id']);

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
         WHERE ' . $regionIdSql . ' = :region_id
         LIMIT 1',
        [':region_id' => $regionId]
    );

    if ($existing === null) {
        api_json([
            'success' => false,
            'message' => 'Region not found.',
        ], 404);
    }

    $existingRegionName = trim((string) ($existing['api_region_name'] ?? ''));
    $nextRegionName = trim((string) ($payload['name'] ?? $payload['region_name'] ?? $existingRegionName));

    if ($nextRegionName === '') {
        api_json([
            'success' => false,
            'message' => 'Region name is required.',
        ], 422);
    }

    $governorId = (int) (
        $payload['governor_id']
        ?? $payload['governorId']
        ?? $existing['api_governor_id']
        ?? 0
    );

    if ($regionGovernorSql !== null && $governorId > 0 && api_table_exists($db, 'governors')) {
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

    $duplicate = api_fetch_one(
        $db,
        'SELECT ' . $regionIdSql . ' AS api_region_id,
                ' . ($regionGovernorSql !== null ? $regionGovernorSql : 'NULL') . ' AS api_governor_id
         FROM regions
         WHERE UPPER(' . $regionNameSql . ') = UPPER(:region_name)
           AND ' . $regionIdSql . ' <> :region_id
         LIMIT 1',
        [
            ':region_name' => $nextRegionName,
            ':region_id' => $regionId,
        ]
    );

    if ($duplicate !== null) {
        $duplicateGovernorId = (int) ($duplicate['api_governor_id'] ?? 0);

        if ($regionGovernorSql !== null && $governorId > 0 && $duplicateGovernorId > 0 && $duplicateGovernorId !== $governorId) {
            api_json([
                'success' => false,
                'message' => 'Region name already exists under a different governor.',
            ], 409);
        }

        api_json([
            'success' => false,
            'message' => 'Region name already exists.',
        ], 409);
    }

    $updateFields = [$regionNameSql . ' = :region_name'];
    $updateParams = [
        ':region_name' => $nextRegionName,
        ':region_id' => $regionId,
    ];

    if ($regionGovernorSql !== null && $governorId > 0) {
        $updateFields[] = $regionGovernorSql . ' = :governor_id';
        $updateParams[':governor_id'] = $governorId;
    }

    api_execute(
        $db,
        'UPDATE regions
         SET ' . implode(', ', $updateFields) . '
         WHERE ' . $regionIdSql . ' = :region_id',
        $updateParams
    );

    $updated = api_fetch_one(
        $db,
        'SELECT ' . $regionIdSql . ' AS api_region_id,
                ' . $regionNameSql . ' AS api_region_name,
                ' . ($regionGovernorSql !== null ? $regionGovernorSql : 'NULL') . ' AS api_governor_id
         FROM regions
         WHERE ' . $regionIdSql . ' = :region_id
         LIMIT 1',
        [':region_id' => $regionId]
    );

    $logRegionName = $existingRegionName !== '' ? $existingRegionName : ('#' . $regionId);
    api_log_admin_action(
        $db,
        $admin,
        'UPDATE',
        'Updated region "' . $logRegionName . '" to "' . $nextRegionName . '"'
    );

    api_json([
        'message' => 'Region updated successfully.',
        'data' => [
            'id' => (int) ($updated['api_region_id'] ?? $regionId),
            'name' => (string) ($updated['api_region_name'] ?? $nextRegionName),
            'governorId' => $regionGovernorSql !== null
                ? (int) ($updated['api_governor_id'] ?? $governorId)
                : null,
        ],
    ]);
} catch (Throwable $error) {
    error_log('Admin region update API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to update region right now.',
    ], 500);
}
