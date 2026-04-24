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

    api_ensure_past_leaders_table($db);
    $map = api_past_leader_field_map($db);
    if ($map['name'] === null || $map['position'] === null || $map['term_start'] === null || $map['term_end'] === null) {
        api_json([
            'success' => false,
            'message' => 'Past leaders table columns are incomplete.',
        ], 500);
    }

    $payload = api_request_data();

    $name = trim((string) ($payload['name'] ?? ''));
    $position = trim((string) ($payload['position'] ?? ''));
    $termStart = (int) preg_replace('/\D+/', '', (string) ($payload['term_start'] ?? $payload['termStart'] ?? ''));
    $termEnd = (int) preg_replace('/\D+/', '', (string) ($payload['term_end'] ?? $payload['termEnd'] ?? ''));
    $achievements = trim((string) ($payload['achievements'] ?? ''));
    $orderPriority = (int) ($payload['order_priority'] ?? $payload['orderPriority'] ?? 0);
    $isActive = (int) ($payload['is_active'] ?? $payload['isActive'] ?? 1) === 1 ? 1 : 0;

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

    $fields = [
        $map['name'],
        $map['position'],
        $map['term_start'],
        $map['term_end'],
    ];
    $placeholders = [
        ':name',
        ':position',
        ':term_start',
        ':term_end',
    ];
    $params = [
        ':name' => $name,
        ':position' => $position,
        ':term_start' => $termStart,
        ':term_end' => $termEnd,
    ];

    if ($map['photo'] !== null) {
        $fields[] = $map['photo'];
        $placeholders[] = ':photo';
        $params[':photo'] = $storedPhoto['filename'] ?? null;
    }

    if ($map['achievements'] !== null) {
        $fields[] = $map['achievements'];
        $placeholders[] = ':achievements';
        $params[':achievements'] = $achievements;
    }

    if ($map['order_priority'] !== null) {
        $fields[] = $map['order_priority'];
        $placeholders[] = ':order_priority';
        $params[':order_priority'] = $orderPriority;
    }

    if ($map['is_active'] !== null) {
        $fields[] = $map['is_active'];
        $placeholders[] = ':is_active';
        $params[':is_active'] = $isActive;
    }

    if ($map['created_at'] !== null) {
        $fields[] = $map['created_at'];
        $placeholders[] = 'CURRENT_TIMESTAMP';
    }

    if ($map['updated_at'] !== null) {
        $fields[] = $map['updated_at'];
        $placeholders[] = 'CURRENT_TIMESTAMP';
    }

    try {
        api_execute(
            $db,
            'INSERT INTO past_leaders (' . implode(', ', array_map('api_quote_identifier', $fields)) . ')
             VALUES (' . implode(', ', $placeholders) . ')',
            $params
        );

        $pastLeaderId = (int) $db->lastInsertId();
        api_log_admin_action($db, $admin, 'CREATE', 'Created past leader "' . $name . '"');

        api_json([
            'message' => 'Past leader created successfully.',
            'data' => api_past_leader_by_id($db, $pastLeaderId),
        ], 201);
    } catch (Throwable $error) {
        api_delete_uploaded_file('past-leaders', $storedPhoto['filename'] ?? null);
        throw $error;
    }
} catch (Throwable $error) {
    error_log('Admin past leader create API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to create past leader right now.',
    ], 500);
}

