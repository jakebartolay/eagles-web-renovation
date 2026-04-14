<?php
// MUST match the session name used on home.php
session_name('EAGLES_PUBLIC');
session_start();

// Clear session data
$_SESSION = [];

// Destroy session
session_destroy();

// Optional: delete session cookie (recommended)
if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(
    session_name(),
    '',
    time() - 42000,
    $params["path"],
    $params["domain"],
    $params["secure"],
    $params["httponly"]
  );
}

header("Location: index.php");
exit;
