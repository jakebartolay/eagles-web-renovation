<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('GET');

try {
    $db = api_db();

    if (!api_table_exists($db, 'clubs')) {
        api_json([
            'ok' => true,
            'data' => [],
        ]);
    }

    $idColumn = api_first_column($db, 'clubs', ['club_id', 'id']) ?? 'club_id';
    $nameColumn = api_first_column($db, 'clubs', ['club_name', 'name']) ?? 'club_name';
    $regionIdColumn = api_first_column($db, 'clubs', ['region_id']);
    $governorIdColumn = api_first_column($db, 'clubs', ['governor_id']);
    $createdAtColumn = api_first_column($db, 'clubs', ['created_at']);
    $updatedAtColumn = api_first_column($db, 'clubs', ['updated_at']);

    $selectParts = [
        api_quote_identifier($idColumn) . ' AS api_id',
        api_quote_identifier($nameColumn) . ' AS api_name',
    ];

    if ($regionIdColumn !== null) {
        $selectParts[] = api_quote_identifier($regionIdColumn) . ' AS api_region_id';
    }
    if ($governorIdColumn !== null) {
        $selectParts[] = api_quote_identifier($governorIdColumn) . ' AS api_governor_id';
    }
    if ($createdAtColumn !== null) {
        $selectParts[] = api_quote_identifier($createdAtColumn) . ' AS api_created_at';
    }
    if ($updatedAtColumn !== null) {
        $selectParts[] = api_quote_identifier($updatedAtColumn) . ' AS api_updated_at';
    }

    $rows = api_fetch_all(
        $db,
        'SELECT ' . implode(', ', $selectParts) . '
         FROM clubs
         ORDER BY ' . api_quote_identifier($nameColumn) . ' ASC'
    );

    $data = array_map(static function (array $row): array {
        return [
            'id' => (int) ($row['api_id'] ?? 0),
            'name' => (string) ($row['api_name'] ?? ''),
            'regionId' => isset($row['api_region_id']) ? (int) $row['api_region_id'] : null,
            'governorId' => isset($row['api_governor_id']) ? (int) $row['api_governor_id'] : null,
            'createdAt' => isset($row['api_created_at']) ? (string) $row['api_created_at'] : null,
            'updatedAt' => isset($row['api_updated_at']) ? (string) $row['api_updated_at'] : null,
        ];
    }, $rows);

    api_json([
        'ok' => true,
        'data' => $data,
    ]);
} catch (Throwable $error) {
    error_log('Client clubs list API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to load clubs right now.',
    ], 500);
}

