<?php
/*
 * giris.php — Kullanıcı girişi
 * Kullanıcı adı ve şifre POST ile gönderilir, girisYap() (yetki.php) ile kontrol edilir.
 */
require_once 'functions.php';

// Zaten giriş yapmışsa ana sayfaya gönder
if (aktifKullanici() !== null) {
    header('Location: index.php');
    exit;
}

$kullaniciAdi = '';
$hata = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kullaniciAdi = trim($_POST['kullanici_adi'] ?? '');
    $sifre = $_POST['sifre'] ?? '';

    if ($kullaniciAdi === '' || $sifre === '') {
        $hata = 'Kullanıcı adı ve şifre boş bırakılamaz.';
    } elseif (girisYap($kullaniciAdi, $sifre)) {
        mesajAyarla('basari', 'Hoş geldiniz, ' . aktifKullanici()['ad_soyad'] . '.');
        header('Location: index.php');
        exit;
    } else {
        // Hangisinin yanlış olduğunu söylemiyoruz (güvenlik için)
        $hata = 'Kullanıcı adı veya şifre hatalı.';
    }
}

$sayfaBasligi = 'Giriş';
require 'header.php';
?>

<div class="giris-sahne">
    <!-- Sol: pafta (mavi kopya) görünümlü tanıtım alanı -->
    <section class="giris-pafta">
        <h1>Şantiyedeki her iş, tek bir pafta üzerinde.</h1>
        <p>İş kalemlerini, ilerlemeyi, bütçeyi ve şantiye defterini birlikte takip edin. Geciken işler kendiliğinden öne çıkar.</p>
        <dl class="pafta-kunye">
            <div><dt>Proje</dt><dd>İnşaat Takip Sistemi</dd></div>
            <div><dt>Ders</dt><dd>İnternet Programcılığı I</dd></div>
            <div><dt>Ölçek</dt><dd>1 : 1</dd></div>
        </dl>
    </section>

    <!-- Sağ: giriş formu -->
    <section class="form-kutu giris-kutu">
        <h2>Giriş yap</h2>
        <?php mesajGoster(); ?>
        <?php if ($hata !== ''): ?>
            <div class="uyari uyari-hata"><?= guvenli($hata) ?></div>
        <?php endif; ?>

        <form method="post" action="giris.php" novalidate>
            <label>Kullanıcı adı
                <input type="text" name="kullanici_adi" value="<?= guvenli($kullaniciAdi) ?>" autocomplete="username" autofocus>
            </label>
            <label>Şifre
                <input type="password" name="sifre" autocomplete="current-password">
            </label>
            <button type="submit" class="buton">Giriş yap</button>
        </form>

        <div class="deneme-hesap">
            <p>Deneme hesapları</p>
            <table>
                <tr><td><b>admin</b> / admin123</td><td>Yönetici, tüm işlemler</td></tr>
                <tr><td><b>selin</b> / mudur123</td><td>Proje müdürü, Yıldız Konutları A Blok</td></tr>
                <tr><td><b>mehmet</b> / sef123</td><td>Şantiye şefi, Yıldız Konutları A Blok</td></tr>
                <tr><td><b>burak</b> / sef123</td><td>Şantiye şefi, henüz atanmamış</td></tr>
            </table>
        </div>
    </section>
</div>

<?php require 'footer.php'; ?>
