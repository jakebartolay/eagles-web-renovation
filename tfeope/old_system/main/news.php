<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/public_session.php";

/* Fetch published news */
$news_result = $conn->query("
  SELECT *
  FROM news_info
  WHERE news_status='Published'
  ORDER BY news_id DESC
");

/* Fetch published videos */
$video_result = $conn->query("
  SELECT *
  FROM video_info
  WHERE video_status='Published'
  ORDER BY video_id DESC
");

/* helper */
function h($v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* get all media for one news */
function fetch_news_media(mysqli $conn, int $newsId): array {
  $out = [];
  $stmt = $conn->prepare("SELECT file_name, file_type FROM news_media WHERE news_id=? ORDER BY media_id ASC");
  if (!$stmt) return $out;

  $stmt->bind_param("i", $newsId);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) {
    $out[] = [
      'file' => (string)($r['file_name'] ?? ''),
      'type' => (string)($r['file_type'] ?? 'image'),
    ];
  }
  $stmt->close();
  return $out;
}

/* pick first IMAGE for card thumbnail (ignore videos for thumbnail) */
function first_image_from_media(array $media): string {
  foreach ($media as $m) {
    if (($m['type'] ?? '') === 'image' && !empty($m['file'])) return (string)$m['file'];
  }
  return '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ang Agila | News & Videos</title>

<link rel="icon" type="image/png" href="/../static/eagles.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Merriweather:wght@700;900&display=swap" rel="stylesheet">

<link rel="stylesheet" href="/../Styles/navbar.css">
<link rel="stylesheet" href="/../Styles/news.css">
<link rel="stylesheet" href="/../Styles/footer.css">
</head>

<body class="is-loading">
<!-- =========================
     PAGE LOADER (FULLSCREEN)
========================= -->
<div id="pageLoader" class="page-loader" aria-label="Loading" role="status">
  <div class="loader-wrap">
    <div class="loader-spinner" aria-hidden="true"></div>
    <p class="loader-text">Loading news and videos...</p>
  </div>
</div>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<!-- HERO -->
<section class="news-hero">
  <h1>News & Videos</h1>
  <p>Stay updated with the latest events, programs, and multimedia content from Ang Agila.</p>
  <div class="hero-buttons">
    <a href="#news" class="btn-primary">Read News</a>
    <a href="#videos" class="btn-secondary">Watch Videos</a>
  </div>
</section>

<!-- NEWS -->
<section class="news-section" id="news">
  <h2>Latest News</h2>
  <p style="text-align:center; max-width:700px; margin:0 auto 50px auto; color:#333;">
    Here are the most recent updates, events, and programs from Ang Agila.
  </p>

  <div class="news-grid">
  <?php if ($news_result && $news_result->num_rows > 0): ?>
      <?php while($row = $news_result->fetch_assoc()): ?>
          <?php
            $newsId = (int)($row['news_id'] ?? 0);

            // fetch all media for this news
            $media = fetch_news_media($conn, $newsId);

            // first uploaded IMAGE for the card thumbnail
            $thumb = first_image_from_media($media);

            // dataset JSON for modal gallery
            $mediaJson = json_encode($media, JSON_UNESCAPED_SLASHES);
            if ($mediaJson === false) $mediaJson = "[]";
          ?>

          <div class="news-card">
              <?php if ($thumb !== ''): ?>
                <img src="/news_images/<?= h($thumb) ?>"
                     alt="<?= h($row['news_title']) ?>">
              <?php else: ?>
                <div class="news-card-placeholder">
                  No Image
                </div>
              <?php endif; ?>

              <div class="news-card-content">
                  <h3><?= h($row['news_title']) ?></h3>

                  <button class="read-more-btn"
                          data-title="<?= h($row['news_title']) ?>"
                          data-text="<?= h($row['news_content']) ?>"
                          data-media="<?= h($mediaJson) ?>">
                      Read More
                  </button>
              </div>
          </div>
      <?php endwhile; ?>
  <?php else: ?>
      <p style="text-align:center; color:#333;">No news available at the moment.</p>
  <?php endif; ?>
  </div>
</section>

<!-- VIDEOS -->
<section class="video-section" id="videos">
  <h2>Latest Videos</h2>
  <p style="text-align:center; max-width:700px; margin:0 auto 50px auto; color:#333;">
    Watch the most recent videos from Ang Agila.
  </p>

  <div class="video-grid">
  <?php if ($video_result && $video_result->num_rows > 0): ?>
      <?php while($video = $video_result->fetch_assoc()): ?>
          <div class="video-card clickable"
               data-title="<?= h($video['video_title']) ?>"
               data-desc="<?= h($video['video_description']) ?>"
               data-src="/videos/<?= h($video['video_file']) ?>"
               data-poster="/videos_thumbnail/<?= h($video['video_thumbnail']) ?>">

              <video controls poster="/videos_thumbnail/<?= h($video['video_thumbnail']) ?>">
                  <source src="/videos/<?= h($video['video_file']) ?>" type="video/mp4">
                  Your browser does not support HTML5 video.
              </video>

              <div class="video-card-content">
                  <h3><?= h($video['video_title']) ?></h3>
              </div>
          </div>
      <?php endwhile; ?>
  <?php else: ?>
      <p style="text-align:center; color:#333;">No videos available at the moment.</p>
  <?php endif; ?>
  </div>
</section>

<!-- NEWS MODAL -->
<div id="newsModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="modal-title"></h3>
      <button class="modal-close" type="button" data-close="newsModal">&times;</button>
    </div>
    <div class="modal-body">
      <!-- MAIN MEDIA (changes when you click thumbnails) -->
      <div id="newsMainMedia" class="news-main-media"></div>

      <!-- THUMBNAILS (all uploaded media) -->
      <div id="newsMediaStrip" class="news-media-strip" aria-label="News media thumbnails"></div>

      <p id="modal-text"></p>
    </div>
  </div>
</div>

<!-- VIDEO MODAL -->
<div id="videoModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="video-modal-title"></h3>
      <button class="modal-close" type="button" data-close="videoModal">&times;</button>
    </div>
    <div class="modal-body">
      <video id="video-modal-player" controls>
        <source id="video-modal-source" src="" type="video/mp4">
        Your browser does not support HTML5 video.
      </video>
      <p id="video-modal-desc"></p>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
/* ==========================================
   PAGE LOADER (SHOW UNTIL EVERYTHING LOADED)
========================================== */
(function () {
  const loader = document.getElementById("pageLoader");
  const hardTimeout = setTimeout(hideLoader, 8000);

  function hideLoader() {
    if (!loader) return;
    clearTimeout(hardTimeout);

    document.body.classList.remove("is-loading");
    loader.classList.add("hide");

    setTimeout(() => {
      if (loader && loader.parentNode) loader.parentNode.removeChild(loader);
    }, 450);
  }

  window.addEventListener("load", hideLoader);
  window.addEventListener("pageshow", (e) => { if (e.persisted) hideLoader(); });
})();

/* =======================
   NEWS MODAL (MULTI MEDIA)
======================= */
const newsModal = document.getElementById("newsModal");
const modalTitle = document.getElementById("modal-title");
const modalText = document.getElementById("modal-text");
const newsMainMedia = document.getElementById("newsMainMedia");
const newsMediaStrip = document.getElementById("newsMediaStrip");

function decodeHTML(s){
  const t = document.createElement('textarea');
  t.innerHTML = s || '';
  return t.value;
}

function safeJsonParse(s){
  try { return JSON.parse(s || "[]"); } catch { return []; }
}

function setMainMedia(item){
  if (!newsMainMedia) return;
  newsMainMedia.innerHTML = "";

  if (!item || !item.file) {
    newsMainMedia.innerHTML = `<div class="news-main-placeholder">No media uploaded.</div>`;
    return;
  }

  const src = "/news_images/" + item.file;

  if (item.type === "video") {
    const v = document.createElement("video");
    v.controls = true;
    v.src = src;
    v.setAttribute("playsinline", "true");
    newsMainMedia.appendChild(v);
  } else {
    const img = document.createElement("img");
    img.src = src;
    img.alt = "News media";
    newsMainMedia.appendChild(img);
  }
}

function buildStrip(media){
  if (!newsMediaStrip) return;
  newsMediaStrip.innerHTML = "";

  if (!media || media.length === 0) return;

  media.forEach((m, idx) => {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "news-thumb";
    btn.setAttribute("aria-label", "Open media " + (idx + 1));

    const src = "/news_images/" + (m.file || "");

    if (m.type === "video") {
      btn.innerHTML = `
        <div class="news-thumb-video">
          <span class="news-thumb-play"><i class="fa-solid fa-play"></i></span>
        </div>
      `;
      btn.style.backgroundImage = `linear-gradient(rgba(0,0,0,.35), rgba(0,0,0,.35))`;
    } else {
      btn.style.backgroundImage = `url('${src}')`;
    }

    btn.addEventListener("click", () => setMainMedia(m));
    newsMediaStrip.appendChild(btn);
  });
}

document.querySelectorAll(".read-more-btn").forEach(btn => {
  btn.addEventListener("click", () => {
    const title = decodeHTML(btn.dataset.title || "");
    const text  = decodeHTML(btn.dataset.text || "");
    const media = safeJsonParse(decodeHTML(btn.dataset.media || "[]"));

    modalTitle.textContent = title;
    modalText.textContent = text;

    // choose first item as main media (prefer image if available)
    let first = media[0] || null;
    const firstImage = media.find(x => x && x.type === "image" && x.file);
    if (firstImage) first = firstImage;

    setMainMedia(first);
    buildStrip(media);

    newsModal.style.display = "flex";
    document.body.style.overflow = "hidden";
  });
});

/* =======================
   VIDEO MODAL
======================= */
const videoModal = document.getElementById("videoModal");
const vTitle = document.getElementById("video-modal-title");
const vDesc  = document.getElementById("video-modal-desc");
const vPlayer = document.getElementById("video-modal-player");
const vSource = document.getElementById("video-modal-source");

document.querySelectorAll(".video-card.clickable").forEach(card => {
  card.addEventListener("click", (e) => {
    if (e.target && (e.target.tagName === "VIDEO" || e.target.tagName === "SOURCE")) return;

    vTitle.textContent = card.dataset.title || "";
    vDesc.textContent  = card.dataset.desc || "";

    vSource.src = card.dataset.src || "";
    vPlayer.load();

    videoModal.style.display = "flex";
    document.body.style.overflow = "hidden";
  });
});

/* =======================
   CLOSE MODALS
======================= */
function closeModal(modalEl) {
  modalEl.style.display = "none";
  document.body.style.overflow = "";

  if (modalEl === videoModal) {
    vPlayer.pause();
    vSource.src = "";
    vPlayer.load();
  }

  if (modalEl === newsModal) {
    // stop any video in main media
    const vid = newsMainMedia ? newsMainMedia.querySelector("video") : null;
    if (vid) vid.pause();
    if (newsMainMedia) newsMainMedia.innerHTML = "";
    if (newsMediaStrip) newsMediaStrip.innerHTML = "";
  }
}

document.querySelectorAll("[data-close]").forEach(btn => {
  btn.addEventListener("click", () => {
    const id = btn.getAttribute("data-close");
    const m = document.getElementById(id);
    if (m) closeModal(m);
  });
});

window.addEventListener("click", (e) => {
  if (e.target === newsModal) closeModal(newsModal);
  if (e.target === videoModal) closeModal(videoModal);
});
</script>

</body>
</html>