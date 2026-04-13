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
  <h1>Board Of Trustees</h1>
  <p>Meet the leaders guiding our organization</p>
</section>

<section class="org-chart">

  <!-- CHAIRMAN -->
  <div class="chart-level president">
    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer1.jpg" alt="Atty. Michael Florentino R. Dumlao III">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Atty. Michael Florentino R. Dumlao III</h4>
        <p>Chairman of the Board of Trustees</p>
      </div>
      <div class="detail-panel">
        <img src="officers/speech_jojo.jpg" alt="Atty. Michael Florentino R. Dumlao III">
        <h3>Atty. Michael Florentino R. Dumlao III</h3>
        <p class="role">Chairman of the Board of Trustees</p>
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
        <h4><span class="officer-eagle">Eagle</span>Lucio F. Ceniza, PHD</h4>
        <p>Chairman Emeritus</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer1.jpg" alt="Lucio F. Ceniza, PHD">
        <h3>Lucio F. Ceniza, PHD</h3>
        <p class="role">Chairman Emeritus</p>
        <p class="speech">"Unity begins within."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer2.jpg" alt='Jose "Jojo" P. Calderon'>
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Jose "Jojo" P. Calderon</h4>
        <p>Board of Trustees</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer2.jpg" alt='Jose "Jojo" P. Calderon'>
        <h3>Jose "Jojo" P. Calderon</h3>
        <p class="role">Board of Trustees</p>
        <p class="speech">"Service without borders."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer3.jpg" alt="Erwin J. Torrefiel">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Erwin J. Torrefiel</h4>
        <p>Board of Trustees</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer3.jpg" alt="Erwin J. Torrefiel">
        <h3>Erwin J. Torrefiel</h3>
        <p class="role">Board of Trustees</p>
        <p class="speech">"Organization is key to success."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer4.jpg" alt="Cesar Yamuta">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Cesar Yamuta</h4>
        <p>Board of Trustees</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer4.jpg" alt="Cesar Yamuta">
        <h3>Cesar Yamuta</h3>
        <p class="role">Board of Trustees</p>
        <p class="speech">"Managing resources responsibly."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer5.jpg" alt="Mgen Romeo V. Calizo PA (RET.)">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Mgen Romeo V. Calizo PA (RET.)</h4>
        <p>Board of Trustees</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer5.jpg" alt="Mgen Romeo V. Calizo PA (RET.)">
        <h3>Mgen Romeo V. Calizo PA (RET.)</h3>
        <p class="role">Board of Trustees</p>
        <p class="speech">"Transparency builds trust."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer6.jpg" alt="Jocil B. Labial">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Jocil B. Labial</h4>
        <p>Board of Trustees</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer6.jpg" alt="Jocil B. Labial">
        <h3>Jocil B. Labial</h3>
        <p class="role">Board of Trustees</p>
        <p class="speech">"Transparency builds trust."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

    <div class="officer-card" onclick="togglePanel(this)">
      <img src="officers/officer7.jpg" alt="Jaime P. Gellor, JR.">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Jaime P. Gellor, JR.</h4>
        <p>Board of Trustees</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer7.jpg" alt="Jaime P. Gellor, JR.">
        <h3>Jaime P. Gellor, JR.</h3>
        <p class="role">Board of Trustees</p>
        <p class="speech">"Transparency builds trust."</p>
        <span class="close" onclick="closePanel(event)">×</span>
      </div>
    </div>

    <!-- 9th card to complete 3–3–3 -->
    <div class="officer-card officer-card--tba" onclick="togglePanel(this)">
      <img src="officers/officer1.jpg" alt="Arnel N. Bautista">
      <div class="officer-info">
        <h4><span class="officer-eagle">Eagle</span>Arnel N. Bautista</h4>
        <p>Board of Trustees</p>
      </div>
      <div class="detail-panel">
        <img src="officers/officer1.jpg" alt="Arnel N. Bautista">
        <h3>Arnel N. Bautista</h3>
        <p class="role">Board of Trustees</p>
        <p class="speech">"Details will be posted soon."</p>
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
