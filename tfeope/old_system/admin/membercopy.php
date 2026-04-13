<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
  header("Location: admin-login");
  exit;
}

require_once __DIR__ . "/../includes/db.php";
if (!isset($conn) || !($conn instanceof mysqli)) {
  die("Database connection not found. Check ../includes/db.php.");
}

$roleId = (int)($_SESSION['role_id'] ?? 0);
$isSuperAdmin = ($roleId === 1);

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function up($v): string { return strtoupper(trim((string)$v)); }

function set_flash(string $type, string $message): void {
  $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}
function pull_flash(): ?array {
  if (!isset($_SESSION['flash'])) return null;
  $f = $_SESSION['flash'];
  unset($_SESSION['flash']);
  return is_array($f) ? $f : null;
}

function set_csv_duplicates(array $dupes): void {
  $_SESSION['csv_duplicates'] = $dupes;
}
function pull_csv_duplicates(): array {
  $d = $_SESSION['csv_duplicates'] ?? [];
  unset($_SESSION['csv_duplicates']);
  return is_array($d) ? $d : [];
}

function redirect_keep_params(array $dropKeys = []): void {
  $params = $_GET;
  foreach ($dropKeys as $k) unset($params[$k]);
  $qs = $params ? ("?" . http_build_query($params)) : "";
  header("Location: member{$qs}");
  exit;
}

function normalize_pic_path(?string $raw): ?string {
  $p = trim((string)$raw);
  if ($p === "") return null;

  if (preg_match('~^https?://~i', $p)) return $p;

  $p = preg_replace('~\?.*$~', '', $p);
  $p = str_replace('\\', '/', $p);
  $p = ltrim($p, '/');

  if (stripos($p, 'uploads/') !== false) {
    $pos = stripos($p, 'uploads/');
    $p = substr($p, $pos);
    return $p;
  }

  if (stripos($p, 'static/') !== false) {
    $pos = stripos($p, 'static/');
    $p = substr($p, $pos);
    return $p;
  }

  return "uploads/" . $p;
}

function photo_url(?string $dbPath): string {
  $p = normalize_pic_path($dbPath);
  if ($p === null) return "/static/default.jpg";
  if (preg_match('~^https?://~i', $p)) return $p;
  return "/" . ltrim($p, '/');
}

function delete_local_upload(?string $dbPath): bool {
  $p = normalize_pic_path($dbPath);
  if ($p === null) return false;
  if (preg_match('~^https?://~i', $p)) return false;

  $p = ltrim($p, '/');
  if (stripos($p, 'uploads/') !== 0) return false;

  $uploads_dir = rtrim((string)$_SERVER['DOCUMENT_ROOT'], "/\\") . DIRECTORY_SEPARATOR . "uploads";
  $abs = rtrim((string)$_SERVER['DOCUMENT_ROOT'], "/\\") . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $p);

  $realUploads = realpath($uploads_dir);
  $realFile = realpath($abs);

  if ($realFile === false) return false;
  if ($realUploads === false || strpos($realFile, $realUploads) !== 0) return false;

  return @unlink($realFile);
}

function handle_photo_upload_with_id(string $memberId, string $fieldName = 'photo'): ?string {
  if (!isset($_FILES[$fieldName]) || ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;

  $uploads_dir = rtrim((string)$_SERVER['DOCUMENT_ROOT'], "/\\") . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR;
  if (!is_dir($uploads_dir)) @mkdir($uploads_dir, 0755, true);

  $orig = (string)($_FILES[$fieldName]['name'] ?? '');
  $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  $allowed = ['jpg','jpeg','png','webp','gif'];

  if (!$ext || !in_array($ext, $allowed, true)) return null;

  $tmp = $_FILES[$fieldName]['tmp_name'] ?? '';
  $imgInfo = ($tmp !== '') ? @getimagesize($tmp) : false;
  if ($imgInfo === false) return null;

  $safeId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim((string)$memberId));
  if ($safeId === '') return null;

  if ($ext === 'jpeg') $ext = 'jpg';

  foreach ($allowed as $a) {
    $a2 = ($a === 'jpeg') ? 'jpg' : $a;
    $old = $uploads_dir . $safeId . "." . $a2;
    if (is_file($old)) @unlink($old);
  }

  $file_name = $safeId . "." . $ext;
  $dest = $uploads_dir . $file_name;

  if (move_uploaded_file($tmp, $dest)) {
    return "uploads/" . $file_name;
  }
  return null;
}

/* ================= NEW: MULTIPLE PHOTO UPLOAD FOR CSV MODE ================= */
function handle_csv_multiple_photos(string $fieldName = 'import_photos'): array {
  $saved = [];

  if (
    !isset($_FILES[$fieldName]) ||
    !isset($_FILES[$fieldName]['name']) ||
    !is_array($_FILES[$fieldName]['name'])
  ) {
    return $saved;
  }

  $uploads_dir = rtrim((string)$_SERVER['DOCUMENT_ROOT'], "/\\") . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR;
  if (!is_dir($uploads_dir)) {
    @mkdir($uploads_dir, 0755, true);
  }

  $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
  $count = count($_FILES[$fieldName]['name']);

  for ($i = 0; $i < $count; $i++) {
    $error = $_FILES[$fieldName]['error'][$i] ?? UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK) continue;

    $origName = (string)($_FILES[$fieldName]['name'][$i] ?? '');
    $tmpPath  = (string)($_FILES[$fieldName]['tmp_name'][$i] ?? '');

    if ($origName === '' || $tmpPath === '') continue;
    if (!is_uploaded_file($tmpPath)) continue;

    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) continue;

    $imgInfo = @getimagesize($tmpPath);
    if ($imgInfo === false) continue;

    $baseName = pathinfo($origName, PATHINFO_FILENAME);
    $safeId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim((string)$baseName));
    if ($safeId === '') continue;

    if ($ext === 'jpeg') $ext = 'jpg';

    foreach ($allowed as $a) {
      $a2 = ($a === 'jpeg') ? 'jpg' : $a;
      $old = $uploads_dir . $safeId . "." . $a2;
      if (is_file($old)) @unlink($old);
    }

    $fileName = $safeId . "." . $ext;
    $dest = $uploads_dir . $fileName;

    if (move_uploaded_file($tmpPath, $dest)) {
      $saved[$safeId] = "uploads/" . $fileName;
    }
  }

  return $saved;
}

$verifyMember = null;

if (isset($_POST['verify_member'])) {
  $vid = trim((string)($_POST['verify_id'] ?? ''));
  if ($vid === "") {
    set_flash('error', 'Please enter an Eagles ID.');
  } else {
    $stmt = $conn->prepare("SELECT * FROM user_info WHERE eagles_id = ? LIMIT 1");
    if (!$stmt) {
      set_flash('error', 'Search failed: ' . $conn->error);
    } else {
      $stmt->bind_param("s", $vid);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($res && $res->num_rows === 1) {
        $verifyMember = $res->fetch_assoc();
      } else {
        set_flash('error', 'Member not found.');
      }
      $stmt->close();
    }
  }
}

if (isset($_POST['bulk_delete'])) {
  if (!$isSuperAdmin) die("You do not have permission to delete members.");

  $ids = $_POST['selected_ids'] ?? [];
  if (!is_array($ids)) $ids = [];

  $clean = [];
  foreach ($ids as $id) {
    $id = trim((string)$id);
    if ($id !== "") $clean[$id] = true;
  }
  $ids = array_keys($clean);

  if (count($ids) === 0) {
    set_flash('warning', 'No members selected.');
    redirect_keep_params();
  }

  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $types = str_repeat('s', count($ids));

  $picsById = [];
  $sqlPics = "SELECT eagles_id, eagles_pic FROM user_info WHERE eagles_id IN ($placeholders)";
  $stmtPics = $conn->prepare($sqlPics);
  if (!$stmtPics) {
    set_flash('error', 'Bulk delete failed: ' . $conn->error);
    redirect_keep_params();
  }
  $stmtPics->bind_param($types, ...$ids);
  $stmtPics->execute();
  $resPics = $stmtPics->get_result();
  if ($resPics) {
    while ($r = $resPics->fetch_assoc()) {
      $picsById[(string)$r['eagles_id']] = $r['eagles_pic'] ?? null;
    }
  }
  $stmtPics->close();

  $sqlDel = "DELETE FROM user_info WHERE eagles_id IN ($placeholders)";
  $stmtDel = $conn->prepare($sqlDel);
  if (!$stmtDel) {
    set_flash('error', 'Bulk delete failed: ' . $conn->error);
    redirect_keep_params();
  }
  $stmtDel->bind_param($types, ...$ids);
  $ok = $stmtDel->execute();
  $affected = $stmtDel->affected_rows;
  $stmtDel->close();

  if ($ok) {
    foreach ($ids as $id) {
      delete_local_upload($picsById[$id] ?? null);
    }
    set_flash('success', "Deleted {$affected} member(s).");
  } else {
    set_flash('error', 'Bulk delete failed. Please try again.');
  }

  redirect_keep_params();
}

if (isset($_GET['delete_id'])) {
  if (!$isSuperAdmin) die("You do not have permission to delete members.");
  $delete_id = trim((string)$_GET['delete_id']);

  $picPath = null;
  $getStmt = $conn->prepare("SELECT eagles_pic FROM user_info WHERE eagles_id = ? LIMIT 1");
  if ($getStmt) {
    $getStmt->bind_param("s", $delete_id);
    $getStmt->execute();
    $getRes = $getStmt->get_result();
    if ($getRes && $getRes->num_rows === 1) {
      $r = $getRes->fetch_assoc();
      $picPath = $r['eagles_pic'] ?? null;
    }
    $getStmt->close();
  }

  $stmt = $conn->prepare("DELETE FROM user_info WHERE eagles_id = ?");
  if (!$stmt) {
    set_flash('error', 'Delete failed: ' . $conn->error);
    redirect_keep_params(['delete_id']);
  }

  $stmt->bind_param("s", $delete_id);
  $ok = $stmt->execute();
  $stmt->close();

  if ($ok) {
    delete_local_upload($picPath);
    set_flash('success', 'Member deleted successfully.');
  } else {
    set_flash('error', 'Delete failed. Please try again.');
  }

  redirect_keep_params(['delete_id']);
}

/* =========================================================
   ADD MEMBER (MANUAL OR CSV)
========================================================= */
if (isset($_POST['add_member'])) {
  if (!$isSuperAdmin) die("You do not have permission to add members.");

  /* ---------- CSV MODE ---------- */
  if (($_POST['add_mode'] ?? '') === 'csv') {

    if (!isset($_FILES['import_csv']) || ($_FILES['import_csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      set_flash('error', "CSV upload failed. Error code: " . ($_FILES['import_csv']['error'] ?? 'no_file'));
      header("Location: member");
      exit;
    }

    $file_tmp = $_FILES['import_csv']['tmp_name'];
    $handle = fopen($file_tmp, "r");
    if ($handle === false) {
      set_flash('error', "Unable to open uploaded CSV file.");
      header("Location: member");
      exit;
    }

    /* NEW: process multiple uploaded photos */
    $csvUploadedPhotos = handle_csv_multiple_photos('import_photos');

    $firstLine = fgets($handle);
    rewind($handle);
    $delimiter = (substr_count((string)$firstLine, ';') > substr_count((string)$firstLine, ',')) ? ';' : ',';

    fgetcsv($handle, 0, $delimiter); // skip header

    $insertStmt = $conn->prepare("
      INSERT INTO user_info
      (eagles_id, eagles_firstName, eagles_lastName, eagles_position, eagles_club, eagles_region, eagles_status, eagles_pic)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$insertStmt) {
      fclose($handle);
      set_flash('error', "Prepare failed: " . $conn->error);
      header("Location: member");
      exit;
    }

    $getExistingStmt = $conn->prepare("SELECT * FROM user_info WHERE eagles_id = ? LIMIT 1");
    if (!$getExistingStmt) {
      $insertStmt->close();
      fclose($handle);
      set_flash('error', "Prepare failed: " . $conn->error);
      header("Location: member");
      exit;
    }

    $inserted = 0;
    $failed = 0;
    $dupCount = 0;
    $duplicates = [];

    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
      if (count($data) === 1 && trim((string)$data[0]) === "") continue;

      $data = array_map('trim', $data);

      $id         = !empty($data[0]) ? trim((string)$data[0]) : uniqid('EAG_');
      $first_name = up($data[1] ?? "");
      $last_name  = up($data[2] ?? "");
      $position   = up($data[3] ?? "");
      $club       = up($data[4] ?? "");
      $region     = up($data[5] ?? "");
      $status     = !empty($data[6]) ? up($data[6]) : "ACTIVE";

      $photo_raw = (string)($data[7] ?? "");
      $photo_path = $csvUploadedPhotos[$id] ?? normalize_pic_path($photo_raw);

      if ($first_name === "" || $last_name === "" || $position === "" || $club === "" || $region === "") {
        $failed++;
        continue;
      }

      $insertStmt->bind_param("ssssssss", $id, $first_name, $last_name, $position, $club, $region, $status, $photo_path);

      try {
        $insertStmt->execute();
        $inserted++;
      } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() === 1062) {
          $dupCount++;

          $getExistingStmt->bind_param("s", $id);
          $getExistingStmt->execute();
          $resEx = $getExistingStmt->get_result();
          $existing = ($resEx && $resEx->num_rows === 1) ? $resEx->fetch_assoc() : [];

          $duplicates[] = [
            'id' => $id,
            'existing' => [
              'eagles_id' => $existing['eagles_id'] ?? $id,
              'eagles_firstName' => $existing['eagles_firstName'] ?? '',
              'eagles_lastName' => $existing['eagles_lastName'] ?? '',
              'eagles_position' => $existing['eagles_position'] ?? '',
              'eagles_club' => $existing['eagles_club'] ?? '',
              'eagles_region' => $existing['eagles_region'] ?? '',
              'eagles_status' => $existing['eagles_status'] ?? '',
              'eagles_pic' => $existing['eagles_pic'] ?? '',
            ],
            'incoming' => [
              'eagles_id' => $id,
              'eagles_firstName' => $first_name,
              'eagles_lastName' => $last_name,
              'eagles_position' => $position,
              'eagles_club' => $club,
              'eagles_region' => $region,
              'eagles_status' => $status,
              'eagles_pic' => $photo_path,
            ],
          ];

          continue;
        }

        $failed++;
        continue;
      }
    }

    $insertStmt->close();
    $getExistingStmt->close();
    fclose($handle);

    if ($dupCount > 0) set_csv_duplicates($duplicates);

    if ($inserted > 0 && $failed === 0 && $dupCount === 0) {
      set_flash('success', "CSV import complete. Inserted: {$inserted}.");
    } elseif ($inserted > 0 && ($failed > 0 || $dupCount > 0)) {
      set_flash('warning', "CSV import complete. Inserted: {$inserted}, Duplicates skipped: {$dupCount}, Failed: {$failed}.");
    } elseif ($inserted === 0 && $dupCount > 0 && $failed === 0) {
      set_flash('warning', "CSV import complete. No new rows inserted. Duplicates skipped: {$dupCount}.");
    } else {
      set_flash('error', "CSV import failed. Inserted: {$inserted}, Duplicates skipped: {$dupCount}, Failed: {$failed}.");
    }

    header("Location: member");
    exit;
  }

  /* ---------- MANUAL MODE ---------- */
  if (($_POST['add_mode'] ?? '') === 'manual') {
    $id         = !empty($_POST['id']) ? trim((string)$_POST['id']) : uniqid('EAG_');
    $first_name = up($_POST['first_name'] ?? "");
    $last_name  = up($_POST['last_name'] ?? "");
    $position   = up($_POST['position'] ?? "");

    $clubSel    = trim((string)($_POST['club'] ?? ""));
    $clubNew    = up($_POST['club_new'] ?? "");
    $club       = ($clubSel === '__NEW__') ? $clubNew : up($clubSel);

    $regionSel  = trim((string)($_POST['region'] ?? ""));
    $regionNew  = up($_POST['region_new'] ?? "");
    $region     = ($regionSel === '__NEW__') ? $regionNew : up($regionSel);

    $status     = "ACTIVE";

    if ($first_name === "" || $last_name === "" || $position === "" || $club === "" || $region === "") {
      set_flash('error', 'Please complete all required fields.');
      header("Location: member");
      exit;
    }

    $photo_path = handle_photo_upload_with_id($id, 'photo');

    $stmt = $conn->prepare("
      INSERT INTO user_info
      (eagles_id, eagles_firstName, eagles_lastName, eagles_position, eagles_club, eagles_region, eagles_status, eagles_pic)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
      set_flash('error', 'Add failed: ' . $conn->error);
      header("Location: member");
      exit;
    }

    try {
      $stmt->bind_param("ssssssss", $id, $first_name, $last_name, $position, $club, $region, $status, $photo_path);
      $stmt->execute();
      $ok = true;
    } catch (mysqli_sql_exception $e) {
      $ok = false;
      if ((int)$e->getCode() === 1062) {
        set_flash('error', "Add failed: Duplicate Eagles ID ({$id}).");
      } else {
        set_flash('error', "Add failed: " . $e->getMessage());
      }
    }
    $stmt->close();

    if ($ok) set_flash('success', 'Member added successfully.');
    header("Location: member");
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

  $clubSel    = trim((string)($_POST['club'] ?? ""));
  $clubNew    = up($_POST['club_new'] ?? "");
  $club       = ($clubSel === '__NEW__') ? $clubNew : up($clubSel);

  $regionSel  = trim((string)($_POST['region'] ?? ""));
  $regionNew  = up($_POST['region_new'] ?? "");
  $region     = ($regionSel === '__NEW__') ? $regionNew : up($regionSel);

  $status     = up($_POST['status'] ?? "");

  if ($first_name === "" || $last_name === "" || $position === "" || $club === "" || $region === "" || $status === "") {
    set_flash('error', 'Please complete all required fields.');
    header("Location: member");
    exit;
  }

  $oldPic = null;
  $qOld = $conn->prepare("SELECT eagles_pic FROM user_info WHERE eagles_id=? LIMIT 1");
  if ($qOld) {
    $qOld->bind_param("s", $id);
    $qOld->execute();
    $oldPic = $qOld->get_result()->fetch_assoc()['eagles_pic'] ?? null;
    $qOld->close();
  }

  $photo_path = handle_photo_upload_with_id($id, 'photo');
  if ($photo_path !== null) {
    delete_local_upload($oldPic);
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
    if (!$stmt) {
      set_flash('error', "Edit failed: " . $conn->error);
      header("Location: member");
      exit;
    }
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
    if (!$stmt) {
      set_flash('error', "Edit failed: " . $conn->error);
      header("Location: member");
      exit;
    }
    $stmt->bind_param("sssssss", $first_name, $last_name, $position, $club, $region, $status, $id);
  }

  $ok = $stmt->execute();
  $stmt->close();

  set_flash($ok ? 'success' : 'error', $ok ? 'Member updated successfully.' : 'Update failed. Please try again.');
  header("Location: member");
  exit;
}

/* ================= FILTERS + SORT ================= */
$selectedClub   = trim((string)($_GET['club'] ?? ''));
$selectedRegion = trim((string)($_GET['region'] ?? ''));
$sort = trim((string)($_GET['sort'] ?? 'newest'));

/* ================= FILTER OPTIONS ================= */
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

if ($selectedRegion !== "" && $selectedClub !== "" && !in_array($selectedClub, $clubOptions, true)) {
  $selectedClub = "";
}

$modalRegions = $regionOptions;

$modalClubs = [];
$modalClubRes = $conn->query("
  SELECT DISTINCT eagles_club
  FROM user_info
  WHERE eagles_club IS NOT NULL AND eagles_club != ''
  ORDER BY eagles_club ASC
");
if ($modalClubRes) {
  while ($r = $modalClubRes->fetch_assoc()) $modalClubs[] = $r['eagles_club'];
  $modalClubRes->free();
}

/* ================= PAGINATION ================= */
$perPage = 15;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1);

$countSql = "SELECT COUNT(*) AS total FROM user_info";
$countParams = [];
$countTypes = "";
$where = [];

if ($selectedRegion !== "") { $where[] = "eagles_region = ?"; $countParams[] = $selectedRegion; $countTypes .= "s"; }
if ($selectedClub !== "")   { $where[] = "eagles_club = ?";   $countParams[] = $selectedClub;   $countTypes .= "s"; }
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
$orderBy = "eagles_id DESC";
if ($sort === 'oldest')  $orderBy = "eagles_id ASC";
if ($sort === 'name_az') $orderBy = "eagles_lastName ASC, eagles_firstName ASC";
if ($sort === 'name_za') $orderBy = "eagles_lastName DESC, eagles_firstName DESC";

$sql = "SELECT * FROM user_info";
$params = [];
$types = "";
$where = [];

if ($selectedRegion !== "") { $where[] = "eagles_region = ?"; $params[] = $selectedRegion; $types .= "s"; }
if ($selectedClub !== "")   { $where[] = "eagles_club = ?";   $params[] = $selectedClub;   $types .= "s"; }
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

function pageUrl(int $targetPage): string {
  $params = $_GET;
  $params['page'] = $targetPage;
  return "member?" . http_build_query($params);
}

$flash = pull_flash();
$csvDupes = pull_csv_duplicates();

function status_class($status): string {
  $s = strtoupper(trim((string)$status));
  if ($s === 'ACTIVE') return 'status-active';
  if ($s === 'RENEWAL') return 'status-renewal';
  return 'status-other';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Members Management</title>
<link rel="icon" type="image/png" href="/static/eagles.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../admin styles/member.css">
<link rel="stylesheet" href="../admin styles/sidebar.css">
<style>
.pagination{display:flex;justify-content:center;align-items:center;gap:8px;margin:20px 0 10px;flex-wrap:wrap;}
.page-btn{padding:8px 14px;border:1px solid rgba(0,0,0,.18);background:#fff;color:#333;text-decoration:none;border-radius:8px;font-size:14px;}
.page-btn:hover{background:#f0f0f0;}
.page-btn.active{background:#1f4068;color:#fff;border-color:#1f4068;pointer-events:none;}
.page-meta{text-align:center;margin:6px 0 14px;color:rgba(0,0,0,.65);font-size:13px;}
.new-inline{display:none;margin-top:8px;}
.new-inline input{width:100%;}
.verify-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:14px 0 6px;}
.verify-row form{display:flex;gap:10px;align-items:center;flex:1;min-width:260px;}
.verify-row input{flex:1;min-width:200px;padding:10px 12px;border:1px solid rgba(0,0,0,.18);border-radius:10px;}
.verify-row button{padding:10px 14px;border:none;border-radius:10px;background:#1f4068;color:#fff;font-weight:700;cursor:pointer;}
.verify-row button:hover{filter:brightness(.95);}
.bulk-actions{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin:10px 0 12px;}
.bulk-left{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.bulk-count{font-size:13px;color:rgba(0,0,0,.7);}
.bulk-delete-btn{padding:10px 14px;border:none;border-radius:10px;background:#b00020;color:#fff;font-weight:800;cursor:pointer;}
.bulk-delete-btn:disabled{opacity:.45;cursor:not-allowed;}
.selbox{width:18px;height:18px;vertical-align:middle;cursor:pointer;}
th.select-col, td.select-col{width:44px;text-align:center;}
</style>
</head>

<body>

<button class="sidebar-toggle"><i class="fas fa-bars"></i></button>
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

<div class="main-content">
  <h1>Members Management</h1>

  <?php if ($isSuperAdmin): ?>
  <div class="top-buttons">
    <button class="add-btn" id="open-add-modal"><i class="fas fa-plus"></i> Add New Member</button>
  </div>
  <?php endif; ?>

  <div class="verify-row">
    <form method="POST">
      <input type="text" name="verify_id" placeholder="Search Eagles ID" required>
      <button type="submit" name="verify_member"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
    </form>
  </div>

  <div class="filters-wrap">
    <div class="filters-head">
      <div>
        <p class="filters-title">Filters</p>
        <p class="filters-sub">Pick a region first to narrow the club list.</p>
      </div>

      <?php if ($selectedClub !== "" || $selectedRegion !== "" || ($sort !== "" && $sort !== "newest")): ?>
        <div class="filter-chips">
          <?php if ($selectedRegion !== ""): ?><span class="chip">Region: <strong><?= h($selectedRegion) ?></strong></span><?php endif; ?>
          <?php if ($selectedClub !== ""): ?><span class="chip">Club: <strong><?= h($selectedClub) ?></strong></span><?php endif; ?>
          <?php if ($sort !== "" && $sort !== "newest"): ?><span class="chip">Sort: <strong><?= h($sort) ?></strong></span><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <form class="filter-bar" method="GET" id="filterForm">
      <input type="hidden" name="page" value="1">

      <div class="filter-field">
        <select name="region" id="regionSelect">
          <option value="">— All Regions —</option>
          <?php foreach ($regionOptions as $regOpt): ?>
            <option value="<?= h($regOpt) ?>" <?= $regOpt === $selectedRegion ? 'selected' : '' ?>><?= h($regOpt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-field">
        <select name="club" id="clubSelect">
          <option value="">— All Clubs —</option>
          <?php foreach ($clubOptions as $clubOpt): ?>
            <option value="<?= h($clubOpt) ?>" <?= $clubOpt === $selectedClub ? 'selected' : '' ?>><?= h($clubOpt) ?></option>
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

  <form method="POST" id="bulkDeleteForm">
    <?php if ($isSuperAdmin): ?>
      <div class="bulk-actions">
        <div class="bulk-left">
          <strong>Bulk Actions</strong>
          <span class="bulk-count" id="bulkCount">0 selected</span>
        </div>

        <button
          type="submit"
          name="bulk_delete"
          class="bulk-delete-btn"
          id="bulkDeleteBtn"
          disabled
          onclick="return confirm('Delete selected members? This will also delete their uploaded photos.');"
        >
          <i class="fa-solid fa-trash"></i> Delete Selected
        </button>
      </div>
    <?php endif; ?>

    <table>
      <thead>
        <tr>
          <?php if ($isSuperAdmin): ?>
            <th class="select-col">
              <input type="checkbox" class="selbox" id="selectAll" aria-label="Select all">
            </th>
          <?php endif; ?>
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
              <?php if ($isSuperAdmin): ?>
                <td class="select-col">
                  <input
                    type="checkbox"
                    class="selbox row-check"
                    name="selected_ids[]"
                    value="<?= h($row['eagles_id']) ?>"
                    aria-label="Select <?= h($row['eagles_id']) ?>"
                  >
                </td>
              <?php endif; ?>

              <td><?= h($row['eagles_id']) ?></td>
              <td><?= h(($row['eagles_firstName'] ?? "")." ".($row['eagles_lastName'] ?? "")) ?></td>
              <td><?= h($row['eagles_position']) ?></td>
              <td><?= h($row['eagles_club']) ?></td>
              <td><?= h($row['eagles_region']) ?></td>
              <td><?= h($row['eagles_status']) ?></td>
              <td>
                <?php if ($isSuperAdmin): ?>
                  <button class="edit-row-btn"
                    type="button"
                    data-id="<?= h($row['eagles_id']) ?>"
                    data-first="<?= h($row['eagles_firstName']) ?>"
                    data-last="<?= h($row['eagles_lastName']) ?>"
                    data-position="<?= h($row['eagles_position']) ?>"
                    data-club="<?= h($row['eagles_club']) ?>"
                    data-region="<?= h($row['eagles_region']) ?>"
                    data-status="<?= h($row['eagles_status']) ?>"
                  ><i class="fas fa-edit"></i></button>

                  <a href="<?= h(pageUrl($page)) ?>&delete_id=<?= urlencode((string)$row['eagles_id']) ?>"
                    onclick="return confirm('Are you sure you want to delete this member? This will also delete the uploaded photo.');"
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
  </form>

  <?php if ($totalPages > 1): ?>
    <div class="page-meta">Showing page <?= h($page) ?> of <?= h($totalPages) ?> (Total: <?= h($totalRecords) ?>)</div>
    <div class="pagination">
      <?php if ($page > 1): ?><a class="page-btn" href="<?= h(pageUrl($page - 1)) ?>">&laquo; Prev</a><?php endif; ?>

      <?php
        $start = max(1, $page - 2);
        $end   = min($totalPages, $page + 2);

        if ($start > 1) {
          echo '<a class="page-btn" href="'.h(pageUrl(1)).'">1</a>';
          if ($start > 2) echo '<span class="page-meta" style="margin:0 6px;">…</span>';
        }
      ?>

      <?php for ($i = $start; $i <= $end; $i++): ?>
        <a class="page-btn <?= $i === $page ? 'active' : '' ?>" href="<?= h(pageUrl($i)) ?>"><?= h($i) ?></a>
      <?php endfor; ?>

      <?php
        if ($end < $totalPages) {
          if ($end < $totalPages - 1) echo '<span class="page-meta" style="margin:0 6px;">…</span>';
          echo '<a class="page-btn" href="'.h(pageUrl($totalPages)).'">'.h($totalPages).'</a>';
        }
      ?>

      <?php if ($page < $totalPages): ?><a class="page-btn" href="<?= h(pageUrl($page + 1)) ?>">Next &raquo;</a><?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($isSuperAdmin): ?>
  <div class="modal" id="add-modal" aria-hidden="true">
    <div class="modal-content modal-lg" role="dialog" aria-modal="true" aria-labelledby="addTitle">
      <button class="modal-x" id="close-add" type="button" aria-label="Close">
        <i class="fa-solid fa-xmark"></i>
      </button>

      <div class="modal-head">
        <h2 id="addTitle">Add Member</h2>
        <p class="modal-sub">Choose manual entry or upload a CSV file.</p>
      </div>

      <form method="POST" enctype="multipart/form-data" class="modal-form">
        <div class="form-row">
          <label class="form-label" for="add-mode">Add Mode</label>
          <select name="add_mode" id="add-mode" required>
            <option value="manual">Manual Entry</option>
            <option value="csv">Upload CSV</option>
          </select>
        </div>

        <div id="manual-fields" class="form-section">
          <div class="grid-2">
            <div class="form-row">
              <label class="form-label" for="add-id">Member ID (optional)</label>
              <input type="text" name="id" id="add-id" placeholder="Auto-generate if empty">
            </div>

            <div class="form-row">
              <label class="form-label" for="add-photo">Photo (optional)</label>
              <input type="file" name="photo" id="add-photo" accept="image/*">
            </div>
          </div>

          <div class="grid-2">
            <div class="form-row">
              <label class="form-label" for="add-first">First Name</label>
              <input type="text" name="first_name" id="add-first" placeholder="First Name" required>
            </div>

            <div class="form-row">
              <label class="form-label" for="add-last">Last Name</label>
              <input type="text" name="last_name" id="add-last" placeholder="Last Name" required>
            </div>
          </div>

          <div class="form-row">
            <label class="form-label" for="add-position">Position</label>
            <input type="text" name="position" id="add-position" placeholder="Position" required>
          </div>

          <div class="grid-2">
            <div class="form-row">
              <label class="form-label" for="add-club">Club</label>
              <select name="club" id="add-club" required>
                <option value="">Select Club</option>
                <?php foreach ($modalClubs as $clubDb): ?>
                  <option value="<?= h($clubDb) ?>"><?= h($clubDb) ?></option>
                <?php endforeach; ?>
                <option value="__NEW__">+ Add new club</option>
              </select>

              <div class="new-inline" id="addClubNewWrap">
                <input type="text" name="club_new" id="add-club-new" placeholder="Enter new club name">
              </div>
            </div>

            <div class="form-row">
              <label class="form-label" for="add-region">Region</label>
              <select name="region" id="add-region" required>
                <option value="">Select Region</option>
                <?php foreach ($modalRegions as $regionDb): ?>
                  <option value="<?= h($regionDb) ?>"><?= h($regionDb) ?></option>
                <?php endforeach; ?>
                <option value="__NEW__">+ Add new region</option>
              </select>

              <div class="new-inline" id="addRegionNewWrap">
                <input type="text" name="region_new" id="add-region-new" placeholder="Enter new region name">
              </div>
            </div>
          </div>
        </div>

        <div id="csv-fields" class="form-section" style="display:none;">
          <div class="upload-box">
            <div class="upload-icon"><i class="fa-solid fa-file-csv"></i></div>
            <div class="upload-text">
              <div class="upload-title">Upload CSV</div>
              <div class="upload-sub">Columns: ID, First, Last, Position, Club, Region, Status, Photo(optional)</div>
              <div class="upload-sub" style="margin-top:4px;">
                Optional: select multiple photos. Each filename must match the Eagles ID.
              </div>
            </div>
          </div>

          <div class="form-row">
            <label class="form-label" for="import_csv">CSV File</label>
            <input type="file" name="import_csv" id="import_csv" accept=".csv">
          </div>

          <div class="form-row">
            <label class="form-label" for="import_photos">Photos (optional)</label>
            <input type="file" name="import_photos[]" id="import_photos" accept="image/*" multiple>
          </div>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn-ghost" id="cancel-add">Cancel</button>
          <button type="submit" name="add_member" class="btn-primary">Add Member</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($isSuperAdmin): ?>
  <div class="modal" id="edit-modal" aria-hidden="true">
    <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="editTitle">
      <span class="close-btn" id="close-edit" aria-label="Close">&times;</span>
      <h2 id="editTitle">Edit Member</h2>

      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" id="edit-id">
        <input type="text" name="first_name" id="edit-first" placeholder="First Name" required>
        <input type="text" name="last_name" id="edit-last" placeholder="Last Name" required>
        <input type="text" name="position" id="edit-position-input" placeholder="Position" required>

        <select name="club" id="edit-club" required>
          <option value="">Select Club</option>
          <?php foreach ($modalClubs as $clubDb): ?>
            <option value="<?= h($clubDb) ?>"><?= h($clubDb) ?></option>
          <?php endforeach; ?>
          <option value="__NEW__">+ Add new club</option>
        </select>
        <div class="new-inline" id="editClubNewWrap">
          <input type="text" name="club_new" id="edit-club-new" placeholder="Enter new club name">
        </div>

        <select name="region" id="edit-region" required>
          <option value="">Select Region</option>
          <?php foreach ($modalRegions as $regionDb): ?>
            <option value="<?= h($regionDb) ?>"><?= h($regionDb) ?></option>
          <?php endforeach; ?>
          <option value="__NEW__">+ Add new region</option>
        </select>
        <div class="new-inline" id="editRegionNewWrap">
          <input type="text" name="region_new" id="edit-region-new" placeholder="Enter new region name">
        </div>

        <select name="status" id="edit-status" required>
          <option value="ACTIVE">Active</option>
          <option value="RENEWAL">Renewal</option>
          <option value="INACTIVE">Inactive</option>
        </select>

        <input type="file" name="photo" accept="image/*">
        <button type="submit" name="edit_member">Save Changes</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php if ($verifyMember):
  $sc = status_class($verifyMember['eagles_status'] ?? '');
  $photo = photo_url($verifyMember['eagles_pic'] ?? '');
?>
<div class="verify-modal active" id="verifyModal">
  <div class="verify-modal-card" role="dialog" aria-modal="true">
    <button class="verify-close" type="button" onclick="closeVerify()" aria-label="Close">
      <i class="fa-solid fa-xmark"></i>
    </button>
    <div class="verify-head">
      <div class="verify-modal-title">Member Record</div>
      <div class="verify-modal-sub">Search result for Eagles ID</div>
    </div>
    <div id="verifyModalContent" class="verify-grid">
      <div class="photo-panel">
        <img
          src="<?= h($photo) ?>"
          alt="Member Photo"
          onerror="this.onerror=null; this.src='/static/default.jpg';"
        >
      </div>
      <div class="details-card">
        <div class="details-head">
          <div class="details-title">Member Details</div>
          <div class="details-sub">Verified record from database</div>
        </div>
        <div class="details-grid">
          <div class="detail">
            <div class="k">EAGLES ID</div>
            <div class="v mono"><?= h($verifyMember['eagles_id'] ?? '') ?></div>
          </div>
          <div class="detail detail-status <?= h($sc) ?>">
            <div class="k">STATUS</div>
            <div class="v"><?= h($verifyMember['eagles_status'] ?? '') ?></div>
          </div>
          <div class="detail">
            <div class="k">LAST NAME</div>
            <div class="v"><?= h($verifyMember['eagles_lastName'] ?? '') ?></div>
          </div>
          <div class="detail">
            <div class="k">FIRST NAME</div>
            <div class="v"><?= h($verifyMember['eagles_firstName'] ?? '') ?></div>
          </div>
          <div class="detail span-2">
            <div class="k">POSITION</div>
            <div class="v"><?= h($verifyMember['eagles_position'] ?? '') ?></div>
          </div>
          <div class="detail">
            <div class="k">CLUB</div>
            <div class="v"><?= h($verifyMember['eagles_club'] ?? '') ?></div>
          </div>
          <div class="detail">
            <div class="k">REGION</div>
            <div class="v"><?= h($verifyMember['eagles_region'] ?? '') ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($csvDupes)): ?>
<div class="verify-modal active" id="csvDupModal">
  <div class="verify-modal-card" role="dialog" aria-modal="true">
    <button class="verify-close" type="button" onclick="closeCsvDupes()" aria-label="Close">
      <i class="fa-solid fa-xmark"></i>
    </button>

    <div class="verify-head">
      <div class="verify-modal-title">Duplicate Entries Skipped</div>
      <div class="verify-modal-sub">These rows were not imported because the Eagles ID already exists.</div>
    </div>

    <div style="margin-top:14px; overflow:auto; max-height:70vh;">
      <table style="width:100%; border-collapse:collapse;">
        <thead>
          <tr>
            <th style="text-align:left; padding:10px; border-bottom:1px solid rgba(0,0,0,.12);">Eagles ID</th>
            <th style="text-align:left; padding:10px; border-bottom:1px solid rgba(0,0,0,.12);">Existing (DB)</th>
            <th style="text-align:left; padding:10px; border-bottom:1px solid rgba(0,0,0,.12);">Incoming (CSV)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($csvDupes as $d):
            $ex = $d['existing'] ?? [];
            $in = $d['incoming'] ?? [];
          ?>
          <tr>
            <td style="padding:10px; border-bottom:1px solid rgba(0,0,0,.08); font-weight:800;">
              <?= h($d['id'] ?? '') ?>
            </td>

            <td style="padding:10px; border-bottom:1px solid rgba(0,0,0,.08);">
              <div><strong>Name:</strong> <?= h(($ex['eagles_firstName'] ?? '').' '.($ex['eagles_lastName'] ?? '')) ?></div>
              <div><strong>Position:</strong> <?= h($ex['eagles_position'] ?? '') ?></div>
              <div><strong>Club:</strong> <?= h($ex['eagles_club'] ?? '') ?></div>
              <div><strong>Region:</strong> <?= h($ex['eagles_region'] ?? '') ?></div>
              <div><strong>Status:</strong> <?= h($ex['eagles_status'] ?? '') ?></div>
              <div style="opacity:.7;"><strong>Photo:</strong> <?= h($ex['eagles_pic'] ?? '') ?></div>
            </td>

            <td style="padding:10px; border-bottom:1px solid rgba(0,0,0,.08);">
              <div><strong>Name:</strong> <?= h(($in['eagles_firstName'] ?? '').' '.($in['eagles_lastName'] ?? '')) ?></div>
              <div><strong>Position:</strong> <?= h($in['eagles_position'] ?? '') ?></div>
              <div><strong>Club:</strong> <?= h($in['eagles_club'] ?? '') ?></div>
              <div><strong>Region:</strong> <?= h($in['eagles_region'] ?? '') ?></div>
              <div><strong>Status:</strong> <?= h($in['eagles_status'] ?? '') ?></div>
              <div style="opacity:.7;"><strong>Photo:</strong> <?= h($in['eagles_pic'] ?? '') ?></div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="margin-top:14px; display:flex; justify-content:flex-end;">
      <button type="button" class="btn-ghost" onclick="closeCsvDupes()">Close</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
document.querySelector(".sidebar-toggle").onclick = () => {
  const sb = document.querySelector(".sidebar");
  if (sb) sb.classList.toggle("show");
};

const isSuperAdmin = <?= $isSuperAdmin ? "true" : "false" ?>;

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
if (clubSelect && filterForm) clubSelect.addEventListener("change", () => filterForm.submit());
if (sortSelect && filterForm) sortSelect.addEventListener("change", () => filterForm.submit());

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
  if (!document.querySelector(".modal.show")) document.body.classList.remove("modal-open");
}

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    const top = document.querySelector(".modal.show");
    if (top) closeModal(top);

    const verify = document.getElementById("verifyModal");
    if (verify) verify.classList.remove("active");

    const csvDup = document.getElementById("csvDupModal");
    if (csvDup) csvDup.classList.remove("active");
  }
});

function toggleNewField(selectEl, wrapEl, inputEl){
  if (!selectEl || !wrapEl || !inputEl) return;
  const isNew = selectEl.value === "__NEW__";
  wrapEl.style.display = isNew ? "block" : "none";
  inputEl.required = isNew;
  if (!isNew) inputEl.value = "";
}

if (isSuperAdmin) {
  const addModal = document.getElementById("add-modal");
  const openAddBtn = document.getElementById("open-add-modal");
  const closeAddBtn = document.getElementById("close-add");
  const cancelAddBtn = document.getElementById("cancel-add");

  if (openAddBtn && addModal) openAddBtn.onclick = () => openModal(addModal);
  if (closeAddBtn && addModal) closeAddBtn.onclick = () => closeModal(addModal);
  if (cancelAddBtn && addModal) cancelAddBtn.onclick = () => closeModal(addModal);

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

    const csvInput = document.getElementById("import_csv");
    if (csvInput) csvInput.required = isCSV;
  }
  if (addMode) addMode.addEventListener("change", toggleAddMode);
  toggleAddMode();

  const addClubSel = document.getElementById("add-club");
  const addClubWrap = document.getElementById("addClubNewWrap");
  const addClubNew = document.getElementById("add-club-new");

  const addRegionSel = document.getElementById("add-region");
  const addRegionWrap = document.getElementById("addRegionNewWrap");
  const addRegionNew = document.getElementById("add-region-new");

  if (addClubSel) addClubSel.addEventListener("change", () => toggleNewField(addClubSel, addClubWrap, addClubNew));
  if (addRegionSel) addRegionSel.addEventListener("change", () => toggleNewField(addRegionSel, addRegionWrap, addRegionNew));
  toggleNewField(addClubSel, addClubWrap, addClubNew);
  toggleNewField(addRegionSel, addRegionWrap, addRegionNew);

  const editModal = document.getElementById("edit-modal");
  const closeEditBtn = document.getElementById("close-edit");
  if (closeEditBtn && editModal) closeEditBtn.onclick = () => closeModal(editModal);

  const editClubSel = document.getElementById("edit-club");
  const editClubWrap = document.getElementById("editClubNewWrap");
  const editClubNew = document.getElementById("edit-club-new");

  const editRegionSel = document.getElementById("edit-region");
  const editRegionWrap = document.getElementById("editRegionNewWrap");
  const editRegionNew = document.getElementById("edit-region-new");

  if (editClubSel) editClubSel.addEventListener("change", () => toggleNewField(editClubSel, editClubWrap, editClubNew));
  if (editRegionSel) editRegionSel.addEventListener("change", () => toggleNewField(editRegionSel, editRegionWrap, editRegionNew));

  function selectOption(select, value){
    if (!select) return false;
    const v = String(value || "").trim().toUpperCase();
    let found = false;
    Array.from(select.options).forEach(opt => {
      const same = (String(opt.value).trim().toUpperCase() === v);
      if (same) found = true;
      opt.selected = same;
    });
    return found;
  }

  document.querySelectorAll(".edit-row-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      document.getElementById("edit-id").value = (btn.dataset.id || "").trim();
      document.getElementById("edit-first").value = (btn.dataset.first || "").trim();
      document.getElementById("edit-last").value = (btn.dataset.last || "").trim();
      document.getElementById("edit-position-input").value = (btn.dataset.position || "").trim();

      const clubFound = selectOption(editClubSel, btn.dataset.club);
      const regionFound = selectOption(editRegionSel, btn.dataset.region);
      selectOption(document.getElementById("edit-status"), btn.dataset.status);

      if (!clubFound && editClubSel) {
        editClubSel.value = "__NEW__";
        if (editClubNew) editClubNew.value = (btn.dataset.club || "").trim();
      }
      if (!regionFound && editRegionSel) {
        editRegionSel.value = "__NEW__";
        if (editRegionNew) editRegionNew.value = (btn.dataset.region || "").trim();
      }

      toggleNewField(editClubSel, editClubWrap, editClubNew);
      toggleNewField(editRegionSel, editRegionWrap, editRegionNew);

      openModal(editModal);
    });
  });

  const selectAll = document.getElementById('selectAll');
  const checks = () => Array.from(document.querySelectorAll('.row-check'));
  const bulkCount = document.getElementById('bulkCount');
  const bulkBtn = document.getElementById('bulkDeleteBtn');

  function updateBulkUI(){
    const boxes = checks();
    const selected = boxes.filter(b => b.checked).length;

    if (bulkCount) bulkCount.textContent = selected + " selected";
    if (bulkBtn) bulkBtn.disabled = (selected === 0);

    if (selectAll) {
      if (boxes.length === 0) selectAll.checked = false;
      else selectAll.checked = (selected === boxes.length);
      selectAll.indeterminate = (selected > 0 && selected < boxes.length);
    }
  }

  if (selectAll) {
    selectAll.addEventListener('change', () => {
      const on = selectAll.checked;
      checks().forEach(b => b.checked = on);
      updateBulkUI();
    });
  }

  checks().forEach(b => b.addEventListener('change', updateBulkUI));
  updateBulkUI();
}

function closeVerify(){
  const vm = document.getElementById('verifyModal');
  if (vm) vm.classList.remove('active');
}

function closeCsvDupes(){
  const m = document.getElementById('csvDupModal');
  if (m) m.classList.remove('active');
}

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