<?php
/*
 * rapor.php — Yazdırılabilir rapor sayfası
 * Tarayıcının "Yazdır" penceresinde hedef olarak "PDF olarak kaydet" seçilerek PDF elde edilir.
 * Ana sayfadaki filtreler adres çubuğu (GET) üzerinden buraya da aktarılır.
 */
require_once 'functions.php';
girisGerekli();

$filtre = filtreleriOku();
$kayitlar = kayitlariFiltrele($filtre);

// Rapor toplamları
$toplamButce = 0;
$toplamHarcanan = 0;
$toplamIlerleme = 0;
foreach ($kayitlar as $kayit) {
    $toplamButce    += (float) $kayit['butce'];
    $toplamHarcanan += (float) $kayit['harcanan'];
    $toplamIlerleme += (int) $kayit['ilerleme'];
}
$ortalamaIlerleme = count($kayitlar) > 0 ? round($toplamIlerleme / count($kayitlar)) : 0;

// Raporun başlığında hangi filtrelerin seçildiğini yaz
$filtreAciklamasi = [];
if ($filtre['insaat'] !== '')   { $filtreAciklamasi[] = 'İnşaat: ' . $filtre['insaat']; }
if ($filtre['bolge'] !== '')    { $filtreAciklamasi[] = 'Bölge: ' . $filtre['bolge']; }
if ($filtre['kategori'] !== '') { $filtreAciklamasi[] = 'Kategori: ' . $filtre['kategori']; }
if ($filtre['durum'] !== '')    { $filtreAciklamasi[] = 'Durum: ' . $filtre['durum']; }
if ($filtre['ara'] !== '')      { $filtreAciklamasi[] = 'Arama: "' . $filtre['ara'] . '"'; }
if ($filtre['geciken'])         { $filtreAciklamasi[] = 'Sadece gecikenler'; }

$sayfaBasligi = 'Rapor';
require 'header.php';
?>

<div class="rapor-araclari yazdirma">
    <a href="<?= guvenli(filtreliAdres('index.php', $filtre)) ?>" class="buton ikincil kucuk">← Listeye dön</a>
    <button type="button" class="buton" onclick="window.print()">Yazdır / PDF olarak kaydet</button>
    <span class="bilgi">İpucu: Yazdır penceresinde hedef olarak "PDF olarak kaydet"i seçin.</span>
</div>

<section class="rapor">
    <h1>İş Kalemleri Raporu</h1>
    <p class="bilgi">
        Rapor tarihi: <?= date('d.m.Y H:i') ?> · Hazırlayan: <?= guvenli(aktifKullanici()['ad_soyad']) ?><br>
        Filtre: <?= count($filtreAciklamasi) > 0 ? guvenli(implode(' · ', $filtreAciklamasi)) : 'Tüm kayıtlar' ?>
        · Sıralama: <?= guvenli(SIRALAMALAR[$filtre['sirala']]['ad']) ?>
    </p>

    <table class="rapor-ozet">
        <tr>
            <td>İş kalemi<br><b><?= count($kayitlar) ?></b></td>
            <td>Tamamlanan<br><b><?= durumSay($kayitlar, 'Tamamlandı') ?></b></td>
            <td>Geciken<br><b><?= gecikenSay($kayitlar) ?></b></td>
            <td>Ort. ilerleme<br><b>%<?= $ortalamaIlerleme ?></b></td>
            <td>Bütçe<br><b><?= paraGoster($toplamButce) ?></b></td>
            <td>Harcanan<br><b><?= paraGoster($toplamHarcanan) ?></b> (%<?= butceKullanimYuzdesi($toplamButce, $toplamHarcanan) ?>)</td>
        </tr>
    </table>

    <table class="tablo rapor-tablo">
        <thead>
            <tr><th>#</th><th>İş Kalemi</th><th>İnşaat / Konum</th><th>Kategori</th><th>Sorumlu</th><th>Durum</th><th>%</th><th>Tarih</th><th>Harcanan / Bütçe</th></tr>
        </thead>
        <tbody>
            <?php $sira = 1; ?>
            <?php foreach ($kayitlar as $kayit): ?>
                <tr>
                    <td><?= $sira++ ?></td>
                    <td><?= guvenli($kayit['baslik']) ?></td>
                    <td><?= guvenli($kayit['insaat']) ?><br><small><?= guvenli($kayit['bolge']) ?> — <?= guvenli($kayit['konum']) ?></small></td>
                    <td><?= guvenli($kayit['kategori']) ?></td>
                    <td><?= guvenli($kayit['sorumlu']) ?></td>
                    <td><?= guvenli($kayit['durum']) ?></td>
                    <td><?= (int) $kayit['ilerleme'] ?></td>
                    <td class="<?= gecikmeVarMi($kayit) ? 'sure-gecikti' : '' ?>">
                        <?= tarihGoster($kayit['baslangic']) ?> → <?= tarihGoster($kayit['bitis']) ?><br><small><?= sureMetni($kayit) ?></small>
                    </td>
                    <td class="<?= butceAsildiMi((float) $kayit['butce'], (float) $kayit['harcanan']) ? 'sure-gecikti' : '' ?>">
                        <?= paraGoster($kayit['harcanan']) ?><br><small>/ <?= paraGoster($kayit['butce']) ?></small>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (count($kayitlar) === 0): ?>
        <p class="bos">Bu ölçütlere uygun kayıt yok.</p>
    <?php endif; ?>
</section>

<?php require 'footer.php'; ?>
