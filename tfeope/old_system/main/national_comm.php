<?php ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ang Agila | Officers</title>

  <link rel="icon" type="image/png" href="static/eagles.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Merriweather:wght@700;900&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="Styles/navbar.css">
  <link rel="stylesheet" href="Styles/footer.css">
  <link rel="stylesheet" href="Styles/national_comm.css">
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<!-- Modal Overlay -->
<div class="modal-overlay" onclick="closeAllModals()"></div>

<section class="officers-hero">
  <h1>National Commissions</h1>
  <p>Meet the leaders guiding our organization</p>
</section>

<section class="org-chart">

  <!-- OTHERS (ONLY 5 CARDS) — Layout target: 3 cards (row 1) + 2 cards (row 2 centered) -->
  <div class="chart-level others">

    <!-- Row 1 (3 cards) -->
    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer1.jpg" alt="Maria Santos">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Erwin Torrefiel</h4>
        <p>Comission On Membership (COME)</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer1.jpg" alt="Maria Santos">
        <h3><span class="officer-eagle">Eagle</span>Erwin Torrefiel</h3>
        <p class="role">Comission On Membership (COME)</p>
        <p class="speech">"Unity begins within."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer2.jpg" alt="Carlos Garcia">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Andy Paul Quitoria</h4>
        <p>Comission on Extension (COMEX)</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer2.jpg" alt="Carlos Garcia">
        <h3><span class="officer-eagle">Eagle</span>Andy Paul Quitoria</h3>
        <p class="role">Comission on Extension (COMEX)</p>
        <p class="speech">"Service without borders."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer3.jpg" alt="Ana Cruz">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Conrado Supangan JR.</h4>
        <p>Comission on Personal Relation</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer3.jpg" alt="Ana Cruz">
        <h3><span class="officer-eagle">Eagle</span>Conrado Supangan JR.</h3>
        <p class="role">Comission on Personal Relation</p>
        <p class="speech">"Organization is key to success."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer5.jpg" alt="Liza Reyes">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Jezreel S. Ayupan</h4>
        <p>Comission on Community Service (COMSERV)</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer5.jpg" alt="Liza Reyes">
        <h3><span class="officer-eagle">Eagle</span>Jezreel S. Ayupan</h3>
        <p class="role">Comission on Community Service (COMSERV)</p>
        <p class="speech">"Transparency builds trust."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer4.jpg" alt="Ramon Lopez">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Russel Jocson</h4>
        <p>Comission On Awards and Recognition</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer4.jpg" alt="Ramon Lopez">
        <h3><span class="officer-eagle">Eagle</span>Russel Jocson</h3>
        <p class="role">Comission On Awards and Recognition</p>
        <p class="speech">"Managing resources responsibly."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

  </div>
</section>

<?php include 'includes/footer.php'; ?>

<script>
const overlay = document.querySelector('.modal-overlay');

function togglePanel(card){
  const panel = card.querySelector('.detail-panel');

  // Close other modals
  document.querySelectorAll('.detail-panel.show').forEach(p => p.classList.remove('show'));

  // If the clicked one is already open, close everything
  if (panel.classList.contains('show')) {
    panel.classList.remove('show');
    overlay.classList.remove('show');
    return;
  }

  overlay.classList.add('show');
  panel.classList.add('show');
}

function closePanel(e){
  e.stopPropagation();
  const panel = e.target.closest('.detail-panel');
  panel.classList.remove('show');
  overlay.classList.remove('show');
}

function closeAllModals(){
  document.querySelectorAll('.detail-panel.show').forEach(p => p.classList.remove('show'));
  overlay.classList.remove('show');
}
</script>

</body>
</html>
