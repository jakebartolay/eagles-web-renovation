<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
  header("Location: admin-login");
  exit;
}

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/admin_logger.php";

if (!isset($conn) || !($conn instanceof mysqli)) {
  die("Database connection not found. Check db.php.");
}

$userRole = (int)($_SESSION['role_id'] ?? 2); // 1 = superadmin, 2 = admin

/* ================= FLASH (Toast Notifications) ================= */
function set_flash(string $type, string $message): void {
  $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}
function pull_flash(): ?array {
  if (!isset($_SESSION['flash'])) return null;
  $f = $_SESSION['flash'];
  unset($_SESSION['flash']);
  return is_array($f) ? $f : null;
}

/* ================= PATHS (FIXED) =================
   - REL: URL paths (start with "/")
   - ABS: filesystem paths using DOCUMENT_ROOT (so deletes actually work)
================================================== */
$VID_DIR_REL   = "/videos/";
$VID_DIR_ABS   = rtrim((string)$_SERVER['DOCUMENT_ROOT'], "/\\") . $VID_DIR_REL;

$THUMB_DIR_REL = "/videos_thumbnail/";
$THUMB_DIR_ABS = rtrim((string)$_SERVER['DOCUMENT_ROOT'], "/\\") . $THUMB_DIR_REL;

/* ================= HELPERS ================= */
function safe_basename(string $name): string {
  return basename(str_replace(['\\', "\0"], ['/', ''], (string)$name));
}
function h($v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function upload_error_message(int $code): string {
  return match ($code) {
    UPLOAD_ERR_OK => "OK",
    UPLOAD_ERR_INI_SIZE => "File is too large (php.ini upload_max_filesize).",
    UPLOAD_ERR_FORM_SIZE => "File is too large (HTML form MAX_FILE_SIZE).",
    UPLOAD_ERR_PARTIAL => "File was only partially uploaded.",
    UPLOAD_ERR_NO_FILE => "No file was uploaded.",
    UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder on server.",
    UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk (permissions).",
    UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload.",
    default => "Unknown upload error.",
  };
}

function ensure_dir(string $absDir): bool {
  if (is_dir($absDir)) return true;
  return @mkdir($absDir, 0755, true);
}

/* safe delete (prevents traversal) */
function safe_delete_in_dir(string $baseAbsDir, ?string $dbFile): void {
  if (!$dbFile) return;

  $baseAbsDir = rtrim($baseAbsDir, "/\\") . DIRECTORY_SEPARATOR;

  $name = safe_basename($dbFile);
  if ($name === '') return;

  $baseReal = realpath($baseAbsDir);
  $fileCandidate = $baseAbsDir . $name;
  $fileReal = realpath($fileCandidate);

  if ($baseReal && $fileReal && strpos($fileReal, $baseReal) === 0 && is_file($fileReal)) {
    @unlink($fileReal);
    return;
  }

  // fallback if realpath fails
  if (is_file($fileCandidate)) @unlink($fileCandidate);
}

/* Ensure folders exist */
ensure_dir($VID_DIR_ABS);
ensure_dir($THUMB_DIR_ABS);

/* ================= DELETE VIDEO (POST + MODAL CONFIRM) ================= */
if (isset($_POST['delete_video']) && isset($_POST['delete_id'])) {
  if ($userRole !== 1) {
    set_flash('error', 'Unauthorized: Only super admins can delete videos.');
    header("Location: videos-management");
    exit;
  }

  $id = (int)$_POST['delete_id'];
  if ($id <= 0) {
    set_flash('error', 'Invalid video item.');
    header("Location: videos-management");
    exit;
  }

  // fetch current row
  $stmt = $conn->prepare("SELECT video_title, video_file, video_thumbnail FROM video_info WHERE video_id=? LIMIT 1");
  if (!$stmt) {
    set_flash('error', 'Delete failed: ' . $conn->error);
    header("Location: videos-managementp");
    exit;
  }
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $current = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$current) {
    set_flash('error', 'Video not found.');
    header("Location: videos-management");
    exit;
  }

  $vf = (string)($current['video_file'] ?? '');
  $vt = (string)($current['video_thumbnail'] ?? '');
  $t  = (string)($current['video_title'] ?? '(unknown)');

  // delete DB row first (so you don’t delete file if DB fails)
  $del = $conn->prepare("DELETE FROM video_info WHERE video_id=? LIMIT 1");
  if (!$del) {
    set_flash('error', 'Delete failed: ' . $conn->error);
    header("Location: videos-management");
    exit;
  }
  $del->bind_param("i", $id);
  $ok = $del->execute();
  $del->close();

  if ($ok) {
    safe_delete_in_dir($VID_DIR_ABS, $vf);
    safe_delete_in_dir($THUMB_DIR_ABS, $vt);

    log_admin_action($conn, "DELETE", "Deleted video ID #{$id} (Title: {$t})");
    set_flash('success', 'Video deleted successfully.');
  } else {
    set_flash('error', 'Delete failed. Please try again.');
  }

  header("Location: videos-management");
  exit;
}

/* ================= ADD / EDIT VIDEO ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_video']) || isset($_POST['edit_video']))) {
  $id = (isset($_POST['video_id']) && $_POST['video_id'] !== "") ? (int)$_POST['video_id'] : null;

  $title = trim((string)($_POST['title'] ?? ""));
  $description = trim((string)($_POST['description'] ?? ""));
  $status = (string)($_POST['status'] ?? "Published");

  if (!($userRole === 1 || $userRole === 2)) {
    set_flash('error', 'Unauthorized.');
    header("Location: videos-management");
    exit;
  }

  if ($title === "" || $description === "") {
    set_flash('error', 'Please complete all required fields.');
    header("Location: videos-management");
    exit;
  }

  $videoFile = "";
  $thumbFile = "";

  $videoUploadOk = true;
  $thumbUploadOk = true;

  if (!ensure_dir($VID_DIR_ABS)) {
    set_flash('error', 'Cannot create/access videos folder: ' . $VID_DIR_ABS);
    header("Location: videos-management");
    exit;
  }
  if (!ensure_dir($THUMB_DIR_ABS)) {
    set_flash('error', 'Cannot create/access thumbnails folder: ' . $THUMB_DIR_ABS);
    header("Location: videos-management");
    exit;
  }

  /* ---------- VIDEO UPLOAD ---------- */
  if (!empty($_FILES['video_file']['name'])) {
    $err = (int)($_FILES['video_file']['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
      $videoUploadOk = false;
      $videoFile = "";
      set_flash('error', 'Video upload failed: ' . upload_error_message($err));
    } else {
      $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)$_FILES['video_file']['name']));
      $videoFile = time() . "_" . $safeBase;

      $tmp = (string)($_FILES['video_file']['tmp_name'] ?? '');
      $dest = $VID_DIR_ABS . $videoFile;

      if (!(is_uploaded_file($tmp) && move_uploaded_file($tmp, $dest))) {
        $videoUploadOk = false;
        $videoFile = "";
        set_flash('error', 'Video upload failed: cannot move file to ' . $dest);
      }
    }
  }

  /* ---------- THUMB UPLOAD ---------- */
  if (!empty($_FILES['video_thumbnail']['name'])) {
    $err = (int)($_FILES['video_thumbnail']['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
      $thumbUploadOk = false;
      $thumbFile = "";
      set_flash('error', 'Thumbnail upload failed: ' . upload_error_message($err));
    } else {
      $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)$_FILES['video_thumbnail']['name']));
      $thumbFile = time() . "_" . $safeBase;

      $tmp = (string)($_FILES['video_thumbnail']['tmp_name'] ?? '');
      $dest = $THUMB_DIR_ABS . $thumbFile;

      if (!(is_uploaded_file($tmp) && move_uploaded_file($tmp, $dest))) {
        $thumbUploadOk = false;
        $thumbFile = "";
        set_flash('error', 'Thumbnail upload failed: cannot move file to ' . $dest);
      }
    }
  }

  if ($id) {
    // EDIT
    $cur = $conn->prepare("SELECT video_file, video_thumbnail, video_title FROM video_info WHERE video_id=? LIMIT 1");
    if (!$cur) {
      set_flash('error', 'Update failed: ' . $conn->error);
      header("Location: videos-management");
      exit;
    }
    $cur->bind_param("i", $id);
    $cur->execute();
    $current = $cur->get_result()->fetch_assoc();
    $cur->close();

    if (!$current) {
      set_flash('error', 'Video not found.');
      header("Location: videos-management");
      exit;
    }

    $oldVideo = (string)($current['video_file'] ?? '');
    $oldThumb = (string)($current['video_thumbnail'] ?? '');

    // Keep old files if no new file OR upload failed
    $newVideo = $videoFile ?: $oldVideo;
    $newThumb = $thumbFile ?: $oldThumb;

    $stmt = $conn->prepare("
      UPDATE video_info
      SET video_title=?, video_description=?, video_file=?, video_thumbnail=?, video_status=?
      WHERE video_id=?
    ");
    if (!$stmt) {
      set_flash('error', 'Update failed: ' . $conn->error);
      header("Location: videos-management");
      exit;
    }
    $stmt->bind_param("sssssi", $title, $description, $newVideo, $newThumb, $status, $id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
      // If new upload succeeded, delete old file(s)
      if ($videoFile && $videoUploadOk && $oldVideo && $oldVideo !== $videoFile) {
        safe_delete_in_dir($VID_DIR_ABS, $oldVideo);
      }
      if ($thumbFile && $thumbUploadOk && $oldThumb && $oldThumb !== $thumbFile) {
        safe_delete_in_dir($THUMB_DIR_ABS, $oldThumb);
      }

      log_admin_action($conn, "UPDATE", "Edited video ID #{$id} (Title: {$title}, Status: {$status})");

      if (!$videoUploadOk && !empty($_FILES['video_file']['name'])) {
        set_flash('warning', 'Video updated, but the video file upload failed (old file kept).');
      } elseif (!$thumbUploadOk && !empty($_FILES['video_thumbnail']['name'])) {
        set_flash('warning', 'Video updated, but the thumbnail upload failed (old thumbnail kept).');
      } else {
        set_flash('success', 'Video updated successfully.');
      }
    } else {
      set_flash('error', 'Update failed. Please try again.');
    }

  } else {
    // ADD
    $stmt = $conn->prepare("
      INSERT INTO video_info (video_title, video_description, video_file, video_thumbnail, video_status)
      VALUES (?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
      set_flash('error', 'Add failed: ' . $conn->error);
      header("Location: videos-management");
      exit;
    }
    $stmt->bind_param("sssss", $title, $description, $videoFile, $thumbFile, $status);
    $ok = $stmt->execute();
    $newId = (int)$stmt->insert_id;
    $stmt->close();

    if ($ok) {
      log_admin_action($conn, "CREATE", "Added video ID #{$newId} (Title: {$title}, Status: {$status})");

      if ((!$videoUploadOk && !empty($_FILES['video_file']['name'])) || (!$thumbUploadOk && !empty($_FILES['video_thumbnail']['name']))) {
        // if an "error" flash was already set by upload failures, keep it; otherwise warn
        $existing = $_SESSION['flash']['message'] ?? '';
        if (!$existing) set_flash('warning', 'Video saved, but one of the uploads failed.');
      } else {
        set_flash('success', 'Video added successfully.');
      }
    } else {
      set_flash('error', 'Add failed. Please try again.');
    }
  }

  header("Location: videos-management");
  exit;
}

/* ================= FETCH VIDEOS ================= */
$result = $conn->query("SELECT * FROM video_info ORDER BY video_id DESC");
$total = $result ? $result->num_rows : 0;

$flash = pull_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Video Management</title>
<link rel="icon" href="/static/eagles.png">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Merriweather:wght@700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="../admin styles/sidebar.css">
<link rel="stylesheet" href="../admin styles/vid_upd.css">

<style>
/* ====== FIX: make modal clickable + close button always on top ====== */
.modal{
  position: fixed;
  inset: 0;
  display: none;
  align-items: center;
  justify-content: center;
  padding: 16px;
  background: rgba(0,0,0,.55);
  z-index: 99990;
}
.modal.show{ display: flex; }

/* Ensure modal content is the click target (overlay doesn't cover it) */
.modal .modal-box{
  position: relative;
  z-index: 1;
}

/* Replace span close with real button + absolute positioning */
.modal-close{
  position: absolute;
  top: 10px;
  right: 12px;
  border: 0;
  background: transparent;
  font-size: 26px;
  line-height: 1;
  cursor: pointer;
  opacity: .75;
  z-index: 999999;
  padding: 6px 8px;
}
.modal-close:hover{ opacity: 1; }
.modal-close:focus{ outline: 2px solid rgba(42,118,232,.35); outline-offset: 2px; border-radius: 10px; }

/* Prevent any pseudo element from blocking clicks */
.modal *{ pointer-events: auto; }

/* (Optional safety) if your external CSS uses ::before overlay on modal-box */
.modal .modal-box::before,
.modal .modal-box::after{
  pointer-events: none !important;
}

/* ===== Confirm modal (your existing) ===== */
.confirm-modal{
  position:fixed; inset:0;
  display:none;
  align-items:center;
  justify-content:center;
  background:rgba(0,0,0,.55);
  z-index:99999;
  padding:16px;
}
.confirm-modal.show{ display:flex; }

.confirm-card{
  width:min(520px, 100%);
  background:#fff;
  border-radius:16px;
  overflow:hidden;
  box-shadow:0 18px 55px rgba(0,0,0,.18);
  animation: pop .14s ease-out;
}
@keyframes pop{
  from{ transform:scale(.98); opacity:.75; }
  to{ transform:scale(1); opacity:1; }
}
.confirm-head{
  display:flex;
  align-items:center;
  justify-content:space-between;
  padding:14px 16px;
  border-bottom:1px solid rgba(0,0,0,.08);
}
.confirm-head h3{ margin:0; font-size:16px; }
.confirm-x{
  border:0;
  background:transparent;
  font-size:22px;
  line-height:1;
  cursor:pointer;
  opacity:.7;
}
.confirm-x:hover{ opacity:1; }

.confirm-body{
  padding:14px 16px 6px;
  color:#222;
  font-size:14px;
}
.confirm-body p{ margin:0 0 10px; }
.confirm-body .danger-note{ font-size:12px; opacity:.8; }

.confirm-actions{
  display:flex;
  gap:10px;
  justify-content:flex-end;
  padding:12px 16px 16px;
}
.btn{
  border:0;
  cursor:pointer;
  border-radius:12px;
  padding:10px 14px;
  font-weight:700;
  font-size:13px;
}
.btn-cancel{ background:#eef2f7; color:#0b0f1a; }
.btn-danger{ background:#c5303f; color:#fff; }

.form-group{ display:flex; flex-direction:column; gap:6px; margin:10px 0; }
.form-group label{ font-weight:700; font-size:13px; }
.form-hint{ font-size:12px; opacity:.75; margin-top:-2px; }
</style>
</head>

<body>
<button class="sidebar-toggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button>
<?php include 'sidebar.php'; ?>

<?php if ($flash && !empty($flash['message'])): ?>
  <div class="toast-wrap" id="toastWrap" data-type="<?= h($flash['type'] ?? 'info') ?>">
    <div class="toast">
      <div class="toast-icon"><i class="fa-solid fa-circle-check"></i></div>
      <div class="toast-body">
        <div class="toast-title"><?= h(strtoupper($flash['type'] ?? 'INFO')) ?></div>
        <div class="toast-msg"><?= h($flash['message']) ?></div>
      </div>
      <button class="toast-close" type="button" aria-label="Close notification"><i class="fa-solid fa-xmark"></i></button>
      <div class="toast-bar"></div>
    </div>
  </div>
<?php endif; ?>

<main class="main">
  <div class="page-header">
    <h1>Video Management</h1>
    <?php if($userRole === 1 || $userRole === 2): ?>
      <button class="btn-primary" id="openModal" type="button">
        <i class="fa fa-plus"></i> Add Video
      </button>
    <?php endif; ?>
  </div>

  <?php if ($total === 0): ?>
    <div class="empty-state">
      <div class="icon"><i class="fa-regular fa-circle-play"></i></div>
      <h3>No video items yet</h3>
      <p>Click "Add Video" to upload your first video.</p>
    </div>
  <?php else: ?>
    <div class="video-grid">
      <?php while ($v = $result->fetch_assoc()):
        $title  = (string)($v['video_title'] ?? "");
        $desc   = (string)($v['video_description'] ?? "");
        $thumb  = (string)($v['video_thumbnail'] ?? "");
        $file   = (string)($v['video_file'] ?? "");
        $id     = (int)($v['video_id'] ?? 0);
        $status = (string)($v['video_status'] ?? "Published");
      ?>
      <div class="video-card">
        <?php if ($thumb !== ""): ?>
          <img src="<?= h($THUMB_DIR_REL . $thumb) ?>" alt="Thumbnail">
        <?php else: ?>
          <video src="<?= h($VID_DIR_REL . $file) ?>" muted preload="metadata"></video>
        <?php endif; ?>

        <div class="info">
          <h4><?= h($title) ?></h4>
          <p><?= h($desc) ?></p>
        </div>

        <div class="action-buttons">
          <button class="play-btn" type="button" data-file="<?= h($file) ?>">
            <i class="fas fa-play"></i>
          </button>

          <?php if($userRole === 1 || $userRole === 2): ?>
            <button class="edit-btn" type="button"
              data-id="<?= $id ?>"
              data-title="<?= h($title) ?>"
              data-desc="<?= h($desc) ?>"
              data-status="<?= h($status) ?>"
            ><i class="fas fa-edit"></i></button>
          <?php endif; ?>

          <?php if($userRole === 1): ?>
            <form method="POST" class="delete-form" style="display:inline;">
              <input type="hidden" name="delete_id" value="<?= $id ?>">
              <input type="hidden" name="delete_video" value="1">
              <button type="button" class="delete-btn js-open-delete" data-title="<?= h($title) ?>">
                <i class="fas fa-trash"></i>
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>
</main>

<!-- ADD / EDIT MODAL -->
<div class="modal" id="videoModal" aria-hidden="true">
  <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <button class="modal-close" type="button" id="closeModal" aria-label="Close">&times;</button>
    <h2 id="modal-title">Add Video</h2>

    <form id="video-form" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="video_id" id="video-id">

      <div class="form-group">
        <label for="video-title">Video title</label>
        <input name="title" id="video-title" placeholder="Enter video title" required>
      </div>

      <div class="form-group">
        <label for="video-desc">Description</label>
        <textarea name="description" id="video-desc" placeholder="Write a short description" rows="4" required></textarea>
      </div>

      <div class="form-group">
        <label for="video-file">Video file</label>
        <input type="file" name="video_file" id="video-file" accept="video/*">
        <div class="form-hint">Upload a video file (MP4 recommended).</div>
      </div>

      <div class="form-group">
        <label for="video-thumb">Thumbnail image (optional)</label>
        <input type="file" name="video_thumbnail" id="video-thumb" accept="image/*">
        <div class="form-hint">If no thumbnail is uploaded, the video preview will be used.</div>
      </div>

      <div class="form-group">
        <label for="video-status">Status</label>
        <select name="status" id="video-status">
          <option value="Published">Published</option>
          <option value="Draft">Draft</option>
        </select>
      </div>

      <button class="btn-primary full" type="submit" id="video-submit" name="save_video">Save video</button>
    </form>
  </div>
</div>

<!-- PLAYBACK MODAL -->
<div class="modal" id="playbackModal" aria-hidden="true">
  <div class="modal-box" role="dialog" aria-modal="true" aria-label="Video playback">
    <button class="modal-close" type="button" id="closePlayback" aria-label="Close">&times;</button>
    <video id="playbackVideo" controls style="width:100%; max-height:70vh;"></video>
  </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="confirm-modal" id="delete-confirm">
  <div class="confirm-card" role="dialog" aria-modal="true" aria-labelledby="delete-title">
    <div class="confirm-head">
      <h3 id="delete-title">Confirm deletion</h3>
      <button class="confirm-x" type="button" id="delete-close" aria-label="Close">&times;</button>
    </div>
    <div class="confirm-body">
      <p id="delete-msg">Delete this video?</p>
      <p class="danger-note">This will permanently remove the video and thumbnail from the server.</p>
    </div>
    <div class="confirm-actions">
      <button class="btn btn-cancel" type="button" id="delete-cancel">Cancel</button>
      <button class="btn btn-danger" type="button" id="delete-confirm-btn">Delete</button>
    </div>
  </div>
</div>

<script>
const modal = document.getElementById("videoModal");
const form = document.getElementById("video-form");
const modalTitle = document.getElementById("modal-title");
const submitBtn = document.getElementById("video-submit");

const playbackModal = document.getElementById("playbackModal");
const playbackVideo = document.getElementById("playbackVideo");

// ===== Helpers =====
function openModal(el){
  el.classList.add("show");
  el.setAttribute("aria-hidden", "false");
}
function closeModal(el){
  el.classList.remove("show");
  el.setAttribute("aria-hidden", "true");
}
function isClickOutside(e, boxSelector){
  const box = e.currentTarget.querySelector(boxSelector);
  return box && !box.contains(e.target);
}

// Add modal
<?php if($userRole === 1 || $userRole === 2): ?>
document.getElementById("openModal").addEventListener("click", () => {
  modalTitle.textContent = "Add Video";
  form.reset();
  document.getElementById("video-id").value = '';
  submitBtn.name = "save_video";
  submitBtn.textContent = "Save video";
  openModal(modal);
});
<?php endif; ?>

document.getElementById("closeModal").addEventListener("click", (e) => {
  e.preventDefault();
  closeModal(modal);
});

// Close add/edit when clicking overlay
modal.addEventListener("mousedown", (e) => {
  if (isClickOutside(e, ".modal-box")) closeModal(modal);
});

// Playback close
document.getElementById("closePlayback").addEventListener("click", (e) => {
  e.preventDefault();
  playbackVideo.pause();
  playbackVideo.src = '';
  closeModal(playbackModal);
});

// Close playback when clicking overlay
playbackModal.addEventListener("mousedown", (e) => {
  if (isClickOutside(e, ".modal-box")) {
    playbackVideo.pause();
    playbackVideo.src = '';
    closeModal(playbackModal);
  }
});

<?php if($userRole === 1 || $userRole === 2): ?>
// Edit modal
document.querySelectorAll(".edit-btn").forEach(btn => {
  btn.addEventListener("click", () => {
    modalTitle.textContent = "Edit Video";
    document.getElementById("video-id").value = btn.dataset.id || '';
    document.getElementById("video-title").value = btn.dataset.title || '';
    document.getElementById("video-desc").value = btn.dataset.desc || '';
    document.getElementById("video-status").value = btn.dataset.status || "Published";
    submitBtn.name = "edit_video";
    submitBtn.textContent = "Save changes";
    openModal(modal);
  });
});
<?php endif; ?>

// Play button (FIX: correct URL with leading "/")
document.querySelectorAll(".play-btn").forEach(btn => {
  btn.addEventListener("click", () => {
    const file = btn.dataset.file || '';
    playbackVideo.pause();
    playbackVideo.src = file ? `<?= h($VID_DIR_REL) ?>${file}` : '';
    openModal(playbackModal);
    if (file) playbackVideo.play();
  });
});

// Sidebar toggle
document.querySelector(".sidebar-toggle").addEventListener("click", () =>
  document.querySelector(".sidebar")?.classList.toggle("show")
);

/* DELETE CONFIRM MODAL */
const delModal = document.getElementById("delete-confirm");
const delMsg = document.getElementById("delete-msg");
const delClose = document.getElementById("delete-close");
const delCancel = document.getElementById("delete-cancel");
const delConfirmBtn = document.getElementById("delete-confirm-btn");

let pendingDeleteForm = null;

function openDeleteModal(formEl, title) {
  pendingDeleteForm = formEl;
  delMsg.textContent = title ? `Delete "${title}"?` : "Delete this video?";
  delModal.classList.add("show");
  delConfirmBtn.focus();
}
function closeDeleteModal() {
  delModal.classList.remove("show");
  pendingDeleteForm = null;
}

document.querySelectorAll(".js-open-delete").forEach(btn => {
  btn.addEventListener("click", () => {
    const form = btn.closest("form.delete-form");
    const title = btn.dataset.title || "";
    openDeleteModal(form, title);
  });
});

delConfirmBtn.addEventListener("click", () => {
  if (!pendingDeleteForm) return;
  pendingDeleteForm.submit();
});

delClose.addEventListener("click", closeDeleteModal);
delCancel.addEventListener("click", closeDeleteModal);

// Close delete modal when clicking overlay
delModal.addEventListener("mousedown", (e) => {
  const card = delModal.querySelector(".confirm-card");
  if (card && !card.contains(e.target)) closeDeleteModal();
});

// Global ESC close
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    if (delModal.classList.contains("show")) closeDeleteModal();
    if (modal.classList.contains("show")) closeModal(modal);
    if (playbackModal.classList.contains("show")) {
      playbackVideo.pause();
      playbackVideo.src = '';
      closeModal(playbackModal);
    }
  }
});

/* TOAST */
(function(){
  const wrap = document.getElementById('toastWrap');
  if (!wrap) return;

  const type = (wrap.getAttribute('data-type') || 'info').toLowerCase();
  wrap.classList.add('is-show', 't-' + type);

  const closeBtn = wrap.querySelector('.toast-close');
  const icon = wrap.querySelector('.toast-icon i');

  if (icon){
    if (type === 'success') icon.className = 'fa-solid fa-circle-check';
    else if (type === 'error') icon.className = 'fa-solid fa-circle-xmark';
    else if (type === 'warning') icon.className = 'fa-solid fa-triangle-exclamation';
    else icon.className = 'fa-solid fa-circle-info';
  }

  function hide(){
    wrap.classList.remove('is-show');
    setTimeout(() => wrap.remove(), 220);
  }

  if (closeBtn) closeBtn.addEventListener('click', hide);
  setTimeout(hide, 4200);
})();
</script>
</body>
</html>