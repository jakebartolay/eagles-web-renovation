<?php
// /includes/navbar.php
require_once __DIR__ . "/public_session.php";

/**
 * Your setup:
 * - index.php is at web root: /index.php
 * - all other pages are inside: /main/*.php
 * - includes are at: /includes/*.php
 * - assets are at: /static, /Styles, etc (root)
 */
$current_path = $_SERVER['REQUEST_URI']; // e.g. "/index.php" or "/"

function root_url(string $path): string {
  $path = "/" . ltrim($path, "/");
  return $path; // always from web root
}

function main_url(string $path): string {
  $path = ltrim($path, "/");
  return "/main/" . $path;
}
?>
<header class="site-header" id="siteHeader">
  <div class="container">
    <div class="logo">
      <a href="<?= root_url("index") ?>">
        <img src="<?= root_url("static/logo.png") ?>" alt="Logo">
      </a>
      <span class="nav-title">Ang Agila</span>
    </div>

<nav id="navbar">
  <a href="<?= root_url("/") ?>" class="<?= $current_path === "/" || $current_path === "/index.php" ? "active" : "" ?>">Home</a>


  <div class="nav-dropdown" id="aboutDropdown">
    <a href="#" class="about-link <?= str_contains($current_path, 'about-us') || str_contains($current_path, 'magna-carta') ? 'active' : '' ?>">About Us</a>
    <div class="dropdown-menu">
      <a href="<?= root_url("about-us") ?>" class="<?= str_contains($current_path, 'about-us') ? 'active' : '' ?>">History</a>
      <a href="<?= root_url("magna-carta") ?>" class="<?= str_contains($current_path, 'magna-carta') ? 'active' : '' ?>">Magna Carta</a>
    </div>
  </div>


  <a href="<?= root_url("news") ?>" class="<?= str_contains($current_path, 'news') ? 'active' : '' ?>">News & Videos</a>

<div class="nav-dropdown" id="officersDropdown">
 <a href="#" class="officers-link <?= str_contains($current_path, 'officers') || str_contains($current_path, 'governors') || str_contains($current_path, 'appointed') ? 'active' : '' ?>">Officers</a> 
<div class="dropdown-menu">

 <a href="<?= root_url("officers") ?>" class="<?= str_contains($current_path, 'officers') ? 'active' : '' ?>">National Officers</a>

 <a href="<?= root_url("governors") ?>" class="<?= str_contains($current_path, 'governors') ? 'active' : '' ?>">Governors</a> 

  <a href="<?= root_url("appointed-ofc") ?>" class="<?= str_contains($current_path, 'appointed') ? 'active' : '' ?>">Appointed Officers</a>
 </div> 
</div>

  <a href="<?= root_url("events") ?>" class="<?= str_contains($current_path, 'events') ? 'active' : '' ?>">Events</a>

  <?php if (!empty($_SESSION['user_id'])): ?>
    <a href="<?= root_url("membership") ?>" rel="noopener" class="<?= str_contains($current_path, 'membership') ? 'active' : '' ?>">Membership</a>
    <a href="<?= root_url("logout") ?>" class="logout-mobile">Logout</a>
  <?php else: ?>
    <a href="<?= root_url("membership") ?>" class="cta-btn <?= str_contains($current_path, 'membership') ? 'active' : '' ?>">Get Started</a>
  <?php endif; ?>
</nav>

    <?php if (!empty($_SESSION['user_id'])): ?>
      <div class="nav-user-group">
        <span class="nav-user"><?= htmlspecialchars($_SESSION['username'] ?? 'User', ENT_QUOTES, 'UTF-8') ?></span>
        <a href="<?= root_url("logout") ?>" class="logout-btn">Logout</a>
      </div>
    <?php endif; ?>

    <div id="menu-toggle" class="menu-toggle">&#9776;</div>
  </div>
</header>

<script>
(() => {
  const headerEl = document.getElementById("siteHeader");
  const nav = document.getElementById("navbar");
  const menuToggle = document.getElementById("menu-toggle");

  const officersDropdown = document.getElementById("officersDropdown");
  const officersLink = officersDropdown ? officersDropdown.querySelector(".officers-link") : null;

  const aboutDropdown = document.getElementById("aboutDropdown");
  const aboutLink = aboutDropdown ? aboutDropdown.querySelector(".about-link") : null;

  if (!headerEl || !nav || !menuToggle) return;

  const toggleDropdown = (dropdown) => dropdown && dropdown.classList.toggle("open");
  const closeDropdowns = () => {
    if (officersDropdown) officersDropdown.classList.remove("open");
    if (aboutDropdown) aboutDropdown.classList.remove("open");
  };

  function enableClickThenHoverOutClose(dropdown, triggerLink) {
    if (!dropdown || !triggerLink) return;

    const menu = dropdown.querySelector(".dropdown-menu");
    let closeTimer = null;

    const cancelClose = () => {
      if (closeTimer) { clearTimeout(closeTimer); closeTimer = null; }
    };

    const scheduleClose = () => {
      cancelClose();
      closeTimer = setTimeout(() => {
        if (window.innerWidth > 900) dropdown.classList.remove("open");
      }, 180);
    };

    triggerLink.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();
      toggleDropdown(dropdown);
      cancelClose();
    });

    dropdown.addEventListener("mouseenter", () => {
      if (window.innerWidth > 900) cancelClose();
    });

    dropdown.addEventListener("mouseleave", () => {
      if (window.innerWidth > 900) scheduleClose();
    });

    if (menu) {
      menu.addEventListener("mouseenter", () => {
        if (window.innerWidth > 900) cancelClose();
      });
      menu.addEventListener("mouseleave", () => {
        if (window.innerWidth > 900) scheduleClose();
      });
    }
  }

  enableClickThenHoverOutClose(officersDropdown, officersLink);
  enableClickThenHoverOutClose(aboutDropdown, aboutLink);

  document.addEventListener("click", (e) => {
    const clickedInsideOfficers = officersDropdown && officersDropdown.contains(e.target);
    const clickedInsideAbout = aboutDropdown && aboutDropdown.contains(e.target);
    if (!clickedInsideOfficers && !clickedInsideAbout) closeDropdowns();

    const clickedInsideNav = e.target.closest("#navbar");
    const clickedBurger = e.target.closest("#menu-toggle");

    if (window.innerWidth <= 900 && nav.classList.contains("active") && !clickedInsideNav && !clickedBurger) {
      nav.classList.remove("active");
      menuToggle.classList.remove("open");
      closeDropdowns();
    }
  });

  menuToggle.addEventListener("click", () => {
    nav.classList.toggle("active");
    menuToggle.classList.toggle("open");
    if (!nav.classList.contains("active")) closeDropdowns();
  });

  nav.addEventListener("click", (e) => {
    const a = e.target.closest("a");
    if (!a) return;
    if (a.classList.contains("about-link") || a.classList.contains("officers-link")) return;

    if (window.innerWidth <= 900 && nav.classList.contains("active")) {
      nav.classList.remove("active");
      menuToggle.classList.remove("open");
      closeDropdowns();
    }
  });

  // Scroll fade (same behavior)
  const BASE = { r: 22, g: 36, b: 71 };
  const FADE_DISTANCE = 260;
  const ALPHA_MAX = 1.00;
  const ALPHA_MIN = 0.08;
  const BLUR_MAX  = 10;

  let scrollables = [];

  function isScrollable(el) {
    if (!el || el === document || el === document.body || el === document.documentElement) return false;
    const cs = getComputedStyle(el);
    const oy = cs.overflowY;
    if (oy !== "auto" && oy !== "scroll") return false;
    return el.scrollHeight > el.clientHeight + 5;
  }

  function collectScrollables() {
    const found = [];
    const preferred = document.querySelectorAll("main, #main, .main, .content, .page, .wrapper, #root, #app, body > div");
    preferred.forEach((el) => { if (isScrollable(el)) found.push(el); });

    if (found.length === 0) {
      const els = Array.from(document.querySelectorAll("body *"));
      for (let i = 0; i < els.length; i++) {
        if (isScrollable(els[i])) found.push(els[i]);
        if (found.length >= 30) break;
      }
    }
    scrollables = found;
  }

  function getMaxScrollTop() {
    let top = 0;
    top = Math.max(top, window.scrollY || 0);
    top = Math.max(top, document.documentElement ? (document.documentElement.scrollTop || 0) : 0);
    top = Math.max(top, document.body ? (document.body.scrollTop || 0) : 0);
    for (const el of scrollables) top = Math.max(top, el.scrollTop || 0);
    return top;
  }

  function setHeader(alpha, blurPx) {
    const rgba = `rgba(${BASE.r}, ${BASE.g}, ${BASE.b}, ${alpha})`;
    headerEl.style.setProperty("background", rgba, "important");
    headerEl.style.setProperty("background-color", rgba, "important");
    headerEl.style.setProperty("backdrop-filter", `blur(${blurPx}px)`, "important");
    headerEl.style.setProperty("-webkit-backdrop-filter", `blur(${blurPx}px)`, "important");
  }

  function applyFade() {
    const y = getMaxScrollTop();
    const t = Math.min(1, Math.max(0, y / FADE_DISTANCE));
    const alpha = (ALPHA_MAX - t * (ALPHA_MAX - ALPHA_MIN)).toFixed(3);
    const blur  = (t * BLUR_MAX).toFixed(1);
    headerEl.classList.toggle("is-faded", t > 0.55);
    setHeader(alpha, blur);
  }

  let raf = 0;
  function scheduleFade() {
    if (raf) return;
    raf = requestAnimationFrame(() => {
      raf = 0;
      applyFade();
    });
  }

  window.addEventListener("scroll", scheduleFade, { passive: true });
  document.addEventListener("scroll", scheduleFade, true);
  window.addEventListener("resize", () => { collectScrollables(); scheduleFade(); });

  let lastTop = -1;
  setInterval(() => {
    const now = getMaxScrollTop();
    if (now !== lastTop) {
      lastTop = now;
      scheduleFade();
    }
  }, 50);

  collectScrollables();
  applyFade();
})();
</script>