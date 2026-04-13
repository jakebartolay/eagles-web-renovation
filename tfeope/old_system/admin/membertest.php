<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin-login");
    exit;
}

require_once __DIR__ . "/../includes/db.php";


/* ===== permissions (safe int compare) ===== */
$roleId = (int)($_SESSION['role_id'] ?? 0);
$isSuperAdmin = ($roleId === 1);

/* ===== helpers ===== */
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function up($v): string { return strtoupper(trim((string)$v)); }

/* ===== keep params on redirects (drop delete_id) ===== */
function redirect_keep_params(array $dropKeys = []): void {
    $params = $_GET;
    foreach ($dropKeys as $k) unset($params[$k]);
    $qs = $params ? ("?" . http_build_query($params)) : "";
    header("Location: membertest{$qs}");
    exit;
}

/* ================= DELETE MEMBER ================= */
if (isset($_GET['delete_id'])) {
    if (!$isSuperAdmin) die("You do not have permission to delete members.");
    $delete_id = trim((string)$_GET['delete_id']);

    $stmt = $conn->prepare("DELETE FROM user_info WHERE eagles_id = ?");
    $stmt->bind_param("s", $delete_id);
    $stmt->execute();
    $stmt->close();

    redirect_keep_params(['delete_id']); // keep page/club/region/sort on return
}

/* ================= ADD MEMBER (MANUAL OR CSV) ================= */
if (isset($_POST['add_member'])) {
    if (!$isSuperAdmin) die("You do not have permission to add members.");

    /* ---------- CSV MODE ---------- */
    if (($_POST['add_mode'] ?? '') === 'csv') {

        if (!isset($_FILES['import_csv']) || $_FILES['import_csv']['error'] !== UPLOAD_ERR_OK) {
            die("CSV upload failed. Error code: " . ($_FILES['import_csv']['error'] ?? 'no_file'));
        }

        $file_tmp = $_FILES['import_csv']['tmp_name'];
        $handle = fopen($file_tmp, "r");
        if ($handle === false) die("Unable to open uploaded CSV file.");

        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = (substr_count((string)$firstLine, ';') > substr_count((string)$firstLine, ',')) ? ';' : ',';

        fgetcsv($handle, 0, $delimiter); // skip header

        $stmt = $conn->prepare("
            INSERT INTO user_info
            (eagles_id, eagles_firstName, eagles_lastName, eagles_position, eagles_club, eagles_region, eagles_status, eagles_pic)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) die("Prepare failed: " . $conn->error);

        $inserted = 0; $failed = 0;
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($data) === 1 && trim((string)$data[0]) === "") continue;

            $data = array_map('trim', $data);

            $id         = !empty($data[0]) ? $data[0] : uniqid('EAG_');
            $first_name = up($data[1] ?? "");
            $last_name  = up($data[2] ?? "");
            $position   = up($data[3] ?? "");
            $club       = up($data[4] ?? "");
            $region     = up($data[5] ?? "");
            $status     = !empty($data[6]) ? up($data[6]) : "ACTIVE";

            $photo_file = trim((string)($data[7] ?? ""));
            $photo_path = $photo_file !== "" ? ("Main/uploads/" . $photo_file) : null;

            if ($first_name === "" || $last_name === "" || $position === "" || $club === "" || $region === "") {
                $failed++;
                continue;
            }

            $stmt->bind_param("ssssssss", $id, $first_name, $last_name, $position, $club, $region, $status, $photo_path);
            if ($stmt->execute()) $inserted++; else $failed++;
        }

        $stmt->close();
        fclose($handle);

        header("Location: membertest?import=ok&inserted={$inserted}&failed={$failed}");
        exit;
    }

    /* ---------- MANUAL MODE ---------- */
    if (($_POST['add_mode'] ?? '') === 'manual') {
        $id         = !empty($_POST['id']) ? trim((string)$_POST['id']) : uniqid('EAG_');
        $first_name = up($_POST['first_name'] ?? "");
        $last_name  = up($_POST['last_name'] ?? "");
        $position   = up($_POST['position'] ?? "");
        $club       = up($_POST['club'] ?? "");
        $region     = up($_POST['region'] ?? "");
        $status     = "ACTIVE";

        $photo_path = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $target_dir = "../Main/uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

            $file_name = basename((string)$_FILES['photo']['name']);
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_dir . $file_name)) {
                $photo_path = "Main/uploads/" . $file_name;
            }
        }

        $stmt = $conn->prepare("
            INSERT INTO user_info
            (eagles_id, eagles_firstName, eagles_lastName, eagles_position, eagles_club, eagles_region, eagles_status, eagles_pic)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssssss", $id, $first_name, $last_name, $position, $club, $region, $status, $photo_path);
        $stmt->execute();
        $stmt->close();

        header("Location: membertest");
        exit;
    }
}

/* ================= EDIT MEMBER ================= */
if (isset($_POST['edit_member'])) {
    if (!$isSuperAdmin) die("You do not have permission to edit members.");

    $id = trim((string)($_POST['id'] ?? ""));
    if ($id === "") die("Invalid member ID.");

    $first_name = up($_POST['first_name'] ?? "");
    $last_name  = up($_POST['last_name'] ?? "");
    $position   = up($_POST['position'] ?? "");
    $club       = up($_POST['club'] ?? "");
    $region     = up($_POST['region'] ?? "");
    $status     = up($_POST['status'] ?? "");

    $photo_path = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "../Main/uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

        $file_name = basename((string)$_FILES['photo']['name']);
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_dir . $file_name)) {
            $photo_path = "Main/uploads/" . $file_name;
        }
    }

    if ($photo_path !== null) {
        $stmt = $conn->prepare("
            UPDATE user_info SET
              eagles_firstName=?,
              eagles_lastName=?,
              eagles_position=?,
              eagles_club=?,
              eagles_region=?,
              eagles_status=?,
              eagles_pic=?
            WHERE eagles_id=?
        ");
        if (!$stmt) die("Prepare failed: " . $conn->error);
        $stmt->bind_param("ssssssss", $first_name, $last_name, $position, $club, $region, $status, $photo_path, $id);
    } else {
        $stmt = $conn->prepare("
            UPDATE user_info SET
              eagles_firstName=?,
              eagles_lastName=?,
              eagles_position=?,
              eagles_club=?,
              eagles_region=?,
              eagles_status=?
            WHERE eagles_id=?
        ");
        if (!$stmt) die("Prepare failed: " . $conn->error);
        $stmt->bind_param("sssssss", $first_name, $last_name, $position, $club, $region, $status, $id);
    }

    $stmt->execute();
    $stmt->close();

    header("Location: membertest");
    exit;
}

/* ================= DATA (STATIC LISTS FOR MODALS) ================= */
$positions = ["PRESIDENT","VICE PRESIDENT","PEACE OFFICER","AUDITOR","TRIBUNAL CHAIRMAN","MEMBER","COMELEC CHAIRMAN","CARE CHAIRMAN","ASSEMBLY MAN","SECRETARY","PROTOCOL OFFICER"];
$clubs = ["THAILAND EAGLES CLUB","QUEZON CITY EAGLES CLUB","CEBU EAGLES CLUB","DAVAO EAGLES CLUB"];
$regions = ["NCR","CENTRAL BUSINESS REGION I","REGION II","REGION III","REGION IV-A","REGION V","REGION VI","REGION VII","REGION VIII","REGION IX","REGION X","REGION XI","REGION XII","CAR","BARMM"];

/* ================= FILTERS + SORT ================= */
$selectedClub   = trim((string)($_GET['club'] ?? ''));
$selectedRegion = trim((string)($_GET['region'] ?? ''));
$sort = trim((string)($_GET['sort'] ?? 'newest')); // newest|oldest|name_az|name_za

/* ================= FILTER OPTIONS (DEPENDENT) ================= */
/* Region dropdown always shows all distinct regions */
$regionOptions = [];
$regionRes = $conn->query("
    SELECT DISTINCT eagles_region
    FROM user_info
    WHERE eagles_region IS NOT NULL AND eagles_region != ''
    ORDER BY eagles_region ASC
");
if ($regionRes) {
    while ($r = $regionRes->fetch_assoc()) $regionOptions[] = $r['eagles_region'];
    $regionRes->free();
}

/*
  Club dropdown depends on selectedRegion:
  - If region is chosen: only clubs under that region
  - Else: show all clubs
*/
$clubOptions = [];
if ($selectedRegion !== "") {
    $clubStmt = $conn->prepare("
        SELECT DISTINCT eagles_club
        FROM user_info
        WHERE eagles_club IS NOT NULL AND eagles_club != ''
          AND eagles_region = ?
        ORDER BY eagles_club ASC
    ");
    if (!$clubStmt) die("Prepare failed: " . $conn->error);
    $clubStmt->bind_param("s", $selectedRegion);
    $clubStmt->execute();
    $clubRes = $clubStmt->get_result();
    while ($clubRes && ($r = $clubRes->fetch_assoc())) $clubOptions[] = $r['eagles_club'];
    $clubStmt->close();
} else {
    $clubRes = $conn->query("
        SELECT DISTINCT eagles_club
        FROM user_info
        WHERE eagles_club IS NOT NULL AND eagles_club != ''
        ORDER BY eagles_club ASC
    ");
    if ($clubRes) {
        while ($r = $clubRes->fetch_assoc()) $clubOptions[] = $r['eagles_club'];
        $clubRes->free();
    }
}

/* If chosen club is not valid under chosen region, clear it to avoid empty results */
if ($selectedRegion !== "" && $selectedClub !== "" && !in_array($selectedClub, $clubOptions, true)) {
    $selectedClub = "";
}

/* ================= PAGINATION ================= */
$perPage = 15;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1);

/* count (respects club + region filters) */
$countSql = "SELECT COUNT(*) AS total FROM user_info";
$countParams = [];
$countTypes = "";

$where = [];
if ($selectedRegion !== "") {
    $where[] = "eagles_region = ?";
    $countParams[] = $selectedRegion;
    $countTypes .= "s";
}
if ($selectedClub !== "") {
    $where[] = "eagles_club = ?";
    $countParams[] = $selectedClub;
    $countTypes .= "s";
}
if ($where) $countSql .= " WHERE " . implode(" AND ", $where);

$countStmt = $conn->prepare($countSql);
if (!$countStmt) die("Prepare failed: " . $conn->error);
if ($countParams) $countStmt->bind_param($countTypes, ...$countParams);
$countStmt->execute();
$countRes = $countStmt->get_result();
$countRow = $countRes ? $countRes->fetch_assoc() : ['total' => 0];
$totalRecords = (int)($countRow['total'] ?? 0);
$countStmt->close();

$totalPages = max((int)ceil($totalRecords / $perPage), 1);
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

/* ================= SORT => ORDER BY ================= */
$orderBy = "eagles_id DESC"; // newest
if ($sort === 'oldest')  $orderBy = "eagles_id ASC";
if ($sort === 'name_az') $orderBy = "eagles_lastName ASC, eagles_firstName ASC";
if ($sort === 'name_za') $orderBy = "eagles_lastName DESC, eagles_firstName DESC";

/* page data (respects club + region filters) */
$sql = "SELECT * FROM user_info";
$params = [];
$types = "";
$where = [];

if ($selectedRegion !== "") {
    $where[] = "eagles_region = ?";
    $params[] = $selectedRegion;
    $types .= "s";
}
if ($selectedClub !== "") {
    $where[] = "eagles_club = ?";
    $params[] = $selectedClub;
    $types .= "s";
}
if ($where) $sql .= " WHERE " . implode(" AND ", $where);

$sql .= " ORDER BY {$orderBy} LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!$stmt) die("Prepare failed: " . $conn->error);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

/* keep params in pagination links */
function pageUrl(int $targetPage): string {
    $params = $_GET;
    $params['page'] = $targetPage;
    return "membertest?" . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Members Management</title>
<link rel="icon" type="image/png" href="static/eagles.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../admin styles/member.css">
<link rel="stylesheet" href="../admin styles/sidebar.css">
<style>
.pagination{
  display:flex;
  justify-content:center;
  align-items:center;
  gap:8px;
  margin:20px 0 10px;
  flex-wrap:wrap;
}
.page-btn{
  padding:8px 14px;
  border:1px solid rgba(0,0,0,.18);
  background:#fff;
  color:#333;
  text-decoration:none;
  border-radius:8px;
  font-size:14px;
}
.page-btn:hover{ background:#f0f0f0; }
.page-btn.active{
  background:#1f4068;
  color:#fff;
  border-color:#1f4068;
  pointer-events:none;
}
.page-meta{
  text-align:center;
  margin:6px 0 14px;
  color:rgba(0,0,0,.65);
  font-size:13px;
}
</style>
</head>

<body>

<button class="sidebar-toggle"><i class="fas fa-bars"></i></button>
<?php include 'sidebar.php'; ?>

<div class="main-content">
  <h1>Members Management</h1>

  <?php if ($isSuperAdmin): ?>
  <div class="top-buttons">
      <button class="add-btn" id="open-add-modal"><i class="fas fa-plus"></i> Add New Member</button>
  </div>
  <?php endif; ?>

  <?php if (isset($_GET['import']) && $_GET['import'] === 'ok'): ?>
    <div class="page-meta">
      Import complete — Inserted: <?= h($_GET['inserted'] ?? 0) ?>, Failed: <?= h($_GET['failed'] ?? 0) ?>
    </div>
  <?php endif; ?>

  <!-- FILTERS (REGION -> CLUB dependent) -->
  <div class="filters-wrap">
    <div class="filters-head">
      <div>
        <p class="filters-title">Filters</p>
        <p class="filters-sub">Pick a region first to narrow the club list.</p>
      </div>

      <?php if ($selectedClub !== "" || $selectedRegion !== "" || ($sort !== "" && $sort !== "newest")): ?>
        <div class="filter-chips">
          <?php if ($selectedRegion !== ""): ?>
            <span class="chip">Region: <strong><?= h($selectedRegion) ?></strong></span>
          <?php endif; ?>
          <?php if ($selectedClub !== ""): ?>
            <span class="chip">Club: <strong><?= h($selectedClub) ?></strong></span>
          <?php endif; ?>
          <?php if ($sort !== "" && $sort !== "newest"): ?>
            <span class="chip">Sort: <strong><?= h($sort) ?></strong></span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <form class="filter-bar" method="GET" id="filterForm">
      <input type="hidden" name="page" value="1">

      <div class="filter-field">
        <select name="region" id="regionSelect">
          <option value="">— All Regions —</option>
          <?php foreach ($regionOptions as $regOpt): ?>
            <option value="<?= h($regOpt) ?>" <?= $regOpt === $selectedRegion ? 'selected' : '' ?>>
              <?= h($regOpt) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-field">
        <select name="club" id="clubSelect">
          <option value="">— All Clubs —</option>
          <?php foreach ($clubOptions as $clubOpt): ?>
            <option value="<?= h($clubOpt) ?>" <?= $clubOpt === $selectedClub ? 'selected' : '' ?>>
              <?= h($clubOpt) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-field" style="min-width:220px; flex:0 0 220px;">
        <select name="sort" id="sortSelect">
          <option value="newest" <?= $sort==='newest'?'selected':'' ?>>Sort: Newest</option>
          <option value="oldest" <?= $sort==='oldest'?'selected':'' ?>>Sort: Oldest</option>
          <option value="name_az" <?= $sort==='name_az'?'selected':'' ?>>Sort: Name A–Z</option>
          <option value="name_za" <?= $sort==='name_za'?'selected':'' ?>>Sort: Name Z–A</option>
        </select>
      </div>

      <?php if ($selectedClub !== "" || $selectedRegion !== "" || ($sort !== "newest")): ?>
        <a class="clear-link" href="member">Clear filters</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- MEMBERS TABLE -->
  <table>
  <thead>
  <tr>
    <th>ID</th>
    <th>Name</th>
    <th>Position</th>
    <th>Club</th>
    <th>Region</th>
    <th>Status</th>
    <th>Action</th>
  </tr>
  </thead>
  <tbody>
  <?php if ($result): ?>
    <?php while ($row = $result->fetch_assoc()): ?>
    <tr>
      <td><?= h($row['eagles_id']) ?></td>
      <td><?= h(($row['eagles_firstName'] ?? "")." ".($row['eagles_lastName'] ?? "")) ?></td>
      <td><?= h($row['eagles_position']) ?></td>
      <td><?= h($row['eagles_club']) ?></td>
      <td><?= h($row['eagles_region']) ?></td>
      <td><?= h($row['eagles_status']) ?></td>
      <td>
        <?php if ($isSuperAdmin): ?>
          <button class="edit-row-btn"
              data-id="<?= h($row['eagles_id']) ?>"
              data-first="<?= h($row['eagles_firstName']) ?>"
              data-last="<?= h($row['eagles_lastName']) ?>"
              data-position="<?= h($row['eagles_position']) ?>"
              data-club="<?= h($row['eagles_club']) ?>"
              data-region="<?= h($row['eagles_region']) ?>"
              data-status="<?= h($row['eagles_status']) ?>"
          ><i class="fas fa-edit"></i></button>

          <a href="<?= h(pageUrl($page)) ?>&delete_id=<?= urlencode((string)$row['eagles_id']) ?>"
             onclick="return confirm('Are you sure you want to delete this member?');"
             class="delete-btn"
          ><i class="fas fa-trash"></i></a>
        <?php else: ?>
          <i class="fas fa-eye" title="View Only"></i>
        <?php endif; ?>
      </td>
    </tr>
    <?php endwhile; ?>
  <?php endif; ?>
  </tbody>
  </table>

  <!-- PAGINATION UI -->
  <?php if ($totalPages > 1): ?>
    <div class="page-meta">
      Showing page <?= h($page) ?> of <?= h($totalPages) ?> (Total: <?= h($totalRecords) ?>)
    </div>

    <div class="pagination">
      <?php if ($page > 1): ?>
        <a class="page-btn" href="<?= h(pageUrl($page - 1)) ?>">&laquo; Prev</a>
      <?php endif; ?>

      <?php
        $start = max(1, $page - 2);
        $end   = min($totalPages, $page + 2);

        if ($start > 1) {
            echo '<a class="page-btn" href="'.h(pageUrl(1)).'">1</a>';
            if ($start > 2) echo '<span class="page-meta" style="margin:0 6px;">…</span>';
        }
      ?>

      <?php for ($i = $start; $i <= $end; $i++): ?>
        <a class="page-btn <?= $i === $page ? 'active' : '' ?>"
           href="<?= h(pageUrl($i)) ?>"><?= h($i) ?></a>
      <?php endfor; ?>

      <?php
        if ($end < $totalPages) {
            if ($end < $totalPages - 1) echo '<span class="page-meta" style="margin:0 6px;">…</span>';
            echo '<a class="page-btn" href="'.h(pageUrl($totalPages)).'">'.h($totalPages).'</a>';
        }
      ?>

      <?php if ($page < $totalPages): ?>
        <a class="page-btn" href="<?= h(pageUrl($page + 1)) ?>">Next &raquo;</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- ADD MODAL -->
  <?php if ($isSuperAdmin): ?>
  <div class="modal" id="add-modal" aria-hidden="true">
      <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="addTitle">
          <span class="close-btn" id="close-add" aria-label="Close">&times;</span>
          <h2 id="addTitle">Add Member</h2>

          <form method="POST" enctype="multipart/form-data">
              <select name="add_mode" id="add-mode" required>
                  <option value="manual">Manual Entry</option>
                  <option value="csv">Upload CSV</option>
              </select>

              <div id="manual-fields">
                  <input type="text" name="id" placeholder="Member ID (optional)">
                  <input type="text" name="first_name" placeholder="First Name" required>
                  <input type="text" name="last_name" placeholder="Last Name" required>

                  <select name="position" required>
                      <option value="">Select Position</option>
                      <?php foreach ($positions as $pos): ?>
                          <option value="<?= h($pos) ?>"><?= h($pos) ?></option>
                      <?php endforeach; ?>
                  </select>

                  <select name="club" required>
                      <option value="">Select Club</option>
                      <?php foreach ($clubs as $clubStatic): ?>
                          <option value="<?= h($clubStatic) ?>"><?= h($clubStatic) ?></option>
                      <?php endforeach; ?>
                  </select>

                  <select name="region" required>
                      <option value="">Select Region</option>
                      <?php foreach ($regions as $region): ?>
                          <option value="<?= h($region) ?>"><?= h($region) ?></option>
                      <?php endforeach; ?>
                  </select>

                  <input type="file" name="photo" accept="image/*">
              </div>

              <div id="csv-fields" style="display:none;">
                  <input type="file" name="import_csv" accept=".csv">
              </div>

              <button type="submit" name="add_member">Add Member</button>
          </form>
      </div>
  </div>
  <?php endif; ?>

  <!-- EDIT MODAL -->
  <?php if ($isSuperAdmin): ?>
  <div class="modal" id="edit-modal" aria-hidden="true">
      <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="editTitle">
          <span class="close-btn" id="close-edit" aria-label="Close">&times;</span>
          <h2 id="editTitle">Edit Member</h2>

          <form method="POST" enctype="multipart/form-data">
              <input type="hidden" name="id" id="edit-id">
              <input type="text" name="first_name" id="edit-first" placeholder="First Name" required>
              <input type="text" name="last_name" id="edit-last" placeholder="Last Name" required>

              <select name="position" id="edit-position" required>
                  <option value="">Select Position</option>
                  <?php foreach ($positions as $pos): ?>
                      <option value="<?= h($pos) ?>"><?= h($pos) ?></option>
                  <?php endforeach; ?>
              </select>

              <select name="club" id="edit-club" required>
                  <option value="">Select Club</option>
                  <?php foreach ($clubs as $clubStatic): ?>
                      <option value="<?= h($clubStatic) ?>"><?= h($clubStatic) ?></option>
                  <?php endforeach; ?>
              </select>

              <select name="region" id="edit-region" required>
                  <option value="">Select Region</option>
                  <?php foreach ($regions as $region): ?>
                      <option value="<?= h($region) ?>"><?= h($region) ?></option>
                  <?php endforeach; ?>
              </select>

              <select name="status" id="edit-status" required>
                  <option value="ACTIVE">Active</option>
                  <option value="INACTIVE">Inactive</option>
              </select>

              <input type="file" name="photo" accept="image/*">

              <button type="submit" name="edit_member">Save Changes</button>
          </form>
      </div>
  </div>
  <?php endif; ?>

</div>

<script>
/* Sidebar toggle */
document.querySelector(".sidebar-toggle").onclick = () => {
  const sb = document.querySelector(".sidebar");
  if (sb) sb.classList.toggle("show");
};

const isSuperAdmin = <?= $isSuperAdmin ? "true" : "false" ?>;

/* ===== FILTER dependency UX =====
   When region changes, clear club first then submit,
   so you don't keep an old club that doesn't belong to the new region.
*/
const filterForm = document.getElementById("filterForm");
const regionSelect = document.getElementById("regionSelect");
const clubSelect = document.getElementById("clubSelect");
const sortSelect = document.getElementById("sortSelect");

if (regionSelect && filterForm) {
  regionSelect.addEventListener("change", () => {
    if (clubSelect) clubSelect.value = "";
    filterForm.submit();
  });
}
if (clubSelect && filterForm) {
  clubSelect.addEventListener("change", () => filterForm.submit());
}
if (sortSelect && filterForm) {
  sortSelect.addEventListener("change", () => filterForm.submit());
}

/* ===== modal helpers ===== */
function openModal(modalEl){
  if (!modalEl) return;
  modalEl.classList.add("show");
  modalEl.setAttribute("aria-hidden", "false");
  document.body.classList.add("modal-open");
}
function closeModal(modalEl){
  if (!modalEl) return;
  modalEl.classList.remove("show");
  modalEl.setAttribute("aria-hidden", "true");
  if (!document.querySelector(".modal.show")) {
    document.body.classList.remove("modal-open");
  }
}

/* click outside to close */
document.querySelectorAll(".modal").forEach(m => {
  m.addEventListener("click", (e) => {
    if (e.target === m) closeModal(m);
  });
});

/* ESC closes top modal */
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    const top = document.querySelector(".modal.show");
    if (top) closeModal(top);
  }
});

if (isSuperAdmin) {
  /* ADD modal */
  const addModal = document.getElementById("add-modal");
  const openAddBtn = document.getElementById("open-add-modal");
  const closeAddBtn = document.getElementById("close-add");

  if (openAddBtn && addModal) openAddBtn.onclick = () => openModal(addModal);
  if (closeAddBtn && addModal) closeAddBtn.onclick = () => closeModal(addModal);

  /* Add mode toggle (manual vs csv) */
  const addMode = document.getElementById("add-mode");
  const manualFields = document.getElementById("manual-fields");
  const csvFields = document.getElementById("csv-fields");

  function toggleAddMode(){
    if (!addMode || !manualFields || !csvFields) return;
    const isCSV = addMode.value === "csv";

    manualFields.style.display = isCSV ? "none" : "block";
    csvFields.style.display = isCSV ? "block" : "none";

    const manualInputs = manualFields.querySelectorAll("input, select");
    manualInputs.forEach(el => {
      if (el.name === "id" || el.type === "file") return;
      el.required = !isCSV;
    });

    const csvInput = csvFields.querySelector('input[name="import_csv"]');
    if (csvInput) csvInput.required = isCSV;
  }

  if (addMode) addMode.addEventListener("change", toggleAddMode);
  toggleAddMode();

  /* EDIT modal */
  const editModal = document.getElementById("edit-modal");
  const closeEditBtn = document.getElementById("close-edit");

  if (closeEditBtn && editModal) closeEditBtn.onclick = () => closeModal(editModal);

  function selectOption(select, value){
    if (!select) return;
    const v = String(value || "").trim().toUpperCase();
    Array.from(select.options).forEach(opt => {
      opt.selected = (String(opt.value).trim().toUpperCase() === v);
    });
  }

  document.querySelectorAll(".edit-row-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      document.getElementById("edit-id").value = (btn.dataset.id || "").trim();
      document.getElementById("edit-first").value = (btn.dataset.first || "").trim();
      document.getElementById("edit-last").value = (btn.dataset.last || "").trim();

      selectOption(document.getElementById("edit-position"), btn.dataset.position);
      selectOption(document.getElementById("edit-club"), btn.dataset.club);
      selectOption(document.getElementById("edit-region"), btn.dataset.region);
      selectOption(document.getElementById("edit-status"), btn.dataset.status);

      openModal(editModal);
    });
  });
}
</script>

</body>
</html>
