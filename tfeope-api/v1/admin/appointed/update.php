<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    $admin = api_require_admin($db);

    $payload = api_request_data();
    $appointedId = (int) ($payload['id'] ?? $payload['appointed_id'] ?? 0);

    if ($appointedId <= 0) {
        api_json([
            'success' => false,
            'message' => 'A valid appointed officer ID is required.',
        ], 422);
    }

    if (!api_table_exists($db, 'appointed')) {
        api_json([
            'success' => false,
            'message' => 'Appointed table is not available.',
        ], 500);
    }

    $map = api_appointed_field_map($db);
    if ($map['name'] === null || $map['position'] === null || $map['committee'] === null || $map['region'] === null) {
        api_json([
            'success' => false,
            'message' => 'Appointed table columns are incomplete.',
        ], 500);
    }

    $existing = api_appointed_by_id($db, $appointedId);
    if ($existing === null) {
        api_json([
            'success' => false,
            'message' => 'Appointed officer not found.',
        ], 404);
    }

    $name = trim((string) ($payload['name'] ?? $existing['name'] ?? ''));
    $position = trim((string) ($payload['position'] ?? $existing['position'] ?? ''));
    $committee = trim((string) ($payload['committee'] ?? $payload['club'] ?? $existing['committee'] ?? ''));
    $region = trim((string) ($payload['region'] ?? $existing['region'] ?? ''));

    if ($name === '' || $position === '' || $committee === '' || $region === '') {
        api_json([
            'success' => false,
            'message' => 'Name, position, committee, and region are required.',
        ], 422);
    }

    $updates = [
        api_quote_identifier($map['name']) . ' = :name',
        api_quote_identifier($map['position']) . ' = :position',
        api_quote_identifier($map['committee']) . ' = :committee',
        api_quote_identifier($map['region']) . ' = :region',
    ];
    $params = [
        ':id' => $appointedId,
        ':name' => $name,
        ':position' => $position,
        ':committee' => $committee,
        ':region' => $region,
    ];

    if (api_has_column($db, 'appointed', 'updated_at')) {
        $updates[] = '`updated_at` = CURRENT_TIMESTAMP';
    }

    api_execute(
        $db,
        'UPDATE appointed SET ' . implode(', ', $updates) . ' WHERE id = :id',
        $params
    );

    api_log_admin_action($db, $admin, 'UPDATE', 'Updated appointed officer "' . $name . '"');

    api_json([
        'message' => 'Appointed officer updated successfully.',
        'data' => api_appointed_by_id($db, $appointedId),
    ]);
} catch (Throwable $error) {
    error_log('Admin appointed update API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to update appointed officer right now.',
    ], 500);
}

