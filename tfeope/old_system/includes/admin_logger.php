<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function admin_session_user_id(): int {
  if (isset($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
  if (isset($_SESSION['id'])) return (int)$_SESSION['id'];
  return 0;
}

function log_admin_action(mysqli $conn, string $type, string $desc): void {
  $uid = admin_session_user_id();
  if ($uid <= 0) return;

  // get username + role
  $stmt = $conn->prepare("SELECT username, role_id FROM users WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $u = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$u) return;

  // only log admins (super_admin=1, admin=2)
  if (!in_array((int)$u['role_id'], [1,2], true)) return;

  $username = $u['username'];
  $ip = $_SERVER['REMOTE_ADDR'] ?? null;

  $ins = $conn->prepare("
    INSERT INTO admin_action_logs (admin_user_id, admin_username, action_type, action_desc, ip_address)
    VALUES (?, ?, ?, ?, ?)
  ");
  $ins->bind_param("issss", $uid, $username, $type, $desc, $ip);
  $ins->execute();
  $ins->close();
}
