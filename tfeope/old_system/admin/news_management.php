<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
  header("Location: login.php");
  exit;
}

/* ================= DB + LOGGER ================= */
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/admin_logger.php";
if (!isset($conn) || !($conn instanceof mysqli)) {
  die("Database connection not found. Check db.php.");
}

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

/* ================= PERMISSIONS =================
   Super Admin (role_id=1): can delete
   Admin (role_id=2): no delete
================================================= */
$roleId = (int)($_SESSION['role_id'] ?? 0);
$isSuperAdmin = ($roleId === 1);
$canDelete = $isSuperAdmin;

/* ================= HELPERS ================= */
function h($v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function safe_basename(string $name): string {
  return basename(str_replace(['\\', "\0"], ['/', ''], (string)$name));
}

/* =========================================================
   UPLOADS PATHS (FIXED)
========================================================= */
$NEWS_DIR_REL = "/news_images/"; // URL path
$NEWS_DIR_ABS = rtrim((string)$_SERVER['DOCUMENT_ROOT'], "/\\") . $NEWS_DIR_REL; // filesystem path

if (!is_dir($NEWS_DIR_ABS)) {
  @mkdir($NEWS_DIR_ABS, 0755, true);
}

/* =========================================================
   SAFE DELETE FILE (prevents path traversal)
========================================================= */
function delete_news_image_file(string $dirAbs, ?string $dbVal): void {
  if (!$dbVal) return;

  $name = safe_basename($dbVal);
  if ($name === '') return;

  $base = realpath($dirAbs);
  $candidate = $dirAbs . $name;
  $real = realpath($candidate);

  if ($base && $real && strpos($real, $base) === 0 && is_file($real)) {
    @unlink($real);
  } elseif (is_file($candidate)) {
    @unlink($candidate);
  }
}

/* =========================================================
   MULTI-MEDIA SUPPORT
========================================================= */
function is_allowed_media_ext(string $filename): bool {
  $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
  $allowed = ['jpg','jpeg','png','webp','gif','mp4','mov','webm'];
  return in_array($ext, $allowed, true);
}

function upload_multiple_media(string $dirAbs, string $inputName = 'news_media'): array {
  $saved = [];

  if (empty($_FILES[$inputName]) || !is_array($_FILES[$inputName]['name'])) return $saved;

  $names = $_FILES[$inputName]['name'];
  $tmps  = $_FILES[$inputName]['tmp_name'];
  $errs  = $_FILES[$inputName]['error'];

  for ($i = 0; $i < count($names); $i++) {
    if (($errs[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;

    $orig = (string)$names[$i];
    if (!is_allowed_media_ext($orig)) continue;

    $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($orig));
    $fileName = time() . "_" . $i . "_" . $safeBase;
    $target = $dirAbs . $fileName;

    if (is_uploaded_file($tmps[$i]) && move_uploaded_file($tmps[$i], $target)) {
      $saved[] = $fileName;
    }
  }

  return $saved;
}

function insert_news_media(mysqli $conn, int $newsId, array $fileNames): void {
  if ($newsId <= 0 || empty($fileNames)) return;

  $stmt = $conn->prepare("INSERT INTO news_media (news_id, file_name, file_type) VALUES (?, ?, ?)");
  if (!$stmt) return;

  foreach ($fileNames as $fn) {
    $ext = strtolower(pathinfo((string)$fn, PATHINFO_EXTENSION));
    $type = in_array($ext, ['mp4','mov','webm'], true) ? 'video' : 'image';
    $stmt->bind_param("iss", $newsId, $fn, $type);
    $stmt->execute();
  }
  $stmt->close();
}

function delete_all_news_media(mysqli $conn, string $dirAbs, int $newsId): void {
  $stmt = $conn->prepare("SELECT file_name FROM news_media WHERE news_id=?");
  if ($stmt) {
    $stmt->bind_param("i", $newsId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
      delete_news_image_file($dirAbs, (string)($r['file_name'] ?? ''));
    }
    $stmt->close();
  }

  $stmt2 = $conn->prepare("DELETE FROM news_media WHERE news_id=?");
  if ($stmt2) {
    $stmt2->bind_param("i", $newsId);
    $stmt2->execute();
    $stmt2->close();
  }
}

function fetch_news_media(mysqli $conn, int $newsId): array {
  $out = [];
  $stmt = $conn->prepare("SELECT media_id, file_name, file_type FROM news_media WHERE news_id=? ORDER BY media_id ASC");
  if (!$stmt) return $out;

  $stmt->bind_param("i", $newsId);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) {
    $out[] = [
      'media_id' => (int)($r['media_id'] ?? 0),
      'file_name' => (string)($r['file_name'] ?? ''),
      'file_type' => (string)($r['file_type'] ?? 'image'),
    ];
  }
  $stmt->close();
  return $out;
}

function delete_one_news_media(mysqli $conn, string $dirAbs, int $newsId, int $mediaId): bool {
  if ($newsId <= 0 || $mediaId <= 0) return false;

  $stmt = $conn->prepare("SELECT file_name FROM news_media WHERE media_id=? AND news_id=? LIMIT 1");
  if (!$stmt) return false;

  $stmt->bind_param("ii", $mediaId, $newsId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$row) return false;

  $file = (string)($row['file_name'] ?? '');
  delete_news_image_file($dirAbs, $file);

  $stmt2 = $conn->prepare("DELETE FROM news_media WHERE media_id=? AND news_id=? LIMIT 1");
  if (!$stmt2) return false;
  $stmt2->bind_param("ii", $mediaId, $newsId);
  $ok = $stmt2->execute();
  $stmt2->close();

  return (bool)$ok;
}

/* ================= HANDLE DELETE ONE MEDIA ================= */
if (isset($_POST['delete_media']) && isset($_POST['news_id']) && isset($_POST['media_id'])) {
  if (!$canDelete) {
    set_flash('error', 'Unauthorized: Only super admins can delete media.');
    header("Location: news_management.php");
    exit;
  }

  $newsId  = (int)($_POST['news_id'] ?? 0);
  $mediaId = (int)($_POST['media_id'] ?? 0);

  if ($newsId <= 0 || $mediaId <= 0) {
    set_flash('error', 'Invalid media item.');
    header("Location: news_management.php");
    exit;
  }

  $tstmt = $conn->prepare("SELECT news_title FROM news_info WHERE news_id=? LIMIT 1");
  $title = '';
  if ($tstmt) {
    $tstmt->bind_param("i", $newsId);
    $tstmt->execute();
    $r = $tstmt->get_result()->fetch_assoc();
    $title = (string)($r['news_title'] ?? '');
    $tstmt->close();
  }

  $ok = delete_one_news_media($conn, $NEWS_DIR_ABS, $newsId, $mediaId);

  if ($ok) {
    log_admin_action($conn, "DELETE", "Deleted media ID #{$mediaId} from news ID #{$newsId} (Title: {$title})");
    set_flash('success', 'Media deleted successfully.');
  } else {
    set_flash('error', 'Media delete failed.');
  }

  header("Location: news_management.php");
  exit;
}

/* ================= HANDLE DELETE NEWS ================= */
if (isset($_POST['delete_news']) && isset($_POST['id'])) {
  if (!$canDelete) {
    set_flash('error', 'Unauthorized: Only super admins can delete news.');
    header("Location: news_management.php");
    exit;
  }

  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) {
    set_flash('error', 'Invalid news item.');
    header("Location: news_management.php");
    exit;
  }

  $stmt = $conn->prepare("SELECT news_title FROM news_info WHERE news_id=? LIMIT 1");
  if (!$stmt) {
    set_flash('error', 'Delete failed: ' . $conn->error);
    header("Location: news_management.php");
    exit;
  }
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$row) {
    set_flash('error', 'News item not found.');
    header("Location: news_management.php");
    exit;
  }

  $title = (string)($row['news_title'] ?? '');

  delete_all_news_media($conn, $NEWS_DIR_ABS, $id);

  $stmt = $conn->prepare("DELETE FROM news_info WHERE news_id=? LIMIT 1");
  if (!$stmt) {
    set_flash('error', 'Delete failed: ' . $conn->error);
    header("Location: news_management.php");
    exit;
  }
  $stmt->bind_param("i", $id);
  $ok = $stmt->execute();
  $stmt->close();

  if ($ok) {
    log_admin_action($conn, "DELETE", "Deleted news ID #{$id} (Title: {$title})");
  }

  set_flash($ok ? 'success' : 'error', $ok ? 'News deleted successfully.' : 'Delete failed. Please try again.');
  header("Location: news_management.php");
  exit;
}

/* ================= HANDLE ADD NEWS ================= */
if (isset($_POST['add_news'])) {
  $title   = trim((string)($_POST['title'] ?? ''));
  $content = trim((string)($_POST['content'] ?? ''));
  $status  = trim((string)($_POST['status'] ?? 'Published'));

  if ($title === '' || $content === '') {
    set_flash('error', 'Title and content are required.');
    header("Location: news_management.php");
    exit;
  }

  $image = null;

  $stmt = $conn->prepare("INSERT INTO news_info (news_title, news_content, news_status, news_image) VALUES (?, ?, ?, ?)");
  if (!$stmt) {
    set_flash('error', 'Add failed: ' . $conn->error);
    header("Location: news_management.php");
    exit;
  }
  $stmt->bind_param("ssss", $title, $content, $status, $image);
  $ok = $stmt->execute();
  $newId = (int)$stmt->insert_id;
  $stmt->close();

  if ($ok) {
    $mediaFiles = upload_multiple_media($NEWS_DIR_ABS, 'news_media');
    insert_news_media($conn, $newId, $mediaFiles);

    log_admin_action($conn, "CREATE", "Added news ID #{$newId} (Title: {$title})");

    if (!empty($_FILES['news_media']['name'][0] ?? '') && empty($mediaFiles)) {
      set_flash('warning', 'News saved, but the media upload failed or invalid file types.');
    } else {
      set_flash('success', 'News added successfully.');
    }
  } else {
    set_flash('error', 'Add failed. Please try again.');
  }

  header("Location: news_management.php");
  exit;
}

/* ================= HANDLE EDIT NEWS ================= */
if (isset($_POST['edit_news'])) {
  $id      = (int)($_POST['id'] ?? 0);
  $title   = trim((string)($_POST['title'] ?? ''));
  $content = trim((string)($_POST['content'] ?? ''));
  $status  = trim((string)($_POST['status'] ?? 'Published'));

  if ($id <= 0) {
    set_flash('error', 'Invalid news item.');
    header("Location: news_management.php");
    exit;
  }
  if ($title === '' || $content === '') {
    set_flash('error', 'Title and content are required.');
    header("Location: news_management.php");
    exit;
  }

  $stmt = $conn->prepare("UPDATE news_info SET news_title=?, news_content=?, news_status=? WHERE news_id=? LIMIT 1");
  if (!$stmt) {
    set_flash('error', 'Update failed: ' . $conn->error);
    header("Location: news_management.php");
    exit;
  }
  $stmt->bind_param("sssi", $title, $content, $status, $id);
  $ok = $stmt->execute();
  $stmt->close();

  if ($ok) {
    $mediaFiles = upload_multiple_media($NEWS_DIR_ABS, 'news_media');
    insert_news_media($conn, $id, $mediaFiles);

    log_admin_action($conn, "UPDATE", "Edited news ID #{$id} (Title: {$title})");

    if (!empty($_FILES['news_media']['name'][0] ?? '') && empty($mediaFiles)) {
      set_flash('warning', 'News updated, but the media upload failed or invalid file types.');
    } else {
      set_flash('success', 'News updated successfully.');
    }
  } else {
    set_flash('error', 'Update failed. Please try again.');
  }

  header("Location: news_management.php");
  exit;
}

/* ================= COUNTS ================= */
$totalCountRes = $conn->query("SELECT COUNT(*) AS total FROM news_info");
$totalCount = $totalCountRes ? (int)($totalCountRes->fetch_assoc()['total'] ?? 0) : 0;

$publishedCountRes = $conn->query("SELECT COUNT(*) AS total FROM news_info WHERE news_status='Published'");
$publishedCount = $publishedCountRes ? (int)($publishedCountRes->fetch_assoc()['total'] ?? 0) : 0;

/* ================= FETCH ALL NEWS (table thumb = first uploaded media) ================= */
$sql = "
  SELECT
    n.*,
    m.file_name AS first_media,
    m.file_type AS first_media_type
  FROM news_info n
  LEFT JOIN (
    SELECT news_id, MIN(media_id) AS first_media_id
    FROM news_media
    GROUP BY news_id
  ) fm ON fm.news_id = n.news_id
  LEFT JOIN news_media m ON m.media_id = fm.first_media_id
  ORDER BY n.news_id DESC
";
$result = $conn->query($sql);
if ($result === false) die("Query failed: " . $conn->error);

/* ================= NOTICE MESSAGE ================= */
$noticeTitle = "";
$noticeText  = "";

if ($totalCount === 0) {
  $noticeTitle = "No news items yet";
  $noticeText  = 'Click "Add News" to create your first post.';
} elseif ($publishedCount === 0) {
  $noticeTitle = "No published news yet";
  $noticeText  = "You can still create drafts. Publish one to show it on the public site.";
}

$flash = pull_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>News Management</title>
<link rel="icon" type="image/png" href="/static/eagles.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Merriweather:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../admin styles/news_upd.css">
<link rel="stylesheet" href="../admin styles/sidebar.css">
</head>

<body>

<button class="sidebar-toggle"><i class="fas fa-bars"></i></button>
<?php include 'sidebar.php'; ?>

<!-- TOAST NOTIFICATION -->
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

<div class="main-content">
  <h1>News Management</h1>

  <div class="top-buttons">
    <button class="add-btn" id="open-add-modal" type="button">
      <i class="fas fa-plus"></i> Add News
    </button>
  </div>

  <?php if (!empty($noticeTitle)): ?>
    <div class="content-area">
      <div class="empty-state">
        <i class="fas fa-newspaper"></i>
        <h3><?= h($noticeTitle) ?></h3>
        <p><?= h($noticeText) ?></p>
      </div>
    </div>
  <?php endif; ?>

  <!-- ADD/EDIT MODAL -->
  <div class="modal" id="news-modal" aria-hidden="true">
    <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="modal-title">
      <button class="close-btn" id="close-news" type="button" aria-label="Close">&times;</button>
      <h2 id="modal-title">Add News</h2>

      <form id="news-form" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" id="news-id">

        <label for="news-title">News Title</label>
        <input type="text" name="title" id="news-title" placeholder="News Title" required>

        <label for="news-content">News Content</label>
        <textarea name="content" id="news-content" placeholder="News Content" rows="6" required></textarea>

        <label for="news-status">Status</label>
        <select name="status" id="news-status">
          <option value="Published">Published</option>
          <option value="Draft">Draft</option>
        </select>

        <!-- EXISTING MEDIA (edit mode only) -->
        <div id="existing-media-wrap" style="display:none;">
          <label>Existing Media</label>
          <div class="field-help">Click the trash icon to remove a specific file.</div>
          <div class="media-list" id="existing-media-list"></div>
        </div>

        <label for="news-media">Upload Images / Videos</label>
        <input type="file" name="news_media[]" id="news-media" accept="image/*,video/*" multiple>
        <div class="field-help">First uploaded image will be shown in the table as the thumbnail.</div>

        <img id="preview-image" class="thumb" src="" alt="" style="display:none;">

        <button type="submit" name="add_news" id="news-submit">Save News</button>
      </form>
    </div>
  </div>

  <!-- NEWS TABLE -->
  <?php if ($totalCount > 0): ?>
  <table id="news-table">
    <thead>
      <tr>
        <th>Image</th>
        <th>Title</th>
        <th>Content</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $result->fetch_assoc()): ?>
        <?php
          $nid = (int)$row['news_id'];
          $media = fetch_news_media($conn, $nid);
          $mediaJson = json_encode($media, JSON_UNESCAPED_SLASHES);
          if ($mediaJson === false) $mediaJson = "[]";
        ?>
        <tr
          data-id="<?= $nid ?>"
          data-title="<?= h($row['news_title']) ?>"
          data-content="<?= h($row['news_content']) ?>"
          data-status="<?= h($row['news_status']) ?>"
          data-media="<?= h($mediaJson) ?>"
        >
          <td>
            <?php if (!empty($row['first_media'])): ?>
              <?php if (($row['first_media_type'] ?? '') === 'video'): ?>
                <video class="thumb" src="<?= h($NEWS_DIR_REL . $row['first_media']) ?>" muted></video>
              <?php else: ?>
                <img src="<?= h($NEWS_DIR_REL . $row['first_media']) ?>" class="thumb" alt="News Media">
              <?php endif; ?>
            <?php else: ?>
              No Media
            <?php endif; ?>
          </td>

          <td class="title"><?= h($row['news_title']) ?></td>
          <td class="content"><?= h($row['news_content']) ?></td>
          <td class="status" data-status="<?= h($row['news_status']) ?>"><?= h($row['news_status']) ?></td>

          <td>
            <button class="edit-btn" type="button" title="Edit"><i class="fas fa-edit"></i></button>

            <?php if ($canDelete): ?>
              <form method="POST" class="delete-form" style="display:inline;">
                <input type="hidden" name="id" value="<?= $nid ?>">
                <input type="hidden" name="delete_news" value="1">
                <button class="delete-btn js-open-delete" type="button" title="Delete"
                        data-title="<?= h($row['news_title']) ?>">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- DELETE CONFIRM MODAL (NEWS) -->
<div class="confirm-modal" id="delete-confirm" aria-hidden="true">
  <div class="confirm-card" role="dialog" aria-modal="true" aria-labelledby="delete-title">
    <div class="confirm-head">
      <h3 id="delete-title">Confirm deletion</h3>
      <button class="confirm-x" type="button" id="delete-close" aria-label="Close">&times;</button>
    </div>
    <div class="confirm-body">
      <p id="delete-msg">Delete this news item?</p>
      <p class="danger-note">This will permanently remove the post and its media files from the server.</p>
    </div>
    <div class="confirm-actions">
      <button class="btn btn-cancel" type="button" id="delete-cancel">Cancel</button>
      <button class="btn btn-danger" type="button" id="delete-confirm-btn">Delete</button>
    </div>
  </div>
</div>

<!-- DELETE CONFIRM MODAL (MEDIA) -->
<div class="confirm-modal" id="media-confirm" aria-hidden="true">
  <div class="confirm-card" role="dialog" aria-modal="true" aria-labelledby="media-title">
    <div class="confirm-head">
      <h3 id="media-title">Confirm media deletion</h3>
      <button class="confirm-x" type="button" id="media-close" aria-label="Close">&times;</button>
    </div>
    <div class="confirm-body">
      <p id="media-msg">Delete this media file?</p>
      <p class="danger-note">This will permanently remove the file from the server.</p>
    </div>
    <div class="confirm-actions">
      <button class="btn btn-cancel" type="button" id="media-cancel">Cancel</button>
      <button class="btn btn-danger" type="button" id="media-confirm-btn">Delete</button>
    </div>
  </div>
</div>

<script>
const newsModal = document.getElementById("news-modal");
const newsForm = document.getElementById("news-form");
const modalTitle = document.getElementById("modal-title");
const newsSubmit = document.getElementById("news-submit");
const previewImage = document.getElementById("preview-image");

const existingWrap = document.getElementById("existing-media-wrap");
const existingList = document.getElementById("existing-media-list");

function decodeHTML(s){
  const t = document.createElement('textarea');
  t.innerHTML = s || '';
  return t.value;
}
function safeJsonParse(s){
  try { return JSON.parse(s || "[]"); } catch { return []; }
}

/* Open Add Modal */
document.getElementById("open-add-modal").onclick = () => {
  modalTitle.innerText = "Add News";
  newsForm.reset();
  previewImage.style.display = "none";
  document.getElementById("news-id").value = '';
  newsSubmit.name = "add_news";

  if (existingWrap) existingWrap.style.display = "none";
  if (existingList) existingList.innerHTML = "";

  newsModal.classList.add("show");
};

/* Close Add/Edit Modal */
document.getElementById("close-news").onclick = () => newsModal.classList.remove("show");

/* Preview FIRST selected file (image only) */
document.getElementById("news-media").onchange = function () {
  const file = this.files && this.files[0] ? this.files[0] : null;
  if (!file) {
    previewImage.style.display = "none";
    return;
  }
  if (file.type && file.type.startsWith("image/")) {
    previewImage.src = URL.createObjectURL(file);
    previewImage.style.display = "block";
  } else {
    previewImage.style.display = "none";
  }
};

/* MEDIA CONFIRM MODAL */
const mediaModal = document.getElementById("media-confirm");
const mediaMsg = document.getElementById("media-msg");
const mediaClose = document.getElementById("media-close");
const mediaCancel = document.getElementById("media-cancel");
const mediaConfirmBtn = document.getElementById("media-confirm-btn");

let pendingMediaForm = null;

function openMediaConfirm(formEl, label){
  pendingMediaForm = formEl;
  mediaMsg.textContent = label ? `Delete "${label}"?` : "Delete this media file?";
  mediaModal.classList.add("show");
  mediaConfirmBtn.focus();
}
function closeMediaConfirm(){
  mediaModal.classList.remove("show");
  pendingMediaForm = null;
}

mediaConfirmBtn.addEventListener("click", () => {
  if (!pendingMediaForm) return;
  pendingMediaForm.submit();
});
mediaClose.addEventListener("click", closeMediaConfirm);
mediaCancel.addEventListener("click", closeMediaConfirm);

/* Render Existing Media Grid */
function renderExistingMedia(newsId, mediaArr){
  if (!existingWrap || !existingList) return;

  if (!newsId || !mediaArr || mediaArr.length === 0){
    existingWrap.style.display = "none";
    existingList.innerHTML = "";
    return;
  }

  existingWrap.style.display = "block";
  existingList.innerHTML = "";

  mediaArr.forEach(m => {
    const mediaId = Number(m.media_id || 0);
    const file = (m.file_name || "");
    const type = (m.file_type || "image");

    const item = document.createElement("div");
    item.className = "media-item";

    const mediaEl = document.createElement(type === "video" ? "video" : "img");
    if (type === "video") {
      mediaEl.src = "<?= h($NEWS_DIR_REL) ?>" + file;
      mediaEl.muted = true;
    } else {
      mediaEl.src = "<?= h($NEWS_DIR_REL) ?>" + file;
      mediaEl.alt = "";
    }
    item.appendChild(mediaEl);

    const form = document.createElement("form");
    form.method = "POST";
    form.className = "media-del-form";
    form.innerHTML = `
      <input type="hidden" name="delete_media" value="1">
      <input type="hidden" name="news_id" value="${newsId}">
      <input type="hidden" name="media_id" value="${mediaId}">
      <button class="media-del" type="button" title="Delete media">
        <i class="fa-solid fa-trash"></i>
      </button>
    `;

    const btn = form.querySelector("button.media-del");
    btn.addEventListener("click", () => openMediaConfirm(form, file));

    item.appendChild(form);
    existingList.appendChild(item);
  });
}

/* Edit news */
document.querySelectorAll(".edit-btn").forEach(btn => {
  btn.addEventListener("click", () => {
    const row = btn.closest("tr");
    modalTitle.innerText = "Edit News";

    const id = row.dataset.id || '';
    document.getElementById("news-id").value = id;
    document.getElementById("news-title").value = decodeHTML(row.dataset.title || '');
    document.getElementById("news-content").value = decodeHTML(row.dataset.content || '');
    document.getElementById("news-status").value = row.dataset.status || 'Published';

    document.getElementById("news-media").value = "";
    previewImage.style.display = "none";

    const media = safeJsonParse(decodeHTML(row.dataset.media || "[]"));
    renderExistingMedia(Number(id), media);

    newsSubmit.name = "edit_news";
    newsModal.classList.add("show");
  });
});

/* DELETE CONFIRM MODAL (NEWS) */
const delModal = document.getElementById("delete-confirm");
const delMsg = document.getElementById("delete-msg");
const delClose = document.getElementById("delete-close");
const delCancel = document.getElementById("delete-cancel");
const delConfirmBtn = document.getElementById("delete-confirm-btn");

let pendingDeleteForm = null;

function openDeleteModal(formEl, title) {
  pendingDeleteForm = formEl;
  delMsg.textContent = title ? `Delete "${decodeHTML(title)}"?` : "Delete this news item?";
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

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    if (delModal.classList.contains("show")) closeDeleteModal();
    if (mediaModal.classList.contains("show")) closeMediaConfirm();
  }
});

/* Sidebar toggle */
const sidebar = document.querySelector('.sidebar');
const toggleBtn = document.querySelector('.sidebar-toggle');
toggleBtn.addEventListener('click', () => { if (sidebar) sidebar.classList.toggle('show'); });

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