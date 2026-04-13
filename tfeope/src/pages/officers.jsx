<?php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/public_session.php";

/*
  NATIONAL OFFICERS (Org chart)
  - category = 'national_officers'
  - ordering uses officer_position_order.hierarchy_level
*/

$stmt = $conn->prepare("
  SELECT o.*
  FROM officers o
  LEFT JOIN officer_position_order p
    ON o.full_position = p.position_name
  WHERE o.category = 'national_officers'
  ORDER BY COALESCE(p.hierarchy_level, 999) ASC, o.name ASC
");
$stmt->execute();
$res = $stmt->get_result();

$byFull = [];
while ($row = $res->fetch_assoc()) {
  $byFull[$row['full_position']] = $row;
}

function officer_img($file) {
  if (!$file) return "static/placeholder.png";
  return "/officers/" . $file;
}

function speech_img($file) {
  if (!$file) return "static/placeholder.png";
  if (strpos($file, "/") !== false) return $file;
  return "/officers/speech/" . $file;
}

function render_card($o, $id = "") {
  if (!$o) return;

  $name = htmlspecialchars($o['name'] ?? '');
  $pos  = htmlspecialchars($o['position'] ?? '');
  $full = htmlspecialchars($o['full_position'] ?? '');
  $img  = htmlspecialchars(officer_img($o['image'] ?? ''));
  $simg = htmlspecialchars(speech_img($o['speech_image'] ?? ''));
  $sp   = htmlspecialchars($o['speech'] ?? '');

  $idAttr = $id ? ' id="'.htmlspecialchars($id).'"' : '';

  echo <<<HTML
  <div class="officer-card"{$idAttr} role="button" tabindex="0" onclick="togglePanel(this)">
    <img class="officer-photo" src="{$img}" alt="{$name}">
    <div class="officer-info">
      <h4>{$name}</h4>
      <p>{$pos}</p>
    </div>

    <!-- modal panel (moved to body via JS) -->
    <div class="detail-panel" role="dialog" aria-modal="true" aria-hidden="true">
      <button class="close" type="button" onclick="closePanel(event)" aria-label="Close">×</button>

      <div class="detail-body">
        <img class="speech-photo" src="{$simg}" alt="{$name} Speech">
        <h3>{$name}</h3>
        <p class="role">{$full}</p>
        <p class="speech">"{$sp}"</p>
      </div>
    </div>
  </div>
  HTML;
}

/* Map to structure */
$president = $byFull['National President'] ?? null;
$secgen    = $byFull['Secretary General'] ?? null;
$execvp    = $byFull['Executive National Vice President'] ?? null;

$vp_luzon  = $byFull['Vice President for Luzon'] ?? null;
$vp_vis    = $byFull['Appointed Vice President for Visayas'] ?? ($byFull['Vice President for Visayas'] ?? null);
$vp_min    = $byFull['Vice President for Mindanao'] ?? null;

$floorlead = $byFull['National Assembly Floor Leader'] ?? null;
$treasurer = $byFull['National Assembly Treasurer'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ang Agila | Officers</title>

<link rel="icon" type="image/png" href="/../static/eagles.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Merriweather:wght@700;900&display=swap" rel="stylesheet">

<link rel="stylesheet" href="/../Styles/officers.css">
<link rel="stylesheet" href="/../Styles/navbar.css">
<link rel="stylesheet" href="/../Styles/footer.css">


</head>
<body>

<div class="page-wrapper">
<?php include __DIR__ . '/../includes/navbar.php'; ?>

  <section class="officers-hero">
    <h1>National Officers</h1>
    <p>Meet the leaders guiding our organization</p>
  </section>

  <section class="org-chart org-v2" id="orgChart">
    <svg class="org-lines" id="orgLines" aria-hidden="true"></svg>

    <div class="org-row row-1">
      <div class="slot center">
        <?php render_card($president, "card-president"); ?>
      </div>
    </div>

    <div class="org-row row-2">
      <div class="slot left">
        <?php render_card($secgen, "card-secgen"); ?>
      </div>

      <div class="slot center">
        <?php render_card($execvp, "card-execvp"); ?>
      </div>

      <!-- empty slot (kept for desktop alignment, hidden on small screens by CSS) -->
      <div class="slot right slot-empty" aria-hidden="true"></div>
    </div>

    <div class="org-row row-3">
      <div class="slot col">
        <?php render_card($vp_luzon, "card-vp-luzon"); ?>
      </div>

      <div class="slot col">
        <?php render_card($vp_vis, "card-vp-visayas"); ?>
      </div>

      <div class="slot col">
        <?php render_card($vp_min, "card-vp-mindanao"); ?>
      </div>
    </div>

    <div class="org-row row-4">
      <div class="slot col">
        <?php render_card($floorlead, "card-floorleader"); ?>
      </div>

      <div class="slot col">
        <?php render_card($treasurer, "card-treasurer"); ?>
      </div>

      <!-- empty slot (kept for desktop alignment, hidden on small screens by CSS) -->
      <div class="slot col slot-empty" aria-hidden="true"></div>
    </div>

  </section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</div>

<!-- IMPORTANT: overlay is GLOBAL (outside page-wrapper) -->
<div class="modal-overlay"></div>

<script>

window.addEventListener('load', () => {
  document.body.classList.add('loaded');
});

/* =========================
   MODAL (FIX CLOSE BUTTON)
========================= */
const overlay = document.querySelector(".modal-overlay");

let activePanel = null;
let activePlaceholder = null;

function stopInsideModal(e) {
  e.stopPropagation();
}

function openPanelFromCard(card) {
  const panel = card.querySelector(".detail-panel");
  if (!panel) return;

  if (activePanel && panel === activePanel) {
    closeAllModals();
    return;
  }

  closeAllModals();

  activePlaceholder = document.createElement("span");
  activePlaceholder.className = "panel-placeholder";
  panel.parentNode.insertBefore(activePlaceholder, panel);

  document.body.appendChild(panel);

  panel.addEventListener("click", stopInsideModal, false);
  panel.addEventListener("mousedown", stopInsideModal, false);

  activePanel = panel;
  overlay.classList.add("show");
  panel.classList.add("show");
  panel.setAttribute("aria-hidden", "false");
  document.body.classList.add("modal-open");
}

function togglePanel(card) {
  openPanelFromCard(card);
}

function closePanel(e) {
  if (e) {
    e.preventDefault();
    e.stopPropagation();
  }
  closeAllModals();
}

function closeAllModals() {
  overlay.classList.remove("show");
  document.body.classList.remove("modal-open");

  if (activePanel) {
    activePanel.removeEventListener("click", stopInsideModal, false);
    activePanel.removeEventListener("mousedown", stopInsideModal, false);

    activePanel.classList.remove("show");
    activePanel.setAttribute("aria-hidden", "true");

    if (activePlaceholder && activePlaceholder.parentNode) {
      activePlaceholder.parentNode.insertBefore(activePanel, activePlaceholder);
      activePlaceholder.remove();
    }

    activePanel = null;
    activePlaceholder = null;
  }

  document.querySelectorAll(".detail-panel.show").forEach(p => p.classList.remove("show"));
}

overlay.addEventListener("click", (e) => {
  e.preventDefault();
  e.stopPropagation();
  closeAllModals();
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeAllModals();
});

/* Keyboard open for accessibility */
document.querySelectorAll(".officer-card").forEach(card => {
  card.addEventListener("keydown", (e) => {
    if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      togglePanel(card);
    }
  });
});

/* ===== ORG LINES (SVG) ===== */
(function(){
  const chart = document.getElementById("orgChart");
  const svg = document.getElementById("orgLines");

  function getEl(id){ return document.getElementById(id); }

  function ptCenterTop(el, rootRect){
    const r = el.getBoundingClientRect();
    return { x: (r.left + r.right)/2 - rootRect.left, y: r.top - rootRect.top };
  }
  function ptCenterBottom(el, rootRect){
    const r = el.getBoundingClientRect();
    return { x: (r.left + r.right)/2 - rootRect.left, y: r.bottom - rootRect.top };
  }
  function ptRightCenter(el, rootRect){
    const r = el.getBoundingClientRect();
    return { x: r.right - rootRect.left, y: (r.top + r.bottom)/2 - rootRect.top };
  }

  function line(x1,y1,x2,y2){
    const l = document.createElementNS("http://www.w3.org/2000/svg", "line");
    l.setAttribute("x1", x1); l.setAttribute("y1", y1);
    l.setAttribute("x2", x2); l.setAttribute("y2", y2);
    l.setAttribute("class", "org-line");
    return l;
  }

  function draw(){
    if (!chart || !svg) return;

    /* if layout stacks, lines won’t match */
    const stacked = window.matchMedia("(max-width: 1100px)").matches;
    if (stacked){ svg.innerHTML = ""; return; }

    const rootRect = chart.getBoundingClientRect();
    const w = rootRect.width, h = rootRect.height;

    svg.setAttribute("viewBox", `0 0 ${w} ${h}`);
    svg.setAttribute("width", w);
    svg.setAttribute("height", h);
    svg.innerHTML = "";

    const president = getEl("card-president");
    const secgen    = getEl("card-secgen");
    const execvp    = getEl("card-execvp");

    const vpL = getEl("card-vp-luzon");
    const vpV = getEl("card-vp-visayas");
    const vpM = getEl("card-vp-mindanao");

    const floor = getEl("card-floorleader");
    const treas = getEl("card-treasurer");

    if (!president || !execvp) return;

    const pB = ptCenterBottom(president, rootRect);
    const eT = ptCenterTop(execvp, rootRect);

    let junctionY;
    if (secgen){
      const sR = ptRightCenter(secgen, rootRect);
      junctionY = sR.y;
      svg.appendChild(line(sR.x, sR.y, pB.x, junctionY));
    } else {
      junctionY = (pB.y + eT.y) / 2;
    }

    svg.appendChild(line(pB.x, pB.y, pB.x, junctionY));
    svg.appendChild(line(pB.x, junctionY, eT.x, eT.y));

    const eB = ptCenterBottom(execvp, rootRect);
    const vpCards = [vpL, vpV, vpM].filter(Boolean);

    if (vpCards.length){
      const tops = vpCards.map(el => ptCenterTop(el, rootRect).y);
      const barY = Math.min(...tops) - 18;

      svg.appendChild(line(eB.x, eB.y, eB.x, barY));

      const centersX = vpCards.map(el => ptCenterTop(el, rootRect).x).sort((a,b)=>a-b);
      const leftX = centersX[0];
      const rightX = centersX[centersX.length - 1];
      svg.appendChild(line(leftX, barY, rightX, barY));

      vpCards.forEach(el=>{
        const t = ptCenterTop(el, rootRect);
        svg.appendChild(line(t.x, barY, t.x, t.y));
      });

      if (vpL && floor){
        const vpLb = ptCenterBottom(vpL, rootRect);
        const flT  = ptCenterTop(floor, rootRect);
        svg.appendChild(line(vpLb.x, vpLb.y, flT.x, flT.y));
      }

      if (vpV && treas){
        const vpVb = ptCenterBottom(vpV, rootRect);
        const trT  = ptCenterTop(treas, rootRect);
        svg.appendChild(line(vpVb.x, vpVb.y, trT.x, trT.y));
      }
    }
  }

  window.addEventListener("load", () => { setTimeout(draw, 80); });
  window.addEventListener("resize", () => draw());
  document.querySelectorAll("#orgChart img").forEach(img => {
    img.addEventListener("load", () => draw());
  });
})();
</script>

</body>
</html>
