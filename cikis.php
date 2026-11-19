<?php
/*
 * cikis.php — Oturumu kapatır ve giriş sayfasına döner.
 */
require_once 'functions.php';

$_SESSION = [];       // Oturumdaki tüm verileri temizle
session_destroy();    // Oturumu sunucudan sil

session_start();      // Çıkış mesajını gösterebilmek için yeni boş oturum
mesajAyarla('basari', 'Çıkış yaptınız.');

header('Location: giris.php');
exit;
