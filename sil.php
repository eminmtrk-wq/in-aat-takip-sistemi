<?php
/*
 * sil.php — Kayıt silme (yönetici veya kendi inşaatında proje müdürü)
 * Sadece POST isteğini kabul eder. Silinecek kaydın id'si gizli form alanından gelir.
 * İşlem bitince silme butonuna basılan sayfaya (index.php veya ekle.php) geri döner.
 */
require_once 'functions.php';
yetkiGerekli(isYonetebilirMi());

// Sayfa adres çubuğundan (GET) açılırsa hiçbir şey silme
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Geri dönülecek sayfa: sadece izin verilen sayfalardan biri olabilir
$geri = $_POST['geri'] ?? 'index.php';
if (!in_array($geri, ['index.php', 'ekle.php'], true)) {
    $geri = 'index.php';
}

$id = (int) ($_POST['id'] ?? 0);
$kayit = kayitBul($id);

// Müdür sadece kendi inşaatındaki kaydı silebilir
if ($kayit !== null && !kayitErisimVarMi($kayit)) {
    mesajAyarla('hata', 'Bu kaydı silme yetkiniz yok.');
} elseif (kayitSil($id)) {
    mesajAyarla('basari', 'İş kalemi, notları ve fotoğraflarıyla birlikte silindi.');
} else {
    mesajAyarla('hata', 'Silinecek kayıt bulunamadı.');
}

header('Location: ' . $geri);
exit;
