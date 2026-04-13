<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
  header("Location: admin-login");
  exit;
}

require_once __DIR__ . "/../includes/db.php";
if (!isset($conn) || !($conn instanceof mysqli)) {
  die("Database connection not found.");
}

/* ================= HELPERS ================= */
function h($v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function format_when($value): string {
  if (!$value) return '';
  $ts = strtotime((string)$value);
  return $ts ? date("M d, Y h:i A", $ts) : (string)$value;
}

/* ================= GET CURRENT ADMIN USER ================= */
$SESSION_USER_ID = 0;
if (isset($_SESSION['user_id'])) $SESSION_USER_ID = (int)$_SESSION['user_id'];
elseif (isset($_SESSION['id'])) $SESSION_USER_ID = (int)$_SESSION['id'];

$currentUser = null;
if ($SESSION_USER_ID > 0) {
  $stmt = $conn->prepare("SELECT id, username, role_id FROM users WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $SESSION_USER_ID);
  $stmt->execute();
  $currentUser = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

if (!$currentUser || !in_array((int)$currentUser['role_id'], [1,2], true)) {
  http_response_code(403);
  die("Forbidden: Admin access only.");
}

$isSuperAdmin = ((int)$currentUser['role_id'] === 1);

/* ================= FETCH CARD DATA ================= */
$totalMembersResult = $conn->query("SELECT COUNT(*) as total FROM user_info");
$totalMembers = $totalMembersResult ? (int)$totalMembersResult->fetch_assoc()['total'] : 0;

$totalRegionsResult = $conn->query("SELECT COUNT(DISTINCT eagles_region) as total FROM user_info");
$totalRegions = $totalRegionsResult ? (int)$totalRegionsResult->fetch_assoc()['total'] : 0;

$totalClubsResult = $conn->query("SELECT COUNT(DISTINCT eagles_club) as total FROM user_info");
$totalClubs = $totalClubsResult ? (int)$totalClubsResult->fetch_assoc()['total'] : 0;

/* ================= FETCH RECENT MEMBERS ================= */
$recentMembersResult = $conn->query("
  SELECT eagles_firstName, eagles_lastName, eagles_region, eagles_club, eagles_position
  FROM user_info
  ORDER BY eagles_id DESC
  LIMIT 5
");

/* ================= FETCH LATEST NEWS ================= */
$latestNews = null;

$latestNewsSql = "
  SELECT news_id, news_title, news_content, news_status, created_at
  FROM news_info
  WHERE news_status = 'Published'
  ORDER BY news_id DESC
  LIMIT 1
";

$latestNewsResult = $conn->query($latestNewsSql);
if ($latestNewsResult && $latestNewsResult->num_rows > 0) {
  $latestNews = $latestNewsResult->fetch_assoc();
}

/* ================= FETCH LATEST VIDEO ================= */
$latestVideo = null;

$latestVideoSql = "
  SELECT video_id, video_title, video_description, video_status, created_at
  FROM video_info
  WHERE video_status = 'Published'
  ORDER BY video_id DESC
  LIMIT 1
";

$latestVideoResult = $conn->query($latestVideoSql);
if ($latestVideoResult && $latestVideoResult->num_rows > 0) {
  $latestVideo = $latestVideoResult->fetch_assoc();
}

/* ================= FETCH LATEST REPORTS (ADMIN LOGS) =================
   Rule:
     - Super Admin: see latest logs across ALL admins
     - Admin: see only THEIR OWN latest logs
*/
$latestReports = [];

if ($isSuperAdmin) {
  $latestReportsSql = "
    SELECT id, admin_user_id, admin_username, action_type, action_desc, ip_address, created_at
    FROM admin_action_logs
    ORDER BY created_at DESC
    LIMIT 5
  ";
  $res = $conn->query($latestReportsSql);
  if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc()) $latestReports[] = $r;
  }
} else {
  // ✅ admin: only own logs
  $latestReportsSql = "
    SELECT id, admin_user_id, admin_username, action_type, action_desc, ip_address, created_at
    FROM admin_action_logs
    WHERE admin_user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
  ";
  $stmt = $conn->prepare($latestReportsSql);
  $uid = (int)$currentUser['id'];
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc()) $latestReports[] = $r;
  }
  $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard</title>

  <link rel="icon" type="image/png" href="/../static/eagles.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Merriweather:wght@700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <link rel="stylesheet" href="../admin styles/dashboard.css">
  <link rel="stylesheet" href="../admin styles/sidebar.css">
</head>
<body>

<button class="sidebar-toggle"><i class="fas fa-bars"></i></button>

<?php include 'sidebar.php'; ?>

<div class="main-content">
  <div class="header">
    <h1>Dashboard</h1>

    <!-- optional top right button like your screenshot -->
    <a class="top-pill" href="admin-report">
      <i class="fas fa-file-alt"></i> View Logs
    </a>
  </div>

  <!-- ================= CARDS ================= -->
  <div class="cards">
    <div class="card">
      <i class="fas fa-users"></i>
      <h3>Total Members</h3>
      <p><?= (int)$totalMembers ?></p>
    </div>
    <div class="card">
      <i class="fas fa-map"></i>
      <h3>Total Regions</h3>
      <p><?= (int)$totalRegions ?></p>
    </div>
    <div class="card">
      <i class="fas fa-flag"></i>
      <h3>Total Clubs</h3>
      <p><?= (int)$totalClubs ?></p>
    </div>
  </div>

  <!-- ================= RECENT MEMBERS TABLE ================= -->
  <div class="section">
    <h2>Recently Added Members</h2>
    <table class="recent-members">
      <thead>
        <tr>
          <th>Name</th>
          <th>Region</th>
          <th>Club</th>
          <th>Position</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($recentMembersResult && $recentMembersResult->num_rows > 0): ?>
          <?php while ($row = $recentMembersResult->fetch_assoc()): ?>
            <tr>
              <td><?= h($row['eagles_firstName'] . ' ' . $row['eagles_lastName']) ?></td>
              <td><?= h($row['eagles_region']) ?></td>
              <td><?= h($row['eagles_club']) ?></td>
              <td><?= h($row['eagles_position']) ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="4">No members found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- ================= LATEST NEWS & VIDEOS ================= -->
  <div class="section news-videos">
    <div class="column">
      <div class="news-card">
        <h3>Latest News</h3>

        <?php if ($latestNews): ?>
          <h4><?= h($latestNews['news_title']) ?></h4>
          <p><?= h(mb_strimwidth((string)$latestNews['news_content'], 0, 160, "...")) ?></p>
          <a class="edit-btn" href="news-management">
            <i class="fas fa-pen"></i> Manage News
          </a>
        <?php else: ?>
          <p>No published news available yet.</p>
          <a class="edit-btn" href="news-management">
            <i class="fas fa-plus"></i> Add News
          </a>
        <?php endif; ?>
      </div>
    </div>

    <div class="column">
      <div class="video-card">
        <h3>Latest Video</h3>

        <?php if ($latestVideo): ?>
          <h4><?= h($latestVideo['video_title']) ?></h4>
          <p><?= h(mb_strimwidth((string)$latestVideo['video_description'], 0, 160, "...")) ?></p>
          <a class="edit-btn" href="videos-management">
            <i class="fas fa-pen"></i> Manage Videos
          </a>
        <?php else: ?>
          <p>No published videos available yet.</p>
          <a class="edit-btn" href="videos-management">
            <i class="fas fa-plus"></i> Add Video
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ================= LATEST REPORTS ================= -->
  <div class="section">
    <h2>Latest Reports</h2>

    <div class="reports-card">
      <?php if (!empty($latestReports)): ?>
        <ul class="reports-list">
          <?php foreach ($latestReports as $r): ?>
            <li class="reports-item">
              <div class="reports-top">
                <span class="reports-type t-<?= h(strtolower($r['action_type'])) ?>">
                  <?= h($r['action_type']) ?>
                </span>
                <span class="reports-id">#<?= (int)$r['id'] ?></span>
              </div>

              <div class="reports-msg">
                <?= h(mb_strimwidth((string)$r['action_desc'], 0, 150, "...")) ?>
              </div>

              <div class="reports-meta">
                <span class="reports-by">by <?= h($r['admin_username']) ?></span>
                <span class="reports-time"><?= h(format_when($r['created_at'])) ?></span>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>


      <?php else: ?>
        <p class="reports-empty">No reports/logs found yet.</p>
        <a class="edit-btn reports-btn" href="admin-report">
          <i class="fas fa-file-alt"></i> View Logs
        </a>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
const sidebar = document.querySelector('.sidebar');
const toggleBtn = document.querySelector('.sidebar-toggle');
toggleBtn?.addEventListener('click', () => sidebar?.classList.toggle('show'));
</script>

</body>
</html>