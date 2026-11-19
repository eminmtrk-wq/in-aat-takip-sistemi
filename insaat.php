<?php
/*
 * insaat.php — Tek bir inşaatın özet sayfası
 * Adres: insaat.php?ad=Yıldız+Konutları+A+Blok
 * İnşaatın genel ilerlemesi, bütçesi, iş kalemleri ve son şantiye notları tek sayfada.
 */
require_once 'functions.php';
girisGerekli();

$insaatAdi = trim($_GET['ad'] ?? '');
$ozetler = insaatOzetleri($insaatAdi);

// insaatOzetleri() şefe sadece kendi inşaatını döndürdüğü için yetki kontrolü de burada sağlanmış olur
if ($insaatAdi === '' || count($ozetler) === 0) {
    mesajAyarla('hata', 'İnşaat bulunamadı veya görüntüleme yetkiniz yok.');
    header('Location: insaatlar.php');
    exit;
}
$ozet = $ozetler[0];

// Bu inşaata ait iş kalemleri (filtre dizisinin sadece "insaat" alanı dolu)
$filtre = ['ara' => '', 'kategori' => '', 'durum' => '', 'bolge' => '',
           'insaat' => $insaatAdi, 'geciken' => false, 'sirala' => 'bitis'];
$isler = kayitlariFiltrele($filtre);
$sonNotlar = insaatSonNotlari($insaatAdi);
$ekip = insaatEkibi($insaatAdi);

$butce    = (float) $ozet['toplam_butce'];
$harcanan = (float) $ozet['toplam_harcanan'];

$sayfaBasligi = $insaatAdi;
require 'header.php';
?>

<p class="geri-link"><a href="insaatlar.php">← Tüm inşaatlar</a></p>

<section class="tanitim">
    <h1><?= guvenli($insaatAdi) ?></h1>
    <p><?= guvenli($ozet['bolge']) ?> — <?= guvenli($ozet['konum']) ?> · <?= tarihGoster($ozet['ilk_baslangic']) ?> → <?= tarihGoster($ozet['son_bitis']) ?></p>
</section>

<section class="ozet">
    <div class="kart"><span class="sayi"><?= (int) $ozet['is_sayisi'] ?></span><span>İş Kalemi</span></div>
    <div class="kart yesil"><span class="sayi"><?= (int) $ozet['tamamlanan'] ?></span><span>Tamamlandı</span></div>
    <div class="kart turuncu"><span class="sayi">%<?= (int) $ozet['ortalama_ilerleme'] ?></span><span>Genel İlerleme</span></div>
    <div class="kart kirmizi"><span class="sayi"><?= (int) $ozet['geciken'] ?></span><span>Geciken</span></div>
    <div class="kart <?= butceAsildiMi($butce, $harcanan) ? 'kirmizi' : 'gri' ?>"><span class="sayi">%<?= butceKullanimYuzdesi($butce, $harcanan) ?></span><span>Bütçe Kullanımı</span></div>
</section>

<section class="detay-kutu">
    <p class="yuzde">Bütçe: <b><?= paraGoster($harcanan) ?></b> harcandı / <?= paraGoster($butce) ?> planlandı
        <span class="<?= butceAsildiMi($butce, $harcanan) ? 'sure-gecikti' : 'sure' ?>">
            <?= butceAsildiMi($butce, $harcanan) ? '⚠ ' . paraGoster($harcanan - $butce) . ' aşım' : 'Kalan: ' . paraGoster($butce - $harcanan) ?>
        </span>
    </p>
    <div class="ilerleme"><div class="ilerleme-dolu <?= butceAsildiMi($butce, $harcanan) ? 'kirmizi-dolu' : 'mavi-dolu' ?>" style="width: <?= min(butceKullanimYuzdesi($butce, $harcanan), 100) ?>%"></div></div>
    <div class="disa-aktar" style="margin-top: 12px;">
        <a href="<?= guvenli(filtreliAdres('rapor.php', $filtre)) ?>" class="buton ikincil kucuk">Bu inşaatın PDF raporu</a>
        <a href="<?= guvenli(filtreliAdres('disa_aktar.php', $filtre)) ?>" class="buton ikincil kucuk">Excel (CSV) indir</a>
    </div>
</section>

<section class="tablo-kutu">
    <div class="ekip-baslik">
        <h2>Ekip</h2>
        <?php if (ekipYonetebilirMi()): ?>
            <a href="kullanicilar.php" class="buton ikincil kucuk">Ekibi yönet</a>
        <?php endif; ?>
    </div>
    <?php if (count($ekip) === 0): ?>
        <p class="bilgi">Bu inşaata henüz proje müdürü veya şef atanmadı.</p>
    <?php else: ?>
        <ul class="ekip-listesi">
            <?php foreach ($ekip as $kisi): ?>
                <li>
                    <b><?= guvenli($kisi['ad_soyad']) ?></b>
                    <span class="rozet <?= $kisi['rol'] === 'proje_muduru' ? 'rozet-turuncu' : 'rozet-gri' ?>"><?= rolAdi($kisi['rol']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="tablo-kutu">
    <h2>İş Kalemleri</h2>
    <div class="tablo-kaydir">
        <table class="tablo">
            <thead>
                <tr><th>İş Kalemi</th><th>Kategori</th><th>Sorumlu</th><th>Durum</th><th>%</th><th>Bitiş</th><th>Harcanan / Bütçe</th></tr>
            </thead>
            <tbody>
                <?php foreach ($isler as $is): ?>
                    <tr>
                        <td><a href="detay.php?id=<?= (int) $is['id'] ?>"><?= guvenli($is['baslik']) ?></a></td>
                        <td><?= guvenli($is['kategori']) ?></td>
                        <td><?= guvenli($is['sorumlu']) ?></td>
                        <td><span class="rozet <?= durumSinifi($is['durum']) ?>"><?= guvenli($is['durum']) ?></span></td>
                        <td><?= (int) $is['ilerleme'] ?></td>
                        <td class="<?= gecikmeVarMi($is) ? 'sure-gecikti' : '' ?>"><?= tarihGoster($is['bitis']) ?><br><small><?= sureMetni($is) ?></small></td>
                        <td class="<?= butceAsildiMi((float) $is['butce'], (float) $is['harcanan']) ? 'sure-gecikti' : '' ?>">
                            <?= paraGoster($is['harcanan']) ?><br><small>/ <?= paraGoster($is['butce']) ?></small>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="tablo-kutu">
    <h2>Son şantiye notları</h2>
    <?php if (count($sonNotlar) === 0): ?>
        <p class="bilgi">Bu inşaat için henüz not yok.</p>
    <?php else: ?>
        <ul class="not-listesi">
            <?php foreach ($sonNotlar as $n): ?>
                <li>
                    <div class="not-ust">
                        <b><?= tarihGoster($n['tarih']) ?> — <a href="detay.php?id=<?= (int) $n['is_id'] ?>#defter"><?= guvenli($n['is_adi']) ?></a></b>
                        <span><?= (int) $n['isci_sayisi'] ?> işçi · <?= guvenli($n['hava']) ?></span>
                    </div>
                    <p><?= nl2br(guvenli($n['not_metni'])) ?></p>
                    <small>Yazan: <?= guvenli($n['yazan']) ?></small>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php require 'footer.php'; ?>
