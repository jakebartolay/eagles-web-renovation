<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}


require_once __DIR__ . "/../includes/db.php";
if (!isset($conn) || !($conn instanceof mysqli)) {
  die("Database connection not found. Check db.php.");
}


/* ================= FETCH ACTIVE MAGNA CARTA ITEMS ================= */
$mcItems = [];
$stmt = $conn->prepare("
  SELECT id, title, subtitle, description, image_path
  FROM magna_carta_items
  WHERE is_active = 1
  ORDER BY id ASC
");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
  $mcItems[] = $row;
}
$stmt->close();


/* ================= HELPERS ================= */
function h($v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function img_url(?string $image_path): string {
  if (!$image_path) return '';
  return "magna_carta/" . rawurlencode($image_path);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ang Agila | Magna Carta</title>


  <link rel="icon" href="/../static/eagles.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Merriweather:wght@700;900&display=swap" rel="stylesheet">


  <link rel="stylesheet" href="/../Styles/navbar.css">
  <link rel="stylesheet" href="/../Styles/footer.css">
  <link rel="stylesheet" href="/../Styles/magnacarta.css?v=3">
</head>


<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>


<!-- OVERLAY (used by BOTH Magna Carta + Pillars modal) -->
<div class="mc-overlay" id="mcOverlay" aria-hidden="true"></div>


<!-- HERO -->
<section class="mc-hero">
  <div class="mc-hero-inner">
    <h1>Magna Carta</h1>
    <p>Click a card to read more.</p>
  </div>
</section>


<!-- MAGNA CARTA CARDS (TITLE ONLY) -->
<section class="mc-topics">
  <div class="mc-wrap">
    <div class="mc-grid" role="list">
      <?php if (!$mcItems): ?>
        <div class="mc-empty" role="status">
          <div class="mc-empty-ic"><i class="fa-regular fa-folder-open"></i></div>
          <div class="mc-empty-title">No Magna Carta topics available</div>
          <div class="mc-empty-sub">Please add items from the admin panel.</div>
        </div>
      <?php else: ?>
        <?php foreach ($mcItems as $it): ?>
          <?php $key = "mc" . (int)$it['id']; ?>
          <button class="mc-card" type="button" data-mc-open="<?= h($key) ?>" role="listitem" aria-label="Open <?= h($it['title']) ?>">
            <span class="mc-card__title"><?= h($it['title']) ?></span>
            <span class="mc-card__chev" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
          </button>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>


<!-- MAGNA CARTA MODALS -->
<?php foreach ($mcItems as $it): ?>
  <?php
    $key = "mc" . (int)$it['id'];
    $img = img_url($it['image_path']);
  ?>
  <div class="mc-modal" id="mcModal-<?= h($key) ?>" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="mc-modal-content" role="document">
      <button class="mc-close" type="button" aria-label="Close">×</button>


      <?php if ($img): ?>
        <div class="mc-img mc-img--modal" style="background-image:url('<?= h($img) ?>')" aria-hidden="true"></div>
      <?php else: ?>
        <div class="mc-img-standby mc-img-standby--modal" aria-hidden="true"></div>
      <?php endif; ?>


      <h3><?= h($it['title']) ?></h3>


      <?php if (!empty($it['subtitle'])): ?>
        <p class="role"><?= h($it['subtitle']) ?></p>
      <?php endif; ?>


      <p class="speech"><?= nl2br(h($it['description'])) ?></p>
    </div>
  </div>
<?php endforeach; ?>


<!-- FOUR PILLARS -->
<section class="pillars" id="pillars">
  <div class="mc-wrap">
    <h2>Our Four Pillars</h2>
    <p class="pillars-subtitle">
      The Four Pillars guide our Brotherhood: Leadership, Brotherhood, Service, and Resilience.
    </p>


    <div class="pillar-list" role="list">
      <button class="pillar-card" type="button" data-pillar="brotherhood" role="listitem">
        <div class="pillar-icon"><i class="fas fa-users"></i></div>
        <h3>Brotherhood</h3>
        <p>Strong bonds through shared values and experiences.</p>
      </button>


      <button class="pillar-card" type="button" data-pillar="service" role="listitem">
        <div class="pillar-icon"><i class="fas fa-hand-holding-heart"></i></div>
        <h3>Service</h3>
        <p>Serving communities with compassion and action.</p>
      </button>


      <button class="pillar-card" type="button" data-pillar="unity" role="listitem">
        <div class="pillar-icon"><i class="fas fa-handshake"></i></div>
        <h3>Unity</h3>
        <p>Standing together as one organization.</p>
      </button>


      <button class="pillar-card" type="button" data-pillar="divine" role="listitem">
        <div class="pillar-icon"><i class="fas fa-shield-halved"></i></div>
        <h3>Divine Power</h3>
        <p>Guided by ethics, faith, and moral strength.</p>
      </button>
    </div>
  </div>
</section>


<!-- PILLARS MODAL (REUSABLE) -->
<div class="mc-modal" id="pillarModal" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="mc-modal-content" role="document">
    <button class="mc-close" type="button" aria-label="Close">×</button>


    <!-- this will be set by JS: img OR standby -->
    <div class="mc-img mc-img--modal" id="pillarModalImg" aria-hidden="true"></div>


    <h3 id="pillarModalTitle"></h3>
    <p class="role" id="pillarModalSubtitle"></p>
    <p class="speech" id="pillarModalDesc"></p>
  </div>
</div>


<?php include __DIR__ . '/../includes/footer.php'; ?>


<script>
const overlay = document.getElementById('mcOverlay');
const mcCards = document.querySelectorAll('[data-mc-open]');
const modals = document.querySelectorAll('.mc-modal');


function closeAll() {
  modals.forEach(m => {
    m.classList.remove('show');
    m.setAttribute('aria-hidden', 'true');
  });


  overlay.classList.remove('show');
  overlay.setAttribute('aria-hidden','true');
  document.body.classList.remove('no-scroll');
}


/* ================= MAGNA CARTA ================= */
mcCards.forEach(btn => {
  btn.addEventListener('click', () => {
    closeAll();
    const modal = document.getElementById('mcModal-' + btn.dataset.mcOpen);
    if (!modal) return;


    modal.classList.add('show');
    modal.setAttribute('aria-hidden','false');


    overlay.classList.add('show');
    overlay.setAttribute('aria-hidden','false');
    document.body.classList.add('no-scroll');
  });
});


/* ================= FOUR PILLARS ================= */
const pillarModal = document.getElementById('pillarModal');
const pillarModalImg = document.getElementById('pillarModalImg');
const pillarModalTitle = document.getElementById('pillarModalTitle');
const pillarModalSubtitle = document.getElementById('pillarModalSubtitle');
const pillarModalDesc = document.getElementById('pillarModalDesc');


const pillarCards = document.querySelectorAll('.pillar-card[data-pillar]');


/* Per-modal standby images (replace later) */
const PILLAR_DATA = {
  brotherhood: {
    title: "Brotherhood",
    subtitle: "Strong bonds",
    desc: "Strong bonds through shared values and experiences.",
    img: "static/1.jpg",
  },
  service: {
    title: "Service",
    subtitle: "Compassion in action",
    desc: "Serving communities with compassion and action.",
    img: "static/2.jpg",
  },
  unity: {
    title: "Unity",
    subtitle: "One organization",
    desc: "Standing together as one organization.",
    img: "static/3.jpg",
  },
  divine: {
    title: "Divine Power",
    subtitle: "Ethics and strength",
    desc: "Guided by ethics, faith, and moral strength.",
    img: "static/1.jpg",
  }
};


function openPillarModal(key){
  if (!pillarModal) return;
  const data = PILLAR_DATA[key];
  if (!data) return;


  closeAll();


  pillarModalTitle.textContent = data.title || "";
  pillarModalSubtitle.textContent = data.subtitle || "";
  pillarModalDesc.textContent = data.desc || "";


  // use img first, if empty use standby
  const imgSrc = (data.img && data.img.trim() !== "") ? data.img.trim()
               : (data.standby && data.standby.trim() !== "") ? data.standby.trim()
               : "";


  pillarModalImg.style.backgroundImage = imgSrc ? `url("${imgSrc}")` : "";


  pillarModal.classList.add('show');
  pillarModal.setAttribute('aria-hidden','false');


  overlay.classList.add('show');
  overlay.setAttribute('aria-hidden','false');
  document.body.classList.add('no-scroll');
}


pillarCards.forEach(btn => {
  btn.addEventListener('click', () => {
    openPillarModal(btn.dataset.pillar);
  });
});


/* Close buttons (works for BOTH Magna Carta and Pillars) */
document.querySelectorAll('.mc-close').forEach(btn => {
  btn.addEventListener('click', closeAll);
});


overlay.addEventListener('click', closeAll);
window.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeAll();
});
</script>


</body>
</html>
