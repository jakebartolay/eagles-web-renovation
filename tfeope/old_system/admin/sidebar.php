<?php
require_once __DIR__ . "/../includes/admin_session.php";
$current_page = basename($_SERVER['PHP_SELF']);

/* =========================================================
   ROLE / SUPER ADMIN (SAFE)
   - ONLY role_id === 1 is Super Admin
   - ignore any fallback session strings/flags (prevents mis-detection)
========================================================= */
$roleId = (int)($_SESSION['role_id'] ?? 0);
$isSuperAdmin = ($roleId === 1);

/* Clean up any stale flags to avoid UI confusion */
if (!$isSuperAdmin) {
  $_SESSION['is_super_admin'] = 0;
  // normalize role strings if they exist
  if (isset($_SESSION['role'])) $_SESSION['role'] = 'admin';
  if (isset($_SESSION['role_name'])) $_SESSION['role_name'] = 'admin';
} else {
  $_SESSION['is_super_admin'] = 1;
  if (isset($_SESSION['role'])) $_SESSION['role'] = 'superadmin';
  if (isset($_SESSION['role_name'])) $_SESSION['role_name'] = 'superadmin';
}

/* ================= USER INFO ================= */
$userName =
    $_SESSION['admin_name']
    ?? $_SESSION['admin_username']
    ?? $_SESSION['name']
    ?? $_SESSION['full_name']
    ?? $_SESSION['username']
    ?? '';

$userName = trim((string)$userName);

$greeting = "Hi";
if ($isSuperAdmin) {
  $greeting = "Hi Super Admin";
} elseif ($roleId === 2) {
  $greeting = "Hi Admin";
}

/* MEMBERS DROPDOWN */
$members_pages  = ['member.php', 'user_management.php'];
$members_active = in_array($current_page, $members_pages, true);

/* CONTENT DROPDOWN */
$content_pages  = ['memorandum.php','news_management.php','videos_management.php','events_management.php','magna_carta_management.php'];
$content_active = in_array($current_page, $content_pages, true);

/* OFFICERS DROPDOWN */
$officers_pages  = ['officer_management.php','appointed_management.php','governor_management.php'];
$officers_active = in_array($current_page, $officers_pages, true);

/* LOGS & REPORTS */
$logs_page   = 'admin-report';
$logs_active = ($current_page === $logs_page);

/*
  IMPORTANT:
  Set this to the REAL location of your reset endpoint from the SITE ROOT.
*/
$RESET_ENDPOINT = "/admin-reset-database"; // <-- CHANGE IF NEEDED
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- Mobile Toggle Button -->
<button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Open sidebar">
  <i class="fas fa-bars"></i>
</button>

<!-- Mobile Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

<aside class="sidebar" id="adminSidebar" aria-label="Admin sidebar">

  <!-- LOGO -->
  <div class="logo">
    <img src="/../static/eagles.png" alt="Eagles Logo">
    <h2>Admin</h2>

    <!-- GREETING -->
    <p class="admin-greeting">
      <?= htmlspecialchars($greeting . ($userName !== '' ? (", " . $userName) : ""), ENT_QUOTES, 'UTF-8') ?>
    </p>
  </div>

  <div class="sidebar-menu" id="sidebarMenu">

    <a href="admin-dashboard" class="<?= $current_page === 'admin_dashboard.php' ? 'active' : '' ?>">
      <i class="fas fa-home"></i><span>Dashboard</span>
    </a>

    <!-- LOGS & REPORTS -->
       <?php if ($isSuperAdmin): ?>
    	<a href="admin-report" class="<?= $current_page === 'admin_report.php' ? 'active' : '' ?>">
      	<i class="fas fa-home"></i><span>Logs & Reports</span>
    	</a>
        <?php endif; ?>

    <!-- MEMBERS -->
    <div class="sidebar-dropdown <?= $members_active ? 'open' : '' ?>" id="membersDropdown">
      <button class="dropdown-toggle <?= $members_active ? 'active' : '' ?>" id="membersToggle" type="button" aria-expanded="<?= $members_active ? 'true' : 'false' ?>">
        <i class="fas fa-users"></i>
        <span>Members Management</span>
        <i class="fas fa-chevron-down arrow" aria-hidden="true"></i>
      </button>

      <div class="dropdown-menu">
        <a href="member" class="<?= $current_page === 'member.php' ? 'active' : '' ?>">
          <i class="fas fa-id-card"></i><span>Official Members</span>
        </a>

        <?php if ($isSuperAdmin): ?>
          <a href="user-management" class="<?= $current_page === 'user_management.php' ? 'active' : '' ?>">
            <i class="fas fa-user-cog"></i><span>Users</span>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- CONTENT -->
    <div class="sidebar-dropdown <?= $content_active ? 'open' : '' ?>" id="contentDropdown">
      <button class="dropdown-toggle <?= $content_active ? 'active' : '' ?>" id="contentToggle" type="button" aria-expanded="<?= $content_active ? 'true' : 'false' ?>">
        <i class="fas fa-folder"></i>
        <span>Content Management</span>
        <i class="fas fa-chevron-down arrow" aria-hidden="true"></i>
      </button>

      <div class="dropdown-menu">
        <a href="memorandum" class="<?= $current_page === 'memorandum.php' ? 'active' : '' ?>">
          <i class="fas fa-file-alt"></i><span>Memorandum</span>
        </a>
        <a href="news-management" class="<?= $current_page === 'news_management.php' ? 'active' : '' ?>">
          <i class="fas fa-newspaper"></i><span>News</span>
        </a>
        <a href="videos-management" class="<?= $current_page === 'videos_management.php' ? 'active' : '' ?>">
          <i class="fas fa-video"></i><span>Videos</span>
        </a>
        <a href="events-management" class="<?= $current_page === 'events_management.php' ? 'active' : '' ?>">
          <i class="fas fa-calendar"></i><span>Events</span>
        </a>

        <a href="magna-carta-management" class="<?= $current_page === 'magna_carta_management.php' ? 'active' : '' ?>">
          <i class="fas fa-book"></i><span>Magna Carta</span>
        </a>
      </div>
    </div>

    <!-- OFFICERS -->
    <div class="sidebar-dropdown <?= $officers_active ? 'open' : '' ?>" id="officersDropdown">
      <button class="dropdown-toggle <?= $officers_active ? 'active' : '' ?>" id="officersToggle" type="button" aria-expanded="<?= $officers_active ? 'true' : 'false' ?>">
        <i class="fas fa-cogs"></i>
        <span>Officers Management</span>
        <i class="fas fa-chevron-down arrow" aria-hidden="true"></i>
      </button>

      <div class="dropdown-menu">
        <a href="officer-management" class="<?= $current_page === 'officer_management.php' ? 'active' : '' ?>">
          <i class="fas fa-user-tie"></i><span>Officers</span>
        </a>
        <a href="appointed-management" class="<?= $current_page === 'appointed_management.php' ? 'active' : '' ?>">
          <i class="fas fa-user-check"></i><span>Appointed</span>
        </a>
        <a href="governor-management" class="<?= $current_page === 'governor_management.php' ? 'active' : '' ?>">
          <i class="fas fa-user-shield"></i><span>Governors</span>
        </a>
      </div>
    </div>

    <!-- RESET DATABASE (SUPER ADMIN ONLY) -->
    <?php if ($isSuperAdmin): ?>
      <button type="button" class="sidebar-danger-btn" id="openResetDb">
        <i class="fas fa-triangle-exclamation"></i>
        <span>Reset Database</span>
      </button>
    <?php endif; ?>

    <!-- LOGOUT -->
    <form method="POST" action="admin-logout" class="sidebar-logout">
      <button type="submit">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
      </button>
    </form>

  </div>
</aside>

<?php if ($isSuperAdmin): ?>
  <!-- RESET DB MODAL -->
  <div class="resetdb-modal" id="resetDbModal" aria-hidden="true">
    <div class="resetdb-backdrop" id="resetDbBackdrop"></div>

    <div class="resetdb-card" role="dialog" aria-modal="true" aria-labelledby="resetDbTitle">
      <button class="resetdb-close" id="resetDbClose" type="button" aria-label="Close">
        <i class="fa-solid fa-xmark"></i>
      </button>

      <h3 id="resetDbTitle">Reset Database</h3>
      <p class="resetdb-subtitle">
        This will delete data from selected tables. This action cannot be undone.
      </p>

      <label class="resetdb-label" for="resetDbPassword">Enter your password to confirm</label>
      <input class="resetdb-input" id="resetDbPassword" type="password" autocomplete="current-password" placeholder="Password" />

      <div class="resetdb-actions">
        <button class="resetdb-cancel" id="resetDbCancel" type="button">Cancel</button>
        <button class="resetdb-confirm" id="resetDbConfirm" type="button">
          <i class="fas fa-trash"></i> Reset Now
        </button>
      </div>

      <div class="resetdb-msg" id="resetDbMsg" aria-live="polite"></div>
    </div>
  </div>
<?php endif; ?>

<script>
(function(){
  // Dropdown toggles (and keep aria-expanded in sync)
  function setup(toggleId, dropdownId){
    const t = document.getElementById(toggleId);
    const d = document.getElementById(dropdownId);
    if (!t || !d) return;

    t.addEventListener("click", function(){
      const isOpen = d.classList.toggle("open");
      t.setAttribute("aria-expanded", isOpen ? "true" : "false");
    });
  }
  setup("membersToggle","membersDropdown");
  setup("contentToggle","contentDropdown");
  setup("officersToggle","officersDropdown");

  // Mobile sidebar toggle + overlay
  const sidebar = document.getElementById("adminSidebar");
  const toggleBtn = document.getElementById("sidebarToggle");
  const overlay = document.getElementById("sidebarOverlay");

  function openSidebar(){
    if (!sidebar) return;
    sidebar.classList.add("show");
    if (overlay) overlay.classList.add("show");
    document.documentElement.style.overflow = "hidden";
    document.body.style.overflow = "hidden";
    if (overlay) overlay.setAttribute("aria-hidden", "false");
  }

  function closeSidebar(){
    if (!sidebar) return;
    sidebar.classList.remove("show");
    if (overlay) overlay.classList.remove("show");
    document.documentElement.style.overflow = "";
    document.body.style.overflow = "";
    if (overlay) overlay.setAttribute("aria-hidden", "true");
  }

  if (toggleBtn) {
    toggleBtn.addEventListener("click", function(){
      if (sidebar.classList.contains("show")) closeSidebar();
      else openSidebar();
    });
  }

  if (overlay) overlay.addEventListener("click", closeSidebar);

  document.addEventListener("keydown", function(e){
    if (e.key === "Escape") closeSidebar();
  });

  window.addEventListener("resize", function(){
    if (window.innerWidth > 768) closeSidebar();
  });

  // ===== Reset DB modal (Super Admin only) =====
  const openBtn = document.getElementById("openResetDb");
  const modal = document.getElementById("resetDbModal");
  const bd = document.getElementById("resetDbBackdrop");
  const close = document.getElementById("resetDbClose");
  const cancel = document.getElementById("resetDbCancel");
  const confirm = document.getElementById("resetDbConfirm");
  const pass = document.getElementById("resetDbPassword");
  const msg = document.getElementById("resetDbMsg");

  function openResetModal(){
    if (!modal) return;
    msg.textContent = "";
    if (pass) pass.value = "";
    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
    document.documentElement.style.overflow = "hidden";
    document.body.style.overflow = "hidden";
    setTimeout(() => pass && pass.focus(), 50);
  }

  function closeResetModal(){
    if (!modal) return;
    modal.classList.remove("open");
    modal.setAttribute("aria-hidden", "true");
    document.documentElement.style.overflow = "";
    document.body.style.overflow = "";
  }

  if (openBtn) openBtn.addEventListener("click", openResetModal);
  if (bd) bd.addEventListener("click", closeResetModal);
  if (close) close.addEventListener("click", closeResetModal);
  if (cancel) cancel.addEventListener("click", closeResetModal);

  document.addEventListener("keydown", function(e){
    if (e.key === "Escape" && modal && modal.classList.contains("open")) {
      closeResetModal();
    }
  });

  async function doReset(){
    if (!pass || !msg || !confirm) return;

    if (!pass.value.trim()){
      msg.textContent = "Password is required.";
      msg.className = "resetdb-msg error";
      pass.focus();
      return;
    }

    msg.textContent = "Resetting...";
    msg.className = "resetdb-msg";
    confirm.disabled = true;

    try{
      const url = <?= json_encode($RESET_ENDPOINT) ?>;

      const res = await fetch(url, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ password: pass.value })
      });

      const ct = (res.headers.get("content-type") || "").toLowerCase();

      if (!ct.includes("application/json")) {
        const text = await res.text();
        msg.textContent = `Reset failed (HTTP ${res.status}). Server returned non-JSON. Check path/session.`;
        msg.className = "resetdb-msg error";
        console.log("Reset response (non-JSON):", text);
        confirm.disabled = false;
        return;
      }

      const data = await res.json();

      if (!res.ok || !data.ok){
        msg.textContent = data.message || `Reset failed (HTTP ${res.status}).`;
        msg.className = "resetdb-msg error";
        console.log("Reset response JSON:", data);
        confirm.disabled = false;
        return;
      }

      msg.textContent = data.message || "Database reset completed.";
      msg.className = "resetdb-msg success";

      setTimeout(() => {
        closeResetModal();
        location.reload();
      }, 900);

    } catch (err){
      msg.textContent = "Network error. Please try again.";
      msg.className = "resetdb-msg error";
      console.error(err);
      confirm.disabled = false;
    }
  }

  if (confirm) confirm.addEventListener("click", doReset);
  if (pass) {
    pass.addEventListener("keydown", function(e){
      if (e.key === "Enter") doReset();
    });
  }
})();
</script>
