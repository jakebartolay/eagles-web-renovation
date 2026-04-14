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
    'CREATE' => ['bg' => '#E1F5EE', 'text' => '#0F6E56'],
    'UPDATE' => ['bg' => '#E6F1FB', 'text' => '#185FA5'],
    'DELETE' => ['bg' => '#FCEBEB', 'text' => '#A32D2D'],
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
            $palette = $action_colors[$log['action_type']] ?? ['bg' => '#f0f0f0', 'text' => '#888'];
            return [
                'admin_username' => $log['admin_username'],
                'action_type' => $log['action_type'],
                'action_desc' => $log['action_desc'],
                'created_at' => $log['created_at'],
                'display_time' => date('M j, g:i A', strtotime($log['created_at'])),
                'badge_bg' => $palette['bg'],
                'badge_text' => $palette['text'],
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
<title>TFEOPE API Dashboard</title>
<style>
:root {
    --bg: #f5f5f3;
    --card: #ffffff;
    --line: #e0dfd8;
    --muted: #7f7f76;
    --text: #1a1a18;
    --accent: #185FA5;
    --accent-soft: #E6F1FB;
    --good: #0F6E56;
    --good-soft: #E1F5EE;
    --warn: #A32D2D;
    --warn-soft: #FCEBEB;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, sans-serif; background: var(--bg); color: var(--text); font-size: 14px; }
.wrap { max-width: 1200px; margin: 0 auto; padding: 24px 16px 40px; }
header { margin-bottom: 18px; display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; flex-wrap: wrap; }
header h1 { font-size: 22px; font-weight: 600; }
header p { font-size: 13px; color: var(--muted); margin-top: 4px; }
.top-right { display: flex; gap: 10px; flex-wrap: wrap; }
.pill { background: var(--card); border: 1px solid var(--line); border-radius: 999px; padding: 10px 14px; min-width: 160px; }
.pill-label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }
.pill-value { font-size: 14px; font-weight: 600; margin-top: 2px; }
.live-dot { display: inline-block; width: 9px; height: 9px; border-radius: 999px; background: #19b66a; margin-right: 8px; box-shadow: 0 0 0 0 rgba(25,182,106,.5); animation: pulse 1.8s infinite; }
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(25,182,106,.5); }
    70% { box-shadow: 0 0 0 10px rgba(25,182,106,0); }
    100% { box-shadow: 0 0 0 0 rgba(25,182,106,0); }
}
.grid-4 { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 10px; margin-bottom: 24px; }
.grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 10px; margin-bottom: 24px; }
.metric, .section { background: var(--card); border: 1px solid var(--line); border-radius: 14px; }
.metric { padding: 14px 16px; }
.metric-label { font-size: 11px; color: var(--muted); margin-bottom: 6px; }
.metric-value { font-size: 30px; font-weight: 600; line-height: 1; }
.metric-sub { font-size: 11px; color: #9a9a91; margin-top: 6px; }
.section { padding: 16px; margin-bottom: 16px; }
.section h2 { font-size: 13px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 14px; }
.two-col { display: grid; grid-template-columns: 1.1fr .9fr; gap: 16px; margin-bottom: 16px; }
.bottom-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.chart-wrap { height: 360px; position: relative; }
#statsChart { width: 100%; height: 100%; display: block; }
.chart-note { font-size: 12px; color: var(--muted); margin-top: 10px; }
.endpoint-group { margin-bottom: 16px; }
.endpoint-group h3 { font-size: 11px; font-weight: 600; color: #aaa; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px; }
.endpoint { display: flex; align-items: center; gap: 10px; padding: 7px 0; border-bottom: 1px solid #f0efe8; }
.endpoint:last-child { border-bottom: none; }
.method { font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 4px; min-width: 38px; text-align: center; flex-shrink: 0; }
.method.get  { background: var(--good-soft); color: var(--good); }
.method.post { background: var(--accent-soft); color: var(--accent); }
.endpoint-url { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; color: var(--text); flex: 1; }
.endpoint-desc { font-size: 12px; color: var(--muted); }
.log-row { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f0efe8; }
.log-row:last-child { border-bottom: none; }
.action-badge { font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 4px; flex-shrink: 0; min-width: 52px; text-align: center; }
.log-desc { font-size: 12px; color: #444; flex: 1; line-height: 1.4; }
.log-meta { font-size: 11px; color: #aaa; white-space: nowrap; }
.inline-stats { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 10px; margin-top: 12px; }
.mini { background: #fafaf8; border: 1px solid #ecebe5; border-radius: 12px; padding: 12px; }
.mini-label { font-size: 11px; color: var(--muted); }
.mini-value { font-size: 22px; font-weight: 700; margin-top: 4px; }
.refresh-btn { appearance: none; border: 1px solid var(--line); background: #fff; border-radius: 10px; padding: 9px 12px; cursor: pointer; font-weight: 600; }
.refresh-btn:hover { background: #f9f9f7; }
.toolbar { display:flex; justify-content: space-between; gap: 10px; align-items: center; margin-bottom: 12px; flex-wrap: wrap; }
.small-muted { font-size: 12px; color: var(--muted); }
@media (max-width: 900px) {
    .grid-4 { grid-template-columns: repeat(2, 1fr); }
    .grid-3 { grid-template-columns: repeat(2, 1fr); }
    .two-col, .bottom-grid { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
    .endpoint-desc { display: none; }
    .grid-3, .grid-4, .inline-stats { grid-template-columns: 1fr; }
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
			<div class="pill-value"><span class="live-dot"></span>Auto-refresh every 30s</div>
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
    <div class="metric"><div class="metric-label">Videos</div><div class="metric-value" data-stat="videos"><?= $stats['videos'] ?></div></div>
    <div class="metric"><div class="metric-label">Events</div><div class="metric-value" data-stat="events"><?= $stats['events'] ?></div><div class="metric-sub"><span data-stat="upcoming"><?= $stats['upcoming'] ?></span> upcoming</div></div>
    <div class="metric"><div class="metric-label">Memorandums</div><div class="metric-value" data-stat="memorandum"><?= $stats['memorandum'] ?></div></div>
</div>

<div class="grid-3">
    <div class="metric"><div class="metric-label">Officers</div><div class="metric-value" data-stat="officers"><?= $stats['officers'] ?></div></div>
    <div class="metric"><div class="metric-label">Governors</div><div class="metric-value" data-stat="governors"><?= $stats['governors'] ?></div></div>
    <div class="metric"><div class="metric-label">Clubs</div><div class="metric-value" data-stat="clubs"><?= $stats['clubs'] ?></div></div>
</div>

<div class="grid-3">
    <div class="metric"><div class="metric-label">Members</div><div class="metric-value" data-stat="members"><?= $stats['members'] ?></div><div class="metric-sub"><span data-stat="active"><?= $stats['active'] ?></span> active</div></div>
    <div class="metric"><div class="metric-label">Admins</div><div class="metric-value" data-stat="admins"><?= $stats['admins'] ?></div></div>
    <div class="metric"><div class="metric-label">System time</div><div class="metric-value" id="clockSmall"><?= date('g:i:s A') ?></div><div class="metric-sub">running live in browser</div></div>
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
            <div class="mini"><div class="mini-label">Published news</div><div class="mini-value" data-stat="published"><?= $stats['published'] ?></div></div>
            <div class="mini"><div class="mini-label">Draft news</div><div class="mini-value" data-stat="draft"><?= $stats['draft'] ?></div></div>
            <div class="mini"><div class="mini-label">Active members</div><div class="mini-value" data-stat="active"><?= $stats['active'] ?></div></div>
        </div>
    </div>

    <div class="section">
        <div class="toolbar">
            <h2 style="margin-bottom:0;">Recent admin actions</h2>
            <div class="small-muted">Auto-updates</div>
        </div>
        <div id="recentLogs">
            <?php if (empty($recent_logs)): ?>
                <p style="color:#aaa;font-size:13px;">No logs yet.</p>
            <?php else: ?>
                <?php foreach ($recent_logs as $log):
                    $ac = $action_colors[$log['action_type']] ?? ['bg' => '#f0f0f0', 'text' => '#888'];
                ?>
                <div class="log-row">
                    <span class="action-badge" style="background:<?= htmlspecialchars($ac['bg']) ?>;color:<?= htmlspecialchars($ac['text']) ?>"><?= htmlspecialchars($log['action_type']) ?></span>
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
        <div class="small-muted" style="line-height:1.7; color:#555;">
            This page now includes a running browser clock, manual refresh, and live polling every 30 seconds using <code>?ajax=dashboard</code>.<br><br>
            To make it more real-time later, puwede mo pa i-upgrade into WebSocket or Server-Sent Events kapag gusto mo na ng push updates instead of polling.
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

    chartCtx.strokeStyle = '#ecebe5';
    chartCtx.fillStyle = '#8b8b82';
    chartCtx.lineWidth = 1;
    chartCtx.font = '12px system-ui, sans-serif';

    for (let i = 0; i <= 4; i++) {
        const y = padding.top + (innerHeight / 4) * i;
        chartCtx.beginPath();
        chartCtx.moveTo(padding.left, y);
        chartCtx.lineTo(width - padding.right, y);
        chartCtx.stroke();

        const labelValue = Math.round(max - (max / 4) * i);
        chartCtx.fillText(String(labelValue), 8, y + 4);
    }

    rows.forEach((item, index) => {
        const x = padding.left + stepX * index + (stepX - barWidth) / 2;
        const barHeight = max === 0 ? 0 : (Number(item.value) / max) * innerHeight;
        const y = padding.top + innerHeight - barHeight;

        const gradient = chartCtx.createLinearGradient(0, y, 0, y + barHeight + 1);
        gradient.addColorStop(0, '#185FA5');
        gradient.addColorStop(1, '#4E93D4');
        chartCtx.fillStyle = gradient;
        roundRect(chartCtx, x, y, barWidth, Math.max(barHeight, 4), 8, true, false);

        chartCtx.fillStyle = '#1a1a18';
        chartCtx.font = 'bold 12px system-ui, sans-serif';
        chartCtx.textAlign = 'center';
        chartCtx.fillText(String(item.value), x + barWidth / 2, y - 8);

        chartCtx.fillStyle = '#66665f';
        chartCtx.font = '12px system-ui, sans-serif';
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
        container.innerHTML = '<p style="color:#aaa;font-size:13px;">No logs yet.</p>';
        return;
    }

    container.innerHTML = logs.map(log => `
        <div class="log-row">
            <span class="action-badge" style="background:${escapeHtml(log.badge_bg)};color:${escapeHtml(log.badge_text)}">${escapeHtml(log.action_type)}</span>
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
        console.error(error);
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
