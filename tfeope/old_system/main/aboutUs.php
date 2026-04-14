<?php
// aboutUs.php (NO DATABASE)
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ang Agila | About Us</title>

  <link rel="icon" type="image/png" href="/../static/eagles.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Merriweather:wght@700;900&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="/../Styles/navbar.css">
  <link rel="stylesheet" href="/../Styles/footer.css">
  <link rel="stylesheet" href="/../Styles/aboutus.css?v=10">
</head>

<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

  <!-- =========================
       HERO
  ========================== -->
  <section class="history-hero">
    <h1>Our History</h1>
  </section>

  <!-- =========================
       TIMELINE
  ========================== -->
  <section class="timeline" id="timeline">
    <div class="timeline-bg" id="timelineBg" aria-hidden="true"></div>

    <div class="timeline-inner">
      <div class="timeline-event">
        <div class="timeline-card">
          <h3>1920 – Foundation</h3>
          <p>The Fraternal Order of Eagles was founded, emphasizing service and brotherhood.</p>
        </div>
      </div>

      <div class="timeline-event">
        <div class="timeline-card">
          <h3>1950 – Expansion</h3>
          <p>The organization expanded nationwide, opening chapters across provinces.</p>
        </div>
      </div>

      <div class="timeline-event">
        <div class="timeline-card">
          <h3>1980 – Community Outreach</h3>
          <p>Large-scale community programs including disaster relief and education.</p>
        </div>
      </div>

      <div class="timeline-event">
        <div class="timeline-card">
          <h3>2000 – Modern Era</h3>
          <p>Digital platforms and technology strengthened member connection.</p>
        </div>
      </div>

      <div class="timeline-event">
        <div class="timeline-card">
          <h3>2026 – Today</h3>
          <p>Continuing a legacy of unity, service, and integrity.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- =========================
       EAGLEISM (TEXT ON IMAGE)
  ========================== -->
  <section class="pillars">
    <div class="eagles-side-image is-hidden" id="eaglesSection">
      <div class="overlay"></div>

      <div class="eagles-overlay-content" id="eaglesOverlay">
        <div class="pillars-container eagleism">
          <div class="eagleism-box">
            <h2 class="eagleism-title slide-left" id="eagleTitle"><strong>EAGLEISM</strong></h2>

            <p class="eagleism-lead slide-right" id="eaglePara">
              <strong>Eagleism</strong> is fraternalism, or that state of relationship characteristic of brothers.
              In the Philippine Eagles, members must have primordially developed a deep sense of brotherhood among them.
              It is the primacy of their relationship is brotherhood.
            </p>
          </div>
        </div>
      </div>

      <img src="/../static/aboutHeader.png" alt="Philippine Eagles" id="eaglesImg">
    </div>
  </section>

  <!-- =========================
       EAGLES SHALL BE
  ========================== -->
  <section class="pillars eagles-shall-be">
    <div class="shallbe-wrap">
      <div class="shallbe-card">
        <div class="pillars-container shallbe-layout">
          <div class="pillars-left">
            <h2 class="eagles-title"><strong>The Philippine Eagles</strong></h2>
            <p class="eagles-sub"><strong>shall be:</strong></p>
          </div>

          <div class="pillars-right eagles-acronym">
            <p><strong>E</strong> – Enlightened and innovative humanitarians</p>
            <p><strong>A</strong> – Animated primarily by a strong bond of brotherhood and fraternal ties</p>
            <p><strong>G</strong> – God-fearing God-conscious non-sectarian</p>
            <p><strong>L</strong> – Law-abiding liberty-oriented</p>
            <p><strong>E</strong> – Emblazed with intense mission of</p>
            <p><strong>S</strong> – Service to country, its people and its Community</p>
          </div>
        </div>
      </div>
    </div>
  </section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const reduceMotion =
    window.matchMedia &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  const timeline = document.getElementById("timeline");
  const timelineBg = document.getElementById("timelineBg");
  const clamp = (v, min, max) => Math.max(min, Math.min(max, v));

  function updateTimelineHeroBg(){
    if (!timeline || !timelineBg) return;

    const r = timeline.getBoundingClientRect();
    const vh = window.innerHeight || document.documentElement.clientHeight;
    const navH = 70;

    const active = (r.top < navH) && (r.bottom > navH);

    const progress = clamp((vh - r.top) / (vh + r.height), 0, 1);
    const posY = 5 + progress * 90;

    const enter = clamp((r.top - navH) / (vh * 0.7), 0, 1);
    const shiftPx = enter * 160;

    timelineBg.style.backgroundPosition = `50% ${posY.toFixed(1)}%`;
    timelineBg.style.transform = `translate3d(0, ${shiftPx.toFixed(0)}px, 0)`;

    if (active) {
      timelineBg.classList.add("is-fixed");
      timelineBg.style.clipPath = "none";
    } else {
      timelineBg.classList.remove("is-fixed");
      timelineBg.style.clipPath = "none";
    }
  }

  updateTimelineHeroBg();

  if (!reduceMotion) {
    let raf = 0;
    function onScroll(){
      if (raf) return;
      raf = requestAnimationFrame(() => {
        raf = 0;
        updateTimelineHeroBg();
      });
    }
    window.addEventListener("scroll", onScroll, { passive: true });
    window.addEventListener("resize", () => requestAnimationFrame(updateTimelineHeroBg));
  }

  /* =========================================================
     EAGLEISM ANIMATION
  ========================================================= */
  const section = document.getElementById("eaglesSection");
  const img = document.getElementById("eaglesImg");
  const title = document.getElementById("eagleTitle");
  const para  = document.getElementById("eaglePara");

  if (!section || !img || !title || !para) return;

  section.classList.add("is-hidden");
  section.classList.remove("revealed");
  title.classList.remove("in");
  para.classList.remove("in");

  if (reduceMotion) {
    section.classList.remove("is-hidden");
    section.classList.add("revealed");
    title.classList.add("in");
    para.classList.add("in");
    return;
  }

  let lastY = window.scrollY;
  let wasInView = false;
  let ticking = false;

  function inActiveZone() {
    const rr = section.getBoundingClientRect();
    const vh2 = window.innerHeight || document.documentElement.clientHeight;
    return rr.top < vh2 * 0.78 && rr.bottom > vh2 * 0.22;
  }

  function resetAnimationState() {
    section.classList.add("is-hidden");
    section.classList.remove("revealed");
    title.classList.remove("in");
    para.classList.remove("in");
  }

  function playAnimation() {
    section.classList.remove("is-hidden");
    section.classList.add("revealed");

    title.classList.remove("in");
    para.classList.remove("in");
    void title.offsetWidth;
    void para.offsetWidth;

    requestAnimationFrame(() => {
      title.classList.add("in");
      setTimeout(() => para.classList.add("in"), 160);
    });
  }

  const BASE_SCALE = 1.18;
  const EXTRA_SCALE = 0.08;

  function parallax() {
    const rect = section.getBoundingClientRect();
    const vh = window.innerHeight || document.documentElement.clientHeight;
    if (rect.bottom < 0 || rect.top > vh) return;

    const center = rect.top + rect.height / 2;
    const p = (center - vh / 2) / (vh / 2);
    const t = Math.min(1, Math.abs(p));
    const s = BASE_SCALE + t * EXTRA_SCALE;

    img.style.transform = `translate3d(0, 0, 0) scale(${s})`;
  }

  function onScrollEagle() {
    if (ticking) return;
    ticking = true;

    requestAnimationFrame(() => {
      ticking = false;

      const y = window.scrollY;
      const direction = y > lastY ? "down" : "up";
      lastY = y;

      const nowInView = inActiveZone();

      if (!nowInView && wasInView) resetAnimationState();
      if (nowInView && !wasInView) {
        if (direction === "down" || direction === "up") playAnimation();
      }

      wasInView = nowInView;
      parallax();
    });
  }

  window.addEventListener("scroll", onScrollEagle, { passive: true });
  window.addEventListener("resize", () => requestAnimationFrame(parallax));
});
</script>

</body>
</html>
