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
    if ($map['name'] === null || $map['position'] === null || $map['term_start'] === null || $map['term_end'] === null) {
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

    $name = trim((string) ($payload['name'] ?? $existing['name'] ?? ''));
    $position = trim((string) ($payload['position'] ?? $existing['position'] ?? ''));
    $termStart = (int) preg_replace('/\D+/', '', (string) ($payload['term_start'] ?? $payload['termStart'] ?? $existing['termStart'] ?? ''));
    $termEnd = (int) preg_replace('/\D+/', '', (string) ($payload['term_end'] ?? $payload['termEnd'] ?? $existing['termEnd'] ?? ''));
    $achievements = trim((string) ($payload['achievements'] ?? $existing['achievements'] ?? ''));
    $orderPriority = (int) ($payload['order_priority'] ?? $payload['orderPriority'] ?? $existing['orderPriority'] ?? 0);
    $isActive = (int) ($payload['is_active'] ?? $payload['isActive'] ?? ($existing['isActive'] ? 1 : 0)) === 1 ? 1 : 0;

    if ($name === '' || $position === '') {
        api_json([
            'success' => false,
            'message' => 'Name and position are required.',
        ], 422);
    }

    if ($termStart < 1900 || $termStart > 2100 || $termEnd < 1900 || $termEnd > 2100) {
        api_json([
            'success' => false,
            'message' => 'Term start and term end must be valid years.',
        ], 422);
    }

    if ($termEnd < $termStart) {
        api_json([
            'success' => false,
            'message' => 'Term end cannot be earlier than term start.',
        ], 422);
    }

    $photoUpload = $_FILES['photo'] ?? $_FILES['image'] ?? null;
    $storedPhoto = null;

    if (is_array($photoUpload) && (int) ($photoUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $storedPhoto = api_store_uploaded_file($photoUpload, 'past-leaders', api_image_extensions());
    }

    $updates = [
        api_quote_identifier($map['name']) . ' = :name',
        api_quote_identifier($map['position']) . ' = :position',
        api_quote_identifier($map['term_start']) . ' = :term_start',
        api_quote_identifier($map['term_end']) . ' = :term_end',
    ];
    $params = [
        ':id' => $pastLeaderId,
        ':name' => $name,
        ':position' => $position,
        ':term_start' => $termStart,
        ':term_end' => $termEnd,
    ];

    if ($map['photo'] !== null && $storedPhoto !== null) {
        $updates[] = api_quote_identifier($map['photo']) . ' = :photo';
        $params[':photo'] = $storedPhoto['filename'];
    }

    if ($map['achievements'] !== null) {
        $updates[] = api_quote_identifier($map['achievements']) . ' = :achievements';
        $params[':achievements'] = $achievements;
    }

    if ($map['order_priority'] !== null) {
        $updates[] = api_quote_identifier($map['order_priority']) . ' = :order_priority';
        $params[':order_priority'] = $orderPriority;
    }

    if ($map['is_active'] !== null) {
        $updates[] = api_quote_identifier($map['is_active']) . ' = :is_active';
        $params[':is_active'] = $isActive;
    }

    if ($map['updated_at'] !== null) {
        $updates[] = api_quote_identifier($map['updated_at']) . ' = CURRENT_TIMESTAMP';
    }

    try {
        api_execute(
            $db,
            'UPDATE past_leaders SET ' . implode(', ', $updates) . '
             WHERE ' . api_quote_identifier($map['id']) . ' = :id',
            $params
        );

        if ($storedPhoto !== null) {
            api_delete_uploaded_file('past-leaders', (string) ($existing['photoFilename'] ?? ''));
            api_delete_uploaded_file('media', (string) ($existing['photoFilename'] ?? ''));
        }

        api_log_admin_action($db, $admin, 'UPDATE', 'Updated past leader "' . $name . '"');

        api_json([
            'message' => 'Past leader updated successfully.',
            'data' => api_past_leader_by_id($db, $pastLeaderId),
        ]);
    } catch (Throwable $error) {
        api_delete_uploaded_file('past-leaders', $storedPhoto['filename'] ?? null);
        throw $error;
    }
} catch (Throwable $error) {
    error_log('Admin past leader update API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to update past leader right now.',
    ], 500);
}

