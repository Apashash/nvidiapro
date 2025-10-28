<?php
session_start();

// Générer un nouveau code CAPTCHA
$_SESSION['captcha'] = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

// Retourner le nouveau code
echo $_SESSION['captcha'];
?>