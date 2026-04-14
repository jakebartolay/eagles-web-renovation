<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

if (!function_exists('admin_api_user_payload')) {
    function admin_api_user_payload(array $admin): array
    {
        $roleId = (int) ($admin['role_id'] ?? 0);
        $roleLabel = (string) ($admin['role_label'] ?? '');

        if ($roleLabel === '') {
            $roleLabel = $roleId === 1 ? 'Super Admin' : 'Admin';
        }

        return [
            'id' => (int) ($admin['id'] ?? 0),
            'name' => (string) ($admin['name'] ?? ''),
            'username' => (string) ($admin['username'] ?? ''),
            'roleId' => $roleId,
            'roleLabel' => $roleLabel,
        ];
    }
}

if (!function_exists('admin_api_forget_session')) {
    function admin_api_forget_session(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        if ((bool) ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                (bool) ($params['secure'] ?? false),
                (bool) ($params['httponly'] ?? true)
            );
        }

        session_destroy();
    }
}

if (!function_exists('admin_api_count')) {
    function admin_api_count(PDO $db, string $table, ?string $where = null, array $params = []): int
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

if (!function_exists('admin_api_admin_count')) {
    function admin_api_admin_count(PDO $db): int
    {
        if (api_table_exists($db, 'users') && api_has_column($db, 'users', 'role_id')) {
            return admin_api_count($db, 'users', 'role_id IN (1, 2)');
        }

        return admin_api_count($db, 'admins');
    }
}

if (!function_exists('admin_api_recent_members')) {
    function admin_api_recent_members(PDO $db, int $limit = 10): array
    {
        if (!api_table_exists($db, 'user_info')) {
            return [];
        }

        $limit = max(1, min(24, $limit));
        $rows = api_fetch_all($db, sprintf(
            '
                SELECT
                    eagles_id,
                    eagles_status,
                    eagles_firstName,
                    eagles_lastName,
                    eagles_position,
                    eagles_club,
                    eagles_region,
                    eagles_pic,
                    eagles_dateAdded
                FROM user_info
                ORDER BY eagles_dateAdded DESC
                LIMIT %d
            ',
            $limit
        ));

        return array_map(static function (array $row): array {
            $photoFile = basename(trim((string) ($row['eagles_pic'] ?? '')));
            $photoAsset = $photoFile !== ''
                ? api_locate_media_file('media', $photoFile)
                : null;

            $firstName = trim((string) ($row['eagles_firstName'] ?? ''));
            $lastName = trim((string) ($row['eagles_lastName'] ?? ''));
            $fullName = trim($firstName . ' ' . $lastName);

            return [
                'id' => (string) ($row['eagles_id'] ?? ''),
                'status' => (string) ($row['eagles_status'] ?? ''),
                'firstName' => $firstName,
                'lastName' => $lastName,
                'fullName' => $fullName !== '' ? $fullName : 'Unnamed member',
                'position' => (string) ($row['eagles_position'] ?? ''),
                'club' => (string) ($row['eagles_club'] ?? ''),
                'region' => (string) ($row['eagles_region'] ?? ''),
                'photoUrl' => $photoAsset['url'] ?? null,
                'dateAdded' => (string) ($row['eagles_dateAdded'] ?? ''),
            ];
        }, $rows);
    }
}

if (!function_exists('admin_api_recent_activity')) {
    function admin_api_recent_activity(PDO $db, int $limit = 12): array
    {
        if (!api_table_exists($db, 'admin_action_logs')) {
            return [];
        }

        $limit = max(1, min(30, $limit));
        $rows = api_fetch_all($db, sprintf(
            '
                SELECT admin_username, action_type, action_desc, ip_address, created_at
                FROM admin_action_logs
                ORDER BY created_at DESC
                LIMIT %d
            ',
            $limit
        ));

        return array_map(static fn (array $row): array => [
            'adminUsername' => (string) ($row['admin_username'] ?? ''),
            'actionType' => (string) ($row['action_type'] ?? ''),
            'description' => (string) ($row['action_desc'] ?? ''),
            'ipAddress' => (string) ($row['ip_address'] ?? ''),
            'createdAt' => (string) ($row['created_at'] ?? ''),
        ], $rows);
    }
}

if (!function_exists('admin_api_first_by_status')) {
    function admin_api_first_by_status(array $items, string $status = 'Published'): ?array
    {
        foreach ($items as $item) {
            if (strcasecmp((string) ($item['status'] ?? ''), $status) === 0) {
                return $item;
            }
        }

        return $items[0] ?? null;
    }
}

if (!function_exists('admin_api_dashboard_data')) {
    function admin_api_dashboard_data(PDO $db): array
    {
        $news = array_slice(api_news_list($db, false), 0, 6);
        $videos = array_slice(api_video_list($db, false), 0, 6);
        $events = array_slice(api_events_list($db), 0, 6);
        $memorandums = array_slice(api_memorandum_list($db, false), 0, 6);
        $officers = array_slice(api_officer_list($db), 0, 8);
        $governors = array_slice(api_governor_list($db), 0, 8);
        $recentMembers = admin_api_recent_members($db, 10);

        return [
            'stats' => [
                'members' => admin_api_count($db, 'user_info'),
                'activeMembers' => admin_api_count($db, 'user_info', 'UPPER(eagles_status) = :status', [
                    ':status' => 'ACTIVE',
                ]),
                'regions' => admin_api_count($db, 'regions'),
                'clubs' => admin_api_count($db, 'clubs'),
                'governors' => admin_api_count($db, 'governors'),
                'officers' => admin_api_count($db, 'officers'),
                'news' => admin_api_count($db, 'news_info'),
                'publishedNews' => admin_api_count($db, 'news_info', 'news_status = :status', [
                    ':status' => 'Published',
                ]),
                'draftNews' => admin_api_count($db, 'news_info', 'news_status = :status', [
                    ':status' => 'Draft',
                ]),
                'videos' => admin_api_count($db, 'video_info'),
                'events' => admin_api_count($db, 'events'),
                'upcomingEvents' => admin_api_count($db, 'events', 'LOWER(event_type) = :event_type', [
                    ':event_type' => 'upcoming',
                ]),
                'memorandums' => admin_api_count($db, 'memorandum'),
                'admins' => admin_api_admin_count($db),
            ],
            'recentMembers' => $recentMembers,
            'latestNews' => admin_api_first_by_status($news),
            'latestVideo' => admin_api_first_by_status($videos),
            'latestEvent' => $events[0] ?? null,
            'latestMemorandum' => admin_api_first_by_status($memorandums),
            'news' => $news,
            'videos' => $videos,
            'events' => $events,
            'memorandums' => $memorandums,
            'officers' => $officers,
            'governors' => $governors,
            'activity' => admin_api_recent_activity($db, 12),
            'meta' => [
                'lastUpdated' => date('c'),
                'apiBasePath' => api_base_path(),
                'databaseName' => (string) (api_config()['db']['name'] ?? ''),
            ],
        ];
    }
}
