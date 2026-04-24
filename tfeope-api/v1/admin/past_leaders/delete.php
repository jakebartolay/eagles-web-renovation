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
            'message' => 'Only super admins can manage past leaders.',
        ], 403);
    }

    $payload = api_request_data();
    $pastLeaderId = (int) ($payload['id'] ?? $payload['past_leader_id'] ?? 0);

    if ($pastLeaderId <= 0) {
        api_json([
            'success' => false,
            'message' => 'A valid past leader ID is required.',
        ], 422);
    }

    api_ensure_past_leaders_table($db);
    $map = api_past_leader_field_map($db);
    if ($map['id'] === null) {
        api_json([
            'success' => false,
            'message' => 'Past leaders table columns are incomplete.',
        ], 500);
    }

    $existing = api_past_leader_by_id($db, $pastLeaderId);
    if ($existing === null) {
        api_json([
            'success' => false,
            'message' => 'Past leader not found.',
        ], 404);
    }

    if ($map['is_active'] !== null) {
        $updates = [
            api_quote_identifier($map['is_active']) . ' = 0',
        ];

        if ($map['updated_at'] !== null) {
            $updates[] = api_quote_identifier($map['updated_at']) . ' = CURRENT_TIMESTAMP';
        }

        api_execute(
            $db,
            'UPDATE past_leaders SET ' . implode(', ', $updates) . '
             WHERE ' . api_quote_identifier($map['id']) . ' = :id',
            [':id' => $pastLeaderId]
        );
    } else {
        api_execute(
            $db,
            'DELETE FROM past_leaders WHERE ' . api_quote_identifier($map['id']) . ' = :id',
            [':id' => $pastLeaderId]
        );
    }

    api_log_admin_action($db, $admin, 'DELETE', 'Deleted past leader "' . (string) ($existing['name'] ?? '') . '"');

    api_json([
        'message' => 'Past leader deleted successfully.',
        'data' => [
            'deletedId' => $pastLeaderId,
        ],
    ]);
} catch (Throwable $error) {
    error_log('Admin past leader delete API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to delete past leader right now.',
    ], 500);
}

