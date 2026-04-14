<?php
/* =========================================================
   PUBLIC SESSION
   - Starts session
   - Tracks logged-in user online (user_sessions)
   - Keeps history but only 1 online session per user
========================================================= */

if (session_status() === PHP_SESSION_NONE) {
  session_name('EAGLES_PUBLIC');
  session_start();
}

require_once __DIR__ . "/../includes/db.php";

/* ---------------------------------------------------------
   Client IP helper
--------------------------------------------------------- */
if (!function_exists('get_client_ip')) {
  function get_client_ip(): ?string {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
      return trim($_SERVER['HTTP_CF_CONNECTING_IP']);
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
      return trim($parts[0]);
    }

    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
      return trim($_SERVER['HTTP_X_REAL_IP']);
    }

    return isset($_SERVER['REMOTE_ADDR']) ? trim($_SERVER['REMOTE_ADDR']) : null;
  }
}

/* ---------------------------------------------------------
   Track user online
   - Keeps history
   - But only 1 session per user stays is_online=1
--------------------------------------------------------- */
function track_user_online(mysqli $conn, int $uid): void {
  if ($uid <= 0) return;

  $sid = session_id();
  $ip  = get_client_ip();
  if ($ip === '::1') $ip = '127.0.0.1';

  $ua  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

  $sql = "
    INSERT INTO user_sessions (session_id, user_id, ip_address, user_agent, last_activity, is_online)
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

  // Mark OTHER sessions of this user as offline (history stays)
  $sql2 = "UPDATE user_sessions SET is_online=0 WHERE user_id=? AND session_id<>?";
  $stmt2 = $conn->prepare($sql2);
  $stmt2->bind_param("is", $uid, $sid);
  $stmt2->execute();
  $stmt2->close();
}

/* ---------------------------------------------------------
   Auto-track logged-in users
--------------------------------------------------------- */
if (!empty($_SESSION['user_id']) && isset($conn) && $conn instanceof mysqli) {
  track_user_online($conn, (int)$_SESSION['user_id']);
}