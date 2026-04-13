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
  <link rel="stylesheet" href="Styles/boft.css">
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<!-- Modal Overlay -->
<div class="modal-overlay" onclick="closeAllModals()"></div>

<section class="officers-hero">
  <h1>National Executives</h1>
  <p>Meet the leaders guiding our organization</p>
</section>

<section class="org-chart">

  <!-- CHAIRMAN -->
  <div class="chart-level president">
    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer1.jpg" alt="Atty. Michael Florentino R. Dumlao III">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Cesar Y. Yamuta</h4>
        <p>Deputy Secretary General</p>
      </div>
      <div class="detail-panel">
        <img src="officers/speech_jojo.jpg" alt="Atty. Michael Florentino R. Dumlao III">
        <h3><span class="officer-eagle">Eagle</span>Cesar Y. Yamuta</h3>
        <p class="role">Deputy Secretary General</p>
        <p class="speech">"Leadership is service, not authority."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>
  </div>

  <!-- TRUSTEES -->
  <div class="chart-level others">

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer1.jpg" alt="Lucio F. Ceniza, PHD">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Atty. Jesus D. Poquiz</h4>
        <p>Secretary General</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer1.jpg" alt="Lucio F. Ceniza, PHD">
        <h3><span class="officer-eagle">Eagle</span>Atty. Jesus D. Poquiz</h3>
        <p class="role">Secretary General</p>
        <p class="speech">"Unity begins within."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer2.jpg" alt='Jose "Jojo" P. Calderon'>
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Rich Nicollie Z. Torrefiel</h4>
        <p>Information Secretary</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer2.jpg" alt='Jose "Jojo" P. Calderon'>
        <h3><span class="officer-eagle">Eagle</span>Rich Nicollie Z. Torrefiel</h3>
        <p class="role">Information Secretary</p>
        <p class="speech">"Service without borders."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer3.jpg" alt="Erwin J. Torrefiel">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Joey A. Valencia</h4>
        <p>Finance Secretary</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer3.jpg" alt="Erwin J. Torrefiel">
        <h3><span class="officer-eagle">Eagle</span>Joey A. Valencia</h3>
        <p class="role">Finance Secretary</p>
        <p class="speech">"Organization is key to success."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer4.jpg" alt="Cesar Yamuta">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Imrushsharif G. Imam</h4>
        <p>National Treasurer</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer4.jpg" alt="Cesar Yamuta">
        <h3><span class="officer-eagle">Eagle</span>Imrushsharif G. Imam</h3>
        <p class="role">National Treasurer</p>
        <p class="speech">"Managing resources responsibly."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer5.jpg" alt="Mgen Romeo V. Calizo PA (RET.)">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Pete Gerald Javier</h4>
        <p>Auditor General</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer5.jpg" alt="Mgen Romeo V. Calizo PA (RET.)">
        <h3><span class="officer-eagle">Eagle</span>Pete Gerald Javier</h3>
        <p class="role">Auditor General</p>
        <p class="speech">"Transparency builds trust."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

<div class="officer-card is-center" onclick="togglePanel(this)">
        <img src="officers/officer6.jpg" alt="Jocil B. Labial">
        <div class="officer-info">
    <h4><span class="officer-eagle">Eagle</span>Jose Philip F. Calderon, JR.</h4>
        <p>National Comptroller</p>
        </div>
        <div class="detail-panel">
    <img src="officers/officer6.jpg" alt="Jocil B. Labial">
    <h3><span class="officer-eagle">Eagle</span>Jose Philip F. Calderon, JR.</h3>
        <p class="role">National Comptroller</p>
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
