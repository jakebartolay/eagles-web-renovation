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
            'success' => false,
            'message' => 'A valid event ID is required.',
        ], 422);
    }

    $eventRow = api_fetch_one($db, '
        SELECT event_id, event_title, event_media
        FROM events
        WHERE event_id = :event_id
        LIMIT 1
    ', [':event_id' => $eventId]);

    if ($eventRow === null) {
        api_json([
            'success' => false,
            'message' => 'Event not found.',
        ], 404);
    }

    api_execute($db, '
        DELETE FROM events
        WHERE event_id = :event_id
    ', [':event_id' => $eventId]);

    api_delete_uploaded_file('media', (string) ($eventRow['event_media'] ?? ''));
    api_log_admin_action($db, $admin, 'DELETE', 'Deleted event "' . (string) ($eventRow['event_title'] ?? '') . '"');

    api_json([
        'message' => 'Event deleted successfully.',
        'data' => [
            'deletedId' => $eventId,
        ],
    ]);
} catch (Throwable $error) {
    error_log('Admin event delete API error: ' . $error->getMessage());
    api_json([
        'success' => false,
        'message' => 'Unable to delete event right now.',
    ], 500);
}
