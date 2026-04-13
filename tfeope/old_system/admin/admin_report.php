<?php
/* =========================================================
  admin_report.php (UPDATED)
  ✅ Adds per-section printing:
     - Members per Region
     - Members per Club
     - Members List
     - Admin Action Logs
  ✅ Each section has its own "Print" button
  ✅ Only the chosen section prints (not the whole page)

  Keeps:
  - Admin action logs, admins/users status, filters, pagination, export CSV
  - Collapsable club + member list
========================================================= */

if (session_status() === PHP_SESSION_NONE) session_start();

/* Guard */
if (!isset($_SESSION['admin_logged_in'])) {
  header("Location: admin-login");
  exit;
}

require_once __DIR__ . "/../includes/db.php";
if (!isset($conn) || !($conn instanceof mysqli)) die("DB connection \$conn not found. Check db.php.");

/* =========================================================
  CONFIG
========================================================= */
$ONLINE_MINUTES = 5;

/* ✅ YOUR MEMBERS TABLE */
$MEMBERS_TABLE   = "user_info";
$COL_MEMBER_ID   = "eagles_id";
$COL_STATUS      = "eagles_status";
$COL_FIRSTNAME   = "eagles_firstName";
$COL_LASTNAME    = "eagles_lastName";
$COL_POSITION    = "eagles_position";
$COL_CLUB        = "eagles_club";
$COL_REGION      = "eagles_region";
$COL_DATEADDED   = "eagles_dateAdded";

/* Earnings */
$EARN_PER_MEMBER = 100;

/* Cleanup old sessions */
$conn->query("UPDATE admin_sessions SET is_online=0 WHERE last_activity < (NOW() - INTERVAL 1 DAY)");
$conn->query("UPDATE user_sessions  SET is_online=0 WHERE last_activity < (NOW() - INTERVAL 1 DAY)");

/* =========================================================
  HELPERS
========================================================= */
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function table_exists(mysqli $conn, string $table): bool {
  $t = $conn->real_escape_string($table);
  $res = $conn->query("SHOW TABLES LIKE '$t'");
  return $res && $res->num_rows > 0;
}

function cols_exist(mysqli $conn, string $table, array $cols): bool {
  if (!table_exists($conn, $table)) return false;
  $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
  $res = $conn->query("SHOW COLUMNS FROM `$t`");
  if (!$res) return false;

  $have = [];
  while ($r = $res->fetch_assoc()) $have[$r['Field']] = true;

  foreach ($cols as $c) if (!isset($have[$c])) return false;
  return true;
}

/* =========================================================
  SESSION -> USER ID
========================================================= */
$SESSION_USER_ID = 0;
if (isset($_SESSION['user_id'])) $SESSION_USER_ID = (int)$_SESSION['user_id'];
elseif (isset($_SESSION['id'])) $SESSION_USER_ID = (int)$_SESSION['id'];

function get_current_user_row(mysqli $conn, int $uid): ?array {
  if ($uid <= 0) return null;
  $stmt = $conn->prepare("SELECT id, username, role_id FROM users WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return $row ?: null;
}

$currentUser = get_current_user_row($conn, $SESSION_USER_ID);

if (!$currentUser || !in_array((int)$currentUser['role_id'], [1,2], true)) {
  http_response_code(403);
  die("Forbidden: Admin access only.");
}

$isSuperAdmin = ((int)$currentUser['role_id'] === 1);

/* =========================================================
  Correct Client IP
========================================================= */
function is_public_ip(string $ip): bool {
  return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

function get_client_ip(): array {
  $h = $_SERVER;
  $chain = [];

  if (!empty($h['HTTP_CF_CONNECTING_IP'])) {
    $ip = trim($h['HTTP_CF_CONNECTING_IP']);
    $chain[] = $ip;
    if (filter_var($ip, FILTER_VALIDATE_IP)) return ['ip' => $ip, 'chain' => implode(', ', $chain)];
  }

  if (!empty($h['HTTP_X_FORWARDED_FOR'])) {
    $parts = array_map('trim', explode(',', $h['HTTP_X_FORWARDED_FOR']));
    foreach ($parts as $p) if ($p !== '') $chain[] = $p;

    foreach ($parts as $p) {
      if (filter_var($p, FILTER_VALIDATE_IP) && is_public_ip($p)) {
        return ['ip' => $p, 'chain' => implode(', ', $chain)];
      }
    }

    $first = $parts[0] ?? '';
    if ($first && filter_var($first, FILTER_VALIDATE_IP)) return ['ip' => $first, 'chain' => implode(', ', $chain)];
  }

  if (!empty($h['HTTP_X_REAL_IP'])) {
    $ip = trim($h['HTTP_X_REAL_IP']);
    $chain[] = $ip;
    if (filter_var($ip, FILTER_VALIDATE_IP)) return ['ip' => $ip, 'chain' => implode(', ', $chain)];
  }

  $ip = trim((string)($h['REMOTE_ADDR'] ?? ''));
  if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
    $chain[] = $ip;
    return ['ip' => $ip, 'chain' => implode(', ', $chain)];
  }

  return ['ip' => null, 'chain' => implode(', ', $chain)];
}

/* =========================================================
  TRACK ONLINE ADMIN
========================================================= */
function track_admin_online(mysqli $conn, int $uid): void {
  $sid = session_id();
  $ipInfo = get_client_ip();
  $ip  = $ipInfo['ip'];
  $ua  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

  $sql = "
    INSERT INTO admin_sessions (session_id, user_id, ip_address, user_agent, last_activity, is_online)
    VALUES (?, ?, ?, ?, NOW(), 1)
    ON DUPLICATE KEY UPDATE
      user_id=VALUES(user_id),
      ip_address=VALUES(ip_address),
      user_agent=VALUES(user_agent),
      last_activity=NOW(),
      is_online=1
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("siss", $sid, $uid, $ip, $ua);
  $stmt->execute();
  $stmt->close();
}
track_admin_online($conn, (int)$currentUser['id']);

/* =========================================================
  FILTERS (GET)
========================================================= */
$from      = trim($_GET['from'] ?? "");
$to        = trim($_GET['to'] ?? "");
$from_time = trim($_GET['from_time'] ?? "00:00");
$to_time   = trim($_GET['to_time'] ?? "23:59");

$admin = trim($_GET['admin'] ?? "");
$type  = trim($_GET['type'] ?? "");
$q     = trim($_GET['q'] ?? "");

/* SUPER ADMIN: member filters */
$regionFilter = trim($_GET['region'] ?? "");
$clubFilter   = trim($_GET['club'] ?? "");
$statusFilter = trim($_GET['m_status'] ?? "");

if ($from === "" && $to === "") {
  $to = date("Y-m-d");
  $from = date("Y-m-d", strtotime("-7 days"));
}

function is_valid_date_ymd(string $s): bool {
  if ($s === "") return true;
  $d = DateTime::createFromFormat('Y-m-d', $s);
  return $d && $d->format('Y-m-d') === $s;
}
function is_valid_time_hm(string $s): bool {
  if ($s === "") return true;
  return (bool)preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $s);
}

if (!is_valid_date_ymd($from) || !is_valid_date_ymd($to)) {
  $to = date("Y-m-d");
  $from = date("Y-m-d", strtotime("-7 days"));
}
if (!is_valid_time_hm($from_time)) $from_time = "00:00";
if (!is_valid_time_hm($to_time))   $to_time   = "23:59";

$fromDT = $from . " " . $from_time . ":00";
$toDT   = $to   . " " . $to_time   . ":59";

/* =========================================================
  LOGS WHERE CLAUSE
========================================================= */
$where = [];
$params = [];
$typestr = "";

/* Permission filter */
if (!$isSuperAdmin) {
  $where[] = "admin_user_id = ?";
  $params[] = (int)$currentUser['id'];
  $typestr .= "i";

  $admin = "";
  $type  = "";
  $q     = "";
}

/* DateTime filter */
$where[]  = "created_at >= ?";
$params[] = $fromDT; $typestr .= "s";
$where[]  = "created_at <= ?";
$params[] = $toDT;   $typestr .= "s";

if ($isSuperAdmin && $admin !== "") {
  $where[] = "admin_username = ?";
  $params[] = $admin; $typestr .= "s";
}
if ($isSuperAdmin && $type !== "") {
  $where[] = "action_type = ?";
  $params[] = $type; $typestr .= "s";
}
if ($isSuperAdmin && $q !== "") {
  $where[] = "(action_desc LIKE ? OR admin_username LIKE ? OR action_type LIKE ? OR ip_address LIKE ?)";
  $like = "%{$q}%";
  $params[] = $like; $typestr .= "s";
  $params[] = $like; $typestr .= "s";
  $params[] = $like; $typestr .= "s";
  $params[] = $like; $typestr .= "s";
}

$whereSql = count($where) ? ("WHERE " . implode(" AND ", $where)) : "";

function qs(array $overrides = []): string {
  $base = $_GET;
  foreach ($overrides as $k => $v) $base[$k] = $v;
  return http_build_query($base);
}

/* =========================================================
  EXPORT CSV (LOGS)
========================================================= */
if (isset($_GET['action']) && $_GET['action'] === 'export') {
  $sql = "
    SELECT id, admin_user_id, admin_username, action_type, action_desc, ip_address, created_at
    FROM admin_action_logs
    $whereSql
    ORDER BY created_at DESC
  ";
  $stmt = $conn->prepare($sql);
  if ($stmt === false) die("Prepare failed: " . $conn->error);
  if ($typestr !== "") $stmt->bind_param($typestr, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="admin_action_logs_' . date("Ymd_His") . '.csv"');

  $out = fopen('php://output', 'w');
  fputcsv($out, ['ID', 'Admin User ID', 'Admin Username', 'Action Type', 'Description', 'IP Address', 'Date/Time']);

  while ($row = $res->fetch_assoc()) {
    fputcsv($out, [
      $row['id'],
      $row['admin_user_id'],
      $row['admin_username'],
      $row['action_type'],
      $row['action_desc'],
      $row['ip_address'],
      $row['created_at'],
    ]);
  }
  fclose($out);
  $stmt->close();
  exit;
}

/* =========================================================
  Dropdowns (LOGS)
========================================================= */
$admins = [];
$types = [];
if ($isSuperAdmin) {
  $adminRes = $conn->query("SELECT DISTINCT admin_username FROM admin_action_logs ORDER BY admin_username ASC");
  if ($adminRes) while ($r = $adminRes->fetch_assoc()) $admins[] = $r['admin_username'];

  $typeRes = $conn->query("SELECT DISTINCT action_type FROM admin_action_logs ORDER BY action_type ASC");
  if ($typeRes) while ($r = $typeRes->fetch_assoc()) $types[] = $r['action_type'];
}

/* =========================================================
  SUMMARY COUNTS (LOGS)
========================================================= */
$sumSql = "
  SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN action_type='CREATE' THEN 1 ELSE 0 END) AS creates,
    SUM(CASE WHEN action_type='UPDATE' THEN 1 ELSE 0 END) AS updates,
    SUM(CASE WHEN action_type='DELETE' THEN 1 ELSE 0 END) AS deletes,
    SUM(CASE WHEN action_type='LOGIN'  THEN 1 ELSE 0 END) AS logins
  FROM admin_action_logs
  $whereSql
";
$sumStmt = $conn->prepare($sumSql);
if ($sumStmt === false) die("Prepare failed: " . $conn->error);
if ($typestr !== "") $sumStmt->bind_param($typestr, ...$params);
$sumStmt->execute();
$sum = $sumStmt->get_result()->fetch_assoc() ?: ['total'=>0,'creates'=>0,'updates'=>0,'deletes'=>0,'logins'=>0];
$sumStmt->close();

/* =========================================================
  PAGINATION + LOGS LIST
========================================================= */
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$countSql = "SELECT COUNT(*) AS c FROM admin_action_logs $whereSql";
$countStmt = $conn->prepare($countSql);
if ($countStmt === false) die("Prepare failed: " . $conn->error);
if ($typestr !== "") $countStmt->bind_param($typestr, ...$params);
$countStmt->execute();
$totalRows = (int)($countStmt->get_result()->fetch_assoc()['c'] ?? 0);
$countStmt->close();

$totalPages = max(1, (int)ceil($totalRows / $perPage));

$sql = "
  SELECT id, admin_username, action_type, action_desc, ip_address, created_at
  FROM admin_action_logs
  $whereSql
  ORDER BY created_at DESC
  LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);
if ($stmt === false) die("Prepare failed: " . $conn->error);

if ($typestr !== "") {
  $bindTypes  = $typestr . "ii";
  $bindParams = array_merge($params, [$perPage, $offset]);
  $stmt->bind_param($bindTypes, ...$bindParams);
} else {
  $stmt->bind_param("ii", $perPage, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

/* =========================================================
  ADMINS STATUS
========================================================= */
$adminStmt = $conn->prepare("
  SELECT
    u.username,
    r.name AS role_name,
    s.last_activity,
    s.ip_address,
    CASE
      WHEN s.last_activity >= (NOW() - INTERVAL ? MINUTE) THEN 1
      ELSE 0
    END AS is_active
  FROM admin_sessions s
  JOIN (
    SELECT user_id, MAX(last_activity) AS max_last
    FROM admin_sessions
    WHERE is_online = 1
    GROUP BY user_id
  ) latest ON latest.user_id = s.user_id AND latest.max_last = s.last_activity
  JOIN users u ON u.id = s.user_id
  JOIN roles r ON r.id = u.role_id
  WHERE s.is_online = 1
    AND u.role_id IN (1,2)
  ORDER BY is_active DESC, s.last_activity DESC
");
$adminStmt->bind_param("i", $ONLINE_MINUTES);
$adminStmt->execute();
$adminSessions = [];
$adminRes2 = $adminStmt->get_result();
while ($row = $adminRes2->fetch_assoc()) $adminSessions[] = $row;
$adminStmt->close();

$activeAdmins = 0;
foreach ($adminSessions as $x) if ((int)$x['is_active'] === 1) $activeAdmins++;

/* =========================================================
  USERS STATUS (non-admin)
========================================================= */
$userStmt = $conn->prepare("
  SELECT
    u.username,
    r.name AS role_name,
    s.last_activity,
    s.ip_address,
    CASE
      WHEN s.last_activity >= (NOW() - INTERVAL ? MINUTE) THEN 1
      ELSE 0
    END AS is_active
  FROM user_sessions s
  JOIN (
    SELECT user_id, MAX(last_activity) AS max_last
    FROM user_sessions
    WHERE is_online = 1
    GROUP BY user_id
  ) latest ON latest.user_id = s.user_id AND latest.max_last = s.last_activity
  JOIN users u ON u.id = s.user_id
  JOIN roles r ON r.id = u.role_id
  WHERE s.is_online = 1
    AND u.role_id NOT IN (1,2)
  ORDER BY is_active DESC, s.last_activity DESC
");
$userStmt->bind_param("i", $ONLINE_MINUTES);
$userStmt->execute();
$userSessions = [];
$userRes2 = $userStmt->get_result();
while ($row = $userRes2->fetch_assoc()) $userSessions[] = $row;
$userStmt->close();

$activeUsers = 0;
foreach ($userSessions as $x) if ((int)$x['is_active'] === 1) $activeUsers++;

/* =========================================================
  SUPER ADMIN: MEMBERS REPORTS
========================================================= */
$memberReportOk = false;
$regions = [];
$clubs = [];
$statuses = ["ACTIVE", "RENEWAL"];

$membersByRegion = [];
$membersByClub   = [];
$membersList     = [];

$totalMembers = 0;
$totalActiveMembers = 0;
$estimatedEarnings  = 0;

if ($isSuperAdmin) {
  $requiredCols = [$COL_MEMBER_ID, $COL_STATUS, $COL_FIRSTNAME, $COL_LASTNAME, $COL_CLUB, $COL_REGION];
  $memberReportOk = cols_exist($conn, $MEMBERS_TABLE, $requiredCols);

  if ($memberReportOk) {
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', $MEMBERS_TABLE);

    $rTot = $conn->query("SELECT COUNT(*) AS c FROM `$t`");
    if ($rTot) $totalMembers = (int)($rTot->fetch_assoc()['c'] ?? 0);

$rAct = $conn->query("SELECT COUNT(*) AS c FROM `$t` WHERE `$COL_STATUS` IN ('ACTIVE','RENEWAL')");
    if ($rAct) $totalActiveMembers = (int)($rAct->fetch_assoc()['c'] ?? 0);

    $estimatedEarnings = $totalActiveMembers * (int)$EARN_PER_MEMBER;

    $regRes = $conn->query("SELECT DISTINCT `$COL_REGION` AS region FROM `$t` WHERE `$COL_REGION`<>'' AND `$COL_REGION` IS NOT NULL ORDER BY `$COL_REGION` ASC");
    if ($regRes) while ($r = $regRes->fetch_assoc()) $regions[] = $r['region'];

    $clubRes = $conn->query("SELECT DISTINCT `$COL_CLUB` AS club FROM `$t` WHERE `$COL_CLUB`<>'' AND `$COL_CLUB` IS NOT NULL ORDER BY `$COL_CLUB` ASC");
    if ($clubRes) while ($r = $clubRes->fetch_assoc()) $clubs[] = $r['club'];

    $mw = [];
    $mp = [];
    $mt = "";

    if ($regionFilter !== "") { $mw[] = "`$COL_REGION` = ?"; $mp[] = $regionFilter; $mt .= "s"; }
    if ($clubFilter   !== "") { $mw[] = "`$COL_CLUB`   = ?"; $mp[] = $clubFilter;   $mt .= "s"; }
    if ($statusFilter !== "" && in_array($statusFilter, $statuses, true)) {
      $mw[] = "`$COL_STATUS` = ?"; $mp[] = $statusFilter; $mt .= "s";
    }
    $mWhere = count($mw) ? ("WHERE " . implode(" AND ", $mw)) : "";

    // Members per Region
    $sqlR = "
      SELECT `$COL_REGION` AS region, COUNT(*) AS cnt
      FROM `$t`
      $mWhere
      GROUP BY `$COL_REGION`
      ORDER BY cnt DESC, region ASC
    ";
    $stR = $conn->prepare($sqlR);
    if ($stR) {
      if ($mt !== "") $stR->bind_param($mt, ...$mp);
      $stR->execute();
      $rs = $stR->get_result();
      while ($row = $rs->fetch_assoc()) $membersByRegion[] = $row;
      $stR->close();
    }

    // Members per Club
    $sqlC = "
      SELECT `$COL_CLUB` AS club, COUNT(*) AS cnt
      FROM `$t`
      $mWhere
      GROUP BY `$COL_CLUB`
      ORDER BY cnt DESC, club ASC
    ";
    $stC = $conn->prepare($sqlC);
    if ($stC) {
      if ($mt !== "") $stC->bind_param($mt, ...$mp);
      $stC->execute();
      $rs = $stC->get_result();
      while ($row = $rs->fetch_assoc()) $membersByClub[] = $row;
      $stC->close();
    }

    // Members list
    $sqlL = "
      SELECT
        `$COL_MEMBER_ID` AS member_id,
        `$COL_STATUS`    AS status,
        `$COL_FIRSTNAME` AS first_name,
        `$COL_LASTNAME`  AS last_name,
        `$COL_POSITION`  AS position,
        `$COL_CLUB`      AS club,
        `$COL_REGION`    AS region,
        `$COL_DATEADDED` AS date_added
      FROM `$t`
      $mWhere
      ORDER BY `$COL_DATEADDED` DESC
      LIMIT 300
    ";
    $stL = $conn->prepare($sqlL);
    if ($stL) {
      if ($mt !== "") $stL->bind_param($mt, ...$mp);
      $stL->execute();
      $rs = $stL->get_result();
      while ($row = $rs->fetch_assoc()) $membersList[] = $row;
      $stL->close();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Reports & Logs</title>

  <link rel="icon" type="image/png" href="/../static/eagles.png" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Merriweather:wght@700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="../admin styles/sidebar.css">
  <link rel="stylesheet" href="../admin styles/admin_report.css">
</head>

<body>
<button class="sidebar-toggle"><i class="fas fa-bars"></i></button>
<?php include 'sidebar.php'; ?>

<div class="main-content">
  <div class="page-head">
    <div>
      <h1>Reports & Admin Logs</h1>
      <p class="sub">Generate reports for actions done by admins. View active and inactive admins/users (live).</p>
      <?php if (!$isSuperAdmin): ?>
        <p class="sub" style="margin-top:6px;">You are viewing your own logs only.</p>
      <?php endif; ?>
    </div>

    <div class="head-actions">
      <a class="btn ghost" href="?<?= h(qs(['action' => 'export', 'page' => 1])) ?>">
        <i class="fa-solid fa-file-csv"></i> Export CSV
      </a>
    </div>
  </div>

  <?php if ($isSuperAdmin): ?>
    <div class="card">
      <div class="card-title">
        <i class="fa-solid fa-chart-pie"></i>
        <span>Members Overview</span>
      </div>

      <?php if (!$memberReportOk): ?>
        <div class="empty">
          Members report not available.
          Make sure table <span class="mono"><?= h($MEMBERS_TABLE) ?></span> has columns:
          <span class="mono"><?= h($COL_REGION) ?></span> and <span class="mono"><?= h($COL_CLUB) ?></span>.
        </div>
      <?php else: ?>
        <div class="report-grid">
          <div class="report-box">
            <div class="rb-label">Total Members</div>
            <div class="rb-value"><?= (int)$totalMembers ?></div>
          </div>

          <div class="report-box">
            <div class="rb-label">Active Members</div>
            <div class="rb-value"><?= (int)$totalActiveMembers ?></div>
          </div>

          <div class="report-box">
            <div class="rb-label">Estimated Earnings</div>
            <div class="rb-value">₱<?= number_format((int)$estimatedEarnings) ?></div>
          </div>

          <div class="report-box">
            <div class="rb-label">Members Listed</div>
            <div class="rb-value"><?= (int)count($membersList) ?></div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- SUPER ADMIN FILTERS -->
    <div class="card filters super-filters">
      <div class="card-title">
        <i class="fa-solid fa-sliders"></i>
        <span>Filters</span>
      </div>

      <form class="filter-grid" method="GET" action="">
        <!-- LOGS FILTER -->
        <div class="field">
          <label>Log From (Date)</label>
          <input type="date" name="from" value="<?= h($from) ?>">
        </div>

        <div class="field">
          <label>Log From (Time)</label>
          <input type="time" name="from_time" value="<?= h($from_time) ?>">
        </div>

        <div class="field">
          <label>Log To (Date)</label>
          <input type="date" name="to" value="<?= h($to) ?>">
        </div>

        <div class="field">
          <label>Log To (Time)</label>
          <input type="time" name="to_time" value="<?= h($to_time) ?>">
        </div>

        <div class="field">
          <label>Admin</label>
          <select name="admin">
            <option value="">All</option>
            <?php foreach ($admins as $a): ?>
              <option value="<?= h($a) ?>" <?= $admin === $a ? 'selected' : '' ?>><?= h($a) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>Action Type</label>
          <select name="type">
            <option value="">All</option>
            <?php foreach ($types as $t): ?>
              <option value="<?= h($t) ?>" <?= $type === $t ? 'selected' : '' ?>><?= h($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field grow">
          <label>Search (Logs)</label>
          <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search description, IP, type...">
        </div>

        <!-- MEMBERS FILTER -->
        <div class="field">
          <label>Members: Region</label>
          <select name="region">
            <option value="">All</option>
            <?php foreach ($regions as $r): ?>
              <option value="<?= h($r) ?>" <?= $regionFilter === $r ? 'selected' : '' ?>><?= h($r) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>Members: Club</label>
          <select name="club">
            <option value="">All</option>
            <?php foreach ($clubs as $c): ?>
              <option value="<?= h($c) ?>" <?= $clubFilter === $c ? 'selected' : '' ?>><?= h($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>Members: Status</label>
          <select name="m_status">
            <option value="">All</option>
            <option value="ACTIVE" <?= $statusFilter === "ACTIVE" ? "selected" : "" ?>>ACTIVE</option>
            <option value="RENEWAL" <?= $statusFilter === "RENEWAL" ? "selected" : "" ?>>RENEWAL</option>
          </select>
        </div>

        <input type="hidden" name="page" value="1" />

        <div class="field actions">
          <button class="btn" type="submit">
            <i class="fa-solid fa-magnifying-glass"></i> Apply
          </button>
          <a class="btn ghost" href="admin_report.php">
            <i class="fa-solid fa-rotate-left"></i> Reset
          </a>
        </div>
      </form>
    </div>

    <!-- MEMBERS PER REGION (PRINTABLE) -->
    <div class="card printable" id="print-region">
      <div class="card-title print-head">
        <span class="ct-left">
          <i class="fa-solid fa-map-location-dot"></i>
          <span>Members per Region</span>
        </span>
        <button class="btn ghost print-btn" type="button" data-print="#print-region">
          <i class="fa-solid fa-print"></i> Print
        </button>
      </div>

      <?php if (!$memberReportOk): ?>
        <div class="empty">Members report not available (missing table/columns).</div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="tbl tbl-compact">
            <thead>
              <tr>
                <th>Region</th>
                <th style="width:160px;">Members</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($membersByRegion)): ?>
                <tr><td colspan="2" class="empty">No members found for current filters.</td></tr>
              <?php else: ?>
                <?php foreach ($membersByRegion as $r): ?>
                  <tr>
                    <td><?= h($r['region'] ?? '') ?></td>
                    <td class="mono"><?= (int)($r['cnt'] ?? 0) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- MEMBERS PER CLUB (COLLAPSABLE + PRINTABLE) -->
    <div class="card collapse-card printable" data-collapse="true" id="print-club">
      <div class="card-title print-head">
        <button class="collapse-toggle" type="button" aria-expanded="false">
          <span class="ct-left">
            <i class="fa-solid fa-people-group"></i>
            <span>Members per Club</span>
            <span class="pill">Click to expand</span>
          </span>
          <span class="ct-right">
            <i class="fa-solid fa-chevron-down collapse-ico" aria-hidden="true"></i>
          </span>
        </button>

        <button class="btn ghost print-btn" type="button" data-print="#print-club">
          <i class="fa-solid fa-print"></i> Print
        </button>
      </div>

      <div class="card-body collapse-body is-collapsed" hidden>
        <?php if (!$memberReportOk): ?>
          <div class="empty">Members report not available (missing table/columns).</div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="tbl tbl-compact">
              <thead>
                <tr>
                  <th>Club</th>
                  <th style="width:160px;">Members</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($membersByClub)): ?>
                  <tr><td colspan="2" class="empty">No clubs found for current filters.</td></tr>
                <?php else: ?>
                  <?php foreach ($membersByClub as $c): ?>
                    <tr>
                      <td><?= h($c['club'] ?? '') ?></td>
                      <td class="mono"><?= (int)($c['cnt'] ?? 0) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- MEMBERS LIST (COLLAPSABLE + PRINTABLE) -->
    <div class="card collapse-card printable" data-collapse="true" id="print-members">
      <div class="card-title print-head">
        <button class="collapse-toggle" type="button" aria-expanded="false">
          <span class="ct-left">
            <i class="fa-solid fa-id-badge"></i>
            <span>Members List</span>
            <span class="pill">Click to expand</span>
          </span>
          <span class="ct-right">
            <i class="fa-solid fa-chevron-down collapse-ico" aria-hidden="true"></i>
          </span>
        </button>

        <button class="btn ghost print-btn" type="button" data-print="#print-members">
          <i class="fa-solid fa-print"></i> Print
        </button>
      </div>

      <div class="card-body collapse-body is-collapsed" hidden>
        <?php if (!$memberReportOk): ?>
          <div class="empty">Members list not available (missing table/columns).</div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="tbl">
              <thead>
                <tr>
                  <th style="width:180px;">Member ID</th>
                  <th style="width:120px;">Status</th>
                  <th>Name</th>
                  <th style="width:240px;">Position</th>
                  <th style="width:260px;">Club</th>
                  <th style="width:220px;">Region</th>
                  <th style="width:210px;">Date Added</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($membersList)): ?>
                  <tr><td colspan="7" class="empty">No members match current filters.</td></tr>
                <?php else: ?>
                  <?php foreach ($membersList as $m): ?>
                    <?php
                      $fullName = trim(($m['first_name'] ?? '') . " " . ($m['last_name'] ?? ''));
                      $st = strtoupper((string)($m['status'] ?? ''));
                      $tagClass = ($st === 'ACTIVE') ? 't-create' : (($st === 'RENEWAL') ? 't-update' : 't-delete');
                    ?>
                    <tr>
                      <td class="mono"><?= h($m['member_id'] ?? '') ?></td>
                      <td><span class="tag <?= h($tagClass) ?>"><?= h($st) ?></span></td>
                      <td><?= h($fullName) ?></td>
                      <td><?= h($m['position'] ?? '') ?></td>
                      <td><?= h($m['club'] ?? '') ?></td>
                      <td><?= h($m['region'] ?? '') ?></td>
                      <td class="mono"><?= h($m['date_added'] ?? '') ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- SUMMARY (LOGS) -->
  <div class="stats">
    <div class="stat">
      <div class="stat-label">Total Logs</div>
      <div class="stat-value"><?= (int)$sum['total'] ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">CREATE</div>
      <div class="stat-value"><?= (int)$sum['creates'] ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">UPDATE</div>
      <div class="stat-value"><?= (int)$sum['updates'] ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">DELETE</div>
      <div class="stat-value"><?= (int)$sum['deletes'] ?></div>
    </div>
    <div class="stat">
      <div class="stat-label">LOGIN</div>
      <div class="stat-value"><?= (int)$sum['logins'] ?></div>
    </div>
  </div>

  <!-- ADMINS STATUS (NOT PRINTABLE) -->
  <div class="card online">
    <div class="card-title">
      <i class="fa-solid fa-signal"></i>
      <span>Admins Status</span>
      <span class="pill"><?= (int)$activeAdmins ?> active / <?= count($adminSessions) ?> total (<?= (int)$ONLINE_MINUTES ?> mins)</span>
    </div>

    <div class="online-grid">
      <?php if (count($adminSessions) === 0): ?>
        <div class="empty" style="grid-column:1/-1;">No admin sessions found.</div>
      <?php else: ?>
        <?php foreach ($adminSessions as $u): ?>
          <?php $initial = strtoupper(substr($u['username'], 0, 1)); $isActive = ((int)$u['is_active'] === 1); ?>
          <div class="online-card">
            <div class="online-top">
              <div class="avatar"><?= h($initial) ?></div>
              <div class="online-meta">
                <div class="online-name"><?= h($u['username']) ?></div>
                <div class="online-role"><?= h($u['role_name']) ?></div>
              </div>
              <div class="dot <?= $isActive ? 'on' : 'idle' ?>"></div>
            </div>
            <div class="online-bottom">
              <span class="badge <?= $isActive ? 'b-on' : 'b-idle' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
              <span class="last"><?= h($u['last_activity']) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- USERS STATUS (NOT PRINTABLE) -->
  <div class="card online users-online">
    <div class="card-title">
      <i class="fa-solid fa-users"></i>
      <span>Users Status</span>
      <span class="pill"><?= (int)$activeUsers ?> active / <?= count($userSessions) ?> total (<?= (int)$ONLINE_MINUTES ?> mins)</span>
    </div>

    <div class="online-grid">
      <?php if (count($userSessions) === 0): ?>
        <div class="empty" style="grid-column:1/-1;">No user sessions found.</div>
      <?php else: ?>
        <?php foreach ($userSessions as $u): ?>
          <?php $initial = strtoupper(substr($u['username'], 0, 1)); $isActive = ((int)$u['is_active'] === 1); ?>
          <div class="online-card">
            <div class="online-top">
              <div class="avatar"><?= h($initial) ?></div>
              <div class="online-meta">
                <div class="online-name"><?= h($u['username']) ?></div>
                <div class="online-role"><?= h($u['role_name']) ?></div>
              </div>
              <div class="dot <?= $isActive ? 'on' : 'idle' ?>"></div>
            </div>
            <div class="online-bottom">
              <span class="badge <?= $isActive ? 'b-on' : 'b-idle' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
              <span class="last"><?= h($u['last_activity']) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- LOG TABLE (PRINTABLE) -->
  <div class="card printable" id="print-logs">
    <div class="card-title print-head">
      <span class="ct-left">
        <i class="fa-solid fa-clipboard-list"></i>
        <span>Admin Action Logs</span>
        <span class="pill"><?= h($fromDT) ?> to <?= h($toDT) ?></span>
      </span>

      <button class="btn ghost print-btn" type="button" data-print="#print-logs">
        <i class="fa-solid fa-print"></i> Print
      </button>
    </div>

    <div class="table-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th style="width:90px;">ID</th>
            <th style="width:180px;">Admin</th>
            <th style="width:140px;">Type</th>
            <th>Description</th>
            <th style="width:160px;">IP</th>
            <th style="width:200px;">Date/Time</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($totalRows === 0): ?>
            <tr><td colspan="6" class="empty">No logs found for the current filters.</td></tr>
          <?php else: ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td>#<?= (int)$row['id'] ?></td>
                <td class="mono"><?= h($row['admin_username']) ?></td>
                <td><span class="tag t-<?= h(strtolower($row['action_type'])) ?>"><?= h($row['action_type']) ?></span></td>
                <td><?= h($row['action_desc']) ?></td>
                <td class="mono"><?= h($row['ip_address']) ?></td>
                <td class="mono"><?= h($row['created_at']) ?></td>
              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalRows > 0): ?>
      <div class="pager no-print">
        <div class="pager-info">Page <?= $page ?> of <?= $totalPages ?> (<?= $totalRows ?> rows)</div>
        <div class="pager-actions">
          <a class="btn ghost <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $page <= 1 ? '#' : '?' . h(qs(['page'=>1])) ?>"><i class="fa-solid fa-angles-left"></i></a>
          <a class="btn ghost <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $page <= 1 ? '#' : '?' . h(qs(['page'=>$page-1])) ?>"><i class="fa-solid fa-angle-left"></i></a>
          <a class="btn ghost <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= $page >= $totalPages ? '#' : '?' . h(qs(['page'=>$page+1])) ?>"><i class="fa-solid fa-angle-right"></i></a>
          <a class="btn ghost <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= $page >= $totalPages ? '#' : '?' . h(qs(['page'=>$totalPages])) ?>"><i class="fa-solid fa-angles-right"></i></a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
const sidebar = document.querySelector('.sidebar');
const toggleBtn = document.querySelector('.sidebar-toggle');
toggleBtn?.addEventListener('click', () => sidebar?.classList.toggle('show'));

/* ================= COLLAPSABLE CARDS ================= */
function setCollapsed(card, open) {
  const body = card.querySelector('.collapse-body');
  const btn  = card.querySelector('.collapse-toggle');
  if (!body || !btn) return;

  card.classList.toggle('is-open', open);
  btn.setAttribute('aria-expanded', open ? 'true' : 'false');

  if (open) {
    body.hidden = false;
    body.classList.remove('is-collapsed');
    body.style.maxHeight = body.scrollHeight + 'px';
    setTimeout(() => {
      if (card.classList.contains('is-open')) body.style.maxHeight = 'none';
    }, 260);
  } else {
    body.style.maxHeight = body.scrollHeight + 'px';
    body.offsetHeight;
    body.style.maxHeight = '0px';
    body.classList.add('is-collapsed');
    setTimeout(() => {
      if (!card.classList.contains('is-open')) body.hidden = true;
    }, 260);
  }
}

document.querySelectorAll('.collapse-card[data-collapse="true"]').forEach((card) => {
  setCollapsed(card, false);
  const btn = card.querySelector('.collapse-toggle');
  btn?.addEventListener('click', () => {
    const isOpen = card.classList.contains('is-open');
    setCollapsed(card, !isOpen);
  });
});

/* ================= PER-SECTION PRINT =================
   - Adds body.printing + body.print-target to print only that section
   - Auto-expands collapsables for that section before print
*/
function openAllCollapsablesInside(rootEl){
  rootEl.querySelectorAll('.collapse-card[data-collapse="true"]').forEach(card=>{
    const body = card.querySelector('.collapse-body');
    const btn  = card.querySelector('.collapse-toggle');
    if (!body || !btn) return;

    card.classList.add('is-open');
    btn.setAttribute('aria-expanded','true');
    body.hidden = false;
    body.classList.remove('is-collapsed');
    body.style.maxHeight = 'none';
  });
}

document.querySelectorAll('.print-btn[data-print]').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const sel = btn.getAttribute('data-print');
    const target = sel ? document.querySelector(sel) : null;
    if (!target) return;

    // Ensure collapsables inside target are open for printing
    openAllCollapsablesInside(target);

    document.body.classList.add('printing');
    document.body.classList.add('print-target');
    target.classList.add('print-this');

    // Print
    window.print();
  });
});

// Cleanup after printing (works in most browsers)
window.addEventListener('afterprint', ()=>{
  document.body.classList.remove('printing','print-target');
  document.querySelectorAll('.print-this').forEach(el=>el.classList.remove('print-this'));
});
</script>

</body>
</html>
<?php
$stmt->close();
?>