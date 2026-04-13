<?php
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/public_session.php";

/* =========================================================
   HELPERS
========================================================= */
function h($v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function is_ajax(): bool {
  return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}
function json_out(array $data, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data);
  exit;
}

/* =========================================================
   AJAX LOGIN (NO PAGE REFRESH)
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login' && is_ajax()) {
  $username = trim($_POST['login_username'] ?? "");
  $password = $_POST['login_password'] ?? "";

  if ($username === "" || $password === "") {
    json_out(['ok' => false, 'message' => 'Please enter your username and password.'], 400);
  }

  try {
    $stmt = $conn->prepare("SELECT id, name, username, password_hash, role_id FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    if ($res && $res->num_rows === 1) {
      $user = $res->fetch_assoc();

      if (password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);

        $_SESSION['user_id']   = (int)$user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['role_id']   = (int)$user['role_id'];

        json_out(['ok' => true, 'redirect' => 'membership.php']);
      }
    }

    json_out(['ok' => false, 'message' => 'Invalid username or password.'], 401);
  } catch (mysqli_sql_exception $e) {
    error_log("Login AJAX error: " . $e->getMessage());
    json_out(['ok' => false, 'message' => 'System error. Please try again later.'], 500);
  }
}

/* =========================================================
   AJAX SIGNUP (NO PAGE REFRESH)
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'signup' && is_ajax()) {
  $name      = trim($_POST['signup_name'] ?? "");
  $username  = trim($_POST['signup_username'] ?? "");
  $eagles_id = strtoupper(trim($_POST['signup_eagles_id'] ?? ""));
  $pass      = $_POST['signup_password'] ?? "";
  $pass2     = $_POST['signup_password_confirm'] ?? "";

  if ($name === "" || $username === "" || $eagles_id === "" || $pass === "" || $pass2 === "") {
    json_out(['ok' => false, 'message' => 'Please complete all fields.'], 400);
  }
  if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $username)) {
    json_out(['ok' => false, 'message' => 'Username must be 4–20 characters (letters, numbers, underscore).'], 400);
  }
  if (!preg_match('/^TFOEPE[0-9]{8}$/', $eagles_id)) {
    json_out(['ok' => false, 'message' => 'ID is invalid.'], 400);
  }
  if ($pass !== $pass2) {
    json_out(['ok' => false, 'message' => 'Passwords do not match.'], 400);
  }
  if (strlen($pass) < 8) {
    json_out(['ok' => false, 'message' => 'Password must be at least 8 characters.'], 400);
  }

  try {
    // 1) Eagles ID must exist in user_info
    $stmt0 = $conn->prepare("SELECT eagles_id FROM user_info WHERE eagles_id = ? LIMIT 1");
    $stmt0->bind_param("s", $eagles_id);
    $stmt0->execute();
    $res0 = $stmt0->get_result();
    $stmt0->close();

    if (!$res0 || $res0->num_rows !== 1) {
      json_out(['ok' => false, 'message' => 'Eagles ID not found. Please contact your chapter officer.'], 400);
    }

    // 2) Username must be unique
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    if ($res && $res->num_rows > 0) {
      json_out(['ok' => false, 'message' => 'Username is already taken.'], 400);
    }

    // 3) Eagles ID must not already be linked
    $stmtX = $conn->prepare("SELECT id FROM users WHERE eagles_id = ? LIMIT 1");
    $stmtX->bind_param("s", $eagles_id);
    $stmtX->execute();
    $resX = $stmtX->get_result();
    $stmtX->close();

    if ($resX && $resX->num_rows > 0) {
      json_out(['ok' => false, 'message' => 'This Eagles ID is already linked to an account.'], 400);
    }

    // Insert account
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $role_id = 4;

    $stmt2 = $conn->prepare(
      "INSERT INTO users (name, username, eagles_id, password_hash, role_id)
       VALUES (?, ?, ?, ?, ?)"
    );
    $stmt2->bind_param("ssssi", $name, $username, $eagles_id, $hash, $role_id);
    $stmt2->execute();
    $stmt2->close();

    json_out(['ok' => true, 'message' => 'Account created. You can sign in now.']);
  } catch (mysqli_sql_exception $e) {
    error_log("Signup AJAX error: " . $e->getMessage());
    json_out(['ok' => false, 'message' => 'System error. Please try again later.'], 500);
  }
}

/* =========================================================
   AJAX VERIFY MEMBERSHIP (NO PAGE REFRESH) - logged in only
   ✅ UPDATED: Active = green, Renewal = orange (badge + accent)
========================================================= */
if (
  $_SERVER['REQUEST_METHOD'] === 'POST'
  && ($_POST['action'] ?? '') === 'verify'
  && is_ajax()
  && !empty($_SESSION['user_id'])
) {
  $search_id = strtoupper(trim($_POST['search_id'] ?? ''));

  if ($search_id === "" || !preg_match('/^TFOEPE[0-9]{8}$/', $search_id)) {
    json_out([
      'ok' => false,
      'html' => "
        <div class='verify-modal-title'>ID is invalid</div>
        <div class='verify-modal-sub'>Please enter a valid Membership ID.</div>
      "
    ], 400);
  }

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

      // normalize status
      $rawStatus = strtolower(trim((string)($row['eagles_status'] ?? '')));
      $normStatus = preg_replace('/[^a-z]+/', '', $rawStatus); // e.g. "for renewal" -> "forrenewal"

      $isActive  = ($normStatus === 'active');
      $isRenewal = in_array($normStatus, ['forrenewal','renewal','renew'], true);

      $statusClass = $isActive ? 'status-active' : ($isRenewal ? 'status-renewal' : 'status-unknown');
      $statusText  = $isActive ? 'ACTIVE' : ($isRenewal ? 'FOR RENEWAL' : strtoupper($rawStatus ?: 'UNKNOWN'));

      // optional stamp only for active (keep your old behavior)
      $stampHTML = $isActive
        ? "<img src='static/certified.png' class='id-stamp-img' alt='Certified'>"
        : "";

      $html = "
        <div class='verify-modal-title'>Member Verified</div>
        <div class='verify-modal-sub'>This member is a verified member of the brotherhood.</div>

        <div class='verify-status-row'>
          <span class='verify-status-pill {$statusClass}'>" . h($statusText) . "</span>
        </div>

        <div class='id-shell {$statusClass}'>
          <div class='id-card'>
            <img src='static/id_template.png' class='id-bg' alt='ID Template'>

            <div class='id-number'>" . h($row['eagles_id']) . "</div>
            <div class='id-last'>" . h($row['eagles_lastName']) . "</div>
            <div class='id-first'>" . h($row['eagles_firstName']) . "</div>

            <img src='" . h($pic) . "' class='id-photo' alt='Member Photo' loading='lazy'>

            <div class='id-info'>
              <div class='id-club'>" . h($row['eagles_club']) . "</div>
              <div class='id-position'>" . h($row['eagles_position']) . "</div>
              <div class='id-region'>" . h($row['eagles_region']) . "</div>
            </div>

            {$stampHTML}
          </div>
        </div>
      ";

      $stmt->close();
      json_out(['ok' => true, 'html' => $html], 200);
    }

    $stmt->close();
    json_out([
      'ok' => false,
      'html' => "
        <div class='verify-modal-title'>ID Not Found</div>
        <div class='verify-modal-sub'>No matching record was found. Please double-check the ID.</div>
      "
    ], 404);
  } catch (mysqli_sql_exception $e) {
    error_log("Membership verification error: " . $e->getMessage());
    json_out([
      'ok' => false,
      'html' => "
        <div class='verify-modal-title'>System Error</div>
        <div class='verify-modal-sub'>Unable to verify membership at this time.</div>
      "
    ], 500);
  }
}

/* =========================================================
   HOME PAGE DATA
========================================================= */
$memo_result = $conn->query("
  SELECT m.memo_id, m.memo_title, m.memo_description
  FROM memorandum m
  WHERE m.memo_status='Published'
  ORDER BY m.memo_id DESC
  LIMIT 12
");

$pages = [];
$memos = [];
while ($memo = $memo_result->fetch_assoc()) {
  $memo_id = (int)$memo['memo_id'];
  $page_res = $conn->query("
    SELECT page_image
    FROM memorandum_pages
    WHERE memo_id = $memo_id
    ORDER BY page_number ASC
  ");
  while ($p = $page_res->fetch_assoc()) {
    $pages[$memo_id][] = $p['page_image'];
  }
  $memos[] = $memo;
}

$latest_news_result = $conn->query("
  SELECT * FROM news_info
  WHERE news_status='Published'
  ORDER BY news_id DESC
  LIMIT 1
");
$latest_news = $latest_news_result->fetch_assoc();

$events_result = $conn->query("
  SELECT event_id, event_title, event_description, event_date, event_type, event_media
  FROM events
  WHERE event_date >= CURDATE()
  ORDER BY event_date ASC
  LIMIT 5
");
$events = [];
while ($event = $events_result->fetch_assoc()) {
  $events[] = $event;
}

$isLoggedIn = !empty($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ang Agila | The Fraternal Order of Eagles</title>

<link rel="icon" type="image/png" href="/static/eagles.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Merriweather:wght@700;900&display=swap" rel="stylesheet">

<link rel="stylesheet" href="/Styles/navbar.css">
<link rel="stylesheet" href="/Styles/home.css?v=15">
<link rel="stylesheet" href="/Styles/footer.css">
<link rel="stylesheet" href="/Styles/home_verify_floating.css?v=2">
</head>
<body>

<!-- Splash Screen -->
<div id="splash">
  <div class="splash-inner">
    <img src="static/logo.png" alt="Logo">
    <h1>Fraternal Order of Eagles</h1>
    <p>Service Through Strong Brotherhood</p>
  </div>
</div>

<?php include __DIR__ . "/includes/navbar.php"; ?>

<section class="hero">
  <div class="hero-overlay"></div>
  <img src="static/homebg.jpg" alt="Hero Image">
</section>

<!-- ================= NEWS + MEMORANDUMS (SIDE-BY-SIDE) ================= -->
<section class="news-memos" id="newsMemos">
  <div class="news-memos-grid">

    <!-- LEFT: LATEST NEWS (WITH LOADER) -->
    <div class="nm-panel nm-news section-loading" id="secNews">
      <div class="section-loader" id="loaderNews" aria-label="Loading latest news" role="status">
        <div class="loader-spinner"></div>
        <div class="loader-text">Loading latest news...</div>
      </div>

      <div class="section-content" id="contentNews">
        <h2>Latest News</h2>

        <?php if ($latest_news): ?>
          <div class="featured-news-card">
            <div class="featured-news-image">
              <img
                src="news_images/<?= h($latest_news['news_image']) ?>"
                alt="<?= h($latest_news['news_title']) ?>"
                loading="eager"
                fetchpriority="high"
              >
            </div>
            <div class="featured-news-content">
              <span class="news-badge">Featured</span>
              <h3><?= h($latest_news['news_title']) ?></h3>
              <p><?= h(substr($latest_news['news_content'],0,150)) ?>...</p>
              <a href="news" class="btn-primary">Read More</a>
            </div>
          </div>
        <?php else: ?>
          <p>No news available at the moment.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- RIGHT: MEMORANDUMS (WITH LOADER) -->
    <div class="nm-panel nm-memos section-loading" id="secMemos">
      <div class="section-loader" id="loaderMemos" aria-label="Loading memorandums" role="status">
        <div class="loader-spinner"></div>
        <div class="loader-text">Loading memorandums...</div>
      </div>

      <div class="section-content" id="contentMemos">
        <h2>Memorandums</h2>

        <div class="memo-container memo-scroller" id="memoScroller">
          <?php if (!empty($memos)): ?>
            <?php $memo_i = 0; ?>
            <?php foreach ($memos as $memo): ?>
              <?php
                $memo_i++;
                $firstImg = $pages[(int)$memo['memo_id']][0] ?? 'default_memo.png';
                $isEager = ($memo_i <= 2);
              ?>
              <div class="memo-card" onclick="openLightbox(<?= (int)$memo['memo_id'] ?>)">
                <img
                  src="memorandum/<?= h($firstImg) ?>"
                  alt="<?= h($memo['memo_title']) ?>"
                  loading="<?= $isEager ? 'eager' : 'lazy' ?>"
                  <?= $isEager ? 'fetchpriority="high"' : '' ?>
                >
                <h3><?= h($memo['memo_title']) ?></h3>
                <p><?= h($memo['memo_description']) ?></p>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p>No memorandums available.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</section>

<!-- ================= HYMNALS & PRAYER (MOVED HERE) ================= -->
<section class="hymnals-section" id="hymnals">
  <div class="hymnals-wrap">

    <div class="hymnals-head">
      <h2 class="hymnals-title">Eagles Hymnals and Prayer</h2>
      <p class="hymnals-sub">
        Sacred songs and prayers that embody faith, patriotism, and brotherhood.
      </p>
    </div>

    <div class="hymnals-grid">

      <article class="hymnal-card">
        <div class="video-frame js-video-open" data-src="static/eagles prayer.mp4" role="button" tabindex="0" aria-label="Play Eagles Prayer">
          <video playsinline preload="metadata" muted oncontextmenu="return false">
            <source src="static/eagles prayer.mp4" type="video/mp4">
          </video>
          <div class="video-play-badge" aria-hidden="true">
            <i class="fa-solid fa-play"></i>
          </div>
        </div>

        <div class="hymnal-content">
          <h3>Eagles Prayer</h3>
          <p>A prayer reflecting faith, unity, and service.</p>
        </div>
      </article>

      <article class="hymnal-card">
        <div class="video-frame js-video-open" data-src="static/national_anthem.mp4" role="button" tabindex="0" aria-label="Play National Anthem">
          <video playsinline preload="metadata" muted oncontextmenu="return false">
            <source src="static/national_anthem.mp4" type="video/mp4">
          </video>
          <div class="video-play-badge" aria-hidden="true">
            <i class="fa-solid fa-play"></i>
          </div>
        </div>

        <div class="hymnal-content">
          <h3>National Anthem</h3>
          <p>The Philippine National Anthem.</p>
        </div>
      </article>

      <article class="hymnal-card">
        <div class="video-frame js-video-open" data-src="static/eagles hymn 2025.mp4" role="button" tabindex="0" aria-label="Play Eagles Hymn">
          <video playsinline preload="metadata" muted oncontextmenu="return false">
            <source src="static/eagles hymn 2025.mp4" type="video/mp4">
          </video>
          <div class="video-play-badge" aria-hidden="true">
            <i class="fa-solid fa-play"></i>
          </div>
        </div>

        <div class="hymnal-content">
          <h3>Eagles Hymn</h3>
          <p>The official hymn of the Philippine Eagles.</p>
        </div>
      </article>

    </div>
  </div>
</section>

<!-- ================= LIGHTBOX ================= -->
<div id="lightbox">
  <span class="close-lightbox" onclick="closeLightbox()">&times;</span>
  <button class="arrow left" onclick="prevPage()"><i class="fas fa-chevron-left"></i></button>
  <img src="" alt="Memo Page" id="lightbox-img">
  <div class="caption" id="lightbox-caption"></div>
  <button class="arrow right" onclick="nextPage()"><i class="fas fa-chevron-right"></i></button>
</div>

<!-- ================= UPCOMING EVENTS (WITH LOADER) ================= -->
<section class="events section-loading" id="secEvents">
  <div class="section-loader" id="loaderEvents" aria-label="Loading upcoming events" role="status">
    <div class="loader-spinner"></div>
    <div class="loader-text">Loading upcoming events...</div>
  </div>

  <div class="section-content" id="contentEvents">
    <h2>Upcoming Events</h2>

    <div class="event-list">
      <?php if (!empty($events)): ?>
        <?php $evt_i = 0; ?>
        <?php foreach ($events as $event): ?>
          <?php
            $evt_i++;
            $mediaFile = trim((string)($event['event_media'] ?? ''));
            $relPath = "event_media/" . $mediaFile;
            $absPath = rtrim($_SERVER['DOCUMENT_ROOT'], "/\\") . "/" . $relPath;

            if ($mediaFile === "" || !file_exists($absPath)) {
              $relPath = "event_media/default_event.png";
            }

            $prettyDate = date("F j, Y", strtotime($event['event_date']));
            $isEager = ($evt_i <= 2);
          ?>

          <div
            class="event-card js-event-open"
            role="button"
            tabindex="0"
            aria-label="View event details"
            data-title="<?= h($event['event_title']) ?>"
            data-date="<?= h($prettyDate) ?>"
            data-type="<?= h($event['event_type']) ?>"
            data-image="<?= h($relPath) ?>"
            data-description="<?= h($event['event_description']) ?>"
          >
            <img
              src="<?= h($relPath) ?>"
              alt="<?= h($event['event_title']) ?>"
              loading="<?= $isEager ? 'eager' : 'lazy' ?>"
              <?= $isEager ? 'fetchpriority="high"' : '' ?>
            >
            <h4><?= h($event['event_title']) ?></h4>
            <p><strong>Date:</strong> <?= h($prettyDate) ?></p>
            <p style="opacity:.75; font-size:13px; margin-top:6px;">Click to view details</p>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p>No upcoming events available.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php include __DIR__ . "/includes/footer.php"; ?>
<!-- =========================================================
     EVENT DETAILS MODAL
========================================================= -->
<div class="event-modal" id="eventModal" aria-hidden="true">
  <div class="event-modal__backdrop" data-close="1"></div>

  <div class="event-modal__wrap" role="presentation">
    <div class="event-modal__dialog" role="dialog" aria-modal="true" aria-label="Event Details">
      <div class="event-modal__header">
        <div>
          <h3 class="event-modal__title" id="eventModalTitle"></h3>
          <div class="event-modal__meta" id="eventModalMeta"></div>
        </div>
        <button class="event-modal__close" type="button" aria-label="Close" data-close="1">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <div class="event-modal__body">
        <div class="event-modal__image">
          <img id="eventModalImg" src="" alt="">
        </div>

        <div class="event-modal__content">
          <p class="event-modal__desc" id="eventModalDesc"></p>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- =========================================================
     FLOATING VERIFY WIDGET
========================================================= -->
<div class="floating-verify" id="floatingVerify">
  <button class="fv-toggle" id="fvToggle" type="button" aria-label="Open Verify Membership">
    <i class="fa-solid fa-id-card"></i>
  </button>

  <div class="fv-card" id="fvCard" aria-hidden="true">
    <div class="fv-head">
      <div class="fv-title">
        <i class="fa-solid fa-magnifying-glass"></i>
        <span>Verify Membership</span>
      </div>
      <button type="button" class="fv-close" id="fvClose" aria-label="Close">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <p class="fv-sub">Enter your Membership ID to check if you are registered.</p>

    <form method="POST" action="index.php" class="fv-form" id="verifyForm">
      <input type="hidden" name="action" value="verify">
      <input type="text" name="search_id" id="verifyInput" required autocomplete="off" placeholder="TFOEPE00000000">
      <button type="submit" id="verifyBtn">Verify</button>
    </form>
  </div>
</div>

<!-- VERIFY MODAL -->
<div id="verifyModal" class="verify-modal">
  <div class="verify-modal-card">
    <button class="verify-close" id="closeVerifyModal"><i class="fa-solid fa-xmark"></i></button>
    <div id="verifyModalContent"></div>
  </div>
</div>

<!-- LOGIN / SIGNUP MODAL -->
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

      <form method="POST" action="index.php" class="login-form" autocomplete="on" id="loginForm">
        <input type="hidden" name="action" value="login">

        <label>Username</label>
        <input type="text" name="login_username" required placeholder="e.g JDcruz27">

        <label>Password</label>
        <div class="password-wrap">
          <input type="password" name="login_password" id="loginPassword" required placeholder="Enter your password">
          <button type="button" class="toggle-pass" id="toggleLoginPass" aria-label="Toggle password">
            <i class="fa-solid fa-eye"></i>
          </button>
        </div>

        <button type="submit" class="login-submit" id="loginSubmitBtn">Sign In</button>
        <div class="login-alert error" id="loginErrorBox" style="display:none;"></div>
      </form>
    </div>

    <!-- SIGN UP -->
    <div class="login-panel" id="signup">
      <h2 class="login-title">Create Account</h2>
      <p class="login-sub">Join the Eagles portal.</p>

      <form method="POST" action="index.php" class="login-form" autocomplete="on" id="signupForm">
        <input type="hidden" name="action" value="signup">

        <label>Full Name</label>
        <input type="text" name="signup_name" required placeholder="e.g Juan Dela Cruz">

        <label>Username</label>
        <input type="text" name="signup_username" required placeholder="e.g JDcruz27">

        <label>Eagles ID</label>
        <input type="text" name="signup_eagles_id" required autocomplete="off" placeholder="TFOEPE00000000">

        <label>Password</label>
        <input type="password" name="signup_password" required placeholder="Minimum 8 characters">

        <label>Confirm Password</label>
        <input type="password" name="signup_password_confirm" required placeholder="Re-enter your password">

        <button type="submit" class="login-submit" id="signupSubmitBtn">Create Account</button>

        <div class="login-alert error" id="signupErrorBox" style="display:none;"></div>
        <div class="login-alert success" id="signupSuccessBox" style="display:none;"></div>
      </form>
    </div>

  </div>
</div>

<!-- Blur Overlay for auth gate -->
<div id="authBlocker" aria-hidden="true"></div>

<!-- =========================================================
     VIDEO MODAL (FOR HYMNALS ON INDEX)
========================================================= -->
<div class="video-modal" id="videoModal" aria-hidden="true">
  <div class="video-modal__backdrop" data-close="1"></div>

  <div class="video-modal__wrap" role="presentation">
    <button class="video-modal__close video-modal__close--outside" type="button" aria-label="Close video" data-close="1">
      <i class="fa-solid fa-xmark"></i>
    </button>

    <div class="video-modal__dialog" role="dialog" aria-modal="true" aria-label="Video Player">
      <div class="video-modal__player">
        <video
          id="modalVideo"
          controls
          playsinline
          preload="metadata"
          controlslist="nodownload noplaybackrate"
          disablePictureInPicture
          oncontextmenu="return false">
          <source id="modalVideoSource" src="" type="video/mp4">
        </video>
      </div>
    </div>
  </div>
</div>

<script>

const heroImg = document.querySelector('.hero img');

if(heroImg.complete) {
  heroImg.classList.add('loaded'); // kung cached na
} else {
  heroImg.addEventListener('load', () => {
    heroImg.classList.add('loaded'); // tanggal blur pag fully loaded
  });
}

// Splash screen (only on real page load)
window.addEventListener("load", () => {
  setTimeout(() => { document.body.classList.add("loaded"); }, 3000);
});

// Memo Lightbox
let currentMemoId = null;
let currentPageIndex = 0;
let memoPages = <?php echo json_encode($pages); ?>;
let memoDescriptions = <?php
  $descs = [];
  foreach ($memos as $m) { $descs[$m['memo_id']] = $m['memo_description']; }
  echo json_encode($descs);
?>;

const lightbox = document.getElementById('lightbox');
const lightboxImg = document.getElementById('lightbox-img');
const lightboxCaption = document.getElementById('lightbox-caption');

function openLightbox(memoId) {
  currentMemoId = memoId;
  currentPageIndex = 0;
  lightbox.style.display = 'flex';
  updateLightbox();
}
function closeLightbox() { lightbox.style.display = 'none'; }
function updateLightbox() {
  const pages = memoPages[currentMemoId];
  if (!pages || pages.length === 0) return;
  lightboxImg.src = 'memorandum/' + pages[currentPageIndex];
  lightboxCaption.textContent = memoDescriptions[currentMemoId] || '';
}
function nextPage() {
  const pages = memoPages[currentMemoId];
  if (!pages) return;
  currentPageIndex = (currentPageIndex + 1) % pages.length;
  updateLightbox();
}
function prevPage() {
  const pages = memoPages[currentMemoId];
  if (!pages) return;
  currentPageIndex = (currentPageIndex - 1 + pages.length) % pages.length;
  updateLightbox();
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {

  /* =========================================================
     SECTION LOADERS (Latest News, Memos, Events)
  ========================================================= */
  function hideLoader(loaderEl, sectionEl){
    if (!loaderEl) return;
    loaderEl.classList.add('hide');
    if (sectionEl) sectionEl.classList.add('is-ready');
    setTimeout(() => {
      if (loaderEl && loaderEl.parentNode) loaderEl.parentNode.removeChild(loaderEl);
    }, 450);
  }

  function showSectionLoader(sectionId, loaderId, imgSelector, opts = {}){
    const section = document.getElementById(sectionId);
    const loader  = document.getElementById(loaderId);
    if (!section || !loader) return;

    const images = Array.from(section.querySelectorAll(imgSelector || "img"));
    const maxWaitMs = typeof opts.maxWaitMs === "number" ? opts.maxWaitMs : 2500;
    const minShowMs = typeof opts.minShowMs === "number" ? opts.minShowMs : 350;

    const startedAt = Date.now();
    let done = false;

    const finish = () => {
      if (done) return;
      done = true;

      const elapsed = Date.now() - startedAt;
      const wait = Math.max(0, minShowMs - elapsed);

      setTimeout(() => hideLoader(loader, section), wait);
    };

    if (!images.length){
      finish();
      return;
    }

    let remaining = 0;

    images.forEach(img => {
      if (img.complete && img.naturalWidth > 0) return;

      remaining++;
      const onDone = () => {
        img.removeEventListener('load', onDone);
        img.removeEventListener('error', onDone);
        remaining--;
        if (remaining <= 0) finish();
      };
      img.addEventListener('load', onDone, { once: true });
      img.addEventListener('error', onDone, { once: true });
    });

    if (remaining <= 0) {
      finish();
      return;
    }

    setTimeout(() => finish(), maxWaitMs);
  }

  showSectionLoader("secNews", "loaderNews", ".featured-news-image img", { maxWaitMs: 4000, minShowMs: 450 });
  showSectionLoader("secMemos", "loaderMemos", ".memo-card img[loading='eager']", { maxWaitMs: 4000, minShowMs: 450 });
  showSectionLoader("secEvents", "loaderEvents", ".event-card img[loading='eager']", { maxWaitMs: 4000, minShowMs: 450 });

  // Floating widget toggle
  const fvToggle = document.getElementById('fvToggle');
  const fvCard = document.getElementById('fvCard');
  const fvClose = document.getElementById('fvClose');

  function openFV() {
    fvCard.classList.add('active');
    fvCard.setAttribute('aria-hidden', 'false');
    fvToggle?.classList.add('is-open');
  }
  function closeFV() {
    fvCard.classList.remove('active');
    fvCard.setAttribute('aria-hidden', 'true');
    fvToggle?.classList.remove('is-open');
  }

  fvToggle?.addEventListener('click', () => {
    fvCard.classList.contains('active') ? closeFV() : openFV();
  });
  fvClose?.addEventListener('click', closeFV);

  // Login modal basics
  const loginModal = document.getElementById('loginModal');
  const closeLogin = document.getElementById('closeLoginModal');

  const togglePass = document.getElementById('toggleLoginPass');
  const passInput = document.getElementById('loginPassword');

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

  function activateTab(name) {
    tabs.forEach(t => t.classList.remove('active'));
    panels.forEach(p => p.classList.remove('active'));
    document.querySelector(`.login-tab[data-tab="${name}"]`)?.classList.add('active');
    document.getElementById(name)?.classList.add('active');
  }

  tabs.forEach(tab => {
    tab.addEventListener('click', () => activateTab(tab.dataset.tab));
  });

  function closeLoginModal() {
    loginModal?.classList.remove('active');
    document.body.style.overflow = 'auto';
    const url = new URL(window.location.href);
    url.searchParams.delete('auth');
    window.history.replaceState({}, '', url.toString());
  }

  closeLogin?.addEventListener('click', closeLoginModal);
  loginModal?.addEventListener('click', (e) => { if (e.target === loginModal) closeLoginModal(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && loginModal?.classList.contains('active')) closeLoginModal(); });

  // Small helpers
  function showBox(el, msg) {
    if (!el) return;
    el.textContent = msg;
    el.style.display = 'block';
  }
  function hideBox(el) {
    if (!el) return;
    el.textContent = '';
    el.style.display = 'none';
  }

  // ================= AJAX LOGIN (NO REFRESH) =================
  const loginForm = document.getElementById('loginForm');
  const loginBtn = document.getElementById('loginSubmitBtn');
  const loginErrorBox = document.getElementById('loginErrorBox');

  loginForm?.addEventListener('submit', async (e) => {
    e.preventDefault();

    hideBox(loginErrorBox);

    if (loginBtn) {
      loginBtn.disabled = true;
      loginBtn.textContent = 'Signing in...';
    }

    try {
      const formData = new FormData(loginForm);

      const res = await fetch('index', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
      });

      const data = await res.json().catch(() => null);

      if (!res.ok || !data || !data.ok) {
        showBox(loginErrorBox, (data && data.message) ? data.message : 'Login failed.');
        return;
      }

      window.location.href = data.redirect || 'membership';
    } catch (err) {
      showBox(loginErrorBox, 'Network error. Please try again.');
    } finally {
      if (loginBtn) {
        loginBtn.disabled = false;
        loginBtn.textContent = 'Sign In';
      }
    }
  });

  // ================= AJAX SIGNUP (NO REFRESH) =================
  const signupForm = document.getElementById('signupForm');
  const signupBtn = document.getElementById('signupSubmitBtn');
  const signupErrorBox = document.getElementById('signupErrorBox');
  const signupSuccessBox = document.getElementById('signupSuccessBox');

  signupForm?.addEventListener('submit', async (e) => {
    e.preventDefault();

    hideBox(signupErrorBox);
    hideBox(signupSuccessBox);

    if (signupBtn) {
      signupBtn.disabled = true;
      signupBtn.textContent = 'Creating...';
    }

    try {
      const formData = new FormData(signupForm);

      const res = await fetch('index.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
      });

      const data = await res.json().catch(() => null);

      if (!res.ok || !data || !data.ok) {
        showBox(signupErrorBox, (data && data.message) ? data.message : 'Signup failed.');
        return;
      }

      showBox(signupSuccessBox, data.message || 'Account created.');
      signupForm.reset();
      activateTab('signin');
    } catch (err) {
      showBox(signupErrorBox, 'Network error. Please try again.');
    } finally {
      if (signupBtn) {
        signupBtn.disabled = false;
        signupBtn.textContent = 'Create Account';
      }
    }
  });

  // ================= VERIFY + AUTH GATE =================
  const isLoggedIn = <?= json_encode($isLoggedIn) ?>;
  const authBlocker = document.getElementById('authBlocker');

  const verifyForm = document.getElementById('verifyForm');
  const verifyInput = document.getElementById('verifyInput');
  const verifyBtn = document.getElementById('verifyBtn');

  const verifyModal = document.getElementById('verifyModal');
  const verifyModalContent = document.getElementById('verifyModalContent');
  const closeVerifyModal = document.getElementById('closeVerifyModal');

  function openAuthGate() {
    if (!loginModal) return;

    authBlocker?.classList.add('active');
    loginModal.classList.add('active');
    document.body.classList.add('auth-locked');
    document.body.style.overflow = 'hidden';
    activateTab('signin');
  }

  function removeAuthGateIfClosed() {
    if (!loginModal.classList.contains('active')) {
      authBlocker?.classList.remove('active');
      document.body.classList.remove('auth-locked');
      document.body.style.overflow = 'auto';
    }
  }

  function openVerifyModal(html) {
    if (!verifyModal || !verifyModalContent) return;
    verifyModalContent.innerHTML = html;
    verifyModal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeVerify() {
    verifyModal?.classList.remove('active');
    document.body.style.overflow = 'auto';
  }

  closeVerifyModal?.addEventListener('click', closeVerify);
  verifyModal?.addEventListener('click', (e) => { if (e.target === verifyModal) closeVerify(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && verifyModal?.classList.contains('active')) closeVerify(); });

  if (!isLoggedIn) {
    const blockEvent = (e) => {
      e.preventDefault();
      e.stopPropagation();
      verifyInput?.blur();
      openAuthGate();
    };

    verifyInput?.addEventListener('focus', blockEvent);
    verifyInput?.addEventListener('mousedown', blockEvent);
    verifyInput?.addEventListener('keydown', blockEvent);
    verifyInput?.addEventListener('touchstart', blockEvent, { passive: false });

    verifyBtn?.addEventListener('click', blockEvent);
    verifyBtn?.addEventListener('mousedown', blockEvent);
    verifyBtn?.addEventListener('touchstart', blockEvent, { passive: false });

    verifyForm?.addEventListener('submit', blockEvent);

    closeLogin?.addEventListener('click', () => setTimeout(removeAuthGateIfClosed, 0));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setTimeout(removeAuthGateIfClosed, 0); });
    loginModal?.addEventListener('click', (e) => { if (e.target === loginModal) setTimeout(removeAuthGateIfClosed, 0); });
  } else {
    verifyForm?.addEventListener('submit', async (e) => {
      e.preventDefault();

      if (verifyBtn) {
        verifyBtn.disabled = true;
        verifyBtn.textContent = 'Checking...';
      }

      try {
        const formData = new FormData(verifyForm);

        const res = await fetch('index.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: formData
        });

        const data = await res.json().catch(() => null);

        if (!data || typeof data.html !== 'string') {
          openVerifyModal(`
            <div class="verify-modal-title">System Error</div>
            <div class="verify-modal-sub">Unable to verify membership at this time.</div>
          `);
          return;
        }

        openVerifyModal(data.html);

      } catch (err) {
        openVerifyModal(`
          <div class="verify-modal-title">Network Error</div>
          <div class="verify-modal-sub">Please try again.</div>
        `);
      } finally {
        if (verifyBtn) {
          verifyBtn.disabled = false;
          verifyBtn.textContent = 'Verify';
        }
      }
    });
  }

  /* =========================================================
     VIDEO MODAL LOGIC (HYMNALS)
  ========================================================= */
  const modal = document.getElementById("videoModal");
  const modalVideo = document.getElementById("modalVideo");
  const modalSource = document.getElementById("modalVideoSource");
  const openers = document.querySelectorAll(".js-video-open");

  function openVideoModal(src){
    if (!modal || !modalVideo || !modalSource) return;

    modalSource.src = src;
    modalVideo.load();

    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");

    const p = modalVideo.play();
    if (p && typeof p.catch === "function") p.catch(() => {});
  }

  function closeVideoModal(){
    if (!modal || !modalVideo || !modalSource) return;

    modalVideo.pause();
    modalVideo.currentTime = 0;
    modalSource.src = "";
    modalVideo.load();

    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("modal-open");
  }

  openers.forEach(el => {
    el.addEventListener("click", () => {
      const src = el.getAttribute("data-src");
      if (src) openVideoModal(src);
    });

    el.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        const src = el.getAttribute("data-src");
        if (src) openVideoModal(src);
      }
    });
  });

  if (modal) {
    modal.addEventListener("click", (e) => {
      const t = e.target;
      if (t && t.getAttribute && t.getAttribute("data-close") === "1") {
        closeVideoModal();
      }
    });
  }

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && modal && modal.classList.contains("is-open")) {
      closeVideoModal();
    }
  });

  /* =========================================================
     EVENT MODAL LOGIC
  ========================================================= */
  const eventModal = document.getElementById("eventModal");
  const eventTitle = document.getElementById("eventModalTitle");
  const eventMeta  = document.getElementById("eventModalMeta");
  const eventImg   = document.getElementById("eventModalImg");
  const eventDesc  = document.getElementById("eventModalDesc");
  const eventOpeners = document.querySelectorAll(".js-event-open");

  function openEventModalFromEl(el){
    if (!eventModal) return;

    const title = el.getAttribute("data-title") || "";
    const date  = el.getAttribute("data-date") || "";
    const type  = el.getAttribute("data-type") || "";
    const img   = el.getAttribute("data-image") || "";
    const desc  = el.getAttribute("data-description") || "";

    if (eventTitle) eventTitle.textContent = title;

    const safeType = String(type || "").trim();
    const typeIsUpcoming = safeType.toLowerCase() === "upcoming";

    if (eventMeta) {
      eventMeta.innerHTML = `
        <span><strong>Date:</strong> ${date}</span>
        ${typeIsUpcoming ? "" : `<span><strong>Type:</strong> ${safeType}</span>`}
      `;
    }

    if (eventImg) {
      eventImg.src = img;
      eventImg.alt = title;
    }
    if (eventDesc) eventDesc.textContent = desc;

    eventModal.classList.add("active");
    eventModal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  }

  function closeEventModal(){
    if (!eventModal) return;
    eventModal.classList.remove("active");
    eventModal.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "auto";
  }

  eventOpeners.forEach(card => {
    card.addEventListener("click", () => openEventModalFromEl(card));
    card.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        openEventModalFromEl(card);
      }
    });
  });

  eventModal?.addEventListener("click", (e) => {
    const t = e.target;
    if (t && t.getAttribute && t.getAttribute("data-close") === "1") {
      closeEventModal();
    }
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && eventModal?.classList.contains("active")) {
      closeEventModal();
    }
  });

  /* =========================================================
     SCROLL-REACTIVE GRADIENT / GLOW (NEWS + MEMOS)
  ========================================================= */
  const prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const section = document.getElementById('newsMemos');

  if (section && !prefersReduced) {
    let raf = 0;
    const clamp = (v, min, max) => Math.max(min, Math.min(max, v));

    function updateGlow() {
      raf = 0;

      const rect = section.getBoundingClientRect();
      const vh = window.innerHeight || 1;

      const start = vh;
      const end = -rect.height;
      const t = (rect.top - end) / (start - end);
      const p = clamp(t, 0, 1);

      const glowY = 50 + (0.5 - p) * 18;
      const glowX = 50 + Math.sin((1 - p) * Math.PI) * 4;
      const glowA = 0.48 + (1 - p) * 0.18;

      const glowS = 1.00 + (1 - p) * 0.06;
      const glowR = (-3 + (1 - p) * 6);

      section.style.setProperty('--glowX', glowX.toFixed(2) + '%');
      section.style.setProperty('--glowY', glowY.toFixed(2) + '%');
      section.style.setProperty('--glowA', glowA.toFixed(3));
      section.style.setProperty('--glowS', glowS.toFixed(3));
      section.style.setProperty('--glowR', glowR.toFixed(2) + 'deg');
    }

    function requestUpdate() {
      if (raf) return;
      raf = requestAnimationFrame(updateGlow);
    }

    updateGlow();
    window.addEventListener('scroll', requestUpdate, { passive: true });
    window.addEventListener('resize', requestUpdate);
  }
});
</script>

</body>
</html>