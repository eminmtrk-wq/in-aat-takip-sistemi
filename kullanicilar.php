<?php
/*
 * kullanicilar.php — Ekip ve atamalar
 *
 *   Yönetici: tüm kullanıcıları görür; proje müdürü veya şef ekler; herkesi her inşaata atayabilir.
 *   Proje müdürü: kendi inşaatının ekibini ve henüz atanmamış şefleri görür;
 *                 yeni şef ekler (kendi inşaatına atanmış olarak);
 *                 atanmamış bir şefi kendi inşaatına atar veya kendi şefini görevden alır.
 *
 * Formlar POST ile bu sayfaya gönderilir; hangi işlemin yapılacağı gizli "islem" alanından anlaşılır.
 */
require_once 'functions.php';
yetkiGerekli(ekipYonetebilirMi());

$ben = aktifKullanici();

// Yeni kişi formunun varsayılan değerleri
$yeni = [
    'ad_soyad'      => '',
    'kullanici_adi' => '',
    'sifre'         => '',
    'rol'           => 'sef',
    'insaat'        => mudurMu() ? (string) $ben['insaat'] : '',
];
$hatalar = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $islem = $_POST['islem'] ?? '';

    if ($islem === 'ekle') {
        foreach ($yeni as $alan => $varsayilan) {
            $yeni[$alan] = trim($_POST[$alan] ?? '');
        }
        // Müdür sadece şef ekleyebilir ve şef otomatik olarak müdürün inşaatına atanır
        if (mudurMu()) {
            $yeni['rol'] = 'sef';
            $yeni['insaat'] = (string) $ben['insaat'];
        }

        $hatalar = kullaniciDogrula($yeni);
        if (count($hatalar) === 0) {
            kullaniciEkle($yeni);
            mesajAyarla('basari', $yeni['ad_soyad'] . ' (' . rolAdi($yeni['rol']) . ') eklendi.'
                . ($yeni['insaat'] !== '' ? ' İnşaat: ' . $yeni['insaat'] : ' Henüz bir inşaata atanmadı.'));
            header('Location: kullanicilar.php');
            exit;
        }
    } elseif ($islem === 'ata') {
        $hedef = kullaniciBul((int) ($_POST['kullanici_id'] ?? 0));
        $yeniInsaat = trim($_POST['insaat'] ?? '');

        // Müdür sadece kendi inşaatına atayabilir veya görevden alabilir
        if (mudurMu() && $yeniInsaat !== '' && $yeniInsaat !== $ben['insaat']) {
            $hedef = null;
        }

        if ($hedef === null || !atamaYapabilirMi($hedef)) {
            mesajAyarla('hata', 'Bu kişinin atamasını değiştirme yetkiniz yok.');
        } elseif ($hedef['rol'] === 'proje_muduru' && $yeniInsaat === '') {
            mesajAyarla('hata', 'Proje müdürü bir inşaata atanmış olmalıdır.');
        } elseif (mb_strlen($yeniInsaat) > 150) {
            mesajAyarla('hata', 'İnşaat adı en fazla 150 karakter olabilir.');
        } else {
            kullaniciAta((int) $hedef['id'], $yeniInsaat);
            mesajAyarla('basari', $yeniInsaat === ''
                ? $hedef['ad_soyad'] . ' görevden alındı.'
                : $hedef['ad_soyad'] . ', ' . $yeniInsaat . ' inşaatına atandı.');
        }
        header('Location: kullanicilar.php');
        exit;
    }
}

$ekip = ekipListesi();
$insaatlar = insaatAdlari();

$sayfaBasligi = 'Ekip';
require 'header.php';
?>

<section class="tanitim">
    <?php if (yoneticiMi()): ?>
        <h1>Ekip ve atamalar</h1>
        <p>Proje müdürü ve şantiye şefi ekleyin, kimin hangi inşaatta çalıştığını belirleyin.</p>
    <?php else: ?>
        <h1>Ekibim</h1>
        <p><b><?= guvenli($ben['insaat'] ?? 'Atanmamış') ?></b> inşaatının ekibi. Yeni şef ekleyebilir, boştaki şefleri inşaatınıza atayabilirsiniz.</p>
    <?php endif; ?>
</section>

<?php mesajGoster(); ?>

<?php if (mudurMu() && $ben['insaat'] === null): ?>
    <div class="uyari uyari-sari">Henüz bir inşaata atanmadınız. Yönetici sizi bir inşaata atadığında ekibinizi buradan yönetebileceksiniz.</div>
<?php else: ?>

<div class="ekip-duzen">
    <!-- Ekip listesi -->
    <section class="tablo-kutu">
        <h2>Kişiler (<?= count($ekip) ?>)</h2>
        <div class="tablo-kaydir">
            <table class="tablo">
                <thead>
                    <tr><th>Ad soyad</th><th>Kullanıcı adı</th><th>Görev</th><th>İnşaat</th><th>Atama</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($ekip as $kisi): ?>
                        <tr class="<?= $kisi['insaat'] === null && $kisi['rol'] !== 'yonetici' ? 'atanmamis' : '' ?>">
                            <td><b><?= guvenli($kisi['ad_soyad']) ?></b><?= (int) $kisi['id'] === $ben['id'] ? ' <small>(siz)</small>' : '' ?></td>
                            <td><?= guvenli($kisi['kullanici_adi']) ?></td>
                            <td><span class="rozet <?= $kisi['rol'] === 'proje_muduru' ? 'rozet-turuncu' : ($kisi['rol'] === 'yonetici' ? 'rozet-koyu' : 'rozet-gri') ?>"><?= rolAdi($kisi['rol']) ?></span></td>
                            <td>
                                <?php if ($kisi['rol'] === 'yonetici'): ?>
                                    <small>Tüm inşaatlar</small>
                                <?php elseif ($kisi['insaat'] === null): ?>
                                    <span class="sure-gecikti">Atanmadı</span>
                                <?php else: ?>
                                    <a href="insaat.php?ad=<?= urlencode($kisi['insaat']) ?>"><?= guvenli($kisi['insaat']) ?></a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!atamaYapabilirMi($kisi)): ?>
                                    <small>—</small>
                                <?php elseif (yoneticiMi()): ?>
                                    <!-- Yönetici: listeden herhangi bir inşaatı seçip kaydeder -->
                                    <form method="post" action="kullanicilar.php" class="atama-formu">
                                        <input type="hidden" name="islem" value="ata">
                                        <input type="hidden" name="kullanici_id" value="<?= (int) $kisi['id'] ?>">
                                        <select name="insaat" aria-label="<?= guvenli($kisi['ad_soyad']) ?> için inşaat">
                                            <?php if ($kisi['rol'] === 'sef'): ?>
                                                <option value="">Atanmadı</option>
                                            <?php endif; ?>
                                            <?php foreach ($insaatlar as $insaat): ?>
                                                <option value="<?= guvenli($insaat) ?>" <?= $insaat === $kisi['insaat'] ? 'selected' : '' ?>><?= guvenli($insaat) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="buton kucuk">Kaydet</button>
                                    </form>
                                <?php elseif ($kisi['insaat'] === null): ?>
                                    <!-- Müdür: boştaki şefi kendi inşaatına atar -->
                                    <form method="post" action="kullanicilar.php">
                                        <input type="hidden" name="islem" value="ata">
                                        <input type="hidden" name="kullanici_id" value="<?= (int) $kisi['id'] ?>">
                                        <input type="hidden" name="insaat" value="<?= guvenli($ben['insaat']) ?>">
                                        <button type="submit" class="buton kucuk">Ekibime ata</button>
                                    </form>
                                <?php else: ?>
                                    <!-- Müdür: kendi şefini görevden alır -->
                                    <form method="post" action="kullanicilar.php" onsubmit="return confirm('<?= guvenli($kisi['ad_soyad']) ?> bu inşaattan görevden alınsın mı?');">
                                        <input type="hidden" name="islem" value="ata">
                                        <input type="hidden" name="kullanici_id" value="<?= (int) $kisi['id'] ?>">
                                        <input type="hidden" name="insaat" value="">
                                        <button type="submit" class="buton tehlike">Görevden al</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Yeni kişi ekleme formu -->
    <section class="form-kutu yeni-kisi">
        <h2><?= yoneticiMi() ? 'Yeni kişi ekle' : 'Yeni şef ekle' ?></h2>

        <?php if (count($hatalar) > 0): ?>
            <div class="uyari uyari-hata">
                <ul>
                    <?php foreach ($hatalar as $hata): ?>
                        <li><?= guvenli($hata) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="kullanicilar.php" novalidate>
            <input type="hidden" name="islem" value="ekle">
            <label>Ad soyad
                <input type="text" name="ad_soyad" maxlength="100" value="<?= guvenli($yeni['ad_soyad']) ?>" placeholder="Örn: Burak Şahin">
            </label>
            <label>Kullanıcı adı
                <input type="text" name="kullanici_adi" maxlength="30" value="<?= guvenli($yeni['kullanici_adi']) ?>" placeholder="Örn: burak" autocomplete="off">
            </label>
            <label>Şifre (en az 6 karakter)
                <input type="password" name="sifre" autocomplete="new-password">
            </label>

            <?php if (yoneticiMi()): ?>
                <label>Görev
                    <select name="rol">
                        <option value="sef" <?= $yeni['rol'] === 'sef' ? 'selected' : '' ?>>Şantiye Şefi</option>
                        <option value="proje_muduru" <?= $yeni['rol'] === 'proje_muduru' ? 'selected' : '' ?>>Proje Müdürü</option>
                    </select>
                </label>
                <label>İnşaat
                    <!-- datalist: listeden seçilebilir veya yeni bir inşaat adı yazılabilir -->
                    <input type="text" name="insaat" list="insaat-listesi" value="<?= guvenli($yeni['insaat']) ?>" placeholder="Seçin veya yeni inşaat adı yazın">
                    <datalist id="insaat-listesi">
                        <?php foreach ($insaatlar as $insaat): ?>
                            <option value="<?= guvenli($insaat) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </label>
                <p class="bilgi">Şef için inşaatı boş bırakırsanız daha sonra atayabilirsiniz.</p>
            <?php else: ?>
                <label>İnşaat
                    <input type="text" value="<?= guvenli($ben['insaat']) ?>" readonly>
                </label>
                <p class="bilgi">Eklediğiniz şef otomatik olarak sizin inşaatınıza atanır.</p>
            <?php endif; ?>

            <button type="submit" class="buton"><?= yoneticiMi() ? 'Kişiyi ekle' : 'Şefi ekle' ?></button>
        </form>
    </section>
</div>

<?php endif; ?>

<?php require 'footer.php'; ?>
