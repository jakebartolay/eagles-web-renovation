<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin-login");
    exit;
}

/*
  ✅ FIXED PATHS (when this file is inside /admin)
  - Use DOCUMENT_ROOT for uploads + absolute URLs for images
  - Make sure db.php path is correct (adjust if your db.php is in /includes/db.php)
*/
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/admin_logger.php";

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not found. Check db.php.");
}

/* =========================================================
   FLASH (Toast Notifications)
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

/* =========================================================
   HELPERS
========================================================= */
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* =========================================================
   UPLOADS PATHS  ✅ FIXED
   - memorandum folder is at SITE ROOT: /memorandum/
========================================================= */
$UPLOAD_DIR_REL = "/memorandum/"; // ✅ leading slash so it works from /admin
$UPLOAD_DIR_ABS = rtrim((string)$_SERVER['DOCUMENT_ROOT'], "/\\") . DIRECTORY_SEPARATOR . "memorandum" . DIRECTORY_SEPARATOR;

/* =========================================================
   PATH HELPERS
========================================================= */
function safe_join_upload_path(string $baseAbs, string $dbName): string {
    return $baseAbs . basename($dbName);
}

function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    if ($items === false) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) rrmdir($path);
        else @unlink($path);
    }
    @rmdir($dir);
}

/* ================= DELETE MEMO ================= */
if (isset($_POST['delete_memo']) && isset($_POST['memo_id'])) {
    if ((int)($_SESSION['role_id'] ?? 0) === 1) {
        $memo_id = (int)$_POST['memo_id'];

        // fetch title for log
        $tStmt = $conn->prepare("SELECT memo_title FROM memorandum WHERE memo_id=? LIMIT 1");
        if (!$tStmt) {
            set_flash('error', 'Delete failed: ' . $conn->error);
            header("Location: memorandum");
            exit;
        }
        $tStmt->bind_param("i", $memo_id);
        $tStmt->execute();
        $tRow = $tStmt->get_result()->fetch_assoc();
        $tStmt->close();
        $memoTitle = $tRow ? $tRow['memo_title'] : "(unknown)";

        // delete images from disk first
        $pageQuery = $conn->prepare("SELECT page_image FROM memorandum_pages WHERE memo_id=?");
        if ($pageQuery) {
            $pageQuery->bind_param("i", $memo_id);
            $pageQuery->execute();
            $res = $pageQuery->get_result();

            while ($pg = $res->fetch_assoc()) {
                $dbVal = (string)$pg['page_image'];

                // allow future "memo_id/filename.jpg" values safely
                $candidateRel = str_replace(['..', '\\'], ['', '/'], $dbVal);
                $candidateAbs = $UPLOAD_DIR_ABS . $candidateRel;
                $candidateAbs = str_replace('/', DIRECTORY_SEPARATOR, $candidateAbs);

                $realBase = realpath($UPLOAD_DIR_ABS);
                $realFile = realpath($candidateAbs);

                if ($realBase && $realFile && strpos($realFile, $realBase) === 0 && is_file($realFile)) {
                    @unlink($realFile);
                } else {
                    $fallback = safe_join_upload_path($UPLOAD_DIR_ABS, $dbVal);
                    if (is_file($fallback)) @unlink($fallback);
                }
            }
            $pageQuery->close();
        }

        // OPTIONAL: if you later use /memorandum/{memo_id}/
        $memoFolder = $UPLOAD_DIR_ABS . $memo_id . DIRECTORY_SEPARATOR;
        rrmdir($memoFolder);

        // delete DB rows
        $stmtDelPages = $conn->prepare("DELETE FROM memorandum_pages WHERE memo_id=?");
        if ($stmtDelPages) {
            $stmtDelPages->bind_param("i", $memo_id);
            $stmtDelPages->execute();
            $stmtDelPages->close();
        }

        $stmtDelMemo = $conn->prepare("DELETE FROM memorandum WHERE memo_id=?");
        if (!$stmtDelMemo) {
            set_flash('error', 'Delete failed: ' . $conn->error);
            header("Location: memorandum");
            exit;
        }
        $stmtDelMemo->bind_param("i", $memo_id);
        $ok = $stmtDelMemo->execute();
        $stmtDelMemo->close();

        log_admin_action($conn, "DELETE", "Deleted memorandum ID #{$memo_id} (Title: {$memoTitle})");

        set_flash($ok ? 'success' : 'error', $ok ? 'Memorandum deleted successfully.' : 'Delete failed. Please try again.');
        header("Location: memorandum");
        exit;
    } else {
        set_flash('error', 'You do not have permission to delete memorandums.');
        header("Location: memorandum");
        exit;
    }
}

/* ================= ADD MEMO ================= */
if (isset($_POST['add_memo'])) {
    $title = trim((string)($_POST['memo_title'] ?? ""));
    $status = trim((string)($_POST['memo_status'] ?? ""));
    $description = trim((string)($_POST['memo_description'] ?? ""));

    $stmt = $conn->prepare("INSERT INTO memorandum (memo_title, memo_status, memo_description) VALUES (?,?,?)");
    if (!$stmt) {
        set_flash('error', 'Add failed: ' . $conn->error);
        header("Location: memorandum");
        exit;
    }
    $stmt->bind_param("sss", $title, $status, $description);
    $okInsert = $stmt->execute();
    $memo_id = $stmt->insert_id;
    $stmt->close();

    if (!$okInsert || $memo_id <= 0) {
        set_flash('error', 'Add failed. Please try again.');
        header("Location: memorandum");
        exit;
    }

    $uploadedPages = 0;

    if (!empty($_FILES['memo_pages']['name'][0])) {
        $targetDirAbs = $UPLOAD_DIR_ABS;

        if (!is_dir($targetDirAbs)) mkdir($targetDirAbs, 0755, true);

        foreach ($_FILES['memo_pages']['name'] as $index => $filename) {
            if ((int)($_FILES['memo_pages']['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;

            $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)$filename));
            $imageName = time() . "_" . $memo_id . "_" . $index . "_" . $safeBase;
            $targetFileAbs = $targetDirAbs . $imageName;

            if (move_uploaded_file($_FILES['memo_pages']['tmp_name'][$index], $targetFileAbs)) {
                $uploadedPages++;
                $pageNum = $index + 1;

                $dbImageValue = $imageName;

                $stmtPage = $conn->prepare("INSERT INTO memorandum_pages (memo_id, page_image, page_number) VALUES (?,?,?)");
                if ($stmtPage) {
                    $stmtPage->bind_param("isi", $memo_id, $dbImageValue, $pageNum);
                    $stmtPage->execute();
                    $stmtPage->close();
                }
            }
        }
    }

    log_admin_action($conn, "CREATE", "Added memorandum ID #{$memo_id} (Title: {$title}, Status: {$status}, Pages uploaded: {$uploadedPages})");

    if ($uploadedPages > 0) set_flash('success', "Memorandum added successfully. Pages uploaded: {$uploadedPages}.");
    else set_flash('success', "Memorandum added successfully.");

    header("Location: memorandum");
    exit;
}

/* ================= EDIT MEMO ================= */
if (isset($_POST['edit_memo'])) {
    $memo_id = (int)($_POST['memo_id'] ?? 0);
    $title = trim((string)($_POST['memo_title'] ?? ""));
    $status = trim((string)($_POST['memo_status'] ?? ""));
    $description = trim((string)($_POST['memo_description'] ?? ""));

    $stmt = $conn->prepare("UPDATE memorandum SET memo_title=?, memo_status=?, memo_description=? WHERE memo_id=?");
    if (!$stmt) {
        set_flash('error', 'Update failed: ' . $conn->error);
        header("Location: memorandum");
        exit;
    }
    $stmt->bind_param("sssi", $title, $status, $description, $memo_id);
    $okUpdate = $stmt->execute();
    $stmt->close();

    if (!$okUpdate) {
        set_flash('error', 'Update failed. Please try again.');
        header("Location: memorandum");
        exit;
    }

    $uploadedPages = 0;

    $hasNewUploads = !empty($_FILES['memo_pages']['name'][0]) && (int)($_FILES['memo_pages']['error'][0] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($hasNewUploads) {
        $targetDirAbs = $UPLOAD_DIR_ABS;
        if (!is_dir($targetDirAbs)) mkdir($targetDirAbs, 0755, true);

        // delete old files
        $oldStmt = $conn->prepare("SELECT page_image FROM memorandum_pages WHERE memo_id=?");
        if ($oldStmt) {
            $oldStmt->bind_param("i", $memo_id);
            $oldStmt->execute();
            $oldRes = $oldStmt->get_result();
            while ($old = $oldRes->fetch_assoc()) {
                $dbVal = (string)$old['page_image'];

                $candidateRel = str_replace(['..', '\\'], ['', '/'], $dbVal);
                $candidateAbs = $UPLOAD_DIR_ABS . $candidateRel;
                $candidateAbs = str_replace('/', DIRECTORY_SEPARATOR, $candidateAbs);

                $realBase = realpath($UPLOAD_DIR_ABS);
                $realFile = realpath($candidateAbs);

                if ($realBase && $realFile && strpos($realFile, $realBase) === 0 && is_file($realFile)) {
                    @unlink($realFile);
                } else {
                    $fallback = safe_join_upload_path($UPLOAD_DIR_ABS, $dbVal);
                    if (is_file($fallback)) @unlink($fallback);
                }
            }
            $oldStmt->close();
        }

        // delete old rows
        $delOld = $conn->prepare("DELETE FROM memorandum_pages WHERE memo_id=?");
        if ($delOld) {
            $delOld->bind_param("i", $memo_id);
            $delOld->execute();
            $delOld->close();
        }

        // upload new pages
        foreach ($_FILES['memo_pages']['name'] as $index => $filename) {
            if ((int)($_FILES['memo_pages']['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;

            $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)$filename));
            $imageName = time() . "_" . $memo_id . "_" . $index . "_" . $safeBase;

            $targetFileAbs = $UPLOAD_DIR_ABS . $imageName;

            if (move_uploaded_file($_FILES['memo_pages']['tmp_name'][$index], $targetFileAbs)) {
                $uploadedPages++;
                $pageNum = $index + 1;

                $dbImageValue = $imageName;

                $stmtPage = $conn->prepare("INSERT INTO memorandum_pages (memo_id, page_image, page_number) VALUES (?,?,?)");
                if ($stmtPage) {
                    $stmtPage->bind_param("isi", $memo_id, $dbImageValue, $pageNum);
                    $stmtPage->execute();
                    $stmtPage->close();
                }
            }
        }
    }

    $extra = ($uploadedPages > 0) ? ", Pages replaced: {$uploadedPages}" : ", Pages unchanged";
    log_admin_action($conn, "UPDATE", "Edited memorandum ID #{$memo_id} (Title: {$title}, Status: {$status}{$extra})");

    if ($uploadedPages > 0) set_flash('success', "Memorandum updated successfully. Pages replaced: {$uploadedPages}.");
    else set_flash('success', "Memorandum updated successfully.");

    header("Location: memorandum");
    exit;
}

/* ================= FETCH MEMO LIST ================= */
$memoList = $conn->query("SELECT memo_id, memo_title, memo_status, memo_description, created_at FROM memorandum ORDER BY memo_id DESC");

$pages = [];
$pageQuery = $conn->query("SELECT * FROM memorandum_pages ORDER BY page_number ASC");
while ($p = $pageQuery->fetch_assoc()) {
    $pages[$p['memo_id']][] = $p;
}

/* flash for UI */
$flash = pull_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Memorandum Management</title>

<link rel="icon" type="image/png" href="/static/eagles.png">
<link rel="stylesheet" href="../admin styles/sidebar.css">
<link rel="stylesheet" href="../admin styles/memorandum.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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

  .btn{
    border:0;
    cursor:pointer;
    border-radius:12px;
    padding:10px 14px;
    font-weight:700;
    font-size:13px;
  }
  .btn-cancel{ background:#eef2f7; color:#0b0f1a; }
  .btn-cancel:hover{ filter:brightness(.98); }

  .btn-danger{ background:#c5303f; color:#fff; }
  .btn-danger:hover{ filter:brightness(.98); }
</style>
</head>

<body>

<button class="sidebar-toggle" type="button" aria-label="Toggle sidebar">
  <i class="fas fa-bars"></i>
</button>

<?php include 'sidebar.php'; ?>

<!-- TOAST NOTIFICATION -->
<?php if ($flash && !empty($flash['message'])): ?>
  <div class="toast-wrap" id="toastWrap" data-type="<?= h($flash['type'] ?? 'info') ?>">
    <div class="toast">
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

  <div class="page-header">
    <h1>Memorandum</h1>
    <button class="add-btn" id="open-add-modal" type="button">+ Add Memorandum</button>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Title</th>
          <th>Status</th>
          <th>Description</th>
          <th>Pages</th>
          <th>Actions</th>
        </tr>
      </thead>


<tbody>
<?php if ($memoList && $memoList->num_rows > 0): ?>
    <?php while ($row = $memoList->fetch_assoc()): ?>
        <tr>
          <td><?= h($row['memo_title']) ?></td>
          <td><?= h($row['memo_status']) ?></td>
          <td class="desc-cell"><?= h($row['memo_description']) ?></td>
          <td>
            <?php if (!empty($pages[$row['memo_id']])): ?>
              <div class="pages-grid">
                <?php foreach ($pages[$row['memo_id']] as $pg): ?>
                  <img
                    src="<?= $UPLOAD_DIR_REL . h($pg['page_image']) ?>"
                    class="thumb"
                    alt="Page <?= (int)$pg['page_number'] ?>"
                    loading="lazy"
                  >
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <span class="no-pages">No Pages</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="action-buttons">
              <button class="edit-btn"
                type="button"
                data-id="<?= (int)$row['memo_id'] ?>"
                data-title="<?= h($row['memo_title']) ?>"
                data-status="<?= h($row['memo_status']) ?>"
                data-description="<?= h($row['memo_description']) ?>">
                <i class="fas fa-edit"></i>
              </button>

              <?php if ((int)($_SESSION['role_id'] ?? 0) === 1): ?>
                <form method="POST" class="delete-form" style="margin:0;">
                  <input type="hidden" name="memo_id" value="<?= (int)$row['memo_id'] ?>">
                  <input type="hidden" name="delete_memo" value="1">
                  <button type="button"
                          class="delete-btn js-open-delete"
                          data-title="<?= h($row['memo_title']) ?>">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
    <?php endwhile; ?>
<?php else: ?>
    <tr>
        <td colspan="5" style="text-align:center; padding:20px; font-style:italic; color:#555;">
            No Memorandum Yet
        </td>
    </tr>
<?php endif; ?>
</tbody>


    </table>
  </div>

</div>

<!-- ADD MODAL -->
<div class="modal" id="memo-modal">
  <div class="modal-content">
    <button class="close-btn" id="close-modal" type="button" aria-label="Close">&times;</button>
    <h2>Add Memorandum</h2>
    <form method="POST" enctype="multipart/form-data">
      <input type="text" name="memo_title" placeholder="Memorandum Title" required>
      <textarea name="memo_description" placeholder="Memorandum Description" rows="4" required></textarea>
      <select name="memo_status">
        <option value="Published">Published</option>
        <option value="Draft">Draft</option>
      </select>
      <label class="file-label">Upload Pages (Multiple Images Allowed)</label>
      <input type="file" name="memo_pages[]" multiple accept="image/*">
      <button type="submit" name="add_memo">Save Memorandum</button>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal" id="edit-modal">
  <div class="modal-content">
    <button class="close-btn" id="close-edit" type="button" aria-label="Close">&times;</button>
    <h2>Edit Memorandum</h2>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="memo_id" id="edit-id">
      <input type="text" name="memo_title" id="edit-title" placeholder="Memorandum Title" required>
      <textarea name="memo_description" id="edit-description" rows="4" placeholder="Memorandum Description" required></textarea>
      <select name="memo_status" id="edit-status">
        <option value="Published">Published</option>
        <option value="Draft">Draft</option>
      </select>
      <label class="file-label">Upload Pages (Multiple Images Allowed)</label>
      <input type="file" name="memo_pages[]" multiple accept="image/*">
      <button type="submit" name="edit_memo">Save Changes</button>
    </form>
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
      <p id="delete-msg">Delete this memorandum?</p>
      <p class="danger-note">This will permanently remove the memorandum and its uploaded pages from the server.</p>
    </div>
    <div class="confirm-actions">
      <button class="btn btn-cancel" type="button" id="delete-cancel">Cancel</button>
      <button class="btn btn-danger" type="button" id="delete-confirm-btn">Delete</button>
    </div>
  </div>
</div>

<script>
  const modal = document.getElementById("memo-modal");
  const editModal = document.getElementById("edit-modal");

  document.getElementById("open-add-modal").onclick = () => modal.classList.add("show");
  document.getElementById("close-modal").onclick = () => modal.classList.remove("show");
  document.getElementById("close-edit").onclick = () => editModal.classList.remove("show");

  // sidebar toggle
  const sidebar = document.querySelector('.sidebar');
  document.querySelector('.sidebar-toggle').addEventListener('click', () => {
    if (sidebar) sidebar.classList.toggle('show');
  });

  document.querySelectorAll(".edit-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      document.getElementById("edit-id").value = btn.dataset.id;
      document.getElementById("edit-title").value = btn.dataset.title;
      document.getElementById("edit-description").value = btn.dataset.description;
      document.getElementById("edit-status").value = btn.dataset.status;
      editModal.classList.add("show");
    });
  });

  // ✅ DO NOT close Add/Edit modal when clicking outside
  // window.onclick = e => {
  //   if (e.target === modal) modal.classList.remove("show");
  //   if (e.target === editModal) editModal.classList.remove("show");
  // };

  /* =============================
     DELETE CONFIRM MODAL LOGIC
  ============================== */
  const delModal = document.getElementById("delete-confirm");
  const delMsg = document.getElementById("delete-msg");
  const delClose = document.getElementById("delete-close");
  const delCancel = document.getElementById("delete-cancel");
  const delConfirmBtn = document.getElementById("delete-confirm-btn");

  let pendingDeleteForm = null;

  function openDeleteModal(formEl, memoTitle) {
    pendingDeleteForm = formEl;
    delMsg.textContent = memoTitle ? `Delete "${memoTitle}"?` : "Delete this memorandum?";
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

  // ✅ DO NOT close delete-confirm modal when clicking outside
  // delModal.addEventListener("click", (e) => {
  //   if (e.target === delModal) closeDeleteModal();
  // });

  // (Optional) ESC closes delete modal
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && delModal.classList.contains("show")) closeDeleteModal();
  });

  /* =============================
     TOAST behavior
  ============================== */
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