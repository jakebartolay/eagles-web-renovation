<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

/* ===============================
   NOTIFICATION HANDLER
================================ */
$notifType = null;
$notifMsg  = null;

if (isset($_SESSION['success'])) {
    $notifType = 'success';
    $notifMsg  = $_SESSION['success'];
    unset($_SESSION['success']);
} elseif (isset($_SESSION['error'])) {
    $notifType = 'error';
    $notifMsg  = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login</title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Merriweather:wght@700&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="/../static/eagles.png">
<link rel="stylesheet" href="../admin styles/login.css">

<!-- TOP RIGHT NOTIFICATION STYLE -->
<style>
.notification {
    position: fixed;
    top: 20px;
    right: -400px;
    width: 340px;
    background: #e74c3c;
    color: #fff;
    border-radius: 10px;
    padding: 16px 18px 22px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    z-index: 9999;
    transition: right 0.5s ease;
    overflow: hidden;
    font-family: Inter, sans-serif;
}
.notification.show {
    right: 20px;
}
.notification .title {
    font-weight: 600;
    font-size: 15px;
}
.notification .close-btn {
    position: absolute;
    top: 10px;
    right: 12px;
    cursor: pointer;
    font-size: 18px;
    opacity: 0.9;
}
.notification .close-btn:hover {
    opacity: 1;
}
.notification .progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 4px;
    background: rgba(255,255,255,0.9);
    width: 100%;
}
.notification.success {
    background: #2ecc71;
}
</style>
</head>
<body>

<!-- NOTIFICATION -->
<?php if ($notifMsg): ?>
<div id="notification" class="notification <?= $notifType ?>">
    <span class="close-btn" onclick="closeNotif()">×</span>
    <div class="title"><?= htmlspecialchars($notifMsg) ?></div>
    <div class="progress" id="progressBar"></div>
</div>
<?php endif; ?>

<div class="login-container">
    <img src="/../static/eagles.png" alt="Eagles Logo">
    <h1>Admin Login</h1>

    <form method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="login">Login</button>
    </form>
</div>

<?php
/* ===============================
   LOGIN LOGIC
================================ */
if (isset($_POST['login'])) {

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();

        if (password_verify($password, $row['password_hash'])) {

            if (in_array($row['role_id'], [1, 2])) {

                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username']  = $row['username'];
                $_SESSION['role_id']         = $row['role_id'];
                $_SESSION['id']              = $row['id'];

                header("Location: admin-dashboard");
                exit;

            } else {
                $_SESSION['error'] = "You do not have admin privileges!";
            }

        } else {
            $_SESSION['error'] = "Incorrect password!";
        }
    } else {
        $_SESSION['error'] = "Username not found!";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>

<!-- NOTIFICATION SCRIPT -->
<script>

window.addEventListener('load', () => {
  document.body.classList.add('loaded');
});

const notif = document.getElementById("notification");
const progress = document.getElementById("progressBar");

if (notif && progress) {
    notif.classList.add("show");

    let width = 100;
    const duration = 3000;
    const interval = 30;
    const decrement = 100 / (duration / interval);

    const timer = setInterval(() => {
        width -= decrement;
        progress.style.width = width + "%";
        if (width <= 0) {
            clearInterval(timer);
            closeNotif();
        }
    }, interval);
}

function closeNotif() {
    if (notif) {
        notif.style.right = "-400px";
    }
}
</script>

</body>
</html>