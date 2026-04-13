<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
  header("Location: admin-login");
  exit;
}

require_once __DIR__ . "/../includes/db.php";
if (!isset($conn) || !($conn instanceof mysqli)) {
  die("Database connection not found. Check db.php.");
}

require_once __DIR__ . "/../includes/admin_logger.php";

/* ================= HELPERS ================= */
function h($v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function up($v): string {
  return strtoupper(trim((string)$v));
}

/* ================= PERMISSIONS =================
   Super Admin (role_id=1): Create / Read / Update / Delete
   Admin      (role_id=2): Create / Read / Update only (NO Delete)
   IMPORTANT: Delete is ONLY allowed when role_id is exactly 1.
================================================= */
$roleId = (int)($_SESSION['role_id'] ?? 0);

$isSuperAdmin = ($roleId === 1);
$isAdmin      = ($roleId === 2);

$canAdd    = ($isSuperAdmin || $isAdmin);
$canEdit   = ($isSuperAdmin || $isAdmin);
$canDelete = ($roleId === 1);

$hasAnyAction = ($canAdd || $canEdit || $canDelete);

/* ================= DELETE APPOINTED OFFICER ================= */
if (isset($_GET['delete_id'])) {
  if (!$canDelete) {
    $attemptId = (int)($_GET['delete_id'] ?? 0);
    if ($attemptId > 0) {
      log_admin_action($conn, "DENY", "Attempted delete blocked for appointed officer ID #{$attemptId}");
    }
    header("Location: appointed-management");
    exit;
  }

  $delete_id = (int)$_GET['delete_id'];

  // Fetch details first (for nicer logs)
  $before = null;
  $s0 = $conn->prepare("SELECT region, committee, position, name FROM appointed WHERE id=? LIMIT 1");
  $s0->bind_param("i", $delete_id);
  $s0->execute();
  $before = $s0->get_result()->fetch_assoc();
  $s0->close();

  $stmt = $conn->prepare("DELETE FROM appointed WHERE id = ?");
  $stmt->bind_param("i", $delete_id);
  $stmt->execute();
  $stmt->close();

  $desc = "Deleted appointed officer ID #{$delete_id}";
  if ($before) {
    $desc .= " ({$before['name']} - {$before['position']} / {$before['committee']} / {$before['region']})";
  }
  log_admin_action($conn, "DELETE", $desc);

  header("Location: appointed-management");
  exit;
}

/* ================= ADD OFFICER (MANUAL OR CSV) ================= */
if (isset($_POST['add_appointed'])) {
  if (!$canAdd) die("No permission.");

  $mode = (string)($_POST['add_mode'] ?? 'manual');

  /* ---------- CSV MODE ---------- */
  if ($mode === "csv") {
    if (!isset($_FILES['import_csv']) || $_FILES['import_csv']['error'] !== UPLOAD_ERR_OK) {
      die("CSV upload failed.");
    }

    $file_tmp = $_FILES['import_csv']['tmp_name'];
    $handle = fopen($file_tmp, "r");
    if (!$handle) die("Failed to open CSV.");

    $firstLine = fgets($handle);
    rewind($handle);
    $delimiter = (substr_count((string)$firstLine, ';') > substr_count((string)$firstLine, ',')) ? ';' : ',';

    // Skip header row (keeps your existing behavior)
    fgetcsv($handle, 0, $delimiter);

    $stmt = $conn->prepare("
      INSERT INTO appointed (region, committee, position, name)
      VALUES (?, ?, ?, ?)
    ");

    $inserted = 0;
    $failed = 0;

    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
      if (count($data) < 4) { $failed++; continue; }

      $region    = up($data[0] ?? '');
      $committee = up($data[1] ?? '');
      $position  = up($data[2] ?? '');
      $name      = trim((string)($data[3] ?? ''));

      if ($region === '' || $committee === '' || $position === '' || $name === '') {
        $failed++;
        continue;
      }

      $stmt->bind_param("ssss", $region, $committee, $position, $name);
      if ($stmt->execute()) $inserted++; else $failed++;
    }

    $stmt->close();
    fclose($handle);

    log_admin_action($conn, "CREATE", "Imported appointed officers via CSV (Inserted: {$inserted}, Failed: {$failed})");

    header("Location: appointed-management?import=ok&inserted={$inserted}&failed={$failed}");
    exit;
  }

  /* ---------- MANUAL MODE ---------- */
  if ($mode === "manual") {
    $region    = up($_POST['region'] ?? '');
    $committee = up($_POST['committee'] ?? '');
    $position  = up($_POST['position'] ?? '');
    $name      = trim((string)($_POST['name'] ?? ''));

    if ($region === '' || $committee === '' || $position === '' || $name === '') {
      die("All fields are required.");
    }

    $stmt = $conn->prepare("
      INSERT INTO appointed (region, committee, position, name)
      VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("ssss", $region, $committee, $position, $name);
    $stmt->execute();
    $newId = (int)$stmt->insert_id;
    $stmt->close();

    log_admin_action($conn, "CREATE", "Added appointed officer ID #{$newId} ({$name} - {$position} / {$committee} / {$region})");

    header("Location: appointed-management");
    exit;
  }
}

/* ================= EDIT APPOINTED OFFICER ================= */
if (isset($_POST['edit_appointed'])) {
  if (!$canEdit) die("No permission.");

  $id        = (int)($_POST['id'] ?? 0);
  $region    = up($_POST['region'] ?? '');
  $committee = up($_POST['committee'] ?? '');
  $position  = up($_POST['position'] ?? '');
  $name      = trim((string)($_POST['name'] ?? ''));

  if ($id <= 0) die("Invalid ID.");
  if ($region === '' || $committee === '' || $position === '' || $name === '') {
    die("All fields are required.");
  }

  // Fetch old details for nicer logs
  $old = null;
  $s1 = $conn->prepare("SELECT region, committee, position, name FROM appointed WHERE id=? LIMIT 1");
  $s1->bind_param("i", $id);
  $s1->execute();
  $old = $s1->get_result()->fetch_assoc();
  $s1->close();

  $stmt = $conn->prepare("
    UPDATE appointed
    SET region=?, committee=?, position=?, name=?
    WHERE id=?
  ");
  $stmt->bind_param("ssssi", $region, $committee, $position, $name, $id);
  $stmt->execute();
  $stmt->close();

  $desc = "Edited appointed officer ID #{$id} -> {$name} - {$position} / {$committee} / {$region}";
  if ($old) {
    $desc .= " (from: {$old['name']} - {$old['position']} / {$old['committee']} / {$old['region']})";
  }
  log_admin_action($conn, "UPDATE", $desc);

  header("Location: appointed-management");
  exit;
}

/* ================= FETCH DATA ================= */
$result = $conn->query("SELECT * FROM appointed ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Appointed Officers Management</title>
  <link rel="icon" type="image/png" href="/../static/eagles.png">

  <link rel="stylesheet" href="../admin styles/appointed.css">
  <link rel="stylesheet" href="../admin styles/sidebar.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

<button class="sidebar-toggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button>
<?php include "sidebar.php"; ?>

<div class="main-content">
  <h1>Appointed Officers Management</h1>

  <?php if ($canAdd): ?>
    <button id="open-add-modal" class="add-btn"><i class="fas fa-plus"></i> Add Appointed Officer</button>
  <?php endif; ?>

  <!-- ADD MODAL -->
  <div class="modal" id="add-modal">
    <div class="modal-content">
      <span class="close-btn" id="close-add">&times;</span>
      <h2>Add Appointed Officer</h2>

      <form method="POST" enctype="multipart/form-data">
        <select name="add_mode" id="add-mode">
          <option value="manual">Manual</option>
          <option value="csv">Import CSV</option>
        </select>

        <div id="manual-fields">
          <input type="text" name="region" placeholder="Region" required>
          <input type="text" name="committee" placeholder="Committee" required>
          <input type="text" name="position" placeholder="Position" required>
          <input type="text" name="name" placeholder="Name" required>
        </div>

        <div id="csv-fields" style="display:none;">
          <input type="file" name="import_csv" accept=".csv">
        </div>

        <button type="submit" name="add_appointed">Add Officer</button>
      </form>
    </div>
  </div>

  <!-- TABLE (responsive scroll) -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="display:none;">ID</th>
          <th>Name</th>
          <th>Region</th>
          <th>Committee</th>
          <th>Position</th>
          <?php if ($hasAnyAction): ?><th>Action</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if ($result): ?>
          <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
              <td style="display:none;"><?= (int)$row['id'] ?></td>
              <td class="name-cell"><?= h($row['name'] ?? '') ?></td>
              <td><?= h($row['region'] ?? '') ?></td>
              <td><?= h($row['committee'] ?? '') ?></td>
              <td><?= h($row['position'] ?? '') ?></td>

              <?php if ($hasAnyAction): ?>
                <td class="actions-cell">
                  <?php if ($canEdit): ?>
                    <button class="edit-btn"
                      type="button"
                      data-id="<?= (int)$row['id'] ?>"
                      data-region="<?= h($row['region'] ?? '') ?>"
                      data-committee="<?= h($row['committee'] ?? '') ?>"
                      data-position="<?= h($row['position'] ?? '') ?>"
                      data-name="<?= h($row['name'] ?? '') ?>"
                      title="Edit"
                    ><i class="fas fa-edit"></i></button>
                  <?php endif; ?>

                  <?php if ($canDelete): ?>
                    <a class="delete-btn"
                       onclick="return confirm('Delete officer?');"
                       href="appointed-management?delete_id=<?= (int)$row['id'] ?>"
                       title="Delete"
                    ><i class="fas fa-trash"></i></a>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- EDIT MODAL -->
  <div class="modal" id="edit-modal">
    <div class="modal-content">
      <span class="close-btn" id="close-edit">&times;</span>
      <h2>Edit Appointed Officer</h2>

      <form method="POST">
        <input type="hidden" name="id" id="edit-id">

        <input type="text" name="region" id="edit-region" required>
        <input type="text" name="committee" id="edit-committee" required>
        <input type="text" name="position" id="edit-position" required>
        <input type="text" name="name" id="edit-name" required>

        <button type="submit" name="edit_appointed">Save Changes</button>
      </form>
    </div>
  </div>
</div>

<script>
/* Sidebar toggle */
document.querySelector(".sidebar-toggle")?.addEventListener("click", () => {
  const sb = document.querySelector(".sidebar");
  if (sb) sb.classList.toggle("show");
});

/* Add modal */
const addModal = document.getElementById("add-modal");
const openAddBtn = document.getElementById("open-add-modal");
const closeAddBtn = document.getElementById("close-add");

openAddBtn?.addEventListener("click", () => addModal?.classList.add("show"));
closeAddBtn?.addEventListener("click", () => addModal?.classList.remove("show"));

/* Add mode switch */
const mode = document.getElementById("add-mode");
const manual = document.getElementById("manual-fields");
const csv = document.getElementById("csv-fields");

if (mode && manual && csv) {
  const csvInput = csv.querySelector('input[name="import_csv"]');
  const manualInputs = manual.querySelectorAll('input');

  const setReq = () => {
    const csvMode = mode.value === "csv";
    manual.style.display = csvMode ? "none" : "block";
    csv.style.display = csvMode ? "block" : "none";

    if (csvInput) csvInput.required = csvMode;
    manualInputs.forEach(input => input.required = !csvMode);
  };

  setReq();
  mode.addEventListener("change", setReq);
}

/* Edit modal */
const editModal = document.getElementById("edit-modal");
const closeEditBtn = document.getElementById("close-edit");
closeEditBtn?.addEventListener("click", () => editModal?.classList.remove("show"));

document.querySelectorAll(".edit-btn").forEach(btn => {
  btn.addEventListener("click", () => {
    document.getElementById("edit-id").value = btn.dataset.id || "";
    document.getElementById("edit-region").value = btn.dataset.region || "";
    document.getElementById("edit-committee").value = btn.dataset.committee || "";
    document.getElementById("edit-position").value = btn.dataset.position || "";
    document.getElementById("edit-name").value = btn.dataset.name || "";
    editModal?.classList.add("show");
  });
});

document.addEventListener("keydown", (e) => {
  if (e.key !== "Escape") return;
  addModal?.classList.remove("show");
  editModal?.classList.remove("show");
});
</script>

</body>
</html>
