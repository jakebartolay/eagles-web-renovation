<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
  header("Location: admin-login");
  exit;
}

/* ================= DB (MOVED: /includes/db.php) ================= */
require_once __DIR__ . "/../includes/db.php";
if (!isset($conn) || !($conn instanceof mysqli)) die("DB connection not found. Check db.php.");

/* ================= LOGGER / TRACKER (MOVED: /includes/...) ================= */
$loggerCandidates = [
  __DIR__ . "/../includes/admin_logger.php",
  rtrim($_SERVER['DOCUMENT_ROOT'], "/\\") . "/includes/admin_logger.php",
];
$trackCandidates = [
  __DIR__ . "/../includes/admin_track.php",
  rtrim($_SERVER['DOCUMENT_ROOT'], "/\\") . "/includes/admin_track.php",
];
foreach ($loggerCandidates as $p) { if (is_file($p)) { require_once $p; break; } }
foreach ($trackCandidates as $p)  { if (is_file($p))  { require_once $p; break; } }

$roleId = (int)($_SESSION['role_id'] ?? 0);

$isSuperAdmin = ($roleId === 1);
$isAdmin      = ($roleId === 2);

$canAdd    = ($isSuperAdmin || $isAdmin);
$canEdit   = ($isSuperAdmin || $isAdmin);
$canDelete = ($isSuperAdmin);

$hasGovActions   = ($canEdit || $canDelete);
$hasClubActions  = ($canEdit || $canDelete);

// ======================= CONFIG =======================
// NOTE: governors folder is NOT inside /admin. It is in the site root: /htdocs/governors/
$UPLOAD_DIR_REL   = "/governors/"; // ✅ public URL from site root
$UPLOAD_DIR_ABS   = realpath(__DIR__ . "/../governors");
$UPLOAD_DIR_ABS   = $UPLOAD_DIR_ABS ? rtrim($UPLOAD_DIR_ABS, "/\\") . DIRECTORY_SEPARATOR : null;

$PLACEHOLDER_IMG  = $UPLOAD_DIR_REL . "placeholder.png";
$MAX_UPLOAD_MB    = 5;
$ALLOWED_EXT      = ["jpg","jpeg","png","webp"];

if (!$UPLOAD_DIR_ABS) {
  die("Upload folder not found. Expected: " . __DIR__ . "/../governors");
}

// ======================= HELPERS =======================
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function clean_text($s) {
  return trim(preg_replace('/\s+/', ' ', $s ?? ''));
}
function safe_filename($name) {
  $name = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $name);
  $name = preg_replace('/_+/', '_', $name);
  return $name ?: ("img_" . time());
}
function ensure_upload_dir($absDir) {
  if (!is_dir($absDir)) {
    @mkdir($absDir, 0755, true);
  }
}
function upload_image($fieldName, $uploadAbsDir, $allowedExt, $maxMb) {
  if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
    return [null, null];
  }
  if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
    return [null, "Upload failed (error code: " . (int)$_FILES[$fieldName]['error'] . ")."];
  }
  $size = (int)$_FILES[$fieldName]['size'];
  if ($size <= 0) return [null, "Uploaded file is empty."];
  if ($size > ($maxMb * 1024 * 1024)) return [null, "File too large. Max {$maxMb}MB."];

  $orig = $_FILES[$fieldName]['name'] ?? "image";
  $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  if (!in_array($ext, $allowedExt, true)) {
    return [null, "Invalid file type. Allowed: " . implode(", ", $allowedExt)];
  }

  ensure_upload_dir($uploadAbsDir);

  $base = safe_filename(pathinfo($orig, PATHINFO_FILENAME));
  $filename = $base . "_" . date("Ymd_His") . "_" . bin2hex(random_bytes(3)) . "." . $ext;
  $targetAbs = rtrim($uploadAbsDir, "/\\") . DIRECTORY_SEPARATOR . $filename;

  if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $targetAbs)) {
    return [null, "Failed to save uploaded image."];
  }
  return [$filename, null];
}

function parse_regions_lines($regionsRaw) {
  $regionsRaw = trim($regionsRaw ?? "");
  if ($regionsRaw === "") return [];
  $lines = preg_split("/\r\n|\n|\r/", $regionsRaw);

  $out = [];
  foreach ($lines as $line) {
    $r = clean_text($line);
    if ($r === "") continue;
    $key = mb_strtolower($r);
    $out[$key] = $r; // de-dupe case-insensitive
  }
  return array_values($out);
}

function region_has_clubs(mysqli $conn, int $regionId): bool {
  $q = $conn->prepare("SELECT 1 FROM clubs WHERE region_id=? LIMIT 1");
  $q->bind_param("i", $regionId);
  $q->execute();
  $res = $q->get_result();
  $q->close();
  return ($res && $res->num_rows > 0);
}

function safe_abs_in_dir(string $baseAbs, string $nameOrRel): string {
  // allow "governors/filename" or just "filename"
  $clean = str_replace(['..', '\\'], ['', '/'], (string)$nameOrRel);
  $clean = ltrim($clean, '/');
  // if someone saved "governors/abc.png" in DB, strip prefix:
  if (strpos($clean, 'governors/') === 0) $clean = substr($clean, strlen('governors/'));
  return rtrim($baseAbs, "/\\") . DIRECTORY_SEPARATOR . basename($clean);
}
function delete_if_exists_abs($absPath) {
  if ($absPath && is_file($absPath)) @unlink($absPath);
}

/* =========================================================
   AJAX ENDPOINTS (SAME FILE)
========================================================= */
if (isset($_GET['action']) && $_GET['action'] === 'regions') {
  header("Content-Type: text/html; charset=UTF-8");

  $govId = (int)($_GET['governor_id'] ?? 0);
  if ($govId <= 0) { echo "<option value='' disabled selected>Select region</option>"; exit; }

  $stmt = $conn->prepare("SELECT region_id, region_name FROM regions WHERE governor_id=? ORDER BY region_name ASC");
  $stmt->bind_param("i", $govId);
  $stmt->execute();
  $res = $stmt->get_result();

  echo "<option value='' disabled selected>Select region</option>";
  while ($r = $res->fetch_assoc()) {
    echo "<option value='".(int)$r['region_id']."'>".h($r['region_name'])."</option>";
  }
  $stmt->close();
  exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'clubs') {
  header("Content-Type: text/html; charset=UTF-8");

  $govId = (int)($_GET['governor_id'] ?? 0);
  if ($govId <= 0) { echo "<p class='empty'>Invalid governor.</p>"; exit; }

  $stmt = $conn->prepare("
    SELECT
      c.club_id,
      c.club_name,
      c.region_id,
      r.region_name,
      p.president_name
    FROM clubs c
    LEFT JOIN regions r ON r.region_id = c.region_id
    LEFT JOIN presidents p ON p.club_id = c.club_id
    WHERE c.governor_id=?
    ORDER BY r.region_name ASC, c.club_name ASC
  ");
  $stmt->bind_param("i", $govId);
  $stmt->execute();
  $res = $stmt->get_result();

  if (!$res || $res->num_rows === 0) {
    echo "<p class='empty'>No clubs found under this governor.</p>";
    $stmt->close();
    exit;
  }

  echo "<div class='table-wrap'>";
  echo "<table class='clubs-table'>";
  echo "<thead><tr>
    <th>Club</th>
    <th>Region</th>
    <th>President</th>";
  if ($GLOBALS['hasClubActions']) echo "<th class='col-actions'>Action</th>";
  echo "</tr></thead><tbody>";

  while ($row = $res->fetch_assoc()) {
    $clubId     = (int)$row['club_id'];
    $clubName   = h($row['club_name']);
    $regionName = h($row['region_name'] ?? 'Unassigned');
    $presName   = h($row['president_name'] ?? 'Not assigned');
    $regionId   = (int)($row['region_id'] ?? 0);

    echo "<tr>
      <td>{$clubName}</td>
      <td>{$regionName}</td>
      <td>{$presName}</td>";

    if ($GLOBALS['hasClubActions']) {
      echo "<td class='actions'>";
      if ($GLOBALS['canEdit']) {
        echo "<button
          type='button'
          class='edit-btn club-edit-btn'
          data-club-id='{$clubId}'
          data-region-id='{$regionId}'
          data-club-name=\"".h($row['club_name'])."\"
          data-president=\"".h(($row['president_name'] ?? ''))."\"
          title='Edit club'
        ><i class='fas fa-edit'></i></button>";
      }

      if ($GLOBALS['canDelete']) {
        // CHANGED: no confirm() here, now uses modal confirm via JS
        echo "<form method='POST' class='delete-club-form' style='display:inline; margin:0;'>
          <input type='hidden' name='delete_club' value='1'>
          <input type='hidden' name='club_id' value='{$clubId}'>
          <input type='hidden' name='view_governor_id' value='{$govId}'>
          <button type='button' class='delete-btn js-open-delete-club' data-club-name=\"".h($row['club_name'])."\" title='Delete club'>
            <i class='fas fa-trash'></i>
          </button>
        </form>";
      }
      echo "</td>";
    }

    echo "</tr>";
  }

  echo "</tbody></table></div>";
  $stmt->close();
  exit;
}

// ======================= ACTIONS (POST/GET) =======================
$errors = [];

/* ---------- DELETE CLUB (POST, for modal submit) ---------- */
if (isset($_POST['delete_club']) && isset($_POST['club_id'])) {
  if (!$canDelete) die("No permission.");
  $clubId = (int)$_POST['club_id'];
  $viewGovId = (int)($_POST['view_governor_id'] ?? 0);

  $beforeClub = null;
  $s = $conn->prepare("
    SELECT c.club_name, r.region_name, p.president_name
    FROM clubs c
    LEFT JOIN regions r ON r.region_id = c.region_id
    LEFT JOIN presidents p ON p.club_id = c.club_id
    WHERE c.club_id=? LIMIT 1
  ");
  $s->bind_param("i", $clubId);
  $s->execute();
  $beforeClub = $s->get_result()->fetch_assoc();
  $s->close();

  $stmt = $conn->prepare("DELETE FROM presidents WHERE club_id=?");
  $stmt->bind_param("i", $clubId);
  $stmt->execute();
  $stmt->close();

  $stmt = $conn->prepare("DELETE FROM clubs WHERE club_id=?");
  $stmt->bind_param("i", $clubId);
  $stmt->execute();
  $stmt->close();

  if (function_exists('log_admin_action')) {
    $desc = "Deleted club ID #{$clubId} under Governor ID #{$viewGovId}";
    if ($beforeClub) {
      $desc .= " ({$beforeClub['club_name']} / " . ($beforeClub['region_name'] ?? 'Unassigned') . " / " . ($beforeClub['president_name'] ?? 'No president') . ")";
    }
    log_admin_action($conn, "DELETE", $desc);
  }

  header("Location: " . strtok($_SERVER["REQUEST_URI"], '?') . "?open_clubs=1&governor_id=" . $viewGovId);
  exit;
}

/* ---------- DELETE GOVERNOR (and children) (POST, for modal submit) ---------- */
if (isset($_POST['delete_governor']) && isset($_POST['governor_id'])) {
  if (!$canDelete) die("No permission.");
  $govId = (int)$_POST['governor_id'];

  $govName = "";
  $oldImg = "";
  $s = $conn->prepare("SELECT governor_name, governor_image FROM governors WHERE governor_id=? LIMIT 1");
  $s->bind_param("i", $govId);
  $s->execute();
  $row = $s->get_result()->fetch_assoc();
  $s->close();
  if ($row) {
    $govName = (string)($row['governor_name'] ?? '');
    $oldImg  = (string)($row['governor_image'] ?? '');
  }

  $stmt = $conn->prepare("DELETE FROM presidents WHERE governor_id=?");
  $stmt->bind_param("i", $govId);
  $stmt->execute();
  $stmt->close();

  $stmt = $conn->prepare("DELETE FROM clubs WHERE governor_id=?");
  $stmt->bind_param("i", $govId);
  $stmt->execute();
  $stmt->close();

  $stmt = $conn->prepare("DELETE FROM regions WHERE governor_id=?");
  $stmt->bind_param("i", $govId);
  $stmt->execute();
  $stmt->close();

  $stmt = $conn->prepare("DELETE FROM governors WHERE governor_id=?");
  $stmt->bind_param("i", $govId);
  $stmt->execute();
  $stmt->close();

  // delete governor photo from disk too
  if ($oldImg !== '') {
    $abs = safe_abs_in_dir($UPLOAD_DIR_ABS, $oldImg);
    delete_if_exists_abs($abs);
  }

  if (function_exists('log_admin_action')) {
    $desc = "Deleted governor ID #{$govId}" . ($govName ? " ({$govName})" : "") . " including regions/clubs/presidents";
    log_admin_action($conn, "DELETE", $desc);
  }

  header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
  exit;
}

/* ---------- ADD GOVERNOR ---------- */
if (isset($_POST['add_governor'])) {
  if (!$canAdd) die("No permission.");

  $govName = clean_text($_POST['governor_name'] ?? "");
  $regionsRaw = trim($_POST['regions'] ?? "");

  if ($govName === "") $errors[] = "Governor name is required.";

  [$uploadedFilename, $uploadErr] = upload_image("governor_image", $UPLOAD_DIR_ABS, $ALLOWED_EXT, $MAX_UPLOAD_MB);
  if ($uploadErr) $errors[] = $uploadErr;

  if (!$errors) {
    if ($uploadedFilename) {
      $stmt = $conn->prepare("INSERT INTO governors (governor_name, governor_image) VALUES (?, ?)");
      $stmt->bind_param("ss", $govName, $uploadedFilename);
    } else {
      $stmt = $conn->prepare("INSERT INTO governors (governor_name) VALUES (?)");
      $stmt->bind_param("s", $govName);
    }
    $stmt->execute();
    $newGovId = (int)$conn->insert_id;
    $stmt->close();

    $regions = parse_regions_lines($regionsRaw);
    if (!empty($regions)) {
      $stmtR = $conn->prepare("INSERT INTO regions (region_name, governor_id) VALUES (?, ?)");
      foreach ($regions as $region) {
        $stmtR->bind_param("si", $region, $newGovId);
        $stmtR->execute();
      }
      $stmtR->close();
    }

    if (function_exists('log_admin_action')) {
      $regCount = count(parse_regions_lines($regionsRaw));
      log_admin_action($conn, "CREATE", "Added governor ID #{$newGovId} ({$govName}) with {$regCount} region(s)");
    }

    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
  }
}

/* ---------- EDIT GOVERNOR (name + regions + photo) ---------- */
if (isset($_POST['edit_governor'])) {
  if (!$canEdit) die("No permission.");

  $govId = (int)($_POST['governor_id'] ?? 0);
  $govName = clean_text($_POST['governor_name'] ?? "");
  $regionsRaw = trim($_POST['regions'] ?? "");

  $oldGov = null;
  if ($govId > 0) {
    $sOld = $conn->prepare("SELECT governor_name, governor_image FROM governors WHERE governor_id=? LIMIT 1");
    $sOld->bind_param("i", $govId);
    $sOld->execute();
    $oldGov = $sOld->get_result()->fetch_assoc();
    $sOld->close();
  }

  if ($govId <= 0) $errors[] = "Invalid governor.";
  if ($govName === "") $errors[] = "Governor name is required.";

  [$uploadedFilename, $uploadErr] = upload_image("edit_governor_image", $UPLOAD_DIR_ABS, $ALLOWED_EXT, $MAX_UPLOAD_MB);
  if ($uploadErr) $errors[] = $uploadErr;

  if (!$errors) {
    try {
      $conn->begin_transaction();

      if ($uploadedFilename) {
        $stmt = $conn->prepare("UPDATE governors SET governor_name=?, governor_image=? WHERE governor_id=?");
        $stmt->bind_param("ssi", $govName, $uploadedFilename, $govId);
      } else {
        $stmt = $conn->prepare("UPDATE governors SET governor_name=? WHERE governor_id=?");
        $stmt->bind_param("si", $govName, $govId);
      }
      $stmt->execute();
      $stmt->close();

      // if changed photo, delete old file
      if ($uploadedFilename && $oldGov && !empty($oldGov['governor_image'])) {
        $abs = safe_abs_in_dir($UPLOAD_DIR_ABS, (string)$oldGov['governor_image']);
        delete_if_exists_abs($abs);
      }

      $desired = parse_regions_lines($regionsRaw);

      $stmtE = $conn->prepare("SELECT region_id, region_name FROM regions WHERE governor_id=?");
      $stmtE->bind_param("i", $govId);
      $stmtE->execute();
      $resE = $stmtE->get_result();

      $existingByKey = [];
      while ($r = $resE->fetch_assoc()) {
        $name = clean_text($r['region_name']);
        $key  = mb_strtolower($name);
        $existingByKey[$key] = ['id' => (int)$r['region_id'], 'name' => $name];
      }
      $stmtE->close();

      // insert missing
      $stmtIns = $conn->prepare("INSERT INTO regions (region_name, governor_id) VALUES (?, ?)");
      foreach ($desired as $name) {
        $key = mb_strtolower($name);
        if (!isset($existingByKey[$key])) {
          $stmtIns->bind_param("si", $name, $govId);
          $stmtIns->execute();
        }
      }
      $stmtIns->close();

      // delete removed (unless has clubs)
      $desiredKeys = [];
      foreach ($desired as $name) $desiredKeys[mb_strtolower($name)] = true;

      $blocked = [];
      foreach ($existingByKey as $key => $info) {
        if (!isset($desiredKeys[$key])) {
          $rid = (int)$info['id'];

          if (region_has_clubs($conn, $rid)) {
            $blocked[] = $info['name'];
            continue;
          }

          $del = $conn->prepare("DELETE FROM regions WHERE region_id=? AND governor_id=?");
          $del->bind_param("ii", $rid, $govId);
          $del->execute();
          $del->close();
        }
      }

      $conn->commit();

      if (function_exists('log_admin_action')) {
        $desc = "Edited governor ID #{$govId}";
        if ($oldGov && !empty($oldGov['governor_name'])) $desc .= " (from: {$oldGov['governor_name']})";
        $desc .= " -> {$govName}";
        if (!empty($blocked)) $desc .= " | Regions not removed (has clubs): " . implode(", ", $blocked);
        log_admin_action($conn, "UPDATE", $desc);
      }

      if (!empty($blocked)) {
        $errors[] = "Some regions were NOT removed because clubs are assigned to them: " . implode(", ", $blocked) . ".";
      }

      header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
      exit;

    } catch (Throwable $e) {
      $conn->rollback();
      $errors[] = "Failed to save governor changes: " . $e->getMessage();
    }
  }
}

/* ---------- ADD CLUB ---------- */
if (isset($_POST['add_club'])) {
  if (!$canAdd) die("No permission.");

  $govId    = (int)($_POST['club_governor_id'] ?? 0);
  $regionId = (int)($_POST['club_region_id'] ?? 0);
  $clubName = clean_text($_POST['club_name'] ?? "");
  $presName = clean_text($_POST['president_name'] ?? "");

  if ($govId <= 0 || $regionId <= 0 || $clubName === "") {
    $errors[] = "Missing club fields.";
  } else {
    $stmt = $conn->prepare("INSERT INTO clubs (club_name, region_id, governor_id) VALUES (?, ?, ?)");
    $stmt->bind_param("sii", $clubName, $regionId, $govId);
    $stmt->execute();
    $clubId = (int)$conn->insert_id;
    $stmt->close();

    if ($presName !== "") {
      $stmt = $conn->prepare("INSERT INTO presidents (president_name, club_id, governor_id) VALUES (?, ?, ?)");
      $stmt->bind_param("sii", $presName, $clubId, $govId);
      $stmt->execute();
      $stmt->close();
    }

    if (function_exists('log_admin_action')) {
      $desc = "Added club ID #{$clubId} ({$clubName}) under Governor ID #{$govId}, Region ID #{$regionId}";
      if ($presName !== "") $desc .= " | President: {$presName}";
      log_admin_action($conn, "CREATE", $desc);
    }

    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?') . "?open_clubs=1&governor_id=" . $govId);
    exit;
  }
}

/* ---------- EDIT CLUB (and president) ---------- */
if (isset($_POST['edit_club'])) {
  if (!$canEdit) die("No permission.");

  $clubId   = (int)($_POST['edit_club_id'] ?? 0);
  $govId    = (int)($_POST['edit_club_governor_id'] ?? 0);
  $regionId = (int)($_POST['edit_club_region_id'] ?? 0);
  $clubName = clean_text($_POST['edit_club_name'] ?? "");
  $presName = clean_text($_POST['edit_president_name'] ?? "");

  $oldClub = null;
  if ($clubId > 0) {
    $s = $conn->prepare("
      SELECT c.club_name, c.region_id, p.president_name
      FROM clubs c
      LEFT JOIN presidents p ON p.club_id = c.club_id
      WHERE c.club_id=? LIMIT 1
    ");
    $s->bind_param("i", $clubId);
    $s->execute();
    $oldClub = $s->get_result()->fetch_assoc();
    $s->close();
  }

  if ($clubId <= 0 || $govId <= 0 || $regionId <= 0 || $clubName === "") {
    $errors[] = "Missing club fields.";
  } else {
    $stmt = $conn->prepare("UPDATE clubs SET club_name=?, region_id=? WHERE club_id=? AND governor_id=?");
    $stmt->bind_param("siii", $clubName, $regionId, $clubId, $govId);
    $stmt->execute();
    $stmt->close();

    $check = $conn->prepare("SELECT president_id FROM presidents WHERE club_id=? LIMIT 1");
    $check->bind_param("i", $clubId);
    $check->execute();
    $res = $check->get_result();

    if ($res && $res->num_rows === 1) {
      $pid = (int)$res->fetch_assoc()['president_id'];
      $check->close();

      if ($presName === "") {
        $del = $conn->prepare("DELETE FROM presidents WHERE president_id=?");
        $del->bind_param("i", $pid);
        $del->execute();
        $del->close();
      } else {
        $up = $conn->prepare("UPDATE presidents SET president_name=?, governor_id=? WHERE president_id=?");
        $up->bind_param("sii", $presName, $govId, $pid);
        $up->execute();
        $up->close();
      }
    } else {
      $check->close();
      if ($presName !== "") {
        $ins = $conn->prepare("INSERT INTO presidents (president_name, club_id, governor_id) VALUES (?, ?, ?)");
        $ins->bind_param("sii", $presName, $clubId, $govId);
        $ins->execute();
        $ins->close();
      }
    }

    if (function_exists('log_admin_action')) {
      $desc = "Edited club ID #{$clubId}";
      if ($oldClub) {
        $desc .= " (from: {$oldClub['club_name']} / RegionID {$oldClub['region_id']} / " . ($oldClub['president_name'] ?? 'No president') . ")";
      }
      $desc .= " -> {$clubName} / RegionID {$regionId} / " . ($presName !== "" ? $presName : "No president");
      log_admin_action($conn, "UPDATE", $desc);
    }

    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?') . "?open_clubs=1&governor_id=" . $govId);
    exit;
  }
}

// ======================= FETCH LIST =======================
$govSql = "
  SELECT
    g.governor_id,
    g.governor_name,
    g.governor_image,
    GROUP_CONCAT(r.region_name ORDER BY r.region_name SEPARATOR ' • ') AS regions
  FROM governors g
  LEFT JOIN regions r ON r.governor_id = g.governor_id
  GROUP BY g.governor_id, g.governor_name, g.governor_image
  ORDER BY g.governor_name ASC
";
$governors = $conn->query($govSql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Governors Management</title>

  <!-- FIX: since you moved admin pages into /admin, use site-root absolute -->
  <link rel="icon" type="image/png" href="/static/eagles.png">

  <link rel="stylesheet" href="../admin styles/governor.css">
  <link rel="stylesheet" href="../admin styles/sidebar.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    /* CONFIRM MODAL (reusable) */
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
      display:flex; align-items:center; justify-content:space-between;
      padding:14px 16px;
      border-bottom:1px solid rgba(0,0,0,.08);
    }
    .confirm-head h3{ margin:0; font-size:16px; }
    .confirm-x{
      border:0; background:transparent;
      font-size:22px; line-height:1;
      cursor:pointer; opacity:.7;
    }
    .confirm-x:hover{ opacity:1; }
    .confirm-body{ padding:14px 16px 6px; color:#222; font-size:14px; }
    .confirm-body p{ margin:0 0 10px; }
    .confirm-body .danger-note{ font-size:12px; opacity:.8; }
    .confirm-actions{
      display:flex; gap:10px;
      justify-content:flex-end;
      padding:12px 16px 16px;
    }
    .cbtn{
      border:0; cursor:pointer;
      border-radius:12px;
      padding:10px 14px;
      font-weight:700; font-size:13px;
    }
    .cbtn-cancel{ background:#eef2f7; color:#0b0f1a; }
    .cbtn-danger{ background:#c5303f; color:#fff; }
  </style>
</head>

<body>

<button class="sidebar-toggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button>
<?php include "sidebar.php"; ?>

<div class="main-content">
  <h1>Governors Management</h1>

  <?php if (!empty($errors)): ?>
    <div class="notice">
      <strong>Notice:</strong>
      <ul style="margin:8px 0 0 18px;">
        <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($canAdd): ?>
    <div class="top-actions">
      <button id="open-add-modal" class="add-btn"><i class="fas fa-plus"></i> Add Governor</button>
    </div>
  <?php endif; ?>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:56px;">Photo</th>
          <th>Name</th>
          <th>Regions</th>
          <th style="width:200px;">Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($governors): ?>
        <?php while ($row = $governors->fetch_assoc()): ?>
          <?php
            $imgFile = $row['governor_image'] ? ($UPLOAD_DIR_REL . $row['governor_image']) : $PLACEHOLDER_IMG;
            $regionsText = $row['regions'] ?? '';
            $regionsForTextarea = str_replace(' • ', "\n", $regionsText);
          ?>
          <tr>
            <td><img class="thumb" src="<?= h($imgFile) ?>" alt=""></td>
            <td class="name-cell"><?= h($row['governor_name']) ?></td>
            <td><?= h($regionsText) ?></td>
            <td style="white-space:nowrap;">
              <button
                type="button"
                class="edit-btn manage-clubs-btn"
                data-gov-id="<?= (int)$row['governor_id'] ?>"
                data-gov-name="<?= h($row['governor_name']) ?>"
                title="Manage clubs"
              ><i class="fas fa-sitemap"></i></button>

              <?php if ($canEdit): ?>
                <button
                  type="button"
                  class="edit-btn edit-gov-btn"
                  data-id="<?= (int)$row['governor_id'] ?>"
                  data-name="<?= h($row['governor_name']) ?>"
                  data-regions="<?= h($regionsForTextarea) ?>"
                  data-img="<?= h($row['governor_image'] ?? '') ?>"
                  title="Edit governor"
                ><i class="fas fa-edit"></i></button>
              <?php endif; ?>

              <?php if ($canDelete): ?>
                <!-- CHANGED: confirm() -> modal confirm with POST -->
                <form method="POST" class="delete-gov-form" style="display:inline; margin:0;">
                  <input type="hidden" name="delete_governor" value="1">
                  <input type="hidden" name="governor_id" value="<?= (int)$row['governor_id'] ?>">
                  <button type="button"
                          class="delete-btn js-open-delete-gov"
                          data-gov-name="<?= h($row['governor_name']) ?>"
                          title="Delete governor">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ADD GOVERNOR MODAL -->
<?php if ($canAdd): ?>
<div class="modal" id="add-modal" aria-hidden="true">
  <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="addTitle">
    <span class="close-btn" id="close-add" aria-label="Close">&times;</span>
    <h2 id="addTitle">Add Governor</h2>

    <form method="POST" enctype="multipart/form-data">
      <div class="grid2">
        <div>
          <label class="small">Governor Name</label>
          <input type="text" name="governor_name" required>
        </div>

        <div>
          <label class="small">Photo (optional)</label>
          <input type="file" name="governor_image" accept=".jpg,.jpeg,.png,.webp">
        </div>

        <div style="grid-column:1/-1;">
          <label class="small">Regions (one per line)</label>
          <textarea name="regions" placeholder="Example:
Cagayan Valley Region V
Supreme Cagayan Valley Region I"></textarea>
        </div>

        <div style="grid-column:1/-1;">
          <button type="submit" name="add_governor">Add Governor</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- EDIT GOVERNOR MODAL -->
<?php if ($canEdit): ?>
<div class="modal" id="edit-modal" aria-hidden="true">
  <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="editTitle">
    <span class="close-btn" id="close-edit" aria-label="Close">&times;</span>
    <h2 id="editTitle">Edit Governor</h2>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="governor_id" id="edit-gov-id">

      <div class="grid2">
        <div>
          <label class="small">Governor Name</label>
          <input type="text" name="governor_name" id="edit-gov-name" required>
        </div>

        <div>
          <label class="small">Change Photo (optional)</label>
          <input type="file" name="edit_governor_image" accept=".jpg,.jpeg,.png,.webp">
          <div class="small" id="edit-gov-current-photo" style="margin-top:6px;"></div>
        </div>

        <div style="grid-column:1/-1;">
          <label class="small">Regions (one per line)</label>
          <textarea name="regions" id="edit-gov-regions"></textarea>
          <div class="small" style="margin-top:6px;">
            Note: Regions with clubs assigned cannot be removed (they will be kept).
          </div>
        </div>

        <div style="grid-column:1/-1;">
          <button type="submit" name="edit_governor">Save Changes</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- MANAGE CLUBS MODAL -->
<div class="modal" id="clubs-modal" aria-hidden="true">
  <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="clubs-title">
    <span class="close-btn" id="close-clubs" aria-label="Close">&times;</span>

    <h2 id="clubs-title">Manage Clubs</h2>
    <div class="modal-tabs">
      <span class="pill" id="clubs-subtitle"></span>
      <?php if ($isSuperAdmin): ?><span class="pill">Delete enabled</span><?php endif; ?>
    </div>

    <div id="clubs-loading">Loading...</div>
    <div id="clubs-body" style="display:none;"></div>

    <?php if ($canAdd): ?>
      <div class="hr"></div>
      <h3>Add Club</h3>

      <form method="POST" id="add-club-form" class="grid2">
        <input type="hidden" name="club_governor_id" id="club-gov-id">

        <div>
          <label class="small">Club Name</label>
          <input type="text" name="club_name" required>
        </div>

        <div>
          <label class="small">Region</label>
          <select name="club_region_id" id="club-region-select" required></select>
        </div>

        <div style="grid-column:1/-1;">
          <label class="small">President Name (optional)</label>
          <input type="text" name="president_name">
        </div>

        <div style="grid-column:1/-1;">
          <button type="submit" name="add_club">Add Club</button>
        </div>
      </form>
    <?php endif; ?>

    <?php if ($canEdit): ?>
      <div class="hr"></div>
      <h3>Edit Club</h3>

      <form method="POST" id="edit-club-form" class="grid2">
        <input type="hidden" name="edit_club_id" id="edit-club-id">
        <input type="hidden" name="edit_club_governor_id" id="edit-club-gov-id">

        <div>
          <label class="small">Club Name</label>
          <input type="text" name="edit_club_name" id="edit-club-name" required>
        </div>

        <div>
          <label class="small">Region</label>
          <select name="edit_club_region_id" id="edit-club-region" required></select>
        </div>

        <div style="grid-column:1/-1;">
          <label class="small">President Name (leave empty to remove)</label>
          <input type="text" name="edit_president_name" id="edit-president-name">
        </div>

        <div style="grid-column:1/-1;">
          <button type="submit" name="edit_club">Save Club</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<!-- DELETE CONFIRM MODAL (REUSED FOR GOV + CLUB) -->
<div class="confirm-modal" id="confirm-delete-modal" aria-hidden="true">
  <div class="confirm-card" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
    <div class="confirm-head">
      <h3 id="confirm-title">Confirm deletion</h3>
      <button class="confirm-x" type="button" id="confirm-x" aria-label="Close">&times;</button>
    </div>
    <div class="confirm-body">
      <p id="confirm-msg">Delete?</p>
      <p class="danger-note" id="confirm-note">This action cannot be undone.</p>
    </div>
    <div class="confirm-actions">
      <button class="cbtn cbtn-cancel" type="button" id="confirm-cancel">Cancel</button>
      <button class="cbtn cbtn-danger" type="button" id="confirm-do">Delete</button>
    </div>
  </div>
</div>

<script>
/* Sidebar toggle */
document.querySelector(".sidebar-toggle")?.addEventListener("click", () => {
  const sb = document.querySelector(".sidebar");
  if (sb) sb.classList.toggle("show");
});

const canEdit = <?= $canEdit ? "true" : "false" ?>;

/* Modal helpers */
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
  if (!document.querySelector(".modal.show") && !document.getElementById("confirm-delete-modal")?.classList.contains("show")) {
    document.body.classList.remove("modal-open");
  }
}

/* ESC to close topmost modal */
document.addEventListener("keydown", (e) => {
  if (e.key !== "Escape") return;

  const confirmModal = document.getElementById("confirm-delete-modal");
  if (confirmModal && confirmModal.classList.contains("show")) {
    closeConfirm();
    return;
  }

  const top = document.querySelector(".modal.show");
  if (top) closeModal(top);
});

/* ===== Governor modals ===== */
const addModal = document.getElementById("add-modal");
const editModal = document.getElementById("edit-modal");

document.getElementById("open-add-modal")?.addEventListener("click", () => openModal(addModal));
document.getElementById("close-add")?.addEventListener("click", () => closeModal(addModal));
document.getElementById("close-edit")?.addEventListener("click", () => closeModal(editModal));

document.querySelectorAll(".edit-gov-btn").forEach(btn => {
  btn.addEventListener("click", () => {
    document.getElementById("edit-gov-id").value = btn.dataset.id || "";
    document.getElementById("edit-gov-name").value = btn.dataset.name || "";
    document.getElementById("edit-gov-regions").value = btn.dataset.regions || "";

    const current = document.getElementById("edit-gov-current-photo");
    const imgFile = btn.dataset.img || "";
    if (current) current.textContent = imgFile ? ("Current photo: " + imgFile) : "Current photo: (none)";

    openModal(editModal);
  });
});

/* ===== Clubs modal ===== */
const clubsModal = document.getElementById("clubs-modal");
const clubsTitle = document.getElementById("clubs-title");
const clubsSubtitle = document.getElementById("clubs-subtitle");
const clubsLoading = document.getElementById("clubs-loading");
const clubsBody = document.getElementById("clubs-body");

document.getElementById("close-clubs")?.addEventListener("click", () => closeModal(clubsModal));

const clubGovIdInput = document.getElementById("club-gov-id");
const regionSelect = document.getElementById("club-region-select");

const editClubId = document.getElementById("edit-club-id");
const editClubGovId = document.getElementById("edit-club-gov-id");
const editClubName = document.getElementById("edit-club-name");
const editClubRegion = document.getElementById("edit-club-region");
const editPresidentName = document.getElementById("edit-president-name");

let currentGovId = null;

function sameFileUrl(params) {
  const url = new URL(window.location.href);
  Object.entries(params).forEach(([k,v]) => url.searchParams.set(k, v));
  return url.toString();
}

function loadRegionsInto(selectEl, govId, selectedId = null) {
  if (!selectEl) return;
  fetch(sameFileUrl({ action: "regions", governor_id: govId }))
    .then(r => r.text())
    .then(html => {
      selectEl.innerHTML = html;
      if (selectedId) selectEl.value = String(selectedId);
    })
    .catch(() => {
      selectEl.innerHTML = "<option value='' disabled selected>No regions</option>";
    });
}

function loadClubsTable(govId) {
  if (clubsLoading) clubsLoading.style.display = "block";
  if (clubsBody) {
    clubsBody.style.display = "none";
    clubsBody.innerHTML = "";
  }

  fetch(sameFileUrl({ action: "clubs", governor_id: govId }))
    .then(r => r.text())
    .then(html => {
      if (clubsLoading) clubsLoading.style.display = "none";
      if (!clubsBody) return;

      clubsBody.style.display = "block";
      clubsBody.innerHTML = html;

      if (canEdit) {
        clubsBody.querySelectorAll(".club-edit-btn").forEach(b => {
          b.addEventListener("click", () => {
            if (editClubId) editClubId.value = b.dataset.clubId || "";
            if (editClubGovId) editClubGovId.value = currentGovId || "";
            if (editClubName) editClubName.value = b.dataset.clubName || "";
            if (editPresidentName) editPresidentName.value = b.dataset.president || "";
            loadRegionsInto(editClubRegion, currentGovId, b.dataset.regionId);
          });
        });
      }

      // hook delete buttons that were injected via AJAX
      clubsBody.querySelectorAll(".js-open-delete-club").forEach(btn => {
        btn.addEventListener("click", () => {
          const form = btn.closest("form.delete-club-form");
          const nm = btn.dataset.clubName || "this club";
          openConfirm(form, `Delete "${nm}"?`, "This will delete the club and its president record.");
        });
      });
    })
    .catch(() => {
      if (clubsLoading) clubsLoading.style.display = "none";
      if (clubsBody) {
        clubsBody.style.display = "block";
        clubsBody.innerHTML = "<p class='empty'>Failed to load clubs.</p>";
      }
    });
}

function openClubsModal(govId, govName) {
  currentGovId = govId;

  if (clubsTitle) clubsTitle.textContent = "Manage Clubs — " + govName;
  if (clubsSubtitle) clubsSubtitle.textContent = "Governor ID: " + govId;

  openModal(clubsModal);

  if (clubGovIdInput) clubGovIdInput.value = govId;

  loadRegionsInto(regionSelect, govId);
  loadRegionsInto(editClubRegion, govId);

  loadClubsTable(govId);
}

document.querySelectorAll(".manage-clubs-btn").forEach(btn => {
  btn.addEventListener("click", () => openClubsModal(btn.dataset.govId, btn.dataset.govName));
});

/* auto reopen after redirect (?open_clubs=1&governor_id=..) */
(function reopenClubsAfterSave(){
  const params = new URLSearchParams(window.location.search);
  const open = params.get("open_clubs");
  const govId = params.get("governor_id");

  if (open === "1" && govId) {
    const btn = document.querySelector(`.manage-clubs-btn[data-gov-id="${govId}"]`);
    const govName = btn ? btn.dataset.govName : ("Governor " + govId);

    openClubsModal(govId, govName);

    params.delete("open_clubs");
    params.delete("governor_id");
    const cleanUrl = window.location.pathname + (params.toString() ? ("?" + params.toString()) : "");
    window.history.replaceState({}, "", cleanUrl);
  }
})();

/* =============================
   CONFIRM DELETE (REUSABLE)
   - Uses form.submit() but forms include hidden flags
============================= */
const confirmModal = document.getElementById("confirm-delete-modal");
const confirmMsg = document.getElementById("confirm-msg");
const confirmNote = document.getElementById("confirm-note");
const confirmDo = document.getElementById("confirm-do");
const confirmCancel = document.getElementById("confirm-cancel");
const confirmX = document.getElementById("confirm-x");

let pendingForm = null;

function openConfirm(formEl, msg, note){
  pendingForm = formEl;
  if (confirmMsg) confirmMsg.textContent = msg || "Delete?";
  if (confirmNote) confirmNote.textContent = note || "This action cannot be undone.";
  if (confirmModal) {
    confirmModal.classList.add("show");
    confirmModal.setAttribute("aria-hidden", "false");
  }
  document.body.classList.add("modal-open");
  confirmDo?.focus();
}

function closeConfirm(){
  if (confirmModal) {
    confirmModal.classList.remove("show");
    confirmModal.setAttribute("aria-hidden", "true");
  }
  pendingForm = null;

  if (!document.querySelector(".modal.show")) {
    document.body.classList.remove("modal-open");
  }
}

confirmDo?.addEventListener("click", () => {
  if (!pendingForm) return;
  pendingForm.submit(); // works because hidden delete_governor/delete_club exists in the form
});

confirmCancel?.addEventListener("click", closeConfirm);
confirmX?.addEventListener("click", closeConfirm);

/* Hook governor delete buttons (static in table) */
document.querySelectorAll(".js-open-delete-gov").forEach(btn => {
  btn.addEventListener("click", () => {
    const form = btn.closest("form.delete-gov-form");
    const nm = btn.dataset.govName || "this governor";
    openConfirm(form, `Delete "${nm}"?`, "This will delete the governor and ALL its regions/clubs/presidents.");
  });
});
</script>

</body>
</html>