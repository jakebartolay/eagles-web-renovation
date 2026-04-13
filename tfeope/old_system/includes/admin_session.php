<?php
if (session_status() === PHP_SESSION_NONE) {
  session_name('EAGLES_ADMIN');
  session_start();
}
