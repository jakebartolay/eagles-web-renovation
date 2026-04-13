<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

api_start();
api_require_method('GET');

try {
    $db = api_db();

    $stats = [
        'members' => 0,
        'regions' => 0,
        'clubs' => 0,
    ];

    if (api_table_exists($db, 'user_info')) {
        $memberRow = api_fetch_one($db, 'SELECT COUNT(*) AS total FROM user_info');
        $regionRow = api_fetch_one($db, "SELECT COUNT(DISTINCT NULLIF(TRIM(eagles_region), '')) AS total FROM user_info");
        $clubRow = api_fetch_one($db, "SELECT COUNT(DISTINCT NULLIF(TRIM(eagles_club), '')) AS total FROM user_info");

        $stats = [
            'members' => (int) ($memberRow['total'] ?? 0),
            'regions' => (int) ($regionRow['total'] ?? 0),
            'clubs' => (int) ($clubRow['total'] ?? 0),
        ];
    }

    $latestNews = null;
    if (api_table_exists($db, 'news_info')) {
        $newsRow = api_fetch_one($db, '
            SELECT news_id, news_title, news_content, news_status, news_image, created_at
            FROM news_info
            WHERE news_status = :news_status
            ORDER BY news_id DESC
            LIMIT 1
        ', [':news_status' => 'Published']);

        if ($newsRow !== null) {
            $latestNews = api_news_payload($db, $newsRow);
        }
    }

    $memorandums = [];
    if (api_table_exists($db, 'memorandum')) {
        $memoRows = api_fetch_all($db, '
            SELECT memo_id, memo_title, memo_description
            FROM memorandum
            WHERE memo_status = :memo_status
            ORDER BY memo_id DESC
            LIMIT 8
        ', [':memo_status' => 'Published']);

        foreach ($memoRows as $memo) {
            $pages = [];

            if (api_table_exists($db, 'memorandum_pages')) {
                $pageRows = api_fetch_all($db, '
                    SELECT page_image
                    FROM memorandum_pages
                    WHERE memo_id = :memo_id
                    ORDER BY page_number ASC
                ', [':memo_id' => (int) ($memo['memo_id'] ?? 0)]);

                foreach ($pageRows as $page) {
                    $pageImage = trim((string) ($page['page_image'] ?? ''));
                    if ($pageImage === '') {
                        continue;
                    }

                    $pageUrl = api_media_url('memorandum', $pageImage);
                    if ($pageUrl !== null) {
                        $pages[] = $pageUrl;
                    }
                }
            }

            $memorandums[] = [
                'id' => (int) ($memo['memo_id'] ?? 0),
                'title' => (string) ($memo['memo_title'] ?? ''),
                'description' => (string) ($memo['memo_description'] ?? ''),
                'coverUrl' => $pages[0] ?? null,
                'pages' => $pages,
            ];
        }
    }

    $events = [];
    if (api_table_exists($db, 'events')) {
        $eventRows = api_fetch_all($db, '
            SELECT event_id, event_title, event_description, event_date, event_type, event_media
            FROM events
            WHERE event_date >= CURDATE()
            ORDER BY event_date ASC
            LIMIT 5
        ');

        foreach ($eventRows as $event) {
            $mediaFile = trim((string) ($event['event_media'] ?? ''));

            $events[] = [
                'id' => (int) ($event['event_id'] ?? 0),
                'title' => (string) ($event['event_title'] ?? ''),
                'description' => (string) ($event['event_description'] ?? ''),
                'date' => (string) ($event['event_date'] ?? ''),
                'type' => (string) ($event['event_type'] ?? ''),
                'mediaUrl' => $mediaFile !== '' ? api_media_url('event_media', $mediaFile) : null,
                'mediaType' => api_media_type($mediaFile),
            ];
        }
    }

    api_json([
        'ok' => true,
        'data' => [
            'stats' => $stats,
            'latestNews' => $latestNews,
            'memorandums' => $memorandums,
            'events' => $events,
        ],
    ]);
} catch (Throwable $error) {
    error_log('Public home API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to load public data right now.',
    ], 500);
}
