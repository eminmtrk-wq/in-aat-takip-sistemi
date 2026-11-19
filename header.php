<?php
// header.php — Tüm sayfalarda ortak üst kısım ve menü.
// $sayfaBasligi değişkeni, bu dosyayı çağıran sayfada tanımlanır.
$kullanici = aktifKullanici();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= guvenli($sayfaBasligi) ?> | İnşaat Takip Sistemi</title>
    <!-- ?v=... : dosya her değiştiğinde adres de değişir, tarayıcı eski CSS'i önbellekten kullanmaz -->
    <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
</head>
<body>
<header class="ust">
    <div class="kapsayici ust-icerik">
        <a href="index.php" class="logo">
            <!-- Kule vinç çizimi -->
            <svg class="logo-isaret" viewBox="0 0 40 40" aria-hidden="true">
                <path d="M9 37V9M15 37V9M9 9h28M9 9l6 6M15 9l-6 6M9 15h6M9 15l6 6M15 15l-6 6M9 21h6M9 21l6 6M15 21l-6 6M9 27h6M9 27l6 6M15 27l-6 6M9 33h6M12 9V3M12 3l25 6M12 3L3 9h6M31 9v10" />
                <rect x="28" y="19" width="6" height="5" />
            </svg>
            <span>İnşaat Takip</span>
        </a>
        <?php if ($kullanici !== null): ?>
            <nav>
                <?php $aktifSayfa = basename($_SERVER['PHP_SELF']); ?>
                <a href="index.php" <?= $aktifSayfa === 'index.php' ? 'aria-current="page"' : '' ?>>İş kalemleri</a>
                <a href="insaatlar.php" <?= in_array($aktifSayfa, ['insaatlar.php', 'insaat.php'], true) ? 'aria-current="page"' : '' ?>>İnşaatlar</a>
                <a href="rapor.php" <?= $aktifSayfa === 'rapor.php' ? 'aria-current="page"' : '' ?>>Rapor</a>
                <?php if (ekipYonetebilirMi()): ?>
                    <a href="kullanicilar.php" <?= $aktifSayfa === 'kullanicilar.php' ? 'aria-current="page"' : '' ?>>Ekip</a>
                <?php endif; ?>
                <?php if (isYonetebilirMi()): ?>
                    <a href="ekle.php" class="buton">Yeni iş ekle</a>
                <?php endif; ?>
                <span class="kullanici-bilgi">
                    <?= guvenli($kullanici['ad_soyad']) ?> <small>(<?= rolAdi($kullanici['rol']) ?>)</small>
                    <a href="cikis.php" class="cikis">Çıkış</a>
                </span>
            </nav>
        <?php endif; ?>
    </div>
</header>
<main class="kapsayici">
