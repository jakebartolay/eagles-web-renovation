<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    $admin = api_require_admin($db);

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

    $payload = api_request_data();
    $name = trim((string) ($payload['name'] ?? ''));
    $position = trim((string) ($payload['position'] ?? ''));
    $committee = trim((string) ($payload['committee'] ?? $payload['club'] ?? ''));
    $region = trim((string) ($payload['region'] ?? ''));

    if ($name === '' || $position === '' || $committee === '' || $region === '') {
        api_json([
            'success' => false,
            'message' => 'Name, position, committee, and region are required.',
        ], 422);
    }

    $fields = [$map['name'], $map['position'], $map['committee'], $map['region']];
    $placeholders = [':name', ':position', ':committee', ':region'];
    $params = [
        ':name' => $name,
        ':position' => $position,
        ':committee' => $committee,
        ':region' => $region,
    ];

    if (api_has_column($db, 'appointed', 'created_at')) {
        $fields[] = 'created_at';
        $placeholders[] = 'CURRENT_TIMESTAMP';
    }

    if (api_has_column($db, 'appointed', 'updated_at')) {
        $fields[] = 'updated_at';
        $placeholders[] = 'CURRENT_TIMESTAMP';
    }

    api_execute(
        $db,
        'INSERT INTO appointed (' . implode(', ', array_map('api_quote_identifier', $fields)) . ')
         VALUES (' . implode(', ', $placeholders) . ')',
        $params
    );

    $appointedId = (int) $db->lastInsertId();
    api_log_admin_action($db, $admin, 'CREATE', 'Created appointed officer "' . $name . '"');

    api_json([
        'message' => 'Appointed officer created successfully.',
        'data' => api_appointed_by_id($db, $appointedId),
    ], 201);
} catch (Throwable $error) {
    error_log('Admin appointed create API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to create appointed officer right now.',
    ], 500);
}

