<div class="id-card">
  <div class="id-logo holo-logo">LOGO</div>
  <div class="id-name">John Doe</div>
</div>

<style>
.holo-logo {
  width: 150px; /* adjust size */
  height: auto;
  display: inline-block;

  /* Gradient overlay */
  background: linear-gradient(
    45deg,
    #ff0000,
    #ff9900,
    #ffff00,
    #00ff00,
    #00ffff,
    #0000ff,
    #9900ff,
    #ff00ff,
    #ff0000
  );
  background-size: 400% 400%;

  /* clip logo shape */
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;

  /* Animation for moving gradient */
  animation: holo-animation 5s linear infinite;
}

/* Gradient movement */
@keyframes holo-animation {
  0% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
  100% { background-position: 0% 50%; }
}
</style>