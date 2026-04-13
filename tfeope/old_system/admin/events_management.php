<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin-login");
    exit;
}

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/admin_logger.php";

// ================= USER ROLE =================
// Supports BOTH role string + role_id
$user_role = $_SESSION['role'] ?? 'admin';        // 'admin' or 'super_admin'
$role_id   = (int)($_SESSION['role_id'] ?? 2);    // 1=super_admin, 2=admin
$is_super_admin = ($user_role === 'super_admin') || ($role_id === 1);

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
function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// ================= MEDIA PATHS (FIX RELATIVE DELETE BUG) =================
$EVENT_MEDIA_REL = "/event_media/";
$EVENT_MEDIA_ABS = __DIR__ . DIRECTORY_SEPARATOR . "/../event_media" . DIRECTORY_SEPARATOR;

function safe_media_abs(string $baseAbs, string $name): string {
    return $baseAbs . basename($name);
}

// ================= HANDLE DELETE EVENT =================
if (isset($_POST['delete_event']) && isset($_POST['event_id'])) {
    if (!$is_super_admin) {
        set_flash('error', 'Unauthorized: Only super admins can delete events.');
        header("Location: events-management");
        exit;
    }

    $id = (int)($_POST['event_id'] ?? 0);
    if ($id <= 0) {
        set_flash('error', 'Invalid event item.');
        header("Location: events-management");
        exit;
    }

    // fetch current safely
    $stmt = $conn->prepare("SELECT event_title, event_media FROM events WHERE event_id=? LIMIT 1");
    if (!$stmt) {
        set_flash('error', 'Delete failed: ' . $conn->error);
        header("Location: events-management");
        exit;
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($current) {
        // delete media file from disk
        $media = (string)($current['event_media'] ?? '');
        if ($media !== '') {
            $abs = safe_media_abs($EVENT_MEDIA_ABS, $media);
            if (is_file($abs)) @unlink($abs);
        }

        // delete row
        $del = $conn->prepare("DELETE FROM events WHERE event_id=?");
        if (!$del) {
            set_flash('error', 'Delete failed: ' . $conn->error);
            header("Location: events-management");
            exit;
        }
        $del->bind_param("i", $id);
        $ok = $del->execute();
        $del->close();

        // LOG
        $t = $current['event_title'] ?? '(unknown)';
        log_admin_action($conn, "DELETE", "Deleted event ID #{$id} (Title: {$t})");

        set_flash($ok ? 'success' : 'error', $ok ? 'Event deleted successfully.' : 'Delete failed. Please try again.');
    } else {
        set_flash('error', 'Event not found.');
    }

    header("Location: events-management");
    exit;
}

// ================= HANDLE ADD / EDIT EVENT =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event'])) {
    $id    = isset($_POST['id']) && $_POST['id'] !== "" ? (int)$_POST['id'] : null;
    $title = trim((string)($_POST['title'] ?? ""));
    $desc  = trim((string)($_POST['description'] ?? ""));
    $date  = (string)($_POST['event_date'] ?? "");
    $type  = (string)($_POST['type'] ?? "upcoming");

    $oldMedia = $_POST['old_media'] ?? null;
    $media = $oldMedia;

    $uploadOk = true;

    if (!empty($_FILES['event_media']['name'])) {
        $targetDirAbs = $EVENT_MEDIA_ABS;
        if (!is_dir($targetDirAbs)) mkdir($targetDirAbs, 0755, true);

        $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)$_FILES['event_media']['name']));
        $mediaName = time() . "_" . $safeBase;
        $targetFileAbs = $targetDirAbs . $mediaName;

        if (is_uploaded_file($_FILES['event_media']['tmp_name']) && move_uploaded_file($_FILES['event_media']['tmp_name'], $targetFileAbs)) {
            if ($oldMedia) {
                $oldAbs = safe_media_abs($EVENT_MEDIA_ABS, (string)$oldMedia);
                if (is_file($oldAbs)) @unlink($oldAbs);
            }
            $media = $mediaName;
        } else {
            $uploadOk = false;
        }
    }

    if ($id) {
        // EDIT
        $stmt = $conn->prepare(
            "UPDATE events
             SET event_title=?, event_description=?, event_date=?, event_type=?, event_media=?
             WHERE event_id=?"
        );
        if (!$stmt) {
            set_flash('error', 'Update failed: ' . $conn->error);
            header("Location: events-management");
            exit;
        }
        $stmt->bind_param("sssssi", $title, $desc, $date, $type, $media, $id);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            log_admin_action($conn, "UPDATE", "Edited event ID #{$id} (Title: {$title}, Date: {$date}, Type: {$type})");
            if (!$uploadOk && !empty($_FILES['event_media']['name'])) {
                set_flash('warning', 'Event updated, but the media upload failed (old media kept).');
            } else {
                set_flash('success', 'Event updated successfully.');
            }
        } else {
            set_flash('error', 'Update failed. Please try again.');
        }
    } else {
        // ADD
        $stmt = $conn->prepare(
            "INSERT INTO events (event_title, event_description, event_date, event_type, event_media)
             VALUES (?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            set_flash('error', 'Add failed: ' . $conn->error);
            header("Location: events-management");
            exit;
        }
        $stmt->bind_param("sssss", $title, $desc, $date, $type, $media);
        $ok = $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();

        if ($ok) {
            log_admin_action($conn, "CREATE", "Added event ID #{$newId} (Title: {$title}, Date: {$date}, Type: {$type})");
            if (!$uploadOk && !empty($_FILES['event_media']['name'])) {
                set_flash('warning', 'Event saved, but the media upload failed.');
            } else {
                set_flash('success', 'Event added successfully.');
            }
        } else {
            set_flash('error', 'Add failed. Please try again.');
        }
    }

    header("Location: events-management");
    exit;
}

// ================= FETCH EVENTS =================
$result = $conn->query("SELECT * FROM events ORDER BY event_date DESC");
$totalCount = $result ? $result->num_rows : 0;

$flash = pull_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Events Management</title>
<link rel="icon" type="image/png" href="/../static/eagles.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../admin styles/event_man.css">
<link rel="stylesheet" href="../admin styles/sidebar.css">
<style>
td img { width: 80px; height: auto; border-radius: 4px; }
td video { width: 120px; height: 80px; }

/* =============================
   DELETE CONFIRM MODAL (MATCHABLE)
============================= */
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
<h1>Events Management</h1>
      <!-- <p class="sub">Manage the Magna Carta topics</p> -->


<div class="top-buttons">
    <button class="add-btn" id="open-add-modal" type="button"><i class="fas fa-plus"></i> Add Event</button>
</div>

<?php if ($totalCount === 0): ?>
    <div class="content-area">
        <div class="empty-state">
            <i class="fas fa-calendar-xmark"></i>
            <h3>No events yet</h3>
            <p>Click “Add Event” to create your first event.</p>
        </div>
    </div>
<?php else: ?>
<table id="events-table">
    <thead>
        <tr>
            <th>Media</th>
            <th>Title</th>
            <th>Date</th>
            <th>Type</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php while($row = $result->fetch_assoc()): ?>
        <tr
            data-id="<?= (int)$row['event_id'] ?>"
            data-title="<?= h($row['event_title']) ?>"
            data-description="<?= h($row['event_description']) ?>"
            data-date="<?= h($row['event_date']) ?>"
            data-type="<?= h($row['event_type']) ?>"
            data-media="<?= h($row['event_media'] ?? '') ?>"
        >
            <td>
                <?php
                  $mediaName = (string)($row['event_media'] ?? '');
                  $absCheck = ($mediaName !== '') ? safe_media_abs($EVENT_MEDIA_ABS, $mediaName) : '';
                ?>
                <?php if ($mediaName !== '' && is_file($absCheck)): ?>
                    <?php $ext = strtolower(pathinfo($mediaName, PATHINFO_EXTENSION)); ?>
                    <?php if (in_array($ext, ['jpg','jpeg','png','gif','webp'])): ?>
                        <img class="thumb" src="<?= $EVENT_MEDIA_REL . h($mediaName) ?>" alt="Event Media">
                    <?php elseif (in_array($ext, ['mp4','webm','ogg'])): ?>
                        <video controls>
                            <source src="<?= $EVENT_MEDIA_REL . h($mediaName) ?>" type="video/<?= h($ext) ?>">
                            Your browser does not support the video tag.
                        </video>
                    <?php else: ?>
                        File
                    <?php endif; ?>
                <?php else: ?>
                    None
                <?php endif; ?>
            </td>
            <td><?= h($row['event_title']) ?></td>
            <td><?= h($row['event_date']) ?></td>
            <td><?= h(ucfirst((string)$row['event_type'])) ?></td>
            <td>
                <button class="action-btn edit-btn" type="button"><i class="fas fa-edit"></i></button>

                <?php if ($is_super_admin): ?>
                <form method="POST" class="delete-event-form" style="display:inline; margin:0;">
                    <input type="hidden" name="event_id" value="<?= (int)$row['event_id'] ?>">
                    <input type="hidden" name="delete_event" value="1">
                    <button class="action-btn delete-btn js-open-delete-event" type="button"
                        data-title="<?= h($row['event_title']) ?>">
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

<!-- ADD/EDIT MODAL -->
<div class="modal" id="event-modal">
    <div class="modal-content">
        <span class="close-btn" id="close-event">&times;</span>
        <h2 id="event-modal-title">Add Event</h2>
        <form id="event-form" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" id="event-id">
            <input type="hidden" name="old_media" id="old-media">

            <input type="text" name="title" id="event-title" placeholder="Event Title" required>
            <textarea name="description" id="event-description" placeholder="Description" rows="5"></textarea>
            <input type="date" name="event_date" id="event-date" required>

            <select name="type" id="event-type">
                <option value="upcoming">Upcoming</option>
                <option value="past">Past</option>
            </select>

            <input type="file" name="event_media" id="event-media" accept="image/*,video/*">
            <button type="submit" name="add_event" id="event-submit">Save Event</button>
        </form>
    </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="confirm-modal" id="delete-event-confirm">
  <div class="confirm-card" role="dialog" aria-modal="true" aria-labelledby="delete-event-title">
    <div class="confirm-head">
      <h3 id="delete-event-title">Confirm deletion</h3>
      <button class="confirm-x" type="button" id="delete-event-close" aria-label="Close">&times;</button>
    </div>
    <div class="confirm-body">
      <p id="delete-event-msg">Delete this event?</p>
      <p class="danger-note">This will permanently remove the event and its media from the server.</p>
    </div>
    <div class="confirm-actions">
      <button class="cbtn cbtn-cancel" type="button" id="delete-event-cancel">Cancel</button>
      <button class="cbtn cbtn-danger" type="button" id="delete-event-confirm-btn">Delete</button>
    </div>
  </div>
</div>

<script>
// Sidebar toggle
document.querySelector('.sidebar-toggle').addEventListener('click', () => {
  const sb = document.querySelector('.sidebar');
  if (sb) sb.classList.toggle('show');
});

// Modal open/close
const eventModal = document.getElementById("event-modal");
document.getElementById("open-add-modal").onclick = () => {
    document.getElementById("event-modal-title").innerText = "Add Event";
    document.getElementById("event-form").reset();
    document.getElementById("event-id").value = "";
    document.getElementById("old-media").value = "";
    document.getElementById("event-submit").name = "add_event";
    eventModal.classList.add("show");
};
document.getElementById("close-event").onclick = () => eventModal.classList.remove("show");

// ✅ DO NOT close Add/Edit modal when clicking outside
// window.onclick = e => {
//   if (e.target === eventModal) eventModal.classList.remove("show");
// };

// Edit event
document.querySelectorAll(".edit-btn").forEach(btn => {
    btn.onclick = () => {
        const row = btn.closest("tr");
        document.getElementById("event-modal-title").innerText = "Edit Event";

        document.getElementById("event-id").value = row.dataset.id;
        document.getElementById("event-title").value = row.dataset.title;
        document.getElementById("event-description").value = row.dataset.description;
        document.getElementById("event-date").value = row.dataset.date;
        document.getElementById("event-type").value = row.dataset.type;
        document.getElementById("old-media").value = row.dataset.media || "";

        document.getElementById("event-submit").name = "add_event";
        eventModal.classList.add("show");
    };
});

/* =============================
   DELETE EVENT CONFIRM MODAL
============================= */
const delModal = document.getElementById("delete-event-confirm");
const delMsg = document.getElementById("delete-event-msg");
const delClose = document.getElementById("delete-event-close");
const delCancel = document.getElementById("delete-event-cancel");
const delConfirmBtn = document.getElementById("delete-event-confirm-btn");

let pendingDeleteForm = null;

function openDeleteEventModal(formEl, title) {
  pendingDeleteForm = formEl;
  delMsg.textContent = title ? `Delete "${title}"?` : "Delete this event?";
  delModal.classList.add("show");
  delConfirmBtn.focus();
}
function closeDeleteEventModal() {
  delModal.classList.remove("show");
  pendingDeleteForm = null;
}

document.querySelectorAll(".js-open-delete-event").forEach(btn => {
  btn.addEventListener("click", () => {
    const form = btn.closest("form.delete-event-form");
    const title = btn.dataset.title || "";
    openDeleteEventModal(form, title);
  });
});

delConfirmBtn.addEventListener("click", () => {
  if (!pendingDeleteForm) return;
  pendingDeleteForm.submit();
});

delClose.addEventListener("click", closeDeleteEventModal);
delCancel.addEventListener("click", closeDeleteEventModal);

// ✅ DO NOT close delete-confirm modal when clicking outside
// delModal.addEventListener("click", (e) => {
//   if (e.target === delModal) closeDeleteEventModal();
// });

// (Optional) ESC still closes delete-confirm.
// If you want ESC to NOT close it, comment this too.
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape" && delModal.classList.contains("show")) closeDeleteEventModal();
});

/* =============================
   TOAST behavior
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