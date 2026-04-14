<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    $admin = api_require_admin($db);

    if (!api_table_exists($db, 'events')) {
        api_json([
            'success' => false,
            'message' => 'Events table is not available.',
        ], 500);
    }

    $payload = api_request_data();
    $title = trim((string) ($payload['title'] ?? $payload['event_title'] ?? ''));
    $description = trim((string) ($payload['description'] ?? $payload['event_description'] ?? ''));
    $eventDate = trim((string) ($payload['date'] ?? $payload['event_date'] ?? ''));
    $eventType = api_normalize_event_type($payload['type'] ?? $payload['event_type'] ?? 'upcoming');

    if ($title === '' || $eventDate === '') {
        api_json([
            'success' => false,
            'message' => 'Event title and date are required.',
        ], 422);
    }

    $mediaUpload = $_FILES['media'] ?? $_FILES['event_media'] ?? null;
    $storedMedia = null;

    if (is_array($mediaUpload) && (int) ($mediaUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $storedMedia = api_store_uploaded_file(
            $mediaUpload,
            'media',
            array_merge(api_image_extensions(), api_video_extensions())
        );
    }

    try {
        api_execute($db, '
            INSERT INTO events (
                event_title,
                event_description,
                event_date,
                event_type,
                event_media
            ) VALUES (
                :event_title,
                :event_description,
                :event_date,
                :event_type,
                :event_media
            )
        ', [
            ':event_title' => $title,
            ':event_description' => $description !== '' ? $description : null,
            ':event_date' => $eventDate,
            ':event_type' => $eventType,
            ':event_media' => $storedMedia['filename'] ?? null,
        ]);

        $eventId = (int) $db->lastInsertId();

        api_log_admin_action($db, $admin, 'CREATE', 'Created event "' . $title . '"');

        api_json([
            'message' => 'Event created successfully.',
            'data' => api_event_by_id($db, $eventId),
        ], 201);
    } catch (Throwable $error) {
        api_delete_uploaded_file('media', $storedMedia['filename'] ?? null);
        throw $error;
    }
} catch (Throwable $error) {
    error_log('Admin event create API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to create event right now.',
    ], 500);
}
