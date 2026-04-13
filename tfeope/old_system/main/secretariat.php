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
  <link rel="stylesheet" href="Styles/secretariat.css">
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<!-- Modal Overlay -->
<div class="modal-overlay" onclick="closeAllModals()"></div>

<section class="officers-hero">
  <h1>National Secretariat</h1>
  <p>Meet the leaders guiding our organization</p>
</section>

<section class="org-chart">



  <!-- TRUSTEES -->
  <div class="chart-level others">

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer1.jpg" alt="Lucio F. Ceniza, PHD">
      <div class="officer-info">
        <h4><span class="officer-eagle">Lady Eagle</span>Judith Z. Torrefiel</h4>
        <p>National Secretariat for Events & Affairs</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer1.jpg" alt="Lucio F. Ceniza, PHD">
        <h3><span class="officer-eagle">Lady Eagle</span>Judith Z. Torrefiel</h3>
        <p class="role">National Secretariat for Events & Affairs</p>
        <p class="speech">"Unity begins within."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer2.jpg" alt='Jose "Jojo" P. Calderon'>
      <div class="officer-info">
        <h4><span class="officer-eagle">Lady Eagle</span>Charity Imam</h4>
        <p>National Secretariat for Sports Development</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer2.jpg" alt='Jose "Jojo" P. Calderon'>
        <h3><span class="officer-eagle">Lady Eagle</span>Charity Imam</h3>
        <p class="role">National Secretariat for Sports Development</p>
        <p class="speech">"Service without borders."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer5.jpg" alt="Mgen Romeo V. Calizo PA (RET.)">
      <div class="officer-info">
        <h4><span class="officer-eagle">Lady Eagle</span>Filipina Z. Quitoria</h4>
        <p>National Secretariat for Philippine Ms. Philippine Eagle</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer5.jpg" alt="Mgen Romeo V. Calizo PA (RET.)">
        <h3><span class="officer-eagle">Lady Eagle</span>Filipina Z. Quitoria</h3>
        <p class="role">National Secretariat for Philippine Ms. Philippine Eagle</p>
        <p class="speech">"Transparency builds trust."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

<div class="officer-card is-center" onclick="togglePanel(this)">
        <img src="officers/officer6.jpg" alt="Jocil B. Labial">
        <div class="officer-info">
        <h4><span class="officer-eagle">Lady Eagle</span>Jean Imelda N. Aquillo</h4>
        <p>National Secretariat for Protocol Services</p>
        </div>
        <div class="detail-panel">
    <img src="officers/officer6.jpg" alt="Jocil B. Labial">
        <h3><span class="officer-eagle">Lady Eagle</span>Jean Imelda N. Aquillo</h3>
        <p class="role">National Secretariat for Protocol Services</p>
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
