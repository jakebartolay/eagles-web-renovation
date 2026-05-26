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
            'message' => 'Only super admins can delete clubs.',
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

    api_execute(
        $db,
        'DELETE FROM clubs WHERE ' . $clubIdSql . ' = :club_id',
        [':club_id' => $clubId]
    );

    $clubName = trim((string) ($existing['api_club_name'] ?? ''));
    api_log_admin_action(
        $db,
        $admin,
        'DELETE',
        'Deleted club "' . ($clubName !== '' ? $clubName : ('#' . $clubId)) . '"'
    );

    api_json([
        'message' => 'Club deleted successfully.',
        'data' => [
            'deletedId' => $clubId,
            'name' => $clubName,
            'regionId' => $clubRegionSql !== null ? (int) ($existing['api_region_id'] ?? 0) : null,
            'governorId' => $clubGovernorSql !== null ? (int) ($existing['api_governor_id'] ?? 0) : null,
        ],
    ]);
} catch (Throwable $error) {
    error_log('Admin club delete API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to delete club right now.',
    ], 500);
}
