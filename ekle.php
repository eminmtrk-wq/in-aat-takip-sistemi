<?php
/*
 * ekle.php — Yeni iş kalemi ekleme ve düzenleme
 *
 * İki modda çalışır:
 *   ekle.php        → Boş form, yeni kayıt ekler (yönetici veya proje müdürü).
 *   ekle.php?id=3   → 3 numaralı kaydın bilgileri formda dolu gelir, kaydedince güncellenir.
 *                     Müdür ve şef sadece kendi inşaatındaki kayıtları düzenleyebilir.
 *
 * Sayfanın altında kayıtlar tablo halinde listelenir; her satırda "Düzenle" ve "Sil" butonları vardır.
 */
require_once 'functions.php';
girisGerekli();

// Formun varsayılan (boş) değerleri
$veri = [
    'baslik'    => '',
    'insaat'    => '',
    'bolge'     => '',
    'konum'     => '',
    'kategori'  => '',
    'sorumlu'   => '',
    'ilerleme'  => '0',
    'durum'     => 'Planlandı',
    'baslangic' => date('Y-m-d'), // Varsayılan başlangıç: bugün
    'bitis'     => '',
    'butce'     => '0',
    'harcanan'  => '0',
    'aciklama'  => '',
];
$hatalar = [];

// Düzenlenecek kaydın id'si: ilk açılışta adresten (GET), kaydederken gizli alandan (POST) gelir.
$duzenlenenId = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$duzenlemeModu = false;

if ($duzenlenenId > 0) {
    $mevcutKayit = kayitBul($duzenlenenId);
    if ($mevcutKayit === null || !kayitErisimVarMi($mevcutKayit)) {
        mesajAyarla('hata', 'Düzenlenecek kayıt bulunamadı veya bu kaydı düzenleme yetkiniz yok.');
        header('Location: index.php');
        exit;
    }
    $duzenlemeModu = true;
    // Formu mevcut kaydın bilgileriyle doldur
    foreach ($veri as $alan => $varsayilan) {
        $veri[$alan] = (string) $mevcutKayit[$alan];
    }
} else {
    // Yeni iş kalemi eklemek yönetici ve proje müdürüne açık
    yetkiGerekli(isYonetebilirMi());
    if (mudurMu() && aktifKullanici()['insaat'] === null) {
        mesajAyarla('hata', 'Henüz bir inşaata atanmadığınız için iş kalemi ekleyemezsiniz.');
        header('Location: index.php');
        exit;
    }
    // Müdürün yeni kaydında inşaat alanı kendi inşaatıyla dolu gelsin
    if (mudurMu()) {
        $veri['insaat'] = aktifKullanici()['insaat'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Formdan gelen her alanı al ve baştaki/sondaki boşlukları temizle
    foreach ($veri as $alan => $varsayilan) {
        $veri[$alan] = trim($_POST[$alan] ?? '');
    }

    // Müdür veya şef, formu değiştirip başka bir inşaat adı gönderse bile kendi inşaatı kullanılır
    if (!yoneticiMi()) {
        $veri['insaat'] = aktifKullanici()['insaat'];
    }

    $hatalar = kayitDogrula($veri);

    if (count($hatalar) === 0) {
        if ($duzenlemeModu) {
            kayitGuncelle($duzenlenenId, $veri);
            mesajAyarla('basari', '"' . $veri['baslik'] . '" iş kalemi güncellendi.');
        } else {
            kayitEkle($veri);
            mesajAyarla('basari', '"' . $veri['baslik'] . '" iş kalemi başarıyla eklendi.');
        }
        header('Location: ekle.php' . ($duzenlemeModu && !isYonetebilirMi() ? '?id=' . $duzenlenenId : ''));
        exit;
    }
}

$tumKayitlar = tumKayitlar();
$sayfaBasligi = $duzenlemeModu ? 'İş Kalemini Düzenle' : 'Yeni İş Ekle';
require 'header.php';
?>

<div style="margin-top: 24px;"><?php mesajGoster(); ?></div>

<section class="form-kutu">
    <h1><?= $duzenlemeModu ? 'İş Kalemini Düzenle' : 'Yeni İş Kalemi Ekle' ?></h1>
    <p class="bilgi">* işaretli alanlar zorunludur.</p>

    <?php if (count($hatalar) > 0): ?>
        <div class="uyari uyari-hata">
            <strong>Kayıt kaydedilemedi. Lütfen şu hataları düzeltin:</strong>
            <ul>
                <?php foreach ($hatalar as $hata): ?>
                    <li><?= guvenli($hata) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- novalidate: tarayıcı kontrolünü kapatır, böylece doğrulamanın PHP tarafında çalıştığı görülebilir -->
    <form method="post" action="ekle.php" novalidate>
        <?php if ($duzenlemeModu): ?>
            <!-- Hangi kaydın güncelleneceğini PHP'ye bildiren gizli alan -->
            <input type="hidden" name="id" value="<?= $duzenlenenId ?>">
        <?php endif; ?>

        <label>İş kalemi adı *
            <input type="text" name="baslik" maxlength="100" value="<?= guvenli($veri['baslik']) ?>" placeholder="Örn: Çatı izolasyonu">
        </label>

        <label>Hangi inşaat (proje adı) *
            <input type="text" name="insaat" value="<?= guvenli($veri['insaat']) ?>" placeholder="Örn: Yıldız Konutları A Blok"
                <?= yoneticiMi() ? '' : 'readonly' ?>>
        </label>

        <div class="iki-sutun">
            <label>Bölge *
                <select name="bolge">
                    <option value="">Seçiniz</option>
                    <?php foreach (BOLGELER as $bolge): ?>
                        <option value="<?= guvenli($bolge) ?>" <?= $bolge === $veri['bolge'] ? 'selected' : '' ?>><?= guvenli($bolge) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Konum (il / ilçe / adres) *
                <input type="text" name="konum" value="<?= guvenli($veri['konum']) ?>" placeholder="Örn: İzmir / Urla, İskele Mah.">
            </label>
        </div>

        <div class="iki-sutun">
            <label>Kategori *
                <select name="kategori">
                    <option value="">Seçiniz</option>
                    <?php foreach (KATEGORILER as $kategori): ?>
                        <option value="<?= guvenli($kategori) ?>" <?= $kategori === $veri['kategori'] ? 'selected' : '' ?>><?= guvenli($kategori) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Durum *
                <select name="durum">
                    <?php foreach (DURUMLAR as $durum): ?>
                        <option value="<?= guvenli($durum) ?>" <?= $durum === $veri['durum'] ? 'selected' : '' ?>><?= guvenli($durum) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="iki-sutun">
            <label>Sorumlu kişi / firma *
                <input type="text" name="sorumlu" value="<?= guvenli($veri['sorumlu']) ?>" placeholder="Örn: Ahmet Yılmaz">
            </label>

            <label>İlerleme (%) *
                <input type="number" name="ilerleme" min="0" max="100" value="<?= guvenli($veri['ilerleme']) ?>">
            </label>
        </div>

        <div class="iki-sutun">
            <label>Başlangıç tarihi *
                <input type="date" name="baslangic" value="<?= guvenli($veri['baslangic']) ?>">
            </label>

            <label>Planlanan bitiş tarihi *
                <input type="date" name="bitis" value="<?= guvenli($veri['bitis']) ?>">
            </label>
        </div>

        <div class="iki-sutun">
            <label>Planlanan bütçe (₺) *
                <input type="text" inputmode="decimal" name="butce" value="<?= guvenli($veri['butce']) ?>" placeholder="Örn: 150000">
            </label>

            <label>Harcanan tutar (₺) *
                <input type="text" inputmode="decimal" name="harcanan" value="<?= guvenli($veri['harcanan']) ?>" placeholder="Örn: 42500,50">
            </label>
        </div>

        <label>Açıklama
            <textarea name="aciklama" rows="3" placeholder="İsteğe bağlı not"><?= guvenli($veri['aciklama']) ?></textarea>
        </label>

        <div class="butonlar">
            <button type="submit" class="buton"><?= $duzenlemeModu ? 'Değişiklikleri Kaydet' : 'Kaydet' ?></button>
            <?php if ($duzenlemeModu): ?>
                <a href="detay.php?id=<?= $duzenlenenId ?>" class="buton ikincil">Detaya Git</a>
            <?php endif; ?>
            <a href="<?= isYonetebilirMi() ? 'ekle.php' : 'index.php' ?>" class="buton ikincil"><?= $duzenlemeModu ? 'Düzenlemeden Çık' : 'Temizle' ?></a>
        </div>
    </form>
</section>

<!-- Kayıtlar tablosu: Düzenle ve Sil butonları -->
<section class="tablo-kutu">
    <h2>Kayıtlı İş Kalemleri (<?= count($tumKayitlar) ?>)</h2>

    <?php if (count($tumKayitlar) === 0): ?>
        <div class="bos">Henüz kayıt yok.</div>
    <?php else: ?>
        <div class="tablo-kaydir">
            <table class="tablo">
                <thead>
                    <tr>
                        <th>İş Kalemi</th>
                        <th>İnşaat</th>
                        <th>Bölge / Konum</th>
                        <th>Durum</th>
                        <th>%</th>
                        <th>Bitiş</th>
                        <th>Bütçe</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tumKayitlar as $kayit): ?>
                        <tr class="<?= (int) $kayit['id'] === $duzenlenenId ? 'secili' : '' ?>">
                            <td><a href="detay.php?id=<?= (int) $kayit['id'] ?>"><?= guvenli($kayit['baslik']) ?></a></td>
                            <td><?= guvenli($kayit['insaat']) ?></td>
                            <td><?= guvenli($kayit['bolge']) ?><br><small><?= guvenli($kayit['konum']) ?></small></td>
                            <td><span class="rozet <?= durumSinifi($kayit['durum']) ?>"><?= guvenli($kayit['durum']) ?></span></td>
                            <td><?= (int) $kayit['ilerleme'] ?></td>
                            <td class="<?= gecikmeVarMi($kayit) ? 'sure-gecikti' : '' ?>">
                                <?= tarihGoster($kayit['bitis']) ?>
                                <?= gecikmeVarMi($kayit) ? '<br><small>⚠ ' . sureMetni($kayit) . '</small>' : '' ?>
                            </td>
                            <td class="<?= butceAsildiMi((float) $kayit['butce'], (float) $kayit['harcanan']) ? 'sure-gecikti' : '' ?>">
                                %<?= butceKullanimYuzdesi((float) $kayit['butce'], (float) $kayit['harcanan']) ?>
                                <br><small><?= paraGoster($kayit['butce']) ?></small>
                            </td>
                            <td class="islemler">
                                <a href="ekle.php?id=<?= (int) $kayit['id'] ?>" class="buton kucuk">Düzenle</a>
                                <?php if (isYonetebilirMi()): ?>
                                    <form method="post" action="sil.php" onsubmit="return confirm('Bu iş kalemi, notları ve fotoğraflarıyla birlikte silinsin mi?');">
                                        <input type="hidden" name="id" value="<?= (int) $kayit['id'] ?>">
                                        <input type="hidden" name="geri" value="ekle.php">
                                        <button type="submit" class="buton tehlike">Sil</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require 'footer.php'; ?>
