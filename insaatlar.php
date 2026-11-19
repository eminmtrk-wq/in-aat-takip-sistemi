<?php
/*
 * insaatlar.php — Tüm inşaatların özet listesi
 * Her inşaat için iş sayısı, ortalama ilerleme, bütçe ve geciken iş sayısı gösterilir.
 * Veriler insaatOzetleri() fonksiyonundaki GROUP BY sorgusundan gelir.
 */
require_once 'functions.php';
girisGerekli();

$insaatlar = insaatOzetleri();

// Genel toplamlar (foreach ile)
$genelButce = 0;
$genelHarcanan = 0;
foreach ($insaatlar as $insaat) {
    $genelButce    += (float) $insaat['toplam_butce'];
    $genelHarcanan += (float) $insaat['toplam_harcanan'];
}

$sayfaBasligi = 'İnşaatlar';
require 'header.php';
?>

<section class="tanitim">
    <h1>İnşaatlar</h1>
    <p>Her inşaatın genel ilerlemesi, bütçe durumu ve geciken işleri.</p>
</section>

<?php mesajGoster(); ?>

<section class="ozet uclu">
    <div class="kart"><span class="sayi"><?= count($insaatlar) ?></span><span>İnşaat</span></div>
    <div class="kart"><span class="sayi kucuk-sayi"><?= paraGoster($genelButce) ?></span><span>Toplam Bütçe</span></div>
    <div class="kart <?= butceAsildiMi($genelButce, $genelHarcanan) ? 'kirmizi' : 'turuncu' ?>">
        <span class="sayi kucuk-sayi"><?= paraGoster($genelHarcanan) ?></span><span>Toplam Harcanan (%<?= butceKullanimYuzdesi($genelButce, $genelHarcanan) ?>)</span>
    </div>
</section>

<?php if (count($insaatlar) === 0): ?>
    <div class="bos">Henüz inşaat yok.</div>
<?php else: ?>
    <div class="liste">
        <?php foreach ($insaatlar as $insaat): ?>
            <?php
            $butce    = (float) $insaat['toplam_butce'];
            $harcanan = (float) $insaat['toplam_harcanan'];
            $asildi   = butceAsildiMi($butce, $harcanan);
            ?>
            <article class="is-karti <?= (int) $insaat['geciken'] > 0 ? 'gecikmis' : '' ?>">
                <div class="is-ust">
                    <h2><a href="insaat.php?ad=<?= urlencode($insaat['insaat']) ?>"><?= guvenli($insaat['insaat']) ?></a></h2>
                    <?php if ((int) $insaat['geciken'] > 0): ?>
                        <span class="rozet rozet-kirmizi"><?= (int) $insaat['geciken'] ?> geciken</span>
                    <?php endif; ?>
                </div>
                <ul class="detay">
                    <li><strong>Bölge / Konum:</strong> <?= guvenli($insaat['bolge']) ?> — <?= guvenli($insaat['konum']) ?></li>
                    <li><strong>İş kalemi:</strong> <?= (int) $insaat['tamamlanan'] ?> / <?= (int) $insaat['is_sayisi'] ?> tamamlandı</li>
                    <li><strong>Tarih:</strong> <?= tarihGoster($insaat['ilk_baslangic']) ?> → <?= tarihGoster($insaat['son_bitis']) ?></li>
                    <li class="<?= $asildi ? 'sure-gecikti' : '' ?>"><strong>Bütçe:</strong> <?= paraGoster($harcanan) ?> / <?= paraGoster($butce) ?> <?= $asildi ? '⚠' : '' ?></li>
                </ul>
                <p class="yuzde">Genel ilerleme: %<?= (int) $insaat['ortalama_ilerleme'] ?></p>
                <div class="ilerleme"><div class="ilerleme-dolu" style="width: <?= (int) $insaat['ortalama_ilerleme'] ?>%"></div></div>
                <p class="yuzde">Bütçe kullanımı: %<?= butceKullanimYuzdesi($butce, $harcanan) ?></p>
                <div class="ilerleme"><div class="ilerleme-dolu <?= $asildi ? 'kirmizi-dolu' : 'mavi-dolu' ?>" style="width: <?= min(butceKullanimYuzdesi($butce, $harcanan), 100) ?>%"></div></div>
                <div class="islemler" style="margin-top: 12px;">
                    <a href="insaat.php?ad=<?= urlencode($insaat['insaat']) ?>" class="buton kucuk">Özeti Gör</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require 'footer.php'; ?>
