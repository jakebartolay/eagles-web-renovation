<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . "/../includes/admin_session.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/admin_logger.php";

/* =========================================================
   SUPER ADMIN CHECK (DO NOT OVERWRITE role_id)
========================================================= */
$is_super_admin = false;

if (isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 1) $is_super_admin = true;

if (!$is_super_admin && isset($_SESSION['is_super_admin']) && (int)$_SESSION['is_super_admin'] === 1) {
  $is_super_admin = true;
}

if (!$is_super_admin && isset($_SESSION['role'])) {
  $r = strtolower(trim((string)$_SESSION['role']));
  if (in_array($r, ['superadmin','super_admin','super admin','super'], true)) $is_super_admin = true;
}

if (!$is_super_admin && isset($_SESSION['role_name'])) {
  $r = strtolower(trim((string)$_SESSION['role_name']));
  if (in_array($r, ['superadmin','super_admin','super admin','super'], true)) $is_super_admin = true;
}

if (!$is_super_admin) {
  $adminId = null;
  if (isset($_SESSION['admin_id'])) $adminId = (int)$_SESSION['admin_id'];
  elseif (isset($_SESSION['user_id'])) $adminId = (int)$_SESSION['user_id'];
  elseif (isset($_SESSION['id'])) $adminId = (int)$_SESSION['id'];

  if ($adminId) {
    $tries = [
      ["sql" => "SELECT role_id FROM admins WHERE id=? LIMIT 1", "type" => "i", "field" => "role_id"],
      ["sql" => "SELECT role_id FROM admin_users WHERE id=? LIMIT 1", "type" => "i", "field" => "role_id"],
      ["sql" => "SELECT role_id FROM users WHERE id=? LIMIT 1", "type" => "i", "field" => "role_id"],
      ["sql" => "SELECT is_super_admin FROM admins WHERE id=? LIMIT 1", "type" => "i", "field" => "is_super_admin"],
      ["sql" => "SELECT is_super_admin FROM users WHERE id=? LIMIT 1", "type" => "i", "field" => "is_super_admin"],
      ["sql" => "SELECT role FROM admins WHERE id=? LIMIT 1", "type" => "i", "field" => "role"],
      ["sql" => "SELECT role_name FROM admins WHERE id=? LIMIT 1", "type" => "i", "field" => "role_name"],
    ];

    foreach ($tries as $t) {
      try {
        $stmt = $conn->prepare($t["sql"]);
        if (!$stmt) continue;

        $stmt->bind_param($t["type"], $adminId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) continue;

        $val = $row[$t["field"]] ?? null;
        if ($val === null) continue;

        if ($t["field"] === "role_id" && (int)$val === 1) { $is_super_admin = true; break; }
        if ($t["field"] === "is_super_admin" && (int)$val === 1) { $is_super_admin = true; break; }

        if (in_array($t["field"], ["role","role_name"], true)) {
          $rv = strtolower(trim((string)$val));
          if (in_array($rv, ['superadmin','super_admin','super admin','super'], true)) { $is_super_admin = true; break; }
        }
      } catch (Throwable $e) {
        // ignore
      }
    }
  }
}

$_SESSION['is_super_admin'] = $is_super_admin ? 1 : 0;

$CATEGORY   = "national_officers";
$TARGET_DIR = __DIR__ . "/officers/";
$SPEECH_DIR = __DIR__ . "/officers/speech/";

if (!is_dir($TARGET_DIR)) mkdir($TARGET_DIR, 0755, true);
if (!is_dir($SPEECH_DIR)) mkdir($SPEECH_DIR, 0755, true);

function safe_filename($original) {
  $original = basename((string)$original);
  return preg_replace('/[^A-Za-z0-9._-]/', '_', $original);
}

function upload_image($fileKey, $dirAbs, $prefix = "") {
  if (empty($_FILES[$fileKey]['name'])) return null;

  $name = safe_filename($_FILES[$fileKey]['name']);
  $newName = time() . ($prefix ? "_{$prefix}_" : "_") . $name;

  $tmp = $_FILES[$fileKey]['tmp_name'] ?? '';
  if ($tmp === '' || !is_uploaded_file($tmp)) return null;

  $dest = rtrim($dirAbs, "/\\") . "/" . $newName;
  return move_uploaded_file($tmp, $dest) ? $newName : null;
}

function delete_if_exists($pathAbs) {
  if ($pathAbs && is_file($pathAbs)) @unlink($pathAbs);
}

/* ===== Positions ===== */
$posRows = [];
$posStmt = $conn->prepare("SELECT position_name, hierarchy_level FROM officer_position_order ORDER BY hierarchy_level ASC, position_name ASC");
$posStmt->execute();
$posRes = $posStmt->get_result();
while ($r = $posRes->fetch_assoc()) $posRows[] = $r;
$posStmt->close();

/* ================= DELETE OFFICER (WITH MODAL CONFIRM) ================= */
if (isset($_POST['delete_officer']) && isset($_POST['id'])) {
  if (!$is_super_admin) {
    die("Unauthorized: Only super admins can delete officers.");
  }

  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) {
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
  }

  // fetch current files + name for log
  $stmt = $conn->prepare("SELECT name, image, speech_image FROM officers WHERE id=? AND category=? LIMIT 1");
  $stmt->bind_param("is", $id, $CATEGORY);
  $stmt->execute();
  $cur = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($cur) {
    $img = (string)($cur['image'] ?? '');
    $sp  = (string)($cur['speech_image'] ?? '');

    if ($img !== '') delete_if_exists($TARGET_DIR . basename($img));
    if ($sp !== '')  delete_if_exists($SPEECH_DIR . basename($sp));

    $del = $conn->prepare("DELETE FROM officers WHERE id=? AND category=?");
    $del->bind_param("is", $id, $CATEGORY);
    $del->execute();
    $del->close();

    $nm = $cur['name'] ?? '(unknown)';
    log_admin_action($conn, "DELETE", "Deleted officer ID #{$id} (Name: {$nm}, Category: {$CATEGORY})");
  }

  header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
  exit;
}

/* ================= ADD OFFICER ================= */
if (isset($_POST['add_officer'])) {
  $name          = trim($_POST['name'] ?? "");
  $position      = trim($_POST['position'] ?? "");
  $full_position = trim($_POST['full_position'] ?? "");
  $speech        = trim($_POST['speech'] ?? "");

  if ($name === "" || $position === "" || $full_position === "") {
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
  }

  $image        = upload_image("image", $TARGET_DIR);
  $speech_image = upload_image("speech_image", $SPEECH_DIR, "speech");

  $stmt = $conn->prepare("
    INSERT INTO officers (name, position, full_position, category, image, speech_image, speech)
    VALUES (?, ?, ?, ?, ?, ?, ?)
  ");
  $stmt->bind_param("sssssss", $name, $position, $full_position, $CATEGORY, $image, $speech_image, $speech);
  $stmt->execute();
  $newId = $stmt->insert_id;
  $stmt->close();

  log_admin_action($conn, "CREATE", "Added officer ID #{$newId} (Name: {$name}, Full Position: {$full_position})");

  header("Location: " . $_SERVER['HTTP_REFERER']);
  exit;
}

/* ================= EDIT OFFICER ================= */
if (isset($_POST['edit_officer'])) {
  $id            = (int)($_POST['id'] ?? 0);
  $name          = trim($_POST['name'] ?? "");
  $position      = trim($_POST['position'] ?? "");
  $full_position = trim($_POST['full_position'] ?? "");
  $speech        = trim($_POST['speech'] ?? "");

  $oldImage       = $_POST['old_image'] ?? null;
  $oldSpeechImage = $_POST['old_speech_image'] ?? null;

  if ($id <= 0 || $name === "" || $position === "" || $full_position === "") {
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
  }

  $image        = $oldImage;
  $speech_image = $oldSpeechImage;

  $newMain = upload_image("image", $TARGET_DIR);
  if ($newMain) {
    $image = $newMain;
    if ($oldImage) delete_if_exists($TARGET_DIR . basename($oldImage));
  }

  $newSpeech = upload_image("speech_image", $SPEECH_DIR, "speech");
  if ($newSpeech) {
    $speech_image = $newSpeech;
    if ($oldSpeechImage) delete_if_exists($SPEECH_DIR . basename($oldSpeechImage));
  }

  $stmt = $conn->prepare("
    UPDATE officers
    SET name=?, position=?, full_position=?, category=?, image=?, speech_image=?, speech=?
    WHERE id=?
  ");
  $stmt->bind_param("sssssssi", $name, $position, $full_position, $CATEGORY, $image, $speech_image, $speech, $id);
  $stmt->execute();
  $stmt->close();

  log_admin_action($conn, "UPDATE", "Edited officer ID #{$id} (Name: {$name}, Full Position: {$full_position})");

  header("Location: " . $_SERVER['HTTP_REFERER']);
  exit;
}

/* ================= BLOCK OLD GET DELETE ================= */
if (isset($_GET['delete'])) {
  $attemptId = (int)($_GET['delete'] ?? 0);
  if ($attemptId > 0) {
    log_admin_action($conn, "DENY", "Attempted delete blocked (GET) for officer ID #{$attemptId}");
  }
  header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
  exit;
}

/* ================= LIST ================= */
$listStmt = $conn->prepare("
  SELECT o.*, COALESCE(p.hierarchy_level, 999) AS hlevel
  FROM officers o
  LEFT JOIN officer_position_order p
    ON o.full_position = p.position_name
  WHERE o.category = ?
  ORDER BY COALESCE(p.hierarchy_level, 999) ASC, o.name ASC
");
$listStmt->bind_param("s", $CATEGORY);
$listStmt->execute();
$result = $listStmt->get_result();
$totalCount = $result ? $result->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>National Officers Management</title>
<link rel="icon" type="image/png" href="static/eagles.png">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../admin styles/sidebar.css">
<link rel="stylesheet" href="../admin styles/officer_man.css">

<style>
/* DELETE CONFIRM MODAL */
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
.cbtn-danger{ background:#c5303f; color:#fff; }
</style>
</head>
<body>

<button class="sidebar-toggle" type="button" aria-label="Toggle Sidebar"><i class="fas fa-bars"></i></button>
<?php include 'sidebar.php'; ?>

<div class="main-content">
  <div class="topbar">
    <div>
      <h1>National Officers Management</h1>
    </div>
    <button class="add-btn" id="open-add-modal" type="button">+ Add Officer</button>
  </div>

  <?php if ($totalCount === 0): ?>
    <div class="empty">
      <p>No national officers yet. Click “Add Officer”.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:90px;">Image</th>
            <th>Name</th>
            <th>Position</th>
            <th>Full Position</th>
            <th style="width:150px;">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr
            data-id="<?= (int)$row['id'] ?>"
            data-name="<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>"
            data-position="<?= htmlspecialchars($row['position'], ENT_QUOTES) ?>"
            data-full_position="<?= htmlspecialchars($row['full_position'], ENT_QUOTES) ?>"
            data-speech="<?= htmlspecialchars($row['speech'] ?? '', ENT_QUOTES) ?>"
            data-image="<?= htmlspecialchars($row['image'] ?? '', ENT_QUOTES) ?>"
            data-speech_image="<?= htmlspecialchars($row['speech_image'] ?? '', ENT_QUOTES) ?>"
          >
            <td>
              <?php if (!empty($row['image'])): ?>
                <img src="/officers/<?= htmlspecialchars($row['image']) ?>" class="thumb" alt="thumb">
              <?php else: ?>
                <div class="thumb ph"><i class="fa-regular fa-image"></i></div>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['position']) ?></td>
            <td><span class="pill"><?= htmlspecialchars($row['full_position']) ?></span></td>
            <td class="actions">
              <button class="icon-btn edit-btn" type="button" title="Edit">
                <i class="fas fa-pen"></i>
              </button>

              <?php if ($is_super_admin): ?>
                <!-- hidden delete_officer makes form.submit() trigger PHP delete -->
                <form method="POST" class="delete-officer-form" style="display:inline; margin:0;">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <input type="hidden" name="delete_officer" value="1">
                  <button class="icon-btn js-open-delete-officer" type="button" title="Delete"
                          data-name="<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <!-- ADD/EDIT MODAL -->
  <div class="modal" id="officer-modal" aria-hidden="true">
    <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="modal-title">
      <div class="modal-head">
        <h2 id="modal-title">Add Officer</h2>
        <button class="close-btn" id="close-officer" type="button" aria-label="Close">&times;</button>
      </div>

      <form id="officer-form" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" id="officer-id">
        <input type="hidden" name="old_image" id="old-image">
        <input type="hidden" name="old_speech_image" id="old-speech-image">

        <div class="grid">
          <div class="field">
            <label>Name</label>
            <input type="text" name="name" id="officer-name" placeholder="Full name" required>
          </div>

          <div class="field">
            <label>Card Position (short)</label>
            <input type="text" name="position" id="officer-position" placeholder="e.g., Vice President for Luzon" required>
          </div>

          <div class="field full">
            <label>Full Position (ordered)</label>
            <select name="full_position" id="officer-full-position" required>
              <option value="">Select from officer_position_order</option>
              <?php foreach ($posRows as $p): ?>
                <option value="<?= htmlspecialchars($p['position_name'], ENT_QUOTES) ?>">
                  <?= (int)$p['hierarchy_level'] ?> — <?= htmlspecialchars($p['position_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small class="hint">If this list is empty, insert rows into officer_position_order first.</small>
          </div>

          <div class="field full">
            <label>Speech</label>
            <textarea name="speech" id="officer-speech" placeholder="Speech / quote" rows="4"></textarea>
          </div>

          <div class="field">
            <label>Main Image</label>
            <input type="file" name="image" id="officer-image" accept="image/*">
            <img id="preview-image" class="thumb preview" style="display:none;" alt="preview">
          </div>

          <div class="field">
            <label>Speech Image</label>
            <input type="file" name="speech_image" id="officer-speech-image" accept="image/*">
            <img id="preview-speech-image" class="thumb preview" style="display:none;" alt="preview speech">
          </div>
        </div>

        <div class="modal-actions">
          <button class="btn" type="button" id="cancel-modal">Cancel</button>
          <button class="btn primary" type="submit" id="officer-submit" name="add_officer">Save</button>
        </div>
      </form>
    </div>
  </div>

  <!-- DELETE CONFIRM MODAL -->
  <div class="confirm-modal" id="delete-officer-confirm">
    <div class="confirm-card" role="dialog" aria-modal="true" aria-labelledby="delete-officer-title">
      <div class="confirm-head">
        <h3 id="delete-officer-title">Confirm deletion</h3>
        <button class="confirm-x" type="button" id="delete-officer-close" aria-label="Close">&times;</button>
      </div>
      <div class="confirm-body">
        <p id="delete-officer-msg">Delete this officer?</p>
        <p class="danger-note">This will permanently remove the officer and images from the server.</p>
      </div>
      <div class="confirm-actions">
        <button class="cbtn cbtn-cancel" type="button" id="delete-officer-cancel">Cancel</button>
        <button class="cbtn cbtn-danger" type="button" id="delete-officer-confirm-btn">Delete</button>
      </div>
    </div>
  </div>

</div>

<script>
const officerModal = document.getElementById("officer-modal");
const officerForm = document.getElementById("officer-form");
const modalTitle = document.getElementById("modal-title");
const submitBtn = document.getElementById("officer-submit");

const previewImage = document.getElementById("preview-image");
const previewSpeechImage = document.getElementById("preview-speech-image");

const oldImageInput = document.getElementById("old-image");
const oldSpeechInput = document.getElementById("old-speech-image");

function openModal(){
  officerModal.classList.add("show");
  officerModal.setAttribute("aria-hidden", "false");
  document.body.classList.add("no-scroll");
}
function closeModal(){
  officerModal.classList.remove("show");
  officerModal.setAttribute("aria-hidden", "true");
  document.body.classList.remove("no-scroll");
}

function resetPreviews(){
  previewImage.style.display = "none";
  previewImage.removeAttribute("src");
  previewSpeechImage.style.display = "none";
  previewSpeechImage.removeAttribute("src");
}

document.getElementById("open-add-modal").addEventListener("click", () => {
  modalTitle.textContent = "Add Officer";
  officerForm.reset();
  resetPreviews();

  oldImageInput.value = "";
  oldSpeechInput.value = "";
  document.getElementById("officer-id").value = "";

  submitBtn.name = "add_officer";
  openModal();
});

document.getElementById("close-officer").addEventListener("click", closeModal);
document.getElementById("cancel-modal").addEventListener("click", closeModal);

officerModal.addEventListener("click", (e) => {
  if (e.target === officerModal) closeModal();
});

window.addEventListener("keydown", (e) => {
  if (e.key === "Escape" && officerModal.classList.contains("show")) closeModal();
});

document.getElementById("officer-image").addEventListener("change", function(){
  const file = this.files && this.files[0];
  if (file){
    previewImage.src = URL.createObjectURL(file);
    previewImage.style.display = "block";
  } else {
    previewImage.style.display = "none";
    previewImage.removeAttribute("src");
  }
});

document.getElementById("officer-speech-image").addEventListener("change", function(){
  const file = this.files && this.files[0];
  if (file){
    previewSpeechImage.src = URL.createObjectURL(file);
    previewSpeechImage.style.display = "block";
  } else {
    previewSpeechImage.style.display = "none";
    previewSpeechImage.removeAttribute("src");
  }
});

document.querySelectorAll(".edit-btn").forEach(btn => {
  btn.addEventListener("click", () => {
    const row = btn.closest("tr");
    if (!row) return;

    modalTitle.textContent = "Edit Officer";

    document.getElementById("officer-id").value = row.dataset.id || "";
    document.getElementById("officer-name").value = row.dataset.name || "";
    document.getElementById("officer-position").value = row.dataset.position || "";
    document.getElementById("officer-full-position").value = row.dataset.full_position || "";
    document.getElementById("officer-speech").value = row.dataset.speech || "";

    oldImageInput.value = row.dataset.image || "";
    oldSpeechInput.value = row.dataset.speech_image || "";

    if (row.dataset.image){
      previewImage.src = "officers/" + row.dataset.image;
      previewImage.style.display = "block";
    } else {
      previewImage.style.display = "none";
      previewImage.removeAttribute("src");
    }

    if (row.dataset.speech_image){
      previewSpeechImage.src = "officers/speech/" + row.dataset.speech_image;
      previewSpeechImage.style.display = "block";
    } else {
      previewSpeechImage.style.display = "none";
      previewSpeechImage.removeAttribute("src");
    }

    submitBtn.name = "edit_officer";
    openModal();
  });
});

/* Sidebar toggle */
const sidebar = document.querySelector(".sidebar");
document.querySelector(".sidebar-toggle")?.addEventListener("click", () => sidebar?.classList.toggle("show"));

/* =============================
   DELETE OFFICER CONFIRM MODAL
============================= */
const delModal = document.getElementById("delete-officer-confirm");
const delMsg = document.getElementById("delete-officer-msg");
const delClose = document.getElementById("delete-officer-close");
const delCancel = document.getElementById("delete-officer-cancel");
const delConfirmBtn = document.getElementById("delete-officer-confirm-btn");

let pendingDeleteForm = null;

function openDeleteOfficerModal(formEl, name) {
  pendingDeleteForm = formEl;
  delMsg.textContent = name ? `Delete "${name}"?` : "Delete this officer?";
  delModal.classList.add("show");
  delConfirmBtn.focus();
}
function closeDeleteOfficerModal() {
  delModal.classList.remove("show");
  pendingDeleteForm = null;
}

document.querySelectorAll(".js-open-delete-officer").forEach(btn => {
  btn.addEventListener("click", () => {
    const form = btn.closest("form.delete-officer-form");
    const name = btn.dataset.name || "";
    openDeleteOfficerModal(form, name);
  });
});

delConfirmBtn.addEventListener("click", () => {
  if (!pendingDeleteForm) return;
  pendingDeleteForm.submit(); // works because delete_officer is a hidden input
});

delClose.addEventListener("click", closeDeleteOfficerModal);
delCancel.addEventListener("click", closeDeleteOfficerModal);

// click outside closes
delModal.addEventListener("click", (e) => {
  if (e.target === delModal) closeDeleteOfficerModal();
});

// ESC closes
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape" && delModal.classList.contains("show")) {
    closeDeleteOfficerModal();
  }
});
</script>

</body>
</html>
<?php $listStmt->close(); ?>
