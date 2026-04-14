    <?php
// includes/admin_track.php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . "/../includes/db.php";
if (!isset($conn) || !($conn instanceof mysqli)) {
  return;
}

/**
 * IMPORTANT:
 * Adjust this to match YOUR session key.
 * In your project, you likely store the logged-in user in:
 *   $_SESSION['user_id']  (recommended)
 * or maybe:
 *   $_SESSION['id']
 *
 * Pick the one you actually use.
 */
$uid = null;
if (isset($_SESSION['user_id'])) $uid = (int)$_SESSION['user_id'];
elseif (isset($_SESSION['id'])) $uid = (int)$_SESSION['id'];

if (!$uid || $uid <= 0) return;

/* Only track admins (role_id 1 super_admin, role_id 2 admin) */
$uStmt = $conn->prepare("SELECT id, username, role_id FROM users WHERE id=? LIMIT 1");
$uStmt->bind_param("i", $uid);
$uStmt->execute();
$userRow = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

if (!$userRow) return;

$roleId = (int)$userRow['role_id'];
if (!in_array($roleId, [1,2], true)) {
  return; // not an admin
}

$sessionId = session_id();
$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

/* UPSERT session row */
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
$sStmt = $conn->prepare($sql);
$sStmt->bind_param("siss", $sessionId, $uid, $ip, $ua);
$sStmt->execute();
$sStmt->close();
