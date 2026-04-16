<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
api_start();

$db = api_db();

function get_dashboard_stats(PDO $db): array {
    return [
        'news'       => (int) (api_fetch_one($db, 'SELECT COUNT(*) as total FROM news_info')['total'] ?? 0),
        'published'  => (int) (api_fetch_one($db, "SELECT COUNT(*) as total FROM news_info WHERE news_status = 'Published'")['total'] ?? 0),
        'draft'      => (int) (api_fetch_one($db, "SELECT COUNT(*) as total FROM news_info WHERE news_status = 'Draft'")['total'] ?? 0),
        'videos'     => (int) (api_fetch_one($db, 'SELECT COUNT(*) as total FROM video_info')['total'] ?? 0),
        'events'     => (int) (api_fetch_one($db, 'SELECT COUNT(*) as total FROM events')['total'] ?? 0),
        'upcoming'   => (int) (api_fetch_one($db, "SELECT COUNT(*) as total FROM events WHERE event_type = 'upcoming'")['total'] ?? 0),
        'memorandum' => (int) (api_fetch_one($db, 'SELECT COUNT(*) as total FROM memorandum')['total'] ?? 0),
        'officers'   => (int) (api_fetch_one($db, 'SELECT COUNT(*) as total FROM officers')['total'] ?? 0),
        'governors'  => (int) (api_fetch_one($db, 'SELECT COUNT(*) as total FROM governors')['total'] ?? 0),
        'clubs'      => (int) (api_fetch_one($db, 'SELECT COUNT(*) as total FROM clubs')['total'] ?? 0),
        'admins'     => (int) (api_fetch_one($db, 'SELECT COUNT(*) as total FROM admins')['total'] ?? 0),
        'members'    => (int) (api_fetch_one($db, 'SELECT COUNT(*) as total FROM user_info')['total'] ?? 0),
        'active'     => (int) (api_fetch_one($db, "SELECT COUNT(*) as total FROM user_info WHERE eagles_status = 'ACTIVE'")['total'] ?? 0),
    ];
}

function get_recent_logs(PDO $db): array {
    return api_fetch_all($db, '
        SELECT admin_username, action_type, action_desc, ip_address, created_at
        FROM admin_action_logs
        ORDER BY created_at DESC
        LIMIT 8
    ') ?: [];
}

function get_chart_rows(array $stats): array {
    return [
        ['label' => 'News', 'value' => $stats['news']],
        ['label' => 'Videos', 'value' => $stats['videos']],
        ['label' => 'Events', 'value' => $stats['events']],
        ['label' => 'Memo', 'value' => $stats['memorandum']],
        ['label' => 'Officers', 'value' => $stats['officers']],
        ['label' => 'Governors', 'value' => $stats['governors']],
        ['label' => 'Clubs', 'value' => $stats['clubs']],
        ['label' => 'Members', 'value' => $stats['members']],
        ['label' => 'Admins', 'value' => $stats['admins']],
    ];
}

$stats = get_dashboard_stats($db);
$recent_logs = get_recent_logs($db);
$chart_rows = get_chart_rows($stats);

$endpoints = [
    'CLIENT (public)' => [
        ['GET', '/v1/client/news/get_all.php',        'All published news'],
        ['GET', '/v1/client/videos/get_all.php',      'All published videos'],
        ['GET', '/v1/client/events/get_all.php',      'All events'],
        ['GET', '/v1/client/events/get_upcoming.php', 'Upcoming events'],
        ['GET', '/v1/client/events/get_past.php',     'Past events'],
        ['GET', '/v1/client/memorandum/get_all.php',  'Published memorandums'],
        ['GET', '/v1/client/officers/get_all.php',    'All officers'],
        ['GET', '/v1/client/governors/get_all.php',   'Governors hierarchy'],
        ['GET', '/v1/client/magna_carta/get_all.php', 'Magna carta items'],
        ['GET', '/v1/client/members/get_all.php',     'All active members'],
        ['GET', '/v1/client/members/get_single.php',  'Single member by ID'],
    ],
    'ADMIN (protected)' => [
        ['GET',  '/v1/admin/news/get_all.php',         'All news (draft + published)'],
        ['POST', '/v1/admin/news/create.php',          'Create news + upload image'],
        ['POST', '/v1/admin/news/delete.php',          'Delete news + image file'],
        ['GET',  '/v1/admin/videos/get_all.php',       'All videos'],
        ['POST', '/v1/admin/videos/create.php',        'Upload video + thumbnail'],
        ['POST', '/v1/admin/videos/delete.php',        'Delete video + file'],
        ['GET',  '/v1/admin/events/get_all.php',       'All events'],
        ['POST', '/v1/admin/events/create.php',        'Create event + media'],
        ['POST', '/v1/admin/events/delete.php',        'Delete event'],
        ['GET',  '/v1/admin/memorandum/get_all.php',   'All memorandums'],
        ['POST', '/v1/admin/memorandum/create.php',    'Create memo + pages'],
        ['POST', '/v1/admin/memorandum/delete.php',    'Delete memo + pages'],
        ['GET',  '/v1/admin/officers/get_all.php',     'All officers'],
        ['POST', '/v1/admin/officers/create.php',      'Add officer'],
        ['POST', '/v1/admin/officers/update.php',      'Update officer'],
        ['POST', '/v1/admin/officers/delete.php',      'Delete officer'],
        ['GET',  '/v1/admin/governors/get_all.php',    'All governors'],
        ['POST', '/v1/admin/governors/create.php',     'Add governor'],
        ['POST', '/v1/admin/governors/update.php',     'Update governor'],
        ['POST', '/v1/admin/governors/delete.php',     'Delete governor'],
        ['GET',  '/v1/admin/members/get_all.php',      'All members (active + inactive)'],
        ['POST', '/v1/admin/members/create.php',       'Add member + pic'],
        ['POST', '/v1/admin/members/update.php',       'Update member'],
        ['POST', '/v1/admin/members/delete.php',       'Delete member + pic'],
    ],
];

$action_colors = [
    'CREATE' => ['bg' => 'rgba(16, 185, 129, 0.15)', 'text' => '#10b981', 'border' => 'rgba(16, 185, 129, 0.3)'],
    'UPDATE' => ['bg' => 'rgba(59, 130, 246, 0.15)', 'text' => '#3b82f6', 'border' => 'rgba(59, 130, 246, 0.3)'],
    'DELETE' => ['bg' => 'rgba(244, 63, 94, 0.15)', 'text' => '#f43f5e', 'border' => 'rgba(244, 63, 94, 0.3)'],
];

if (isset($_GET['ajax']) && $_GET['ajax'] === 'dashboard') {
    header('Content-Type: application/json; charset=utf-8');

    $latestStats = get_dashboard_stats($db);
    $latestLogs = get_recent_logs($db);

    echo json_encode([
        'ok' => true,
        'server_time' => date('c'),
        'display_time' => date('F j, Y g:i:s A'),
        'stats' => $latestStats,
        'chart' => get_chart_rows($latestStats),
        'logs' => array_map(static function(array $log) use ($action_colors): array {
            $palette = $action_colors[$log['action_type']] ?? ['bg' => 'rgba(156, 163, 175, 0.15)', 'text' => '#9ca3af', 'border' => 'rgba(156, 163, 175, 0.3)'];
            return [
                'admin_username' => $log['admin_username'],
                'action_type' => $log['action_type'],
                'action_desc' => $log['action_desc'],
                'created_at' => $log['created_at'],
                'display_time' => date('M j, g:i A', strtotime($log['created_at'])),
                'badge_bg' => $palette['bg'],
                'badge_text' => $palette['text'],
                'badge_border' => $palette['border'],
            ];
        }, $latestLogs),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TFEOPE API Dashboard • Real-time Monitoring</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root {
    --bg-primary: #0a0e1a;
    --bg-secondary: #111827;
    --bg-elevated: #1a202e;
    --card-bg: #1e2533;
    --border: #2d3548;
    --border-hover: #3d4558;
    --text-primary: #e5e7eb;
    --text-secondary: #9ca3af;
    --text-muted: #6b7280;
    --accent-blue: #3b82f6;
    --accent-blue-dark: #2563eb;
    --accent-purple: #8b5cf6;
    --accent-cyan: #06b6d4;
    --accent-emerald: #10b981;
    --accent-amber: #f59e0b;
    --accent-rose: #f43f5e;
    --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --gradient-success: linear-gradient(135deg, #10b981 0%, #059669 100%);
    --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    --gradient-danger: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%);
    --gradient-info: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.2);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.4), 0 4px 6px -2px rgba(0, 0, 0, 0.3);
    --shadow-glow: 0 0 20px rgba(59, 130, 246, 0.3);
}

* { 
    box-sizing: border-box; 
    margin: 0; 
    padding: 0; 
}

body { 
    font-family: 'Sora', -apple-system, sans-serif; 
    background: var(--bg-primary);
    background-image: 
        radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.08) 0px, transparent 50%),
        radial-gradient(at 100% 100%, rgba(139, 92, 246, 0.08) 0px, transparent 50%);
    color: var(--text-primary); 
    font-size: 14px;
    line-height: 1.6;
    min-height: 100vh;
    position: relative;
}

body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.02'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    opacity: 0.4;
    pointer-events: none;
    z-index: 0;
}

.wrap { 
    max-width: 1400px; 
    margin: 0 auto; 
    padding: 32px 24px 60px;
    position: relative;
    z-index: 1;
}

/* Header Styling */
header { 
    margin-bottom: 40px; 
    display: flex; 
    justify-content: space-between; 
    gap: 24px; 
    align-items: flex-start; 
    flex-wrap: wrap;
    animation: fadeInDown 0.6s ease-out;
}

@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

header h1 { 
    font-size: 32px; 
    font-weight: 800;
    background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.5px;
}

header p { 
    font-size: 14px; 
    color: var(--text-muted); 
    margin-top: 8px;
    font-family: 'JetBrains Mono', monospace;
}

.top-right { 
    display: flex; 
    gap: 12px; 
    flex-wrap: wrap; 
}

.pill { 
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 12px 18px;
    min-width: 180px;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.pill:hover {
    border-color: var(--border-hover);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.pill-label { 
    font-size: 10px; 
    color: var(--text-muted); 
    text-transform: uppercase; 
    letter-spacing: 1px;
    font-weight: 600;
}

.pill-value { 
    font-size: 14px; 
    font-weight: 600; 
    margin-top: 4px;
    color: var(--text-primary);
    font-family: 'JetBrains Mono', monospace;
}

.live-dot { 
    display: inline-block; 
    width: 10px; 
    height: 10px; 
    border-radius: 50%; 
    background: var(--accent-emerald);
    margin-right: 8px; 
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { box-shadow: 0 0 0 12px rgba(16, 185, 129, 0); }
    100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

/* Grid Layouts */
.grid-4 { 
    display: grid; 
    grid-template-columns: repeat(4, minmax(0,1fr)); 
    gap: 16px; 
    margin-bottom: 32px;
}

.grid-3 { 
    display: grid; 
    grid-template-columns: repeat(3, minmax(0,1fr)); 
    gap: 16px; 
    margin-bottom: 32px;
}

/* Metric Cards */
.metric, .section { 
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 16px;
    box-shadow: var(--shadow-md);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.metric::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--gradient-primary);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.metric:hover::before {
    opacity: 1;
}

.metric:hover {
    border-color: var(--border-hover);
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.metric { 
    padding: 20px 24px;
    animation: fadeIn 0.6s ease-out;
    animation-fill-mode: both;
}

.metric:nth-child(1) { animation-delay: 0.1s; }
.metric:nth-child(2) { animation-delay: 0.15s; }
.metric:nth-child(3) { animation-delay: 0.2s; }
.metric:nth-child(4) { animation-delay: 0.25s; }

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.metric-label { 
    font-size: 11px; 
    color: var(--text-muted); 
    margin-bottom: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
}

.metric-value { 
    font-size: 36px; 
    font-weight: 800; 
    line-height: 1;
    background: linear-gradient(135deg, #ffffff 0%, #e5e7eb 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.metric-sub { 
    font-size: 12px; 
    color: var(--text-secondary); 
    margin-top: 10px;
    font-family: 'JetBrains Mono', monospace;
}

/* Section Styling */
.section { 
    padding: 24px;
    margin-bottom: 24px;
}

.section h2 { 
    font-size: 14px; 
    font-weight: 700; 
    color: var(--text-secondary); 
    text-transform: uppercase; 
    letter-spacing: 1.2px;
    margin-bottom: 20px;
}

.two-col { 
    display: grid; 
    grid-template-columns: 1.2fr 0.8fr; 
    gap: 24px; 
    margin-bottom: 24px;
}

.bottom-grid { 
    display: grid; 
    grid-template-columns: 1fr 1fr; 
    gap: 24px;
}

/* Chart Styling */
.chart-wrap { 
    height: 400px; 
    position: relative;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 12px;
    padding: 20px;
}

#statsChart { 
    width: 100%; 
    height: 100%; 
    display: block;
}

.chart-note { 
    font-size: 12px; 
    color: var(--text-muted); 
    margin-top: 16px;
    font-style: italic;
}

/* Endpoints Styling */
.endpoint-group { 
    margin-bottom: 24px;
}

.endpoint-group h3 { 
    font-size: 11px; 
    font-weight: 700; 
    color: var(--accent-blue); 
    text-transform: uppercase; 
    letter-spacing: 1px;
    margin-bottom: 12px;
    padding-left: 12px;
    border-left: 3px solid var(--accent-blue);
}

.endpoint { 
    display: flex; 
    align-items: center; 
    gap: 12px; 
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 8px;
    transition: all 0.2s ease;
}

.endpoint:hover {
    background: rgba(59, 130, 246, 0.05);
}

.method { 
    font-size: 10px; 
    font-weight: 700; 
    padding: 4px 10px;
    border-radius: 6px;
    min-width: 45px; 
    text-align: center;
    flex-shrink: 0;
    font-family: 'JetBrains Mono', monospace;
    letter-spacing: 0.5px;
}

.method.get  { 
    background: rgba(16, 185, 129, 0.15);
    color: var(--accent-emerald);
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.method.post { 
    background: rgba(59, 130, 246, 0.15);
    color: var(--accent-blue);
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.endpoint-url { 
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px; 
    color: var(--text-primary); 
    flex: 1;
    font-weight: 500;
}

.endpoint-desc { 
    font-size: 12px; 
    color: var(--text-muted);
}

/* Log Rows */
.log-row { 
    display: flex; 
    align-items: flex-start; 
    gap: 12px; 
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 8px;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}

.log-row:hover {
    background: rgba(59, 130, 246, 0.05);
    border-color: var(--border);
}

.action-badge { 
    font-size: 10px; 
    font-weight: 700; 
    padding: 4px 10px;
    border-radius: 6px;
    flex-shrink: 0;
    min-width: 60px; 
    text-align: center;
    font-family: 'JetBrains Mono', monospace;
}

.log-desc { 
    font-size: 13px; 
    color: var(--text-secondary); 
    flex: 1; 
    line-height: 1.5;
}

.log-desc strong {
    color: var(--text-primary);
    font-weight: 600;
}

.log-meta { 
    font-size: 11px; 
    color: var(--text-muted); 
    white-space: nowrap;
    font-family: 'JetBrains Mono', monospace;
}

/* Inline Stats */
.inline-stats { 
    display: grid; 
    grid-template-columns: repeat(3, minmax(0,1fr)); 
    gap: 12px; 
    margin-top: 16px;
}

.mini { 
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    transition: all 0.3s ease;
}

.mini:hover {
    border-color: var(--border-hover);
    transform: translateY(-2px);
}

.mini-label { 
    font-size: 11px; 
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.mini-value { 
    font-size: 28px; 
    font-weight: 800; 
    margin-top: 8px;
    background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Refresh Button */
.refresh-btn { 
    appearance: none;
    border: 1px solid var(--border);
    background: var(--card-bg);
    border-radius: 10px;
    padding: 10px 18px;
    cursor: pointer;
    font-weight: 600;
    color: var(--text-primary);
    transition: all 0.3s ease;
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.refresh-btn::before {
    content: '⟳';
    font-size: 16px;
}

.refresh-btn:hover { 
    background: var(--accent-blue);
    border-color: var(--accent-blue);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.toolbar { 
    display: flex; 
    justify-content: space-between; 
    gap: 12px; 
    align-items: center; 
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.small-muted { 
    font-size: 12px; 
    color: var(--text-muted);
    font-style: italic;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .wrap { max-width: 100%; }
}

@media (max-width: 900px) {
    .grid-4 { grid-template-columns: repeat(2, 1fr); }
    .grid-3 { grid-template-columns: repeat(2, 1fr); }
    .two-col, .bottom-grid { grid-template-columns: 1fr; }
}

@media (max-width: 600px) {
    header h1 { font-size: 24px; }
    .endpoint-desc { display: none; }
    .grid-3, .grid-4, .inline-stats { grid-template-columns: 1fr; }
    .pill { min-width: 140px; }
    .metric-value { font-size: 28px; }
}

/* Scrollbar Styling */
::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

::-webkit-scrollbar-track {
    background: var(--bg-secondary);
}

::-webkit-scrollbar-thumb {
    background: var(--border-hover);
    border-radius: 5px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--text-muted);
}
</style>
</head>
<body>
<div class="wrap">

<header>
    <div>
        <h1>TFEOPE API Dashboard</h1>
        <p>api.tfoepe-inc.com.ph &nbsp;|&nbsp; PHP <?= htmlspecialchars(phpversion()) ?></p>
    </div>
    <div class="top-right">
        <div class="pill">
            <div class="pill-label">Live status</div>
            <div class="pill-value"><span class="live-dot"></span>Auto-refresh 30s</div>
        </div>
        <div class="pill">
            <div class="pill-label">Clock</div>
            <div class="pill-value" id="liveClock"><?= date('F j, Y g:i:s A') ?></div>
        </div>
        <div class="pill">
            <div class="pill-label">Last sync</div>
            <div class="pill-value" id="lastSync"><?= date('F j, Y g:i:s A') ?></div>
        </div>
    </div>
</header>

<div class="grid-4">
    <div class="metric">
        <div class="metric-label">Total news</div>
        <div class="metric-value" data-stat="news"><?= $stats['news'] ?></div>
        <div class="metric-sub">
            <span data-stat="published"><?= $stats['published'] ?></span> published •
            <span data-stat="draft"><?= $stats['draft'] ?></span> draft
        </div>
    </div>
    <div class="metric">
        <div class="metric-label">Videos</div>
        <div class="metric-value" data-stat="videos"><?= $stats['videos'] ?></div>
    </div>
    <div class="metric">
        <div class="metric-label">Events</div>
        <div class="metric-value" data-stat="events"><?= $stats['events'] ?></div>
        <div class="metric-sub"><span data-stat="upcoming"><?= $stats['upcoming'] ?></span> upcoming</div>
    </div>
    <div class="metric">
        <div class="metric-label">Memorandums</div>
        <div class="metric-value" data-stat="memorandum"><?= $stats['memorandum'] ?></div>
    </div>
</div>

<div class="grid-3">
    <div class="metric">
        <div class="metric-label">Officers</div>
        <div class="metric-value" data-stat="officers"><?= $stats['officers'] ?></div>
    </div>
    <div class="metric">
        <div class="metric-label">Governors</div>
        <div class="metric-value" data-stat="governors"><?= $stats['governors'] ?></div>
    </div>
    <div class="metric">
        <div class="metric-label">Clubs</div>
        <div class="metric-value" data-stat="clubs"><?= $stats['clubs'] ?></div>
    </div>
</div>

<div class="grid-3">
    <div class="metric">
        <div class="metric-label">Members</div>
        <div class="metric-value" data-stat="members"><?= $stats['members'] ?></div>
        <div class="metric-sub"><span data-stat="active"><?= $stats['active'] ?></span> active</div>
    </div>
    <div class="metric">
        <div class="metric-label">Admins</div>
        <div class="metric-value" data-stat="admins"><?= $stats['admins'] ?></div>
    </div>
    <div class="metric">
        <div class="metric-label">System time</div>
        <div class="metric-value" id="clockSmall"><?= date('g:i:s A') ?></div>
        <div class="metric-sub">running live in browser</div>
    </div>
</div>

<div class="two-col">
    <div class="section">
        <div class="toolbar">
            <h2 style="margin-bottom:0;">Live data bar chart</h2>
            <button class="refresh-btn" type="button" id="manualRefresh">Refresh now</button>
        </div>
        <div class="chart-wrap">
            <canvas id="statsChart"></canvas>
        </div>
        <div class="chart-note">Chart updates automatically from database counts without reloading the full page.</div>
        <div class="inline-stats">
            <div class="mini">
                <div class="mini-label">Published news</div>
                <div class="mini-value" data-stat="published"><?= $stats['published'] ?></div>
            </div>
            <div class="mini">
                <div class="mini-label">Draft news</div>
                <div class="mini-value" data-stat="draft"><?= $stats['draft'] ?></div>
            </div>
            <div class="mini">
                <div class="mini-label">Active members</div>
                <div class="mini-value" data-stat="active"><?= $stats['active'] ?></div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="toolbar">
            <h2 style="margin-bottom:0;">Recent admin actions</h2>
            <div class="small-muted">Auto-updates</div>
        </div>
        <div id="recentLogs">
            <?php if (empty($recent_logs)): ?>
                <p style="color:var(--text-muted);font-size:13px;">No logs yet.</p>
            <?php else: ?>
                <?php foreach ($recent_logs as $log):
                    $ac = $action_colors[$log['action_type']] ?? ['bg' => 'rgba(156, 163, 175, 0.15)', 'text' => '#9ca3af', 'border' => 'rgba(156, 163, 175, 0.3)'];
                ?>
                <div class="log-row">
                    <span class="action-badge" style="background:<?= htmlspecialchars($ac['bg']) ?>;color:<?= htmlspecialchars($ac['text']) ?>;border:1px solid <?= htmlspecialchars($ac['border']) ?>"><?= htmlspecialchars($log['action_type']) ?></span>
                    <span class="log-desc"><strong><?= htmlspecialchars($log['admin_username']) ?></strong> &mdash; <?= htmlspecialchars($log['action_desc']) ?></span>
                    <span class="log-meta"><?= date('M j, g:i A', strtotime($log['created_at'])) ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="bottom-grid">
    <div class="section">
        <h2>API Endpoints</h2>
        <?php foreach ($endpoints as $group => $items): ?>
        <div class="endpoint-group">
            <h3><?= htmlspecialchars($group) ?></h3>
            <?php foreach ($items as [$method, $url, $desc]): ?>
            <div class="endpoint">
                <span class="method <?= strtolower($method) ?>"><?= $method ?></span>
                <span class="endpoint-url"><?= htmlspecialchars($url) ?></span>
                <span class="endpoint-desc"><?= htmlspecialchars($desc) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="section">
        <h2>Automation notes</h2>
        <div class="small-muted" style="line-height:1.7; color:var(--text-secondary);">
            This dashboard features a running browser clock, manual refresh, and live polling every 30 seconds using <code style="background:rgba(0,0,0,0.3);padding:2px 6px;border-radius:4px;color:var(--accent-cyan);">?ajax=dashboard</code>.<br><br>
            For real-time updates, consider upgrading to WebSocket or Server-Sent Events for push-based notifications instead of polling.
        </div>
    </div>
</div>

</div>

<script>
const chartCanvas = document.getElementById('statsChart');
const chartCtx = chartCanvas.getContext('2d');
const refreshUrl = new URL(window.location.href);
refreshUrl.searchParams.set('ajax', 'dashboard');

let chartData = <?= json_encode($chart_rows, JSON_UNESCAPED_SLASHES) ?>;

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function resizeCanvas() {
    const ratio = window.devicePixelRatio || 1;
    const rect = chartCanvas.getBoundingClientRect();
    chartCanvas.width = Math.max(320, Math.floor(rect.width * ratio));
    chartCanvas.height = Math.max(260, Math.floor(rect.height * ratio));
    chartCtx.setTransform(ratio, 0, 0, ratio, 0, 0);
    drawChart(chartData);
}

function drawChart(rows) {
    const width = chartCanvas.clientWidth;
    const height = chartCanvas.clientHeight;
    chartCtx.clearRect(0, 0, width, height);

    const padding = { top: 20, right: 16, bottom: 54, left: 40 };
    const innerWidth = width - padding.left - padding.right;
    const innerHeight = height - padding.top - padding.bottom;
    const max = Math.max(...rows.map(item => Number(item.value)), 5);
    const stepX = innerWidth / rows.length;
    const barWidth = Math.min(48, stepX * 0.58);

    // Grid lines
    chartCtx.strokeStyle = 'rgba(45, 53, 72, 0.5)';
    chartCtx.fillStyle = '#9ca3af';
    chartCtx.lineWidth = 1;
    chartCtx.font = '11px JetBrains Mono, monospace';

    for (let i = 0; i <= 4; i++) {
        const y = padding.top + (innerHeight / 4) * i;
        chartCtx.beginPath();
        chartCtx.moveTo(padding.left, y);
        chartCtx.lineTo(width - padding.right, y);
        chartCtx.stroke();

        const labelValue = Math.round(max - (max / 4) * i);
        chartCtx.fillText(String(labelValue), 8, y + 4);
    }

    // Bars
    rows.forEach((item, index) => {
        const x = padding.left + stepX * index + (stepX - barWidth) / 2;
        const barHeight = max === 0 ? 0 : (Number(item.value) / max) * innerHeight;
        const y = padding.top + innerHeight - barHeight;

        const gradient = chartCtx.createLinearGradient(0, y, 0, y + barHeight + 1);
        gradient.addColorStop(0, '#667eea');
        gradient.addColorStop(1, '#764ba2');
        chartCtx.fillStyle = gradient;
        roundRect(chartCtx, x, y, barWidth, Math.max(barHeight, 4), 8, true, false);

        // Value on top
        chartCtx.fillStyle = '#e5e7eb';
        chartCtx.font = 'bold 12px Sora, sans-serif';
        chartCtx.textAlign = 'center';
        chartCtx.fillText(String(item.value), x + barWidth / 2, y - 8);

        // Label at bottom
        chartCtx.fillStyle = '#9ca3af';
        chartCtx.font = '11px JetBrains Mono, monospace';
        chartCtx.fillText(item.label, x + barWidth / 2, height - 22);
        chartCtx.textAlign = 'start';
    });
}

function roundRect(ctx, x, y, width, height, radius, fill, stroke) {
    if (width < 2 * radius) radius = width / 2;
    if (height < 2 * radius) radius = height / 2;
    ctx.beginPath();
    ctx.moveTo(x + radius, y);
    ctx.arcTo(x + width, y, x + width, y + height, radius);
    ctx.arcTo(x + width, y + height, x, y + height, radius);
    ctx.arcTo(x, y + height, x, y, radius);
    ctx.arcTo(x, y, x + width, y, radius);
    ctx.closePath();
    if (fill) ctx.fill();
    if (stroke) ctx.stroke();
}

function tickClock() {
    const now = new Date();
    const text = now.toLocaleString(undefined, {
        year: 'numeric', month: 'long', day: 'numeric',
        hour: 'numeric', minute: '2-digit', second: '2-digit'
    });
    document.getElementById('liveClock').textContent = text;
    document.getElementById('clockSmall').textContent = now.toLocaleTimeString([], {
        hour: 'numeric', minute: '2-digit', second: '2-digit'
    });
}

function updateStats(stats) {
    Object.entries(stats).forEach(([key, value]) => {
        document.querySelectorAll(`[data-stat="${key}"]`).forEach(el => {
            el.textContent = value;
        });
    });
}

function updateLogs(logs) {
    const container = document.getElementById('recentLogs');
    if (!logs || !logs.length) {
        container.innerHTML = '<p style="color:var(--text-muted);font-size:13px;">No logs yet.</p>';
        return;
    }

    container.innerHTML = logs.map(log => `
        <div class="log-row">
            <span class="action-badge" style="background:${escapeHtml(log.badge_bg)};color:${escapeHtml(log.badge_text)};border:1px solid ${escapeHtml(log.badge_border)}">${escapeHtml(log.action_type)}</span>
            <span class="log-desc"><strong>${escapeHtml(log.admin_username)}</strong> &mdash; ${escapeHtml(log.action_desc)}</span>
            <span class="log-meta">${escapeHtml(log.display_time)}</span>
        </div>
    `).join('');
}

async function refreshDashboard() {
    try {
        const response = await fetch(refreshUrl.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store'
        });
        if (!response.ok) throw new Error('Failed to load live data.');
        const data = await response.json();

        if (!data.ok) throw new Error('Invalid dashboard payload.');

        updateStats(data.stats);
        chartData = data.chart || [];
        drawChart(chartData);
        updateLogs(data.logs || []);
        document.getElementById('lastSync').textContent = data.display_time || new Date().toLocaleString();
    } catch (error) {
        console.error('Dashboard refresh error:', error);
    }
}

document.getElementById('manualRefresh').addEventListener('click', refreshDashboard);
window.addEventListener('resize', resizeCanvas);
resizeCanvas();
tickClock();
setInterval(tickClock, 1000);
setInterval(refreshDashboard, 30000);
</script>
</body>
</html>