<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

api_start();
api_require_method('POST');

try {
    $db = api_db();
    $admin = api_require_admin($db);

    $payload = api_request_data();
    $eventId = (int) ($payload['id'] ?? $payload['event_id'] ?? 0);

    if ($eventId <= 0) {
        api_json([
            'ok' => false,
            'message' => 'A valid event ID is required.',
        ], 422);
    }

    $current = api_fetch_one($db, '
        SELECT *
        FROM events
        WHERE event_id = :event_id
        LIMIT 1
    ', [':event_id' => $eventId]);

    if ($current === null) {
        api_json([
            'ok' => false,
            'message' => 'Event not found.',
        ], 404);
    }

    $title = trim((string) ($payload['title'] ?? $payload['event_title'] ?? $current['event_title'] ?? ''));
    $description = trim((string) ($payload['description'] ?? $payload['event_description'] ?? $current['event_description'] ?? ''));
    $eventDate = trim((string) ($payload['date'] ?? $payload['event_date'] ?? $current['event_date'] ?? ''));
    $eventType = api_normalize_event_type($payload['type'] ?? $payload['event_type'] ?? $current['event_type'] ?? 'upcoming');

    if ($title === '' || $eventDate === '') {
        api_json([
            'ok' => false,
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
            UPDATE events
            SET event_title = :event_title,
                event_description = :event_description,
                event_date = :event_date,
                event_type = :event_type,
                event_media = :event_media
            WHERE event_id = :event_id
        ', [
            ':event_id' => $eventId,
            ':event_title' => $title,
            ':event_description' => $description !== '' ? $description : null,
            ':event_date' => $eventDate,
            ':event_type' => $eventType,
            ':event_media' => $storedMedia['filename'] ?? (string) ($current['event_media'] ?? ''),
        ]);
    } catch (Throwable $error) {
        api_delete_uploaded_file('media', $storedMedia['filename'] ?? null);
        throw $error;
    }

    if ($storedMedia !== null) {
        api_delete_uploaded_file('media', (string) ($current['event_media'] ?? ''));
    }

    api_log_admin_action($db, $admin, 'UPDATE', 'Updated event "' . $title . '"');

    api_json([
        'ok' => true,
        'message' => 'Event updated successfully.',
        'data' => api_event_by_id($db, $eventId),
    ]);
} catch (Throwable $error) {
    error_log('Admin event update API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to update event right now.',
    ], 500);
}
