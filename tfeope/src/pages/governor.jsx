<?php
require_once __DIR__ . "/../includes/db.php";
if (!isset($conn) || !($conn instanceof mysqli)) {
  die("Database connection \$conn not found. Check db.php.");
}

require_once __DIR__ . "/../includes/public_session.php";

$IMG_BASE = "/governors/";
$PLACEHOLDER_IMG = $IMG_BASE . "placeholder.png";

/* =========================
   AJAX ENDPOINT (clubs list)
========================= */
if (isset($_GET['action']) && $_GET['action'] === 'details') {
  header("Content-Type: text/html; charset=UTF-8");

  $govId = isset($_GET['governor_id']) ? (int)$_GET['governor_id'] : 0;
  if ($govId <= 0) {
    echo "<p class='empty'>Invalid governor.</p>";
    exit;
  }

  $sql = "
    SELECT
      c.club_id,
      c.club_name,
      r.region_name,
      p.president_name
    FROM clubs c
    LEFT JOIN regions r ON r.region_id = c.region_id
    LEFT JOIN presidents p ON p.club_id = c.club_id
    WHERE c.governor_id = ?
    ORDER BY r.region_name ASC, c.club_name ASC
  ";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $govId);
  $stmt->execute();
  $res = $stmt->get_result();

  if (!$res || $res->num_rows === 0) {
    echo "<p class='empty'>No clubs found under this governor.</p>";
    exit;
  }

  $byRegion = [];
  while ($row = $res->fetch_assoc()) {
    $region = $row['region_name'] ?: 'Unassigned Region';
    $byRegion[$region][] = $row;
  }
  ?>
  <div class="clubs-wrap">
    <?php foreach ($byRegion as $regionName => $clubs): ?>
      <div class="region-block">
        <h4 class="region-title"><?= htmlspecialchars($regionName) ?></h4>
        <div class="clubs-list">
          <?php foreach ($clubs as $c): ?>
            <div class="club-row">
              <div class="club-name"><?= htmlspecialchars($c['club_name']) ?></div>
              <div class="club-sub">
                President:
                <span class="club-president">
                  <?= htmlspecialchars($c['president_name'] ?? 'Not assigned') ?>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php
  exit;
}

/* =========================
   MAIN PAGE DATA
========================= */
$sql = "
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
$governors = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ang Agila | Regional Governors</title>

<link rel="icon" type="image/png" href="/../static/eagles.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Merriweather:wght@700;900&display=swap" rel="stylesheet">

<link rel="stylesheet" href="/../Styles/governors.css">
<link rel="stylesheet" href="/../Styles/navbar.css">
<link rel="stylesheet" href="/../Styles/footer.css">

<style>
img { -webkit-user-drag: none; user-select: none; }

/* Brush Script prefix */
.eagle-prefix{
  font-family: "Brush Script MT", cursive;
  font-size: 20px;
  font-weight: 400;
  margin-right: 6px;
  color: #0f2d55;
  white-space: nowrap;
}

@font-face {
  font-family: 'Brush Script MT';
  src: url('../fonts/BrushScriptStd.otf') format('opentype');
  font-weight: normal;
  font-style: normal;
}
.empty-state-center {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 40px 20px;
    margin: 20px auto;
    background-color: #f3f4f6; /* light gray */
    border-radius: 12px;
    max-width: 400px; /* optional */
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.empty-message {
    font-size: 18px;
    color: #6b7280; /* gray */
    margin-top: 12px;
    font-weight: 500;
}
</style>
</head>

<body>

<!-- REQUIRED for the scroll-driven background -->
<div class="page-background" aria-hidden="true"></div>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<section class="officers-hero">
  <h1>Regional Governors</h1>
  <p>E.Y. 2026</p>
</section>

<section class="org-chart">
  <div class="chart-level">
    <?php if ($governors && $governors->num_rows > 0): ?>
      <?php while($g = $governors->fetch_assoc()): ?>
        <?php
          $img = !empty($g['governor_image'])
            ? ($IMG_BASE . $g['governor_image'])
            : $PLACEHOLDER_IMG;
        ?>
        <div
          class="officer-card"
          role="button"
          tabindex="0"
          data-gov-id="<?= (int)$g['governor_id'] ?>"
          data-gov-name="<?= htmlspecialchars($g['governor_name'], ENT_QUOTES) ?>"
          data-gov-regions="<?= htmlspecialchars(($g['regions'] ?? 'No region assigned'), ENT_QUOTES) ?>"
          data-gov-img="<?= htmlspecialchars($img, ENT_QUOTES) ?>"
        >
          <img
            class="gov-img"
            src="<?= htmlspecialchars($img) ?>"
            alt="<?= htmlspecialchars($g['governor_name']) ?>"
            onerror="this.src='<?= htmlspecialchars($PLACEHOLDER_IMG) ?>';"
          >

          <div class="officer-info">
            <h4 class="gov-name">
              <span class="eagle-prefix">Eagle</span>
              <span><?= htmlspecialchars($g['governor_name']) ?></span>
            </h4>
            <p><?= htmlspecialchars($g['regions'] ?? 'No region assigned') ?></p>
          </div>
        </div>
      <?php endwhile; ?>
<?php else: ?>
<div class="empty-state-center">
    <i class="fas fa-user-shield" style="font-size:48px; color:#9ca3af;"></i>
    <p class="empty-message">No governors found.</p>
</div>
<?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<!-- OVERLAY (added for better mobile UX) -->
<div class="modal-overlay" id="modalOverlay" aria-hidden="true"></div>

<!-- MODAL -->
<div class="detail-panel" id="govModal" aria-hidden="true">
  <div class="panel-head">
    <img class="panel-avatar" src="<?= htmlspecialchars($PLACEHOLDER_IMG) ?>" alt="" id="modalAvatar">
    <div class="panel-head-text">
      <h3 class="gov-name">
        <span class="eagle-prefix">Eagle</span>
        <span id="modalName">Governor</span>
      </h3>
      <p class="role" id="modalRegions"></p>
    </div>
  </div>

  <div class="panel-body">
    <div class="panel-loading" id="modalLoading">Loading clubs...</div>
    <div class="panel-content" id="modalContent" style="display:none;"></div>
  </div>

  <button class="close" type="button" id="modalClose" aria-label="Close">×</button>
</div>

<script>
const modal = document.getElementById("govModal");
const overlay = document.getElementById("modalOverlay");
const modalAvatar = document.getElementById("modalAvatar");
const modalName = document.getElementById("modalName");
const modalRegions = document.getElementById("modalRegions");
const modalLoading = document.getElementById("modalLoading");
const modalContent = document.getElementById("modalContent");
const modalClose = document.getElementById("modalClose");

const PLACEHOLDER_IMG = "<?= htmlspecialchars($PLACEHOLDER_IMG, ENT_QUOTES) ?>";
const detailsCache = new Map();
let activeGovId = null;

function buildDetailsUrl(govId) {
  const url = new URL(window.location.href);
  url.searchParams.set("action", "details");
  url.searchParams.set("governor_id", govId);
  return url.toString();
}

function showModal(){
  modal.classList.add("show");
  overlay.classList.add("show");
  document.body.classList.add("modal-open");
}

function hideModal(){
  modal.classList.remove("show");
  overlay.classList.remove("show");
  document.body.classList.remove("modal-open");
  activeGovId = null;
}

function openModal({ govId, name, regions, img }) {
  activeGovId = govId;
  showModal();

  modalName.textContent = name;
  modalRegions.textContent = regions;

  modalAvatar.src = img || PLACEHOLDER_IMG;
  modalAvatar.onerror = () => modalAvatar.src = PLACEHOLDER_IMG;

  modalLoading.style.display = "block";
  modalContent.style.display = "none";

  if (detailsCache.has(govId)) {
    modalLoading.style.display = "none";
    modalContent.style.display = "block";
    modalContent.innerHTML = detailsCache.get(govId);
    return;
  }

  fetch(buildDetailsUrl(govId))
    .then(res => res.text())
    .then(html => {
      if (activeGovId !== govId) return;
      detailsCache.set(govId, html);
      modalLoading.style.display = "none";
      modalContent.style.display = "block";
      modalContent.innerHTML = html;
    })
    .catch(() => {
      if (activeGovId !== govId) return;
      modalLoading.textContent = "Failed to load clubs.";
    });
}

document.querySelectorAll(".officer-card").forEach(card => {
  card.addEventListener("click", () => {
    openModal({
      govId: card.dataset.govId,
      name: card.dataset.govName,
      regions: card.dataset.govRegions,
      img: card.dataset.govImg
    });
  });

  card.addEventListener("keydown", (e) => {
    if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      card.click();
    }
  });
});

modalClose.addEventListener("click", hideModal);
overlay.addEventListener("click", hideModal);

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") hideModal();
});

/* =========================================
   GRADUAL SCROLL → GRADIENT STRENGTH
========================================= */
(function(){
  const bg = document.querySelector('.page-background');
  if (!bg) return;

  const FULL_AT = 0.80;
  function clamp01(x){ return Math.max(0, Math.min(1, x)); }

  function update(){
    const doc = document.documentElement;
    const maxScroll = (doc.scrollHeight - window.innerHeight) || 1;
    const t = clamp01((window.scrollY / maxScroll) / FULL_AT);
    bg.style.setProperty('--bgProgress', t.toFixed(4));
  }

  update();
  window.addEventListener('scroll', update, { passive: true });
  window.addEventListener('resize', update);
})();
</script>

</body>
</html>
