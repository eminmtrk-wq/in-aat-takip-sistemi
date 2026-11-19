<?php
/*
 * index.php — Ana sayfa
 * Görevleri: özet sayaçlar, yaklaşan işler uyarısı, arama/filtreleme/sıralama ve iş kalemlerini listelemek.
 */
require_once 'functions.php';
girisGerekli();

// Filtre, arama ve sıralama değerleri GET ile adres çubuğundan gelir
$filtre = filtreleriOku();
$listelenecek = kayitlariFiltrele($filtre);

// Özet bilgiler (kullanıcının görebildiği tüm kayıtlar üzerinden)
$tumKayitlar = tumKayitlar();
$toplam      = count($tumKayitlar);
$tamamlanan  = durumSay($tumKayitlar, 'Tamamlandı');
$devamEden   = durumSay($tumKayitlar, 'Devam Ediyor');
$planlanan   = durumSay($tumKayitlar, 'Planlandı');
$geciken     = gecikenSay($tumKayitlar);

$yaklasanlar = yaklasanIsler();

$sayfaBasligi = 'Ana Sayfa';
require 'header.php';
?>

<section class="tanitim">
    <h1>İnşaat Takip Sistemi</h1>
    <p>Şantiyelerdeki iş kalemlerini kaydedin; ilerlemeyi, bütçeyi ve teslim tarihlerini izleyin.</p>
</section>

<?php mesajGoster(); ?>

<?php if (!yoneticiMi()): ?>
    <?php if (aktifKullanici()['insaat'] === null): ?>
        <div class="uyari uyari-sari">Henüz bir inşaata atanmadınız. Proje müdürünüz sizi bir inşaata atadığında o inşaatın işleri burada görünecek.</div>
    <?php else: ?>
        <div class="uyari uyari-bilgi"><?= rolAdi(aktifKullanici()['rol']) ?> olarak sadece <b><?= guvenli(aktifKullanici()['insaat']) ?></b> inşaatının işlerini görüyorsunuz.</div>
    <?php endif; ?>
<?php endif; ?>

<!-- Yaklaşan işler uyarısı -->
<?php if (count($yaklasanlar) > 0): ?>
    <div class="uyari uyari-sari yaklasan">
        <strong>Önümüzdeki <?= YAKLASAN_GUN ?> gün içinde bitmesi gereken işler:</strong>
        <ul>
            <?php foreach ($yaklasanlar as $is): ?>
                <li>
                    <a href="detay.php?id=<?= (int) $is['id'] ?>"><?= guvenli($is['baslik']) ?></a>
                    — <?= guvenli($is['insaat']) ?>, <?= tarihGoster($is['bitis']) ?> (<?= sureMetni($is) ?>, %<?= (int) $is['ilerleme'] ?> bitti)
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Özet sayaçlar -->
<section class="ozet" aria-label="Özet sayaçlar">
    <div class="kart"><span class="sayi"><?= $toplam ?></span><span>Toplam İş</span></div>
    <div class="kart yesil"><span class="sayi"><?= $tamamlanan ?></span><span>Tamamlandı</span></div>
    <div class="kart turuncu"><span class="sayi"><?= $devamEden ?></span><span>Devam Ediyor</span></div>
    <div class="kart gri"><span class="sayi"><?= $planlanan ?></span><span>Planlandı</span></div>
    <a href="index.php?geciken=1" class="kart kirmizi"><span class="sayi"><?= $geciken ?></span><span>Geciken</span></a>
</section>

<!-- Arama, filtreleme ve sıralama formu (GET yöntemi) -->
<form method="get" action="index.php" class="filtre">
    <label class="genis">Ara
        <input type="search" name="ara" value="<?= guvenli($filtre['ara']) ?>" placeholder="İş adı, inşaat, konum veya sorumlu">
    </label>
    <?php if (yoneticiMi()): ?>
        <label>Bölge
            <select name="bolge">
                <option value="">Tümü</option>
                <?php foreach (BOLGELER as $bolge): ?>
                    <option value="<?= guvenli($bolge) ?>" <?= $bolge === $filtre['bolge'] ? 'selected' : '' ?>><?= guvenli($bolge) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    <?php endif; ?>
    <label>Kategori
        <select name="kategori">
            <option value="">Tümü</option>
            <?php foreach (KATEGORILER as $kategori): ?>
                <option value="<?= guvenli($kategori) ?>" <?= $kategori === $filtre['kategori'] ? 'selected' : '' ?>><?= guvenli($kategori) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Durum
        <select name="durum">
            <option value="">Tümü</option>
            <?php foreach (DURUMLAR as $durum): ?>
                <option value="<?= guvenli($durum) ?>" <?= $durum === $filtre['durum'] ? 'selected' : '' ?>><?= guvenli($durum) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Sırala
        <select name="sirala">
            <?php foreach (SIRALAMALAR as $anahtar => $secenek): ?>
                <option value="<?= $anahtar ?>" <?= $anahtar === $filtre['sirala'] ? 'selected' : '' ?>><?= guvenli($secenek['ad']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php if ($filtre['insaat'] !== ''): ?>
        <input type="hidden" name="insaat" value="<?= guvenli($filtre['insaat']) ?>">
    <?php endif; ?>
    <label class="onay">
        <input type="checkbox" name="geciken" value="1" <?= $filtre['geciken'] ? 'checked' : '' ?>> Sadece gecikenler
    </label>
    <button type="submit" class="buton">Uygula</button>
    <?php if (filtreSeciliMi($filtre)): ?>
        <a href="index.php" class="buton ikincil">Temizle</a>
    <?php endif; ?>
</form>

<div class="liste-ust">
    <p class="bilgi">
        <?= count($listelenecek) ?> kayıt gösteriliyor<?= $filtre['insaat'] !== '' ? ' — İnşaat: <b>' . guvenli($filtre['insaat']) . '</b>' : '' ?><?= $filtre['ara'] !== '' ? ' — "' . guvenli($filtre['ara']) . '" araması' : '' ?>.
    </p>
    <div class="disa-aktar">
        <a href="<?= guvenli(filtreliAdres('disa_aktar.php', $filtre)) ?>" class="buton ikincil kucuk">Excel (CSV) indir</a>
        <a href="<?= guvenli(filtreliAdres('rapor.php', $filtre)) ?>" class="buton ikincil kucuk">PDF rapor</a>
    </div>
</div>

<!-- Kayıt listesi -->
<?php if (count($listelenecek) === 0): ?>
    <div class="bos">Bu ölçütlere uygun iş kalemi bulunamadı.</div>
<?php else: ?>
    <div class="liste">
        <?php foreach ($listelenecek as $kayit): ?>
            <?php
            $gecikti = gecikmeVarMi($kayit);
            $asildi  = butceAsildiMi((float) $kayit['butce'], (float) $kayit['harcanan']);
            ?>
            <article class="is-karti <?= $gecikti ? 'gecikmis' : '' ?>">
                <div class="is-ust">
                    <h2><a href="detay.php?id=<?= (int) $kayit['id'] ?>"><?= guvenli($kayit['baslik']) ?></a></h2>
                    <span class="rozet <?= durumSinifi($kayit['durum']) ?>"><?= guvenli($kayit['durum']) ?></span>
                </div>
                <ul class="detay">
                    <li><strong>İnşaat:</strong> <a href="insaat.php?ad=<?= urlencode($kayit['insaat']) ?>"><?= guvenli($kayit['insaat']) ?></a></li>
                    <li><strong>Bölge / Konum:</strong> <?= guvenli($kayit['bolge']) ?> — <?= guvenli($kayit['konum']) ?></li>
                    <li><strong>Kategori:</strong> <?= guvenli($kayit['kategori']) ?></li>
                    <li><strong>Sorumlu:</strong> <?= guvenli($kayit['sorumlu']) ?></li>
                    <li><strong>Tarih:</strong> <?= tarihGoster($kayit['baslangic']) ?> → <?= tarihGoster($kayit['bitis']) ?></li>
                    <li class="<?= $asildi ? 'sure-gecikti' : '' ?>">
                        <strong>Bütçe:</strong> <?= paraGoster($kayit['harcanan']) ?> / <?= paraGoster($kayit['butce']) ?>
                        <?= $asildi ? '⚠ Bütçe aşıldı' : '' ?>
                    </li>
                </ul>
                <div class="ilerleme" title="%<?= (int) $kayit['ilerleme'] ?>">
                    <div class="ilerleme-dolu" style="width: <?= (int) $kayit['ilerleme'] ?>%"></div>
                </div>
                <p class="yuzde">
                    İlerleme: %<?= (int) $kayit['ilerleme'] ?>
                    <span class="sure <?= $gecikti ? 'sure-gecikti' : '' ?>"><?= $gecikti ? '⚠ ' : '' ?><?= sureMetni($kayit) ?></span>
                </p>
                <?php if ((string) $kayit['aciklama'] !== ''): ?>
                    <p class="aciklama"><?= guvenli($kayit['aciklama']) ?></p>
                <?php endif; ?>

                <div class="islemler">
                    <a href="detay.php?id=<?= (int) $kayit['id'] ?>" class="buton ikincil kucuk">Defter & Foto</a>
                    <a href="ekle.php?id=<?= (int) $kayit['id'] ?>" class="buton kucuk">Düzenle</a>
                    <?php if (isYonetebilirMi()): ?>
                        <!-- Silme işlemi POST ile sil.php'ye gönderilir -->
                        <form method="post" action="sil.php" onsubmit="return confirm('Bu iş kalemi, notları ve fotoğraflarıyla birlikte silinsin mi?');">
                            <input type="hidden" name="id" value="<?= (int) $kayit['id'] ?>">
                            <button type="submit" class="buton tehlike">Sil</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require 'footer.php'; ?>
