<?php
session_name('EAGLES_ADMIN');
session_start();

/* clear session */
$_SESSION = [];
session_destroy();

/* delete cookie */
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

/* START NEW SESSION FOR LOGOUT MESSAGE */
session_start();
$_SESSION['success'] = "You’ve been logged out successfully.";

header("Location: admin-login");
exit;