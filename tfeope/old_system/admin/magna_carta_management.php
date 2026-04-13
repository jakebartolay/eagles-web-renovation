<?php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!isset($_SESSION['admin_logged_in'])) {
  header("Location: admin-login");
  exit;
}

/*
  FIXED PATHS AFTER MOVING ADMIN PAGES INTO /admin
  - db.php is inside /includes
*/
require_once __DIR__ . "/../includes/db.php";
if (!isset($conn) || !($conn instanceof mysqli)) {
  die("Database connection not found. Check includes/db.php.");
}

/* ================= ROLE FLAGS ================= */
$roleId = (int)($_SESSION['role_id'] ?? 0);
$isSuperAdmin = ($roleId === 1); // ONLY super admin can delete

/* ================= HELPERS ================= */
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* =========================================================
   FLASH HELPERS (floating toast)
========================================================= */
function set_flash(string $type, string $message): void {
  $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}
function pull_flash(): ?array {
  if (!isset($_SESSION['flash'])) return null;
  $f = $_SESSION['flash'];
  unset($_SESSION['flash']);
  return is_array($f) ? $f : null;
}

function column_exists(mysqli $conn, string $table, string $column): bool {
  $stmt = $conn->prepare("
    SELECT COUNT(*) AS c
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = ?
      AND COLUMN_NAME = ?
  ");
  $stmt->bind_param("ss", $table, $column);
  $stmt->execute();
  $c = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
  $stmt->close();
  return $c > 0;
}

function index_exists(mysqli $conn, string $table, string $indexName): bool {
  $stmt = $conn->prepare("
    SELECT COUNT(*) AS c
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = ?
      AND INDEX_NAME = ?
  ");
  $stmt->bind_param("ss", $table, $indexName);
  $stmt->execute();
  $c = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
  $stmt->close();
  return $c > 0;
}

/* =========================================================
  IMAGE UPLOAD (folder NOT inside /admin)
  Assumption: folder is at site root: /htdocs/magna_carta/
========================================================= */
$UPLOAD_DIR_REL = "/magna_carta/"; // ✅ public URL from site root
$UPLOAD_DIR_ABS = realpath(__DIR__ . "/../magna_carta");
$UPLOAD_DIR_ABS = $UPLOAD_DIR_ABS ? rtrim($UPLOAD_DIR_ABS, "/\\") . DIRECTORY_SEPARATOR : null;

$ALLOWED_EXT = ['jpg','jpeg','png','webp'];
$MAX_MB = 5;

if (!$UPLOAD_DIR_ABS) {
  // Create if missing (at site root)
  $tryCreate = __DIR__ . "/../magna_carta";
  if (!is_dir($tryCreate)) {
    @mkdir($tryCreate, 0755, true);
  }
  $UPLOAD_DIR_ABS = realpath($tryCreate);
  $UPLOAD_DIR_ABS = $UPLOAD_DIR_ABS ? rtrim($UPLOAD_DIR_ABS, "/\\") . DIRECTORY_SEPARATOR : null;
}

if (!$UPLOAD_DIR_ABS) {
  die("Upload folder not found: " . __DIR__ . "/../magna_carta");
}

function upload_image(string $field, ?string $old = null): ?string {
  global $UPLOAD_DIR_ABS, $ALLOWED_EXT, $MAX_MB;

  if (empty($_FILES[$field]['name'])) return $old;

  if (!isset($_FILES[$field]['error']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
    throw new RuntimeException("Image upload failed.");
  }

  if (!isset($_FILES[$field]['size']) || $_FILES[$field]['size'] > $MAX_MB * 1024 * 1024) {
    throw new RuntimeException("Image must be under {$MAX_MB}MB.");
  }

  $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, $ALLOWED_EXT, true)) {
    throw new RuntimeException("Invalid image type. Use JPG, PNG, or WEBP.");
  }

  $tmp = $_FILES[$field]['tmp_name'] ?? '';
  if ($tmp === '' || !is_uploaded_file($tmp) || @getimagesize($tmp) === false) {
    throw new RuntimeException("Invalid image file.");
  }

  $name = uniqid("mc_", true) . "." . $ext;
  $dest = $UPLOAD_DIR_ABS . $name;

  if (!move_uploaded_file($tmp, $dest)) {
    throw new RuntimeException("Failed to save image.");
  }

  if ($old && is_file($UPLOAD_DIR_ABS . $old)) {
    @unlink($UPLOAD_DIR_ABS . $old);
  }

  return $name;
}

/* =========================================================
  TABLE (ensure)
========================================================= */
$conn->query("
  CREATE TABLE IF NOT EXISTS magna_carta_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    subtitle VARCHAR(200) DEFAULT NULL,
    description TEXT NOT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* =========================================================
  MIGRATION (safe)
========================================================= */
$table = "magna_carta_items";

if (column_exists($conn, $table, "sort_order")) {
  $conn->query("ALTER TABLE magna_carta_items DROP COLUMN sort_order");
}
if (column_exists($conn, $table, "category")) {
  $conn->query("ALTER TABLE magna_carta_items DROP COLUMN category");
}
if (column_exists($conn, $table, "item_key")) {
  if (index_exists($conn, $table, "item_key")) {
    $conn->query("ALTER TABLE magna_carta_items DROP INDEX item_key");
  }
  $conn->query("ALTER TABLE magna_carta_items DROP COLUMN item_key");
}

if (!column_exists($conn, $table, "subtitle")) {
  $conn->query("ALTER TABLE magna_carta_items ADD COLUMN subtitle VARCHAR(200) DEFAULT NULL AFTER title");
}
if (!column_exists($conn, $table, "image_path")) {
  $conn->query("ALTER TABLE magna_carta_items ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER description");
}

/* =========================================================
  ACTIONS
========================================================= */
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // Save (create/update)
  if ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_active   = isset($_POST['is_active']) ? 1 : 0;

    $subtitle = ""; // intentionally blank

    if ($title === '' || $description === '') {
      set_flash('error', "Title and Description are required.");
      header("Location: magna_carta_management.php");
      exit;
    }

    $oldImage = null;
    if ($id > 0) {
      $q = $conn->prepare("SELECT image_path FROM magna_carta_items WHERE id=? LIMIT 1");
      $q->bind_param("i", $id);
      $q->execute();
      $oldImage = $q->get_result()->fetch_assoc()['image_path'] ?? null;
      $q->close();
    }

    try {
      $image_path = upload_image('image', $oldImage);
    } catch (Throwable $e) {
      set_flash('error', $e->getMessage());
      header("Location: magna_carta_management.php");
      exit;
    }

    if ($id > 0) {
      $stmt = $conn->prepare("
        UPDATE magna_carta_items
        SET title=?, subtitle=?, description=?, image_path=?, is_active=?
        WHERE id=?
        LIMIT 1
      ");
      $stmt->bind_param("ssssii", $title, $subtitle, $description, $image_path, $is_active, $id);
      $ok = $stmt->execute();
      $err = $stmt->error;
      $stmt->close();

      set_flash($ok ? 'success' : 'error', $ok ? "Updated successfully." : ("Update failed: " . $err));
    } else {
      $stmt = $conn->prepare("
        INSERT INTO magna_carta_items (title, subtitle, description, image_path, is_active)
        VALUES (?, ?, ?, ?, ?)
      ");
      $stmt->bind_param("ssssi", $title, $subtitle, $description, $image_path, $is_active);
      $ok = $stmt->execute();
      $err = $stmt->error;
      $stmt->close();

      set_flash($ok ? 'success' : 'error', $ok ? "Created successfully." : ("Create failed: " . $err));
    }

    header("Location: magna-carta-management");
    exit;
  }

  // Delete (SUPER ADMIN ONLY)
  if ($action === 'delete') {

    if (!$isSuperAdmin) {
      set_flash('error', "You are not allowed to delete topics.");
      header("Location: magna-carta-management");
      exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
      set_flash('error', "Invalid item.");
      header("Location: magna-carta-management");
      exit;
    }

    $q = $conn->prepare("SELECT image_path, title FROM magna_carta_items WHERE id=? LIMIT 1");
    $q->bind_param("i", $id);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    $q->close();

    $img = $row['image_path'] ?? null;

    if ($img && is_file($UPLOAD_DIR_ABS . $img)) {
      @unlink($UPLOAD_DIR_ABS . $img);
    }

    $stmt = $conn->prepare("DELETE FROM magna_carta_items WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $id);
    $ok = $stmt->execute();
    $err = $stmt->error;
    $stmt->close();

    set_flash($ok ? 'success' : 'error', $ok ? "Deleted successfully." : ("Delete failed: " . $err));

    header("Location: magna-carta-management");
    exit;
  }
}

/* =========================================================
  FETCH LIST
========================================================= */
$items = [];
$res = $conn->query("
  SELECT id, title, subtitle, description, image_path, is_active, updated_at, created_at
  FROM magna_carta_items
  ORDER BY id DESC
");
if ($res) while ($r = $res->fetch_assoc()) $items[] = $r;

$flash = pull_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin | Magna Carta</title>

  <!-- FIXED: absolute from site root -->
  <link rel="icon" type="image/png" href="/static/eagles.png" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Merriweather:wght@700&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="../admin styles/sidebar.css">
  <link rel="stylesheet" href="../admin styles/admin_magnacarta.css">

  <style>
    /* =============================
       DELETE CONFIRM MODAL
    ============================== */
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
      box-shadow:0 18px 55px rgba(0,0,0,.18);
      overflow:hidden;
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
    .cbtn{
      border:0;
      cursor:pointer;
      border-radius:12px;
      padding:10px 14px;
      font-weight:700;
      font-size:13px;
    }
    .cbtn-cancel{ background:#eef2f7; color:#0b0f1a; }
    .cbtn-cancel:hover{ filter:brightness(.98); }
    .cbtn-danger{ background:#c5303f; color:#fff; }
    .cbtn-danger:hover{ filter:brightness(.98); }
  </style>
</head>
<body>

<button class="sidebar-toggle"><i class="fas fa-bars"></i></button>
<?php include 'sidebar.php'; ?>

<!-- FLOATING TOAST (same system) -->
<?php if ($flash && !empty($flash['message'])): ?>
  <div class="toast-wrap" id="toastWrap" data-type="<?= h($flash['type'] ?? 'info') ?>">
    <div class="toast-float">
      <div class="toast-icon">
        <i class="fa-solid fa-circle-check"></i>
      </div>
      <div class="toast-body">
        <div class="toast-title"><?= h(strtoupper($flash['type'] ?? 'INFO')) ?></div>
        <div class="toast-msg"><?= h($flash['message']) ?></div>
      </div>
      <button class="toast-close" type="button" aria-label="Close notification">
        <i class="fa-solid fa-xmark"></i>
      </button>
      <div class="toast-bar"></div>
    </div>
  </div>
<?php endif; ?>

<div class="main-content">
  <div class="page-head">
    <div>
      <h1>Magna Carta</h1>
      <p class="sub">Manage the Magna Carta topics</p>
    </div>

    <div class="head-actions">
      <button class="btn" type="button" id="openCreate">
        <i class="fa-solid fa-plus"></i> Add Topic
      </button>
    </div>
  </div>

  <div class="card">
    <div class="card-title">
      <i class="fa-solid fa-list"></i>
      <span>Topics</span>
      <span class="pill"><?= count($items) ?> total</span>
    </div>

    <div class="table-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th style="width:90px;">Image</th>
            <th>Title + Description</th>
            <th style="width:140px;">Active</th>
            <th style="width:210px;">Updated</th>
            <th style="width:210px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($items) === 0): ?>
            <tr><td colspan="5" class="empty">No Magna Carta topics yet.</td></tr>
          <?php else: ?>
            <?php foreach ($items as $it): ?>
              <?php $imgUrl = (!empty($it['image_path'])) ? ($UPLOAD_DIR_REL . $it['image_path']) : ""; ?>
              <tr>
                <td>
                  <?php if ($imgUrl): ?>
                    <img class="thumb" src="<?= h($imgUrl) ?>" alt="">
                  <?php else: ?>
                    <div class="thumb ph"><i class="fa-regular fa-image"></i></div>
                  <?php endif; ?>
                </td>

                <td>
                  <div class="title-cell">
                    <div class="t"><?= h($it['title']) ?></div>
                    <div class="d"><?= h(mb_strimwidth((string)$it['description'], 0, 140, "...")) ?></div>
                  </div>
                </td>

                <td>
                  <span class="pill <?= (int)$it['is_active'] === 1 ? 'p-on' : 'p-off' ?>">
                    <?= (int)$it['is_active'] === 1 ? 'Yes' : 'No' ?>
                  </span>
                </td>

                <td class="mono"><?= h($it['updated_at']) ?></td>

                <td>
                  <div class="row-actions">
                    <button
                      class="btn ghost small editBtn"
                      type="button"
                      data-id="<?= (int)$it['id'] ?>"
                      data-title="<?= h($it['title']) ?>"
                      data-desc="<?= h($it['description']) ?>"
                      data-image="<?= h($it['image_path'] ?? '') ?>"
                      data-active="<?= (int)$it['is_active'] ?>"
                    >
                      <i class="fa-solid fa-pen"></i> Edit
                    </button>

                    <?php if ($isSuperAdmin): ?>
                      <form method="POST" action="" class="delete-mc-form" style="margin:0;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                        <button class="btn danger small js-open-delete-mc" type="button"
                                data-title="<?= h($it['title']) ?>">
                          <i class="fa-solid fa-trash"></i> Delete
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- CREATE/EDIT MODAL -->
<div class="modal" id="modal" aria-hidden="true">
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <button class="modal-close" type="button" aria-label="Close">×</button>

    <h2 id="modalTitle">Add Topic</h2>
    <p class="modal-sub">Upload an image and write the description</p>

    <form class="form" method="POST" action="" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="f_id" value="0">

      <div class="form-grid">
        <div class="field full">
          <label>Title</label>
          <input type="text" name="title" id="f_title" required>
        </div>

        <div class="field full">
          <label>Image (optional)</label>
          <input type="file" name="image" id="f_image" accept=".jpg,.jpeg,.png,.webp">
          <div class="hint">JPEG / PNG / WEBP • Max 5MB</div>

          <div class="img-preview" id="imgPreview" style="display:none;">
            <img id="imgPreviewTag" src="" alt="">
            <div class="img-preview-note">Current image</div>
          </div>
        </div>

        <div class="field full">
          <label>Description</label>
          <textarea name="description" id="f_desc" rows="8" required></textarea>
        </div>

        <div class="field check">
          <label class="chk">
            <input type="checkbox" name="is_active" id="f_active" checked>
            <span>Active (shows on frontend)</span>
          </label>
        </div>
      </div>

      <div class="form-actions">
        <button class="btn ghost" type="button" id="cancelBtn">Cancel</button>
        <button class="btn" type="submit">
          <i class="fa-solid fa-save"></i> Save
        </button>
      </div>
    </form>
  </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="confirm-modal" id="delete-mc-confirm">
  <div class="confirm-card" role="dialog" aria-modal="true" aria-labelledby="delete-mc-title">
    <div class="confirm-head">
      <h3 id="delete-mc-title">Confirm deletion</h3>
      <button class="confirm-x" type="button" id="delete-mc-close" aria-label="Close">&times;</button>
    </div>
    <div class="confirm-body">
      <p id="delete-mc-msg">Delete this topic?</p>
      <p class="danger-note">This will permanently remove the topic and its image from the server.</p>
    </div>
    <div class="confirm-actions">
      <button class="cbtn cbtn-cancel" type="button" id="delete-mc-cancel">Cancel</button>
      <button class="cbtn cbtn-danger" type="button" id="delete-mc-confirm-btn">Delete</button>
    </div>
  </div>
</div>

<script>
/* Sidebar toggle */
const sidebar = document.querySelector('.sidebar');
const toggleBtn = document.querySelector('.sidebar-toggle');
toggleBtn?.addEventListener('click', () => sidebar?.classList.toggle('show'));

/* Create/Edit Modal */
const modal = document.getElementById('modal');
const openCreate = document.getElementById('openCreate');
const cancelBtn = document.getElementById('cancelBtn');
const closeBtn = document.querySelector('.modal-close');

const f_id = document.getElementById('f_id');
const f_title = document.getElementById('f_title');
const f_desc = document.getElementById('f_desc');
const f_active = document.getElementById('f_active');
const modalTitle = document.getElementById('modalTitle');

const imgPreview = document.getElementById('imgPreview');
const imgPreviewTag = document.getElementById('imgPreviewTag');

function openModal() {
  modal.classList.add('show');
  modal.setAttribute('aria-hidden', 'false');
  document.body.classList.add('no-scroll');
}
function closeModal() {
  modal.classList.remove('show');
  modal.setAttribute('aria-hidden', 'true');
  document.body.classList.remove('no-scroll');
}

function resetForm() {
  f_id.value = 0;
  f_title.value = '';
  f_desc.value = '';
  f_active.checked = true;
  modalTitle.textContent = 'Add Topic';

  imgPreview.style.display = 'none';
  imgPreviewTag.src = '';
  document.getElementById('f_image').value = '';
}

openCreate.addEventListener('click', () => { resetForm(); openModal(); });
cancelBtn.addEventListener('click', closeModal);
closeBtn.addEventListener('click', closeModal);

// ESC still closes Create/Edit modal
window.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closeModal();
});

/* Edit buttons */
document.querySelectorAll('.editBtn').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.dataset.id || "0";
    const title = btn.dataset.title || '';
    const desc = btn.dataset.desc || '';
    const active = btn.dataset.active === "1";
    const image = btn.dataset.image || '';

    f_id.value = id;
    f_title.value = title;
    f_desc.value = desc;
    f_active.checked = active;
    modalTitle.textContent = 'Edit Topic';

    if (image) {
      imgPreviewTag.src = "<?= h($UPLOAD_DIR_REL) ?>" + image; // ✅ respects your upload folder
      imgPreview.style.display = 'block';
    } else {
      imgPreview.style.display = 'none';
      imgPreviewTag.src = '';
    }

    document.getElementById('f_image').value = '';
    openModal();
  });
});

/* =============================
   DELETE CONFIRM MODAL LOGIC
============================= */
const delModal = document.getElementById("delete-mc-confirm");
const delMsg = document.getElementById("delete-mc-msg");
const delClose = document.getElementById("delete-mc-close");
const delCancel = document.getElementById("delete-mc-cancel");
const delConfirmBtn = document.getElementById("delete-mc-confirm-btn");

let pendingDeleteForm = null;

function openDeleteModal(formEl, title) {
  pendingDeleteForm = formEl;
  delMsg.textContent = title ? `Delete "${title}"?` : "Delete this topic?";
  delModal.classList.add("show");
  delConfirmBtn.focus();
}

function closeDeleteModal() {
  delModal.classList.remove("show");
  pendingDeleteForm = null;
}

document.querySelectorAll(".js-open-delete-mc").forEach(btn => {
  btn.addEventListener("click", () => {
    const form = btn.closest("form.delete-mc-form");
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

// ESC closes Delete Confirm modal
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape" && delModal.classList.contains("show")) {
    closeDeleteModal();
  }
});

/* =============================
   FLOATING TOAST behavior
============================= */
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