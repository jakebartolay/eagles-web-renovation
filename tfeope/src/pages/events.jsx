<?php
require_once __DIR__ . "/../includes/db.php";

// Fetch upcoming events
$upcoming = $conn->query("SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC");

// Fetch past events
$past = $conn->query("SELECT * FROM events WHERE event_date < CURDATE() ORDER BY event_date DESC");

function h($v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function is_video_file($file): bool {
  return (bool)preg_match('/\.(mp4|mov|avi)$/i', (string)$file);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Ang Agila | Events</title>

<link rel="icon" type="image/png" href="/../static/eagles.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Merriweather:wght@700;900&display=swap" rel="stylesheet">

<link rel="stylesheet" href="/../Styles/events.css">
<link rel="stylesheet" href="/../Styles/navbar.css">
<link rel="stylesheet" href="/../Styles/footer.css">
</head>
</head>

<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<section class="hero">
  <div class="hero-overlay"></div>
  <div class="hero-content">
    <h1>Upcoming Events</h1>
    <p>Stay updated with the latest activities and gatherings of the Philippine Eagles</p>
  </div>
</section>

<section class="events-container">
  <div class="events-layout">

    <!-- CALENDAR -->
    <div class="calendar">
      <div class="calendar-header">
        <button id="prev" type="button">&#10094;</button>
        <h2 id="monthYear"></h2>
        <button id="next" type="button">&#10095;</button>
      </div>

      <div class="calendar-days">
        <span>Sun</span><span>Mon</span><span>Tue</span>
        <span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
      </div>

      <div id="calendarDates" class="calendar-dates"></div>
      <p id="noUpcomingText" class="no-event" style="display:none;">No upcoming events for this month.</p>
    </div>

    <!-- EVENT LIST (UPCOMING) -->
    <div class="event-list section-loading" id="secUpcoming">
      <!-- Loader overlay -->
      <div class="section-loader" id="loaderUpcoming" aria-label="Loading upcoming events" role="status">
        <div class="loader-spinner"></div>
        <div class="loader-text">Loading upcoming events...</div>
      </div>

      <div class="section-content" id="contentUpcoming">
        <h2>Event Highlights</h2>

        <?php if ($upcoming && $upcoming->num_rows > 0): ?>
          <?php $i = 0; ?>
          <?php while($row = $upcoming->fetch_assoc()): ?>
            <?php
              $i++;
              $title = h($row['event_title'] ?? '');
              $desc  = h($row['event_description'] ?? '');
              $dateRaw = h($row['event_date'] ?? '');
              $datePretty = date("F j, Y", strtotime($row['event_date'] ?? 'now'));
              $media = $row['event_media'] ?? '';
              $mediaSafe = h($media);
              $mediaType = $media ? (is_video_file($media) ? 'video' : 'image') : '';
              $isEager = ($i <= 2); // only first 2 "eager" for loader
            ?>
            <div
              class="event-item js-open-event"
              role="button"
              tabindex="0"
              data-date="<?= $dateRaw ?>"
              data-title="<?= $title ?>"
              data-description="<?= $desc ?>"
              data-media="<?= $mediaSafe ?>"
              data-media-type="<?= h($mediaType) ?>"
              data-date-pretty="<?= h($datePretty) ?>"
            >
              <?php if ($media): ?>
                <?php if ($mediaType === 'video'): ?>
                  <video
                    src="/event_media/<?= rawurlencode($media) ?>"
                    muted
                    playsinline
                    preload="metadata"
                    <?= $isEager ? '' : 'preload="none"' ?>
                  ></video>
                <?php else: ?>
                  <img
                    src="/event_media/<?= rawurlencode($media) ?>"
                    alt="<?= $title ?>"
                    loading="<?= $isEager ? 'eager' : 'lazy' ?>"
                    <?= $isEager ? 'fetchpriority="high"' : '' ?>
                  >
                <?php endif; ?>
              <?php endif; ?>

              <div class="event-info">
                <h3><?= $title ?></h3>
                <p class="event-date"><?= h($datePretty) ?></p>
              </div>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <p class="no-event">No upcoming events at the moment.</p>
        <?php endif; ?>

      </div>
    </div>

  </div>
</section>

<!-- PAST EVENTS -->
<section class="past-events-container section-loading" id="secPast">
  <!-- Loader overlay -->
  <div class="section-loader" id="loaderPast" aria-label="Loading past events" role="status">
    <div class="loader-spinner"></div>
    <div class="loader-text">Loading past events...</div>
  </div>

  <div class="section-content" id="contentPast">
    <div class="past-events-inner">
      <div class="past-events-header">
        <h2>Past Events</h2>
        <p class="past-events-subtitle">
          Take a look at some of our memorable activities from previous years.
        </p>
      </div>

      <div class="past-events-grid">
        <?php if ($past && $past->num_rows > 0): ?>
          <?php $j = 0; ?>
          <?php while($row = $past->fetch_assoc()): ?>
            <?php
              $j++;
              $title = h($row['event_title'] ?? '');
              $desc  = h($row['event_description'] ?? '');
              $dateRaw = h($row['event_date'] ?? '');
              $datePretty = date("F j, Y", strtotime($row['event_date'] ?? 'now'));
              $media = $row['event_media'] ?? '';
              $mediaSafe = h($media);
              $mediaType = $media ? (is_video_file($media) ? 'video' : 'image') : '';
              $isEager = ($j <= 3); // past grid: first 3 help loader
            ?>
            <div
              class="past-event-card js-open-event"
              role="button"
              tabindex="0"
              data-date="<?= $dateRaw ?>"
              data-title="<?= $title ?>"
              data-description="<?= $desc ?>"
              data-media="<?= $mediaSafe ?>"
              data-media-type="<?= h($mediaType) ?>"
              data-date-pretty="<?= h($datePretty) ?>"
            >
              <?php if ($media): ?>
                <?php if ($mediaType === 'video'): ?>
                  <video
                    src="/event_media/<?= rawurlencode($media) ?>"
                    muted
                    playsinline
                    preload="metadata"
                    oncontextmenu="return false;"
                  ></video>
                <?php else: ?>
                  <img
                    src="/event_media/<?= rawurlencode($media) ?>"
                    alt="<?= $title ?>"
                    loading="<?= $isEager ? 'eager' : 'lazy' ?>"
                    <?= $isEager ? 'fetchpriority="high"' : '' ?>
                  >
                <?php endif; ?>
              <?php endif; ?>

              <div class="event-info">
                <h3><?= $title ?></h3>
                <p class="event-date"><?= h($datePretty) ?></p>
              </div>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <p id="noPastText" class="no-event">No past events yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- MODAL -->
<div class="event-modal" id="eventModal" aria-hidden="true">
  <div class="event-modal-backdrop" id="eventModalBackdrop"></div>

  <div class="event-modal-card" role="dialog" aria-modal="true" aria-labelledby="eventModalTitle">
    <button class="event-modal-close" id="eventModalClose" type="button" aria-label="Close modal">
      <i class="fa-solid fa-xmark"></i>
    </button>

    <div class="event-modal-grid">
      <div class="event-modal-media" id="eventModalMedia"></div>

      <div class="event-modal-body">
        <h3 id="eventModalTitle"></h3>
        <p class="event-modal-date" id="eventModalDate"></p>
        <p class="event-modal-desc" id="eventModalDesc"></p>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
document.addEventListener("DOMContentLoaded", () => {

  /* =========================================================
     SECTION LOADER HELPERS (Upcoming + Past)
  ========================================================= */
  function hideLoader(loaderEl, sectionEl){
    if (!loaderEl) return;
    loaderEl.classList.add("hide");
    if (sectionEl) sectionEl.classList.add("is-ready");
    setTimeout(() => {
      if (loaderEl && loaderEl.parentNode) loaderEl.parentNode.removeChild(loaderEl);
    }, 450);
  }

  function waitForMedia(sectionId, loaderId, selector, opts = {}){
    const section = document.getElementById(sectionId);
    const loader  = document.getElementById(loaderId);
    if (!section || !loader) return;

    const maxWaitMs = typeof opts.maxWaitMs === "number" ? opts.maxWaitMs : 3500;
    const minShowMs = typeof opts.minShowMs === "number" ? opts.minShowMs : 350;

    const startedAt = Date.now();
    let done = false;

    const finish = () => {
      if (done) return;
      done = true;

      const elapsed = Date.now() - startedAt;
      const wait = Math.max(0, minShowMs - elapsed);
      setTimeout(() => hideLoader(loader, section), wait);
    };

    const items = Array.from(section.querySelectorAll(selector));
    if (!items.length) { finish(); return; }

    let remaining = 0;

    items.forEach(el => {
      // IMG
      if (el.tagName === "IMG") {
        if (el.complete && el.naturalWidth > 0) return;
        remaining++;
        const onDone = () => {
          el.removeEventListener("load", onDone);
          el.removeEventListener("error", onDone);
          remaining--;
          if (remaining <= 0) finish();
        };
        el.addEventListener("load", onDone, { once: true });
        el.addEventListener("error", onDone, { once: true });
        return;
      }

      // VIDEO
      if (el.tagName === "VIDEO") {
        // metadata loaded is enough for poster-less previews
        if (el.readyState >= 1) return;
        remaining++;
        const onDone = () => {
          el.removeEventListener("loadedmetadata", onDone);
          el.removeEventListener("error", onDone);
          remaining--;
          if (remaining <= 0) finish();
        };
        el.addEventListener("loadedmetadata", onDone, { once: true });
        el.addEventListener("error", onDone, { once: true });
        return;
      }
    });

    if (remaining <= 0) { finish(); return; }
    setTimeout(() => finish(), maxWaitMs);
  }

  // Upcoming: only wait for the first eager images (fast + accurate)
  waitForMedia("secUpcoming", "loaderUpcoming",
    ".event-item img[loading='eager'], .event-item video",
    { maxWaitMs: 4500, minShowMs: 450 }
  );

  // Past: wait for first eager images (and videos)
  waitForMedia("secPast", "loaderPast",
    ".past-event-card img[loading='eager'], .past-event-card video",
    { maxWaitMs: 4500, minShowMs: 450 }
  );


  // ===== Calendar (upcoming highlight only) =====
  const eventCards = document.querySelectorAll(".event-item");
  const eventDates = [...eventCards].map(e => e.dataset.date);

  const today = new Date();
  let currentDate = new Date(today);

  const monthYear = document.getElementById("monthYear");
  const calendarDates = document.getElementById("calendarDates");
  const noUpcomingText = document.getElementById("noUpcomingText");

  function renderCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();

    monthYear.textContent = currentDate.toLocaleString("default", { month: "long", year: "numeric" });
    calendarDates.innerHTML = "";
    let monthHasEvent = false;

    const firstDay = new Date(year, month, 1).getDay();
    const lastDate = new Date(year, month + 1, 0).getDate();

    for (let i = 0; i < firstDay; i++) {
      const empty = document.createElement("div");
      empty.classList.add("empty");
      calendarDates.appendChild(empty);
    }

    for (let d = 1; d <= lastDate; d++) {
      const dateKey = `${year}-${String(month+1).padStart(2,"0")}-${String(d).padStart(2,"0")}`;
      const cell = document.createElement("div");
      cell.textContent = d;

      if (eventDates.includes(dateKey)) {
        cell.classList.add("event","active");
        cell.dataset.date = dateKey;
        monthHasEvent = true;
      }

      if (d === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
        cell.classList.add("today");
      }

      calendarDates.appendChild(cell);
    }

    noUpcomingText.style.display = monthHasEvent ? "none" : "block";

    eventCards.forEach(card => {
      const cardDate = new Date(card.dataset.date);
      if(cardDate.getMonth() === month && cardDate.getFullYear() === year) {
        card.classList.add("active");
        card.classList.remove("dim");
      } else {
        card.classList.remove("active");
        card.classList.add("dim");
      }
    });
  }

  document.getElementById("prev").onclick = () => { currentDate.setMonth(currentDate.getMonth()-1); renderCalendar(); };
  document.getElementById("next").onclick = () => { currentDate.setMonth(currentDate.getMonth()+1); renderCalendar(); };
  renderCalendar();

  // ===== Modal =====
  const modal = document.getElementById("eventModal");
  const closeBtn = document.getElementById("eventModalClose");
  const backdrop = document.getElementById("eventModalBackdrop");

  const mTitle = document.getElementById("eventModalTitle");
  const mDate  = document.getElementById("eventModalDate");
  const mDesc  = document.getElementById("eventModalDesc");
  const mMedia = document.getElementById("eventModalMedia");

  function openModalFromCard(card) {
    const title = card.dataset.title || "";
    const datePretty = card.dataset.datePretty || "";
    const desc = card.dataset.description || "";
    const media = card.dataset.media || "";
    const mediaType = card.dataset.mediaType || "";

    mTitle.textContent = title;
    mDate.textContent = datePretty;
    mDesc.textContent = desc;

    mMedia.innerHTML = "";
    if (media) {
      if (mediaType === "video") {
        const v = document.createElement("video");
        v.src = "event_media/" + media;
        v.controls = true;
        v.playsInline = true;
        v.preload = "metadata";
        v.muted = false;
        mMedia.appendChild(v);
      } else {
        const img = document.createElement("img");
        img.src = "event_media/" + media;
        img.alt = title;
        mMedia.appendChild(img);
      }
    }

    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
  }

  function closeModal() {
    modal.classList.remove("open");
    modal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("modal-open");

    const v = mMedia.querySelector("video");
    if (v) { v.pause(); v.currentTime = 0; }
  }

  document.querySelectorAll(".js-open-event").forEach(card => {
    card.addEventListener("click", () => openModalFromCard(card));
    card.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        openModalFromCard(card);
      }
    });
  });

  closeBtn.addEventListener("click", closeModal);
  backdrop.addEventListener("click", closeModal);
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && modal.classList.contains("open")) closeModal();
  });
});
</script>

</body>
</html>
