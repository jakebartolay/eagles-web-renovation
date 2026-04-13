<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
api_start();

$db = api_db();

$stats = [
    'news'       => api_fetch_one($db, 'SELECT COUNT(*) as total FROM news_info')['total'] ?? 0,
    'published'  => api_fetch_one($db, "SELECT COUNT(*) as total FROM news_info WHERE news_status = 'Published'")['total'] ?? 0,
    'draft'      => api_fetch_one($db, "SELECT COUNT(*) as total FROM news_info WHERE news_status = 'Draft'")['total'] ?? 0,
    'videos'     => api_fetch_one($db, 'SELECT COUNT(*) as total FROM video_info')['total'] ?? 0,
    'events'     => api_fetch_one($db, 'SELECT COUNT(*) as total FROM events')['total'] ?? 0,
    'upcoming'   => api_fetch_one($db, "SELECT COUNT(*) as total FROM events WHERE event_type = 'upcoming'")['total'] ?? 0,
    'memorandum' => api_fetch_one($db, 'SELECT COUNT(*) as total FROM memorandum')['total'] ?? 0,
    'officers'   => api_fetch_one($db, 'SELECT COUNT(*) as total FROM officers')['total'] ?? 0,
    'governors'  => api_fetch_one($db, 'SELECT COUNT(*) as total FROM governors')['total'] ?? 0,
    'clubs'      => api_fetch_one($db, 'SELECT COUNT(*) as total FROM clubs')['total'] ?? 0,
    'admins'     => api_fetch_one($db, 'SELECT COUNT(*) as total FROM admins')['total'] ?? 0,
    'members'    => api_fetch_one($db, 'SELECT COUNT(*) as total FROM user_info')['total'] ?? 0,
    'active'     => api_fetch_one($db, "SELECT COUNT(*) as total FROM user_info WHERE eagles_status = 'ACTIVE'")['total'] ?? 0,
];

$recent_logs = api_fetch_all($db, '
    SELECT admin_username, action_type, action_desc, ip_address, created_at
    FROM admin_action_logs
    ORDER BY created_at DESC
    LIMIT 8
');

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
        ['GET', '/v1/client/members/get_all.php',    'All active members'],
        ['GET', '/v1/client/members/get_single.php', 'Single member by ID'],
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
    'CREATE' => ['bg' => '#E1F5EE', 'text' => '#0F6E56'],
    'UPDATE' => ['bg' => '#E6F1FB', 'text' => '#185FA5'],
    'DELETE' => ['bg' => '#FCEBEB', 'text' => '#A32D2D'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TFEOPE API Dashboard</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, sans-serif; background: #f5f5f3; color: #1a1a18; font-size: 14px; }
.wrap { max-width: 1100px; margin: 0 auto; padding: 24px 16px; }
header { margin-bottom: 24px; }
header h1 { font-size: 20px; font-weight: 500; }
header p { font-size: 13px; color: #888; margin-top: 4px; }
.grid-4 { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 10px; margin-bottom: 24px; }
.grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 10px; margin-bottom: 24px; }
.metric { background: #fff; border: 0.5px solid #e0dfd8; border-radius: 10px; padding: 14px 16px; }
.metric-label { font-size: 11px; color: #888; margin-bottom: 6px; }
.metric-value { font-size: 26px; font-weight: 500; }
.metric-sub { font-size: 11px; color: #aaa; margin-top: 4px; }
.section { background: #fff; border: 0.5px solid #e0dfd8; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
.section h2 { font-size: 13px; font-weight: 500; color: #888; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px; }
.endpoint-group { margin-bottom: 16px; }
.endpoint-group h3 { font-size: 11px; font-weight: 500; color: #aaa; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; }
.endpoint { display: flex; align-items: center; gap: 10px; padding: 7px 0; border-bottom: 0.5px solid #f0efe8; }
.endpoint:last-child { border-bottom: none; }
.method { font-size: 10px; font-weight: 500; padding: 2px 7px; border-radius: 4px; min-width: 38px; text-align: center; flex-shrink: 0; }
.method.get  { background: #E1F5EE; color: #0F6E56; }
.method.post { background: #E6F1FB; color: #185FA5; }
.endpoint-url { font-family: monospace; font-size: 12px; color: #1a1a18; flex: 1; }
.endpoint-desc { font-size: 12px; color: #888; }
.log-row { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; border-bottom: 0.5px solid #f0efe8; }
.log-row:last-child { border-bottom: none; }
.action-badge { font-size: 10px; font-weight: 500; padding: 2px 7px; border-radius: 4px; flex-shrink: 0; min-width: 52px; text-align: center; }
.log-desc { font-size: 12px; color: #444; flex: 1; line-height: 1.4; }
.log-meta { font-size: 11px; color: #aaa; white-space: nowrap; }
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 700px) {
    .grid-4 { grid-template-columns: repeat(2, 1fr); }
    .grid-3 { grid-template-columns: repeat(2, 1fr); }
    .two-col { grid-template-columns: 1fr; }
    .endpoint-desc { display: none; }
}
</style>
</head>
<body>
<div class="wrap">

<header>
    <h1>TFEOPE API Dashboard</h1>
    <p>api.tfoepe-inc.com.ph &nbsp;|&nbsp; PHP <?= phpversion() ?> &nbsp;|&nbsp; <?= date('F j, Y g:i A') ?></p>
</header>

<div class="grid-4">
    <div class="metric">
        <div class="metric-label">Total news</div>
        <div class="metric-value"><?= $stats['news'] ?></div>
        <div class="metric-sub"><?= $stats['published'] ?> published &bull; <?= $stats['draft'] ?> draft</div>
    </div>
    <div class="metric">
        <div class="metric-label">Videos</div>
        <div class="metric-value"><?= $stats['videos'] ?></div>
    </div>
    <div class="metric">
        <div class="metric-label">Events</div>
        <div class="metric-value"><?= $stats['events'] ?></div>
        <div class="metric-sub"><?= $stats['upcoming'] ?> upcoming</div>
    </div>
    <div class="metric">
        <div class="metric-label">Memorandums</div>
        <div class="metric-value"><?= $stats['memorandum'] ?></div>
    </div>
</div>

<div class="grid-3">
    <div class="metric">
        <div class="metric-label">Officers</div>
        <div class="metric-value"><?= $stats['officers'] ?></div>
    </div>
    <div class="metric">
        <div class="metric-label">Governors</div>
        <div class="metric-value"><?= $stats['governors'] ?></div>
    </div>
    <div class="metric">
        <div class="metric-label">Clubs</div>
        <div class="metric-value"><?= $stats['clubs'] ?></div>
    </div>
</div>

<div class="grid-3">
    <div class="metric">
        <div class="metric-label">Members</div>
        <div class="metric-value"><?= $stats['members'] ?></div>
        <div class="metric-sub"><?= $stats['active'] ?> active</div>
    </div>
    <div class="metric">
        <div class="metric-label">Admins</div>
        <div class="metric-value"><?= $stats['admins'] ?></div>
    </div>
    <div class="metric">
        <div class="metric-label">Memorandums</div>
        <div class="metric-value"><?= $stats['memorandum'] ?></div>
    </div>
</div>

<div class="two-col">

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
    <h2>Recent admin actions</h2>
    <?php if (empty($recent_logs)): ?>
        <p style="color:#aaa;font-size:13px;">No logs yet.</p>
    <?php else: ?>
        <?php foreach ($recent_logs as $log):
            $ac = $action_colors[$log['action_type']] ?? ['bg' => '#f0f0f0', 'text' => '#888'];
        ?>
        <div class="log-row">
            <span class="action-badge" style="background:<?= $ac['bg'] ?>;color:<?= $ac['text'] ?>">
                <?= htmlspecialchars($log['action_type']) ?>
            </span>
            <span class="log-desc">
                <strong><?= htmlspecialchars($log['admin_username']) ?></strong>
                &mdash; <?= htmlspecialchars($log['action_desc']) ?>
            </span>
            <span class="log-meta"><?= date('M j, g:i A', strtotime($log['created_at'])) ?></span>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</div>
</div>
</body>
</html>
