<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login");
    exit;
}

require_once __DIR__ . "/../includes/db.php";
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not found. Check ../includes/db.php.");
}

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function pull_flash(): ?array {
    if (!isset($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return is_array($f) ? $f : null;
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
        return substr($p, $pos);
    }

    if (stripos($p, 'static/') !== false) {
        $pos = stripos($p, 'static/');
        return substr($p, $pos);
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

    $uploadsDir = rtrim((string)$_SERVER['DOCUMENT_ROOT'], "/\\") . DIRECTORY_SEPARATOR . "uploads";
    $abs = rtrim((string)$_SERVER['DOCUMENT_ROOT'], "/\\") . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $p);

    $realUploads = realpath($uploadsDir);
    $realFile = realpath($abs);

    if ($realFile === false) return false;
    if ($realUploads === false || strpos($realFile, $realUploads) !== 0) return false;

    return @unlink($realFile);
}

function handle_photo_upload_with_id(string $memberId, string $fieldName = 'photo'): ?string {
    if (!isset($_FILES[$fieldName]) || ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $uploadsDir = rtrim((string)$_SERVER['DOCUMENT_ROOT'], "/\\") . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0755, true);
    }

    $orig = (string)($_FILES[$fieldName]['name'] ?? '');
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if ($ext === 'jpeg') $ext = 'jpg';
    if (!$ext || !in_array($ext, $allowed, true)) return null;

    $tmp = (string)($_FILES[$fieldName]['tmp_name'] ?? '');
    if ($tmp === '') return null;

    $imgInfo = @getimagesize($tmp);
    if ($imgInfo === false) return null;

    $safeId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($memberId));
    if ($safeId === '') return null;

    foreach ($allowed as $a) {
        $a2 = ($a === 'jpeg') ? 'jpg' : $a;
        $old = $uploadsDir . $safeId . '.' . $a2;
        if (is_file($old)) @unlink($old);
    }

    $fileName = $safeId . '.' . $ext;
    $dest = $uploadsDir . $fileName;

    if (move_uploaded_file($tmp, $dest)) {
        return "uploads/" . $fileName;
    }

    return null;
}

/* ================= UPDATE MEMBER IMAGE ================= */
if (isset($_POST['update_member_image'])) {
    $memberId = trim((string)($_POST['member_id'] ?? ''));
    if ($memberId === '') {
        set_flash('error', 'Invalid member ID.');
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $oldPic = null;
    $q = $conn->prepare("SELECT eagles_pic FROM user_info WHERE eagles_id = ? LIMIT 1");
    if ($q) {
        $q->bind_param("s", $memberId);
        $q->execute();
        $res = $q->get_result();
        if ($res && $res->num_rows === 1) {
            $oldPic = $res->fetch_assoc()['eagles_pic'] ?? null;
        }
        $q->close();
    }

    $newPhoto = handle_photo_upload_with_id($memberId, 'photo');

    if ($newPhoto === null) {
        set_flash('error', 'Upload failed. Please select a valid image file.');
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE user_info SET eagles_pic = ? WHERE eagles_id = ?");
    if (!$stmt) {
        set_flash('error', 'Update failed: ' . $conn->error);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $stmt->bind_param("ss", $newPhoto, $memberId);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        if ($oldPic && normalize_pic_path($oldPic) !== 'static/default.jpg') {
            delete_local_upload($oldPic);
        }
        set_flash('success', 'Member image updated successfully.');
    } else {
        set_flash('error', 'Failed to update image.');
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/* ================= FETCH MEMBERS ================= */
$sql = "SELECT eagles_id, eagles_firstName, eagles_lastName, eagles_pic FROM user_info ORDER BY eagles_lastName ASC, eagles_firstName ASC";
$res = $conn->query($sql);
$members = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$flash = pull_flash();

$withImages = count(array_filter($members, function ($m) {
    $p = trim((string)($m['eagles_pic'] ?? ''));
    return $p !== '' && strtolower($p) !== 'static/default.jpg';
}));
$withoutImages = count($members) - $withImages;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Members Image Gallery</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body{
    font-family:Arial,sans-serif;
    background:#f7f7f7;
    margin:0;
    padding:0;
}
.container{
    max-width:1280px;
    margin:40px auto;
    padding:0 20px;
}
h1{
    text-align:center;
    margin-bottom:30px;
    color:#1f4068;
}
.stats{
    display:flex;
    justify-content:space-around;
    flex-wrap:wrap;
    gap:20px;
    margin-bottom:30px;
}
.stat-card{
    background:#e1eaff;
    flex:1 1 220px;
    padding:20px;
    border-radius:12px;
    text-align:center;
    box-shadow:0 2px 6px rgba(0,0,0,.08);
}
.stat-card h2{
    margin:0;
    font-size:32px;
    color:#1f4068;
}
.stat-card p{
    margin:8px 0 0;
    font-size:16px;
    color:#333;
}
.flash{
    max-width:700px;
    margin:0 auto 25px;
    padding:14px 16px;
    border-radius:10px;
    font-weight:600;
}
.flash.success{
    background:#e7f7ec;
    color:#146c2e;
    border:1px solid #b7e2c3;
}
.flash.error{
    background:#fdeaea;
    color:#9f1f1f;
    border:1px solid #efb6b6;
}
.table-wrap{
    background:#fff;
    border-radius:14px;
    box-shadow:0 2px 8px rgba(0,0,0,.08);
    overflow:auto;
}
table{
    width:100%;
    border-collapse:collapse;
    min-width:900px;
}
thead th{
    background:#1f4068;
    color:#fff;
    padding:14px 12px;
    text-align:left;
    font-size:14px;
}
tbody td{
    padding:14px 12px;
    border-bottom:1px solid #eee;
    vertical-align:middle;
}
.member-info{
    display:flex;
    align-items:center;
    gap:12px;
}
.member-photo{
    width:72px;
    height:72px;
    object-fit:cover;
    border-radius:10px;
    border:1px solid #ddd;
    background:#fafafa;
}
.member-name{
    font-weight:700;
    color:#1f4068;
    margin-bottom:4px;
}
.member-id{
    font-size:12px;
    color:#666;
}
.path-text{
    font-size:12px;
    color:#555;
    word-break:break-all;
}
.upload-form{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}
.file-input{
    max-width:220px;
}
.btn{
    border:none;
    border-radius:8px;
    padding:10px 14px;
    font-weight:700;
    cursor:pointer;
}
.btn-primary{
    background:#1f4068;
    color:#fff;
}
.btn-primary:hover{
    opacity:.92;
}
.badge{
    display:inline-block;
    padding:6px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
}
.badge.has{
    background:#e7f7ec;
    color:#146c2e;
}
.badge.none{
    background:#f1f1f1;
    color:#666;
}
@media (max-width: 768px){
    .container{
        margin:20px auto;
    }
}
</style>
</head>
<body>
<div class="container">
    <h1>Members Image Gallery</h1>

    <?php if ($flash && !empty($flash['message'])): ?>
        <div class="flash <?= h($flash['type'] ?? 'success') ?>">
            <?= h($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat-card">
            <h2><?= count($members) ?></h2>
            <p>Total Members</p>
        </div>
        <div class="stat-card">
            <h2><?= $withImages ?></h2>
            <p>Members with Custom Images</p>
        </div>
        <div class="stat-card">
            <h2><?= $withoutImages ?></h2>
            <p>Using Default Image</p>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:34%;">Member</th>
                    <th style="width:16%;">Current Image</th>
                    <th style="width:20%;">Status</th>
                    <th style="width:30%;">Replace Image</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m): ?>
                    <?php
                        $fullName = trim(($m['eagles_firstName'] ?? '') . ' ' . ($m['eagles_lastName'] ?? ''));
                        $picRaw = $m['eagles_pic'] ?? '';
                        $isDefault = (trim((string)$picRaw) === '' || strtolower(trim((string)$picRaw)) === 'static/default.jpg');
                    ?>
                    <tr>
                        <td>
                            <div class="member-info">
                                <img class="member-photo" src="<?= h(photo_url($picRaw)) ?>" alt="<?= h($fullName) ?>">
                                <div>
                                    <div class="member-name"><?= h($fullName) ?></div>
                                    <div class="member-id">ID: <?= h($m['eagles_id']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="path-text"><?= h($picRaw ?: 'static/default.jpg') ?></div>
                        </td>
                        <td>
                            <span class="badge <?= $isDefault ? 'none' : 'has' ?>">
                                <?= $isDefault ? 'Default Image' : 'Custom Image' ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" enctype="multipart/form-data" class="upload-form">
                                <input type="hidden" name="member_id" value="<?= h($m['eagles_id']) ?>">
                                <input class="file-input" type="file" name="photo" accept="image/*" required>
                                <button type="submit" name="update_member_image" class="btn btn-primary">
                                    <i class="fa-solid fa-upload"></i> Update Image
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($members)): ?>
                    <tr>
                        <td colspan="4" style="text-align:center; padding:30px;">No members found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>