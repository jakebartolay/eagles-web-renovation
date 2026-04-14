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
  <link rel="stylesheet" href="Styles/peil_directors.css">
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<!-- Modal Overlay -->
<div class="modal-overlay" onclick="closeAllModals()"></div>

<section class="officers-hero">
  <h1>PEIL Directors</h1>
  <p>Meet the leaders guiding our organization</p>
</section>

<section class="org-chart">

  <!-- OTHERS (7 cards) -->
  <div class="chart-level others">

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer1.jpg" alt="Erwin Torrefiel">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Alexandeer T. Alag</h4>
        <p>Executive PIEL Director</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer1.jpg" alt="Erwin Torrefiel">
        <h3><span class="officer-eagle">Eagle</span>Alexandeer T. Alag</h3>
        <p class="role">Executive PIEL Director</p>
        <p class="speech">"Unity begins within."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer2.jpg" alt="Dominic Baliwag">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Cesar Y. Yamuta</h4>
        <p>Senior PIEL Director, EY 2025</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer2.jpg" alt="Dominic Baliwag">
        <h3><span class="officer-eagle">Eagle</span>Cesar Y. Yamuta</h3>
        <p class="role">Senior PIEL Director, EY 2025</p>
        <p class="speech">"Service without borders."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer3.jpg" alt="Francisco J. Arañes">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Harvey B. Lauglaug</h4>
        <p> PIEL Director</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer3.jpg" alt="Francisco J. Arañes">
        <h3><span class="officer-eagle">Eagle</span>Harvey B. Lauglaug</h3>
        <p class="role"> PIEL Director</p>
        <p class="speech">"Organization is key to success."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer4.jpg" alt="Rommel Abital">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Mariano Anthony M. Aresta</h4>
        <p>PIEL Director</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer4.jpg" alt="Rommel Abital">
        <h3><span class="officer-eagle">Eagle</span>Mariano Anthony M. Aresta</h3>
        <p class="role">PIEL Director</p>
        <p class="speech">"Managing resources responsibly."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer5.jpg" alt="Ramiro I. Aquillo">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Flordelino A. Almazan</h4>
        <p>PIEL Director</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer5.jpg" alt="Ramiro I. Aquillo">
        <h3><span class="officer-eagle">Eagle</span>Flordelino A. Almazan</h3>
        <p class="role">PIEL Director</p>
        <p class="speech">"Transparency builds trust."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer6.jpg" alt="Joel Sonugan Elorde">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Atty. Junefifth G. Esto</h4>
        <p>PIEL Director</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer6.jpg" alt="Joel Sonugan Elorde">
        <h3><span class="officer-eagle">Eagle</span>Atty. Junefifth G. Esto</h3>
        <p class="role">PIEL Director</p>
        <p class="speech">"Transparency builds trust."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer7.jpg" alt="Gabby Bautista">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Francisco M. Buangjug</h4>
        <p>PIEL Director for Europe</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer7.jpg" alt="Gabby Bautista">
        <h3><span class="officer-eagle">Eagle</span>Francisco M. Buangjug</h3>
        <p class="role">PIEL Director for Europe</p>
        <p class="speech">"Transparency builds trust."</p>
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

  document.querySelectorAll('.detail-panel.show').forEach(p => p.classList.remove('show'));

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
