<?php
// membership.php
require_once __DIR__ . "/../includes/public_session.php";
require_once __DIR__ . "/../includes/db.php";

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// ================= LOGIN HANDLER (USERNAME) =================
$login_error = "";
$login_success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
  $username = trim($_POST['login_username'] ?? "");
  $password = $_POST['login_password'] ?? "";

  if ($username === "" || $password === "") {
    $login_error = "Please enter your username and password.";
  } else {
    try {
      $stmt = $conn->prepare("SELECT id, name, username, password_hash, role_id FROM users WHERE username = ? LIMIT 1");
      $stmt->bind_param("s", $username);
      $stmt->execute();
      $res = $stmt->get_result();

      if ($res && $res->num_rows === 1) {
        $user = $res->fetch_assoc();

        if (password_verify($password, $user['password_hash'])) {
          $_SESSION['user_id'] = (int)$user['id'];
          $_SESSION['user_name'] = $user['name'];
          $_SESSION['username'] = $user['username'];
          $_SESSION['role_id'] = (int)$user['role_id'];

          header("Location: /index.php");
          exit;
        } else {
          $login_error = "Invalid username or password.";
        }
      } else {
        $login_error = "Invalid username or password.";
      }

      $stmt->close();
    } catch (mysqli_sql_exception $e) {
      error_log("Login error: " . $e->getMessage());
      $login_error = "System error. Please try again later.";
    }
  }
}

// ================= SIGNUP HANDLER (WITH TFOEPE ID + USERNAME) =================
$signup_error = "";
$signup_success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'signup') {
  $name = trim($_POST['signup_name'] ?? "");
  $username = trim($_POST['signup_username'] ?? "");
  $eagles_id = strtoupper(trim($_POST['signup_eagles_id'] ?? ""));
  $pass = $_POST['signup_password'] ?? "";
  $pass2 = $_POST['signup_password_confirm'] ?? "";

  if ($name === "" || $username === "" || $eagles_id === "" || $pass === "" || $pass2 === "") {
    $signup_error = "Please complete all fields.";
  } elseif (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $username)) {
    $signup_error = "Username must be 4–20 characters (letters, numbers, underscore).";
  } elseif (!preg_match('/^TFOEPE[0-9]{8}$/', $eagles_id)) {
    $signup_error = "ID is invalid.";
  } elseif ($pass !== $pass2) {
    $signup_error = "Passwords do not match.";
  } elseif (strlen($pass) < 8) {
    $signup_error = "Password must be at least 8 characters.";
  } else {
    try {
      // 1) Ensure Eagles ID exists in user_info
      $stmt0 = $conn->prepare("SELECT eagles_id FROM user_info WHERE eagles_id = ? LIMIT 1");
      $stmt0->bind_param("s", $eagles_id);
      $stmt0->execute();
      $res0 = $stmt0->get_result();

      if (!$res0 || $res0->num_rows !== 1) {
        $signup_error = "Eagles ID not found. Please contact your chapter officer.";
      } else {
        // 2) check if username already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows > 0) {
          $signup_error = "Username is already taken.";
        } else {
          // 3) prevent one Eagles ID being used for multiple accounts
          $stmtX = $conn->prepare("SELECT id FROM users WHERE eagles_id = ? LIMIT 1");
          $stmtX->bind_param("s", $eagles_id);
          $stmtX->execute();
          $resX = $stmtX->get_result();

          if ($resX && $resX->num_rows > 0) {
            $signup_error = "This Eagles ID is already linked to an account.";
          } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $role_id = 4; // default user

            $stmt2 = $conn->prepare(
              "INSERT INTO users (name, username, eagles_id, password_hash, role_id)
               VALUES (?, ?, ?, ?, ?)"
            );
            $stmt2->bind_param("ssssi", $name, $username, $eagles_id, $hash, $role_id);
            $stmt2->execute();
            $stmt2->close();

            $signup_success = "Account created. You can sign in now.";
          }

          $stmtX->close();
        }

        $stmt->close();
      }

      $stmt0->close();
    } catch (mysqli_sql_exception $e) {
      error_log("Signup error: " . $e->getMessage());
      $signup_error = "System error. Please try again later.";
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ang Agila | Membership Guide</title>

<link rel="icon" type="image/png" href="/../static/eagles.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Merriweather:wght@700;900&display=swap" rel="stylesheet">

<link rel="stylesheet" href="/../Styles/member.css">
<link rel="stylesheet" href="/../Styles/navbar.css">
<link rel="stylesheet" href="/../Styles/footer.css">

</head>

<body>

  <!-- NAVBAR -->
<?php include __DIR__ . '/../includes/navbar.php'; ?>

  <!-- HEADER -->
  <section class="page-header">
    <h1>Membership Guide</h1>
    <p>
      This membership guide will help you understand who we are, what we stand for,
      and how you can become part of a strong brotherhood dedicated to service, unity, and integrity.
    </p>
  </section>

  <!-- MAIN WRAPPER -->
  <div class="container-guide layout-modern">

    <!-- LEFT CONTENT -->
    <div class="guide-left">
      <!-- WHO CAN JOIN -->
      <div class="guide-section">
        <h2><i class="fa-solid fa-user-check"></i> Who Can Join?</h2>
        <p>
          Membership is open to individuals who are willing to follow the values and mission of the Eagles.
          Applicants must demonstrate good moral character and commitment to helping the community.
        </p>
        <ul class="list">
          <li>Respectful and responsible individuals</li>
          <li>Willing to participate in events and community service</li>
          <li>Committed to the Four Pillars of the Eagles</li>
          <li>Ready to support the brotherhood and uphold discipline</li>
        </ul>
        <div class="note">
          <strong>Note:</strong> Each local chapter may have specific requirements such as age, student status, and approval process.
        </div>
      </div>

      <!-- BENEFITS -->
      <div class="guide-section">
        <h2><i class="fa-solid fa-handshake-angle"></i> Benefits of Becoming an Eagle</h2>
        <p>Joining Ang Agila means being part of a respected organization that supports personal growth and community impact.</p>
        <div class="grid">
          <div class="card"><h3><i class="fa-solid fa-people-group"></i> Brotherhood</h3><p>Build strong bonds, lifelong friendships, and teamwork through activities and gatherings.</p></div>
          <div class="card"><h3><i class="fa-solid fa-heart"></i> Charity & Service</h3><p>Join outreach programs, donation drives, and volunteer work to help communities.</p></div>
          <div class="card"><h3><i class="fa-solid fa-star"></i> Leadership</h3><p>Develop leadership and responsibility through training, committees, and chapter roles.</p></div>
          <div class="card"><h3><i class="fa-solid fa-shield-halved"></i> Discipline</h3><p>Learn values of integrity, respect, and accountability as part of the organization.</p></div>
        </div>
      </div>

      <!-- RESPONSIBILITIES -->
      <div class="guide-section">
        <h2><i class="fa-solid fa-scale-balanced"></i> Responsibilities of a Member</h2>
        <p>Membership comes with responsibilities that protect the image of the organization and strengthen the brotherhood.</p>
        <ul class="list">
          <li>Follow the Eagles Code of Conduct and chapter rules</li>
          <li>Participate in meetings, events, and service activities</li>
          <li>Show respect to officers, members, and guests</li>
          <li>Promote peace, unity, and discipline in all situations</li>
          <li>Help represent Ang Agila with pride and professionalism</li>
        </ul>
      </div>

      <!-- HOW TO JOIN -->
      <div class="guide-section">
        <h2><i class="fa-solid fa-clipboard-list"></i> How to Join (Step-by-Step)</h2>
        <p>Here’s a simple guide to becoming an official member:</p>
        <ol class="steps">
          <li><strong>Get a referral</strong> from an existing member or chapter officer.</li>
          <li><strong>Fill out the membership form</strong> with your personal information and contact details.</li>
          <li><strong>Attend a short orientation</strong> to learn the values, mission, and rules of the Eagles.</li>
          <li><strong>Interview & approval</strong> — the chapter will review and approve your application.</li>
          <li><strong>Pay membership dues</strong> (if required by the chapter).</li>
          <li><strong>Induction</strong> — once approved, you’ll receive your status as an official Eagle member.</li>
        </ol>
        <div class="note">
          <strong>Reminder:</strong> Membership is not just a title — it’s a commitment to serve, grow, and represent the organization honorably.
        </div>
      </div>

      <!-- WHAT TO EXPECT -->
      <div class="guide-section">
        <h2><i class="fa-solid fa-calendar-check"></i> What to Expect After Joining</h2>
        <p>After becoming a member, you will experience:</p>
        <ul class="list">
          <li>Regular chapter meetings and planning sessions</li>
          <li>Training and leadership development activities</li>
          <li>Community outreach and charity missions</li>
          <li>Brotherhood bonding events and gatherings</li>
          <li>Opportunities to earn recognition and roles</li>
        </ul>
      </div>
    </div>

    <!-- RIGHT PANEL (VERIFY ONLY) -->
    <aside class="guide-right">
      <div class="verify-card" id="verifyCard">
        <h2><i class="fa-solid fa-magnifying-glass"></i> Verify Membership</h2>
        <p>Enter your Membership ID to check if you are a registered Eagle member.</p>

        <form method="POST" class="modern-search" id="verifyForm">
          <input type="hidden" name="action" value="verify">
          <input
            type="text"
            name="search_id"
            id="verifyInput"
            required
            autocomplete="off"
          >
          <button type="submit" id="verifyBtn">Verify</button>
        </form>
      </div>
    </aside>

  </div>

  <!-- VERIFY MODAL -->
  <div id="verifyModal" class="verify-modal">
    <div class="verify-modal-card">
      <button class="verify-close" id="closeVerifyModal"><i class="fa-solid fa-xmark"></i></button>
      <div id="verifyModalContent"></div>
    </div>
  </div>

  <!-- LOGIN / SIGNUP MODAL (ONLY ONE) -->
  <div id="loginModal" class="login-modal">
    <div class="login-modal-card">

      <button class="login-close" id="closeLoginModal">
        <i class="fa-solid fa-xmark"></i>
      </button>

      <div class="login-tabs">
        <button type="button" class="login-tab active" data-tab="signin">Sign In</button>
        <button type="button" class="login-tab" data-tab="signup">Create Account</button>
      </div>

      <!-- SIGN IN -->
      <div class="login-panel active" id="signin">
        <h2 class="login-title">Welcome Back</h2>
        <p class="login-sub">Sign in to continue.</p>

        <form method="POST" class="login-form" autocomplete="on">
          <input type="hidden" name="action" value="login">

          <label>Username</label>
          <input type="text" name="login_username" required placeholder="e.g JDcruz27">

          <label>Password</label>
          <div class="password-wrap">
            <input
              type="password"
              name="login_password"
              id="loginPassword"
              required
              placeholder="Enter your password"
            >
            <button type="button" class="toggle-pass" id="toggleLoginPass" aria-label="Toggle password">
              <i class="fa-solid fa-eye"></i>
            </button>
          </div>

          <button type="submit" class="login-submit">Sign In</button>

          <?php if (!empty($login_error)): ?>
            <div class="login-alert error"><?= htmlspecialchars($login_error) ?></div>
          <?php endif; ?>
        </form>
      </div>

      <!-- SIGN UP -->
      <div class="login-panel" id="signup">
        <h2 class="login-title">Create Account</h2>
        <p class="login-sub">Join the Eagles portal.</p>

        <form method="POST" class="login-form" autocomplete="on">
          <input type="hidden" name="action" value="signup">

          <label>Full Name</label>
          <input type="text" name="signup_name" required placeholder="e.g Juan Dela Cruz">

          <label>Username</label>
          <input type="text" name="signup_username" required placeholder="e.g JDcruz27">

          <label>Eagles ID</label>
          <input
            type="text"
            name="signup_eagles_id"
            required
            autocomplete="off"
          />

          <label>Password</label>
          <input type="password" name="signup_password" required placeholder="Minimum 8 characters">

          <label>Confirm Password</label>
          <input type="password" name="signup_password_confirm" required placeholder="Re-enter your password">

          <button type="submit" class="login-submit">Create Account</button>

          <?php if (!empty($signup_error)): ?>
            <div class="login-alert error"><?= htmlspecialchars($signup_error) ?></div>
          <?php endif; ?>

          <?php if (!empty($signup_success)): ?>
            <div class="login-alert success"><?= htmlspecialchars($signup_success) ?></div>
          <?php endif; ?>
        </form>
      </div>

    </div>
  </div>

  <!-- FOOTER -->
<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
// ================= VERIFY MEMBERSHIP =================
// Server-side protection: only allow verification if logged in
if (
  $_SERVER['REQUEST_METHOD'] === 'POST'
  && ($_POST['action'] ?? '') === 'verify'
  && isset($_POST['search_id'])
  && !empty($_SESSION['user_id'])
) {
  $search_id = strtoupper(trim($_POST['search_id']));

  // If empty OR format wrong => "ID is invalid."
  if ($search_id === "" || !preg_match('/^TFOEPE[0-9]{8}$/', $search_id)) {
    $modalHTML = "
      <div class='verify-modal-title'>ID is invalid</div>
      <div class='verify-modal-sub'>Please enter a valid Membership ID.</div>
    ";
  } else {
    try {
      $stmt = $conn->prepare("
        SELECT 
          eagles_id,
          eagles_status,
          eagles_firstName,
          eagles_lastName,
          eagles_position,
          eagles_club,
          eagles_region,
          eagles_pic
        FROM user_info
        WHERE eagles_id = ?
        LIMIT 1
      ");
      $stmt->bind_param("s", $search_id);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $pic = 'uploads/' . basename((string)$row['eagles_pic']);

        $rawStatus = strtolower(trim((string)$row['eagles_status']));
        // Normalize "for renewal" variants
        $isActive  = ($rawStatus === 'active');
        $isRenewal = in_array($rawStatus, ['for renewal', 'renewal', 'renew', 'for_renewal', 'for-renewal'], true);

        // Status pill
        if ($isActive) {
          $statusLabel = "ACTIVE";
          $statusClass = "status-active";
          $title = "Member Verified";
          $sub   = "This member is verified and currently active.";
        } elseif ($isRenewal) {
          $statusLabel = "FOR RENEWAL";
          $statusClass = "status-renewal";
          $title = "Member Verified (For Renewal)";
          $sub   = "This member is verified but currently marked for renewal.";
        } else {
          $statusLabel = strtoupper($rawStatus ?: "UNKNOWN");
          $statusClass = "status-other";
          $title = "Member Verified";
          $sub   = "This member is a verified record, but the status is not active.";
        }

        // Stamp only for ACTIVE
        $stampHTML = $isActive
          ? "<img src='static/certify.png' class='id-stamp-img' alt='Certified'>"
          : "";

        $modalHTML = "
          <div class='verify-modal-title'>{$title}</div>
          <div class='verify-modal-sub'>{$sub}</div>

          <div class='status-pill {$statusClass}'>{$statusLabel}</div>

          <div class='id-card'>
            <img src='static/id_template.png' class='id-bg' alt='ID Template'>
            <div class='id-number'>" . htmlspecialchars((string)$row['eagles_id']) . "</div>
            <div class='id-last'>" . htmlspecialchars((string)$row['eagles_lastName']) . "</div>
            <div class='id-first'>" . htmlspecialchars((string)$row['eagles_firstName']) . "</div>
            <img src='" . htmlspecialchars($pic) . "' class='id-photo' alt='Member Photo'>

            <div class='id-info'>
              <div class='id-club'>" . htmlspecialchars((string)$row['eagles_club']) . "</div>
              <div class='id-position'>" . htmlspecialchars((string)$row['eagles_position']) . "</div>
              <div class='id-region'>" . htmlspecialchars((string)$row['eagles_region']) . "</div>
            </div>

            {$stampHTML}
          </div>
        ";
      } else {
        $modalHTML = "
          <div class='verify-modal-title'>ID Not Found</div>
          <div class='verify-modal-sub'>No matching record was found. Please double-check the ID.</div>
        ";
      }

      $stmt->close();
    } catch (mysqli_sql_exception $e) {
      error_log('Membership verification error: ' . $e->getMessage());
      $modalHTML = "
        <div class='verify-modal-title'>System Error</div>
        <div class='verify-modal-sub'>Unable to verify membership at this time.</div>
      ";
    }
  }

  echo "<script>
    document.addEventListener('DOMContentLoaded', function () {
      const modal = document.getElementById('verifyModal');
      const modalContent = document.getElementById('verifyModalContent');
      const closeBtn = document.getElementById('closeVerifyModal');

      modalContent.innerHTML = `" . addslashes($modalHTML) . "`;
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';

      closeBtn.onclick = () => {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
      };

      modal.onclick = (e) => {
        if (e.target === modal) {
          modal.classList.remove('active');
          document.body.style.overflow = 'auto';
        }
      };

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          modal.classList.remove('active');
          document.body.style.overflow = 'auto';
        }
      });
    });
  </script>";
}
?>

<!-- Blur Overlay -->
<style>
  body.auth-locked { overflow: hidden; }
  #authBlocker {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.35);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    opacity: 0;
    pointer-events: none;
    transition: opacity .18s ease;
    z-index: 9998;
  }
  #authBlocker.active { opacity: 1; pointer-events: auto; }
</style>
<div id="authBlocker" aria-hidden="true"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const loginModal = document.getElementById('loginModal');
  const closeLogin = document.getElementById('closeLoginModal');

  const togglePass = document.getElementById('toggleLoginPass');
  const passInput = document.getElementById('loginPassword');

  // Toggle password
  if (togglePass && passInput) {
    togglePass.addEventListener('click', () => {
      const isPass = passInput.type === 'password';
      passInput.type = isPass ? 'text' : 'password';
      togglePass.innerHTML = isPass
        ? '<i class="fa-solid fa-eye-slash"></i>'
        : '<i class="fa-solid fa-eye"></i>';
    });
  }

  // Tabs
  const tabs = document.querySelectorAll('.login-tab');
  const panels = document.querySelectorAll('.login-panel');

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      panels.forEach(p => p.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById(tab.dataset.tab).classList.add('active');
    });
  });

  // Auto-open modal if login/signup error/success
  const hasLoginError = <?= json_encode(!empty($login_error)) ?>;
  const hasSignupError = <?= json_encode(!empty($signup_error) || !empty($signup_success)) ?>;

  if ((hasLoginError || hasSignupError) && loginModal) {
    loginModal.classList.add('active');
    document.body.style.overflow = 'hidden';

    // If signup error/success, switch to signup tab
    if (hasSignupError) {
      tabs.forEach(t => t.classList.remove('active'));
      panels.forEach(p => p.classList.remove('active'));
      document.querySelector('.login-tab[data-tab="signup"]').classList.add('active');
      document.getElementById('signup').classList.add('active');
    }
  }

  // Close button
  if (closeLogin && loginModal) {
    closeLogin.addEventListener('click', () => {
      loginModal.classList.remove('active');
      document.body.style.overflow = 'auto';
    });
  }

  // Click outside closes
  if (loginModal) {
    loginModal.addEventListener('click', (e) => {
      if (e.target === loginModal) {
        loginModal.classList.remove('active');
        document.body.style.overflow = 'auto';
      }
    });
  }

  // ESC closes
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && loginModal) {
      loginModal.classList.remove('active');
      document.body.style.overflow = 'auto';
    }
  });

  // ================= VERIFY GATE (BLUR + OPEN LOGIN) =================
  const isLoggedIn = <?= json_encode(!empty($_SESSION['user_id'])) ?>;
  const authBlocker = document.getElementById('authBlocker');

  const verifyForm = document.getElementById('verifyForm');
  const verifyInput = document.getElementById('verifyInput');
  const verifyBtn = document.getElementById('verifyBtn');

  function openAuthGate() {
    if (!loginModal) return;

    if (authBlocker) authBlocker.classList.add('active');

    loginModal.classList.add('active');

    document.body.classList.add('auth-locked');
    document.body.style.overflow = 'hidden';

    // force Sign In tab
    const signInTab = document.querySelector('.login-tab[data-tab="signin"]');
    const signInPanel = document.getElementById('signin');
    if (signInTab && signInPanel) {
      tabs.forEach(t => t.classList.remove('active'));
      panels.forEach(p => p.classList.remove('active'));
      signInTab.classList.add('active');
      signInPanel.classList.add('active');
    }
  }

  function removeAuthGateIfClosed() {
    if (!loginModal.classList.contains('active')) {
      if (authBlocker) authBlocker.classList.remove('active');
      document.body.classList.remove('auth-locked');
      document.body.style.overflow = 'auto';
    }
  }

  if (!isLoggedIn) {
    const blockEvent = (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (verifyInput) verifyInput.blur();
      openAuthGate();
    };

    if (verifyInput) {
      verifyInput.addEventListener('focus', blockEvent);
      verifyInput.addEventListener('mousedown', blockEvent);
      verifyInput.addEventListener('keydown', blockEvent);
      verifyInput.addEventListener('touchstart', blockEvent, { passive: false });
    }

    if (verifyBtn) {
      verifyBtn.addEventListener('click', blockEvent);
      verifyBtn.addEventListener('mousedown', blockEvent);
      verifyBtn.addEventListener('touchstart', blockEvent, { passive: false });
    }

    if (verifyForm) {
      verifyForm.addEventListener('submit', blockEvent);
    }

    // remove blur when modal closes
    if (closeLogin) closeLogin.addEventListener('click', () => setTimeout(removeAuthGateIfClosed, 0));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setTimeout(removeAuthGateIfClosed, 0); });
    if (loginModal) loginModal.addEventListener('click', (e) => {
      if (e.target === loginModal) setTimeout(removeAuthGateIfClosed, 0);
    });
  }
});
</script>

</body>
</html>