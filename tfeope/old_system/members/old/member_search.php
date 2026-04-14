<?php
// id_search.php — QR ONLY (AUTO VERIFY) — MODAL ONLY
require_once __DIR__ . "/../includes/db.php";

$WEB_DIR = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // /id_search
$FS_DIR  = __DIR__;

function find_asset(string $base, array $exts, string $fsDir, string $webDir): ?string {
  foreach ($exts as $ext) {
    $fs = $fsDir . DIRECTORY_SEPARATOR . $base . '.' . $ext;
    if (file_exists($fs)) return $webDir . '/' . $base . '.' . $ext;
  }
  return null;
}

$exts = ['png','jpg','jpeg','webp','gif'];
$templateUrl = find_asset('id_template', $exts, $FS_DIR, $WEB_DIR);
$stampUrl    = find_asset('Certified', $exts, $FS_DIR, $WEB_DIR) ?: find_asset('certified', $exts, $FS_DIR, $WEB_DIR);

function build_modal(mysqli $conn, string $search_id, ?string $templateUrl, ?string $stampUrl, string $webDir, string $fsDir): string {
  $search_id = strtoupper(trim($search_id));

  if (!preg_match('/^TFOEPE[0-9]{8}$/', $search_id)) {
    return "
<div class='verify-status error'>
  <span class='status-circle error'></span>
  <div class='verify-modal-title'>ID Not Found</div>
</div>
<div class='verify-modal-sub'>No record matched this Membership ID.</div>
    ";
  }

  try {
    $stmt = $conn->prepare("
      SELECT
        eagles_id,
        eagles_status,
        eagles_firstName,
        eagles_lastName,
        eagles_position,
        eagles_club,
        eagles_region,
        eagles_pic
      FROM user_info
      WHERE eagles_id = ?
      LIMIT 1
    ");
    $stmt->bind_param("s", $search_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows === 1) {
      $row = $res->fetch_assoc();

      // Photo lives at: /id_search/uploads/<filename>
      $photoUrl = "";
      $pic = (string)($row['eagles_pic'] ?? "");
      if ($pic !== "") {
        $file = basename($pic);
        if (file_exists($fsDir . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . $file)) {
          $photoUrl = $webDir . "/uploads/" . $file;
        }
      }

      $active = strtolower((string)$row['eagles_status']) === 'active';
      return "


<div class='verify-status'>
  <span class='status-circle'></span>
  <div class='verify-modal-title'>VERIFIED</div>
</div>
<div class='verify-modal-sub'><i>Officially verified Eagle Member.</i></div>


  <div class='id-card'>
    " . ($templateUrl ? "<img src='" . htmlspecialchars($templateUrl) . "' class='id-bg' alt='ID Template'>" : "") . "

    <div class='id-number'>" . htmlspecialchars((string)$row['eagles_id']) . "</div>
    <div class='id-last'>" . htmlspecialchars((string)$row['eagles_lastName']) . "</div>
    <div class='id-first'>" . htmlspecialchars((string)$row['eagles_firstName']) . "</div>

    " . ($photoUrl ? "<img src='" . htmlspecialchars($photoUrl) . "' class='id-photo' alt='Photo'>" : "") . "

    <div class='id-club'>" . htmlspecialchars((string)$row['eagles_club']) . "</div>
    <div class='id-position'>" . htmlspecialchars((string)$row['eagles_position']) . "</div>
    <div class='id-region'>" . htmlspecialchars((string)$row['eagles_region']) . "</div>
  </div>

            " . ($active && $stampUrl ? "<img src='" . htmlspecialchars($stampUrl) . "' class='id-stamp-img'>" : "") . "
            ";
    }

    $stmt->close();
    return "
      <div class='verify-modal-title'>ID Not Found</div>
      <div class='verify-modal-sub'>No record matched this Membership ID.</div>
    ";
  } catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
    return "
      <div class='verify-modal-title'>System Error</div>
      <div class='verify-modal-sub'>Please try again later.</div>
    ";
  }
}

$showModal = false;
$modalHTML = "";

if (isset($_GET['id'])) {
  $showModal = true;
  $modalHTML = build_modal($conn, (string)$_GET['id'], $templateUrl, $stampUrl, $WEB_DIR, $FS_DIR);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Ang Agila | ID Verification</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link rel="preload" href="background.png" as="image">
  <link rel="stylesheet" href="id_search.css">
</head>
<body>

<?php if ($showModal): ?> 
<div class="verify-modal" id="verifyModal"> 
<div class="verify-modal-card" role="dialog" aria-modal="true"> 
<!-- Close icon button (top-right) --> <button class="verify-close" type="button" onclick="window.location='/'"> 
<i class="fa fa-home"></i> </button>
 <div id="verifyModalContent">
<?= $modalHTML ?></div> 
</div> 
</div> 
<?php endif; ?>

<script>

// Preload background image
const bgImg = new Image();
bgImg.src = "background.png";
bgImg.onload = () => {
  document.body.classList.add("bg-loaded"); // removes blur
};

// Wait for the image to load and remove blur
document.querySelectorAll('.id-photo').forEach(img => {
  if (img.complete) {
    img.classList.add('loaded'); // already loaded (cached)
  } else {
    img.addEventListener('load', () => {
      img.classList.add('loaded'); // remove blur once loaded
    });
  }
});

function closeModal(){
  const modal = document.getElementById('verifyModal');
  if (modal) modal.style.display = 'none';
}
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closeModal();
});
</script>

</body>
</html>
