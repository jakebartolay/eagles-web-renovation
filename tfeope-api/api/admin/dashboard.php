<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

api_start();
api_require_method('GET');

try {
    $db = api_db();
    $user = api_require_admin($db);
    $isSuperAdmin = ((int) ($user['role_id'] ?? 0)) === 1;

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

    $recentMembers = [];
    if (api_table_exists($db, 'user_info')) {
        $memberRows = api_fetch_all($db, '
            SELECT eagles_firstName, eagles_lastName, eagles_region, eagles_club, eagles_position
            FROM user_info
            ORDER BY eagles_id DESC
            LIMIT 5
        ');

        foreach ($memberRows as $member) {
            $recentMembers[] = [
                'name' => trim((string) ($member['eagles_firstName'] ?? '') . ' ' . (string) ($member['eagles_lastName'] ?? '')),
                'region' => (string) ($member['eagles_region'] ?? ''),
                'club' => (string) ($member['eagles_club'] ?? ''),
                'position' => (string) ($member['eagles_position'] ?? ''),
            ];
        }
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

    $latestVideo = null;
    if (api_table_exists($db, 'video_info')) {
        $videoRow = api_fetch_one($db, '
            SELECT video_id, video_title, video_description, created_at
            FROM video_info
            WHERE video_status = :video_status
            ORDER BY video_id DESC
            LIMIT 1
        ', [':video_status' => 'Published']);

        if ($videoRow !== null) {
            $latestVideo = [
                'id' => (int) ($videoRow['video_id'] ?? 0),
                'title' => (string) ($videoRow['video_title'] ?? ''),
                'excerpt' => api_excerpt((string) ($videoRow['video_description'] ?? ''), 160),
                'createdAt' => (string) ($videoRow['created_at'] ?? ''),
            ];
        }
    }

    $activity = [];
    if (api_table_exists($db, 'admin_action_logs')) {
        if ($isSuperAdmin) {
            $logRows = api_fetch_all($db, '
                SELECT admin_username, action_type, action_desc, created_at
                FROM admin_action_logs
                ORDER BY created_at DESC
                LIMIT 5
            ');
        } else {
            $logRows = api_fetch_all($db, '
                SELECT admin_username, action_type, action_desc, created_at
                FROM admin_action_logs
                WHERE admin_user_id = :admin_user_id
                ORDER BY created_at DESC
                LIMIT 5
            ', [':admin_user_id' => (int) ($user['id'] ?? 0)]);
        }

        foreach ($logRows as $log) {
            $activity[] = [
                'adminUsername' => (string) ($log['admin_username'] ?? ''),
                'actionType' => (string) ($log['action_type'] ?? ''),
                'description' => (string) ($log['action_desc'] ?? ''),
                'createdAt' => (string) ($log['created_at'] ?? ''),
            ];
        }
    }

    api_json([
        'ok' => true,
        'user' => [
            'id' => (int) ($user['id'] ?? 0),
            'name' => (string) ($user['name'] ?? $user['username'] ?? ''),
            'username' => (string) ($user['username'] ?? ''),
            'roleId' => (int) ($user['role_id'] ?? 0),
            'roleLabel' => (string) ($user['role_label'] ?? ''),
        ],
        'data' => [
            'stats' => $stats,
            'recentMembers' => $recentMembers,
            'latestNews' => $latestNews,
            'latestVideo' => $latestVideo,
            'activity' => $activity,
        ],
    ]);
} catch (Throwable $error) {
    error_log('Admin dashboard API error: ' . $error->getMessage());
    api_json([
        'ok' => false,
        'message' => 'Unable to load dashboard data right now.',
    ], 500);
}
