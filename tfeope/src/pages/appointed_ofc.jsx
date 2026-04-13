<?php
// appointments.php


require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/public_session.php";


/* Fetch all regions dynamically */
$region_result = $conn->query("
  SELECT DISTINCT region
  FROM appointed
  ORDER BY region
");


$regions = [];
while ($r = $region_result->fetch_assoc()) {
  $regions[] = $r['region'];
}


/* Fetch all appointments grouped by region and committee */
$appt_result = $conn->query("
  SELECT *
  FROM appointed
  ORDER BY region, committee, position, name
");


$appointments = [];
while ($row = $appt_result->fetch_assoc()) {
  $appointments[$row['region']][$row['committee']][] = $row;
}


/* helper for consistent ids */
function slug($s): string {
  $s = strtolower(trim((string)$s));
  $s = preg_replace('/[^a-z0-9\s-]/', '', $s);
  $s = preg_replace('/\s+/', '-', $s);
  return $s ?: 'region';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ang Agila | Appointed Officers</title>

<link rel="icon" type="image/png" href="/../static/eagles.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Merriweather:wght@700;900&display=swap" rel="stylesheet">

<link rel="stylesheet" href="/../Styles/appt_ofc.css">
<link rel="stylesheet" href="/../Styles/navbar.css">
<link rel="stylesheet" href="/../Styles/footer.css">

</head>


<body class="appt-body">

<?php include __DIR__ . '/../includes/navbar.php'; ?>
<div class="appt-shell">
  <main class="appt-main">
    <div class="appt-wrap">


      <?php foreach ($regions as $region): ?>
        <?php
          $regionSlug = slug($region);
          $regionAppointments = $appointments[$region] ?? [];
        ?>


        <section class="appt-card" id="<?= $regionSlug ?>Card">
          <div class="appt-card-head">
            <div class="appt-text">
              <div class="appt-kicker">Appointed Officers</div>
              <h1 class="appt-title"><?= htmlspecialchars($region) ?></h1>
              <p class="appt-desc">
                View the list of appointed officers and committee members for the <?= htmlspecialchars($region) ?> region.
              </p>
            </div>


            <button
              class="appt-toggle"
              type="button"
              aria-expanded="false"
              aria-controls="<?= $regionSlug ?>Drop"
            >
              <span class="appt-chev" aria-hidden="true"></span>
            </button>
          </div>


          <div class="appt-drop" id="<?= $regionSlug ?>Drop" hidden>
            <div class="dash-in">
              <div class="dash-top">
                <div class="dash-top-row">
                  <h2 class="dash-title">Appointed Officers in <?= htmlspecialchars($region) ?></h2>


                  <div class="dash-filter">
                    <label class="dash-filter-label" for="<?= $regionSlug ?>Filter">Filter:</label>
                    <select
                      id="<?= $regionSlug ?>Filter"
                      class="dash-filter-select"
                      onchange="filterCommittee('<?= $regionSlug ?>')"
                    >
                      <option value="all">All Committees</option>
                      <?php foreach ($regionAppointments as $committee => $_): ?>
                        <option value="<?= slug(trim($committee)) ?>">
                          <?= htmlspecialchars($committee) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              </div>


              <div class="dash-tablewrap">
                <table class="dash-table" id="<?= $regionSlug ?>Table" aria-label="<?= htmlspecialchars($region) ?> appointments table">
                  <thead>
                    <tr>
                      <th>Committee</th>
                      <th>Position</th>
                      <th>Name</th>
                      <th>Location</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($regionAppointments as $committee => $rows): ?>
                      <?php foreach ($rows as $row): ?>
                        <tr data-committee="<?= slug(trim($row['committee'])) ?>">
                          <td class="td-strong" data-label="Committee"><?= htmlspecialchars($row['committee']) ?></td>
                          <td data-label="Position"><?= htmlspecialchars($row['position']) ?></td>


                          <!-- keep <td> as a real table-cell; flex only inside .name-wrap -->
                          <td class="name-cell" data-label="Name">
                            <span class="name-wrap">
                              <span class="officer-eagle">Eagle</span>
                              <span class="officer-name"><?= htmlspecialchars($row['name']) ?></span>
                            </span>
                          </td>


                          <td class="location-text td-location" data-label="Location"><?= htmlspecialchars($row['region']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>


            </div>
          </div>


        </section>
      <?php endforeach; ?>


    </div>
  </main>


<?php include __DIR__ . '/../includes/footer.php'; ?>
</div>


<script>
function initCardToggle(cardId, dropId){
  const card = document.getElementById(cardId);
  if(!card) return;


  const btn  = card.querySelector(".appt-toggle");
  const drop = document.getElementById(dropId);
  if(!btn || !drop) return;


  drop.hidden = true;
  drop.style.maxHeight = "0px";


  function openDrop(){
    drop.hidden = false;
    btn.setAttribute("aria-expanded","true");
    requestAnimationFrame(() => {
      drop.style.maxHeight = drop.scrollHeight + "px";
    });
  }


  function closeDrop(){
    btn.setAttribute("aria-expanded","false");
    drop.style.maxHeight = "0px";
    setTimeout(() => { drop.hidden = true; }, 260);
  }


  btn.addEventListener("click", () => {
    const isOpen = btn.getAttribute("aria-expanded") === "true";
    if(isOpen) closeDrop();
    else openDrop();
  });


  window.addEventListener("resize", () => {
    const isOpen = btn.getAttribute("aria-expanded") === "true";
    if(isOpen && !drop.hidden){
      drop.style.maxHeight = drop.scrollHeight + "px";
    }
  });
}


/* IMPORTANT: your mobile CSS forces `tr { display:block !important; }`
   So normal `row.style.display = "none"` will NOT work.
   We must set display with `!important` via setProperty.
*/
function getRowDisplayMode(){
  // must match your CSS breakpoint for card-mode
  return window.matchMedia("(max-width: 600px)").matches ? "block" : "table-row";
}


function reZebra(regionId){
  const rows = Array.from(document.querySelectorAll("#" + regionId + "Table tbody tr"))
    .filter(r => getComputedStyle(r).display !== "none");


  rows.forEach((row, i) => {
    row.classList.toggle("zebra", i % 2 === 1);
  });
}


function filterCommittee(regionId){
  const select = document.getElementById(regionId + "Filter");
  const value = ((select && select.value) ? select.value : "all").trim();


  const displayMode = getRowDisplayMode();
  const rows = document.querySelectorAll("#" + regionId + "Table tbody tr");


  rows.forEach(row => {
    const committee = (row.getAttribute("data-committee") || "").trim();
    const show = (value === "all" || committee === value);


    // THIS is the fix: override CSS `display: ... !important`
    row.style.setProperty("display", show ? displayMode : "none", "important");
  });


  reZebra(regionId);


  const drop = document.getElementById(regionId + "Drop");
  const btn  = document.querySelector("#" + regionId + "Card .appt-toggle");
  const isOpen = btn && btn.getAttribute("aria-expanded") === "true";
  if(isOpen && drop && !drop.hidden){
    drop.style.maxHeight = drop.scrollHeight + "px";
  }
}


document.addEventListener("DOMContentLoaded", () => {
  const regions = <?= json_encode(array_map('slug', $regions)) ?>;


  regions.forEach(id => {
    initCardToggle(id + "Card", id + "Drop");


    // bind change in JS too (in case inline onchange gets overridden)
    const sel = document.getElementById(id + "Filter");
    if(sel){
      sel.addEventListener("change", () => filterCommittee(id));
    }


    filterCommittee(id);
  });


  // re-apply on rotate/resize so the displayMode switches correctly
  window.addEventListener("resize", () => {
    regions.forEach(id => filterCommittee(id));
  });
});
</script>




</body>
</html>