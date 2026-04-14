<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

api_start();
api_require_method('GET');

if (!function_exists('public_api_count')) {
    function public_api_count(PDO $db, string $table, ?string $where = null, array $params = []): int
    {
        if (!api_table_exists($db, $table)) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) AS total FROM ' . api_quote_identifier($table);
        if ($where !== null && trim($where) !== '') {
            $sql .= ' WHERE ' . $where;
        }

        $row = api_fetch_one($db, $sql, $params);

        return (int) ($row['total'] ?? 0);
    }
}

try {
    $db = api_db();
    $news = api_news_list($db, true);
    $memorandums = array_slice(api_memorandum_list($db, true), 0, 6);
    $events = array_slice(api_events_list($db, 'upcoming'), 0, 6);

    api_json([
        'ok' => true,
        'data' => [
            'stats' => [
                'members' => public_api_count($db, 'user_info'),
                'regions' => public_api_count($db, 'regions'),
                'clubs' => public_api_count($db, 'clubs'),
            ],
            'latestNews' => $news[0] ?? null,
            'memorandums' => $memorandums,
            'events' => $events,
        ],
    ]);
} catch (Throwable $error) {
    error_log('Public home API error: ' . $error->getMessage());

    api_json([
        'ok' => false,
        'message' => 'Unable to load public home data right now.',
    ], 500);
}
