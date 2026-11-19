<?php
/*
 * detay.php — Tek bir iş kaleminin detay sayfası
 *   - İş kaleminin tüm bilgileri ve bütçe durumu
 *   - Günlük şantiye defteri (not ekleme / silme)
 *   - Fotoğraflar (yükleme / silme)
 *
 * Bu sayfadaki tüm formlar POST ile bu sayfanın kendisine gönderilir.
 * Hangi işlemin yapılacağı gizli "islem" alanından anlaşılır.
 */
require_once 'functions.php';
girisGerekli();

$id = (int) ($_GET['id'] ?? 0);
$kayit = kayitBul($id);

if ($kayit === null || !kayitErisimVarMi($kayit)) {
    mesajAyarla('hata', 'İş kalemi bulunamadı veya görüntüleme yetkiniz yok.');
    header('Location: index.php');
    exit;
}

// Not formunun varsayılan değerleri
$not = ['tarih' => date('Y-m-d'), 'isci_sayisi' => '', 'hava' => 'Güneşli', 'not_metni' => ''];
$notHatalari = [];
$fotoHatalari = [];
$fotoAciklama = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $islem = $_POST['islem'] ?? '';

    if ($islem === 'not_ekle') {
        foreach ($not as $alan => $varsayilan) {
            $not[$alan] = trim($_POST[$alan] ?? '');
        }
        $notHatalari = notDogrula($not);
        if (count($notHatalari) === 0) {
            notEkle($id, $not);
            mesajAyarla('basari', 'Günlük not eklendi.');
            header('Location: detay.php?id=' . $id . '#defter');
            exit;
        }
    } elseif ($islem === 'not_sil') {
        $silinecek = notBul((int) ($_POST['not_id'] ?? 0));
        // Not bu iş kalemine ait olmalı ve kullanıcının silme yetkisi olmalı
        if ($silinecek !== null && (int) $silinecek['is_id'] === $id && silebilirMi($silinecek)) {
            notSil((int) $silinecek['id']);
            mesajAyarla('basari', 'Not silindi.');
        } else {
            mesajAyarla('hata', 'Bu notu silme yetkiniz yok.');
        }
        header('Location: detay.php?id=' . $id . '#defter');
        exit;
    } elseif ($islem === 'foto_yukle') {
        $fotoAciklama = trim($_POST['foto_aciklama'] ?? '');
        $fotoHatalari = fotoYukle($id, $_FILES['foto'] ?? ['error' => UPLOAD_ERR_NO_FILE], $fotoAciklama);
        if (count($fotoHatalari) === 0) {
            mesajAyarla('basari', 'Fotoğraf yüklendi.');
            header('Location: detay.php?id=' . $id . '#fotograflar');
            exit;
        }
    } elseif ($islem === 'foto_sil') {
        $foto = fotoBul((int) ($_POST['foto_id'] ?? 0));
        if ($foto !== null && (int) $foto['is_id'] === $id && silebilirMi($foto)) {
            fotoSil($foto);
            mesajAyarla('basari', 'Fotoğraf silindi.');
        } else {
            mesajAyarla('hata', 'Bu fotoğrafı silme yetkiniz yok.');
        }
        header('Location: detay.php?id=' . $id . '#fotograflar');
        exit;
    }
}

$notlar = notlariGetir($id);
$fotograflar = fotograflariGetir($id);

$butce    = (float) $kayit['butce'];
$harcanan = (float) $kayit['harcanan'];
$butceYuzde = butceKullanimYuzdesi($butce, $harcanan);
$gecikti = gecikmeVarMi($kayit);

$sayfaBasligi = $kayit['baslik'];
require 'header.php';
?>

<p class="geri-link"><a href="index.php">← İş kalemlerine dön</a> · <a href="insaat.php?ad=<?= urlencode($kayit['insaat']) ?>"><?= guvenli($kayit['insaat']) ?> özeti</a></p>

<?php mesajGoster(); ?>

<!-- İş kalemi bilgileri -->
<section class="detay-kutu <?= $gecikti ? 'gecikmis' : '' ?>">
    <div class="is-ust">
        <h1><?= guvenli($kayit['baslik']) ?></h1>
        <span class="rozet <?= durumSinifi($kayit['durum']) ?>"><?= guvenli($kayit['durum']) ?></span>
    </div>

    <div class="detay-izgara">
        <div><span>İnşaat</span><b><?= guvenli($kayit['insaat']) ?></b></div>
        <div><span>Bölge</span><b><?= guvenli($kayit['bolge']) ?></b></div>
        <div><span>Konum</span><b><?= guvenli($kayit['konum']) ?></b></div>
        <div><span>Kategori</span><b><?= guvenli($kayit['kategori']) ?></b></div>
        <div><span>Sorumlu</span><b><?= guvenli($kayit['sorumlu']) ?></b></div>
        <div><span>Tarih</span><b><?= tarihGoster($kayit['baslangic']) ?> → <?= tarihGoster($kayit['bitis']) ?></b></div>
    </div>

    <div class="iki-cubuk">
        <div>
            <p class="yuzde">İlerleme: %<?= (int) $kayit['ilerleme'] ?>
                <span class="sure <?= $gecikti ? 'sure-gecikti' : '' ?>"><?= $gecikti ? '⚠ ' : '' ?><?= sureMetni($kayit) ?></span></p>
            <div class="ilerleme"><div class="ilerleme-dolu" style="width: <?= (int) $kayit['ilerleme'] ?>%"></div></div>
        </div>
        <div>
            <p class="yuzde">Bütçe kullanımı: %<?= $butceYuzde ?>
                <span class="<?= butceAsildiMi($butce, $harcanan) ? 'sure-gecikti' : 'sure' ?>">
                    <?= paraGoster($harcanan) ?> / <?= paraGoster($butce) ?>
                </span></p>
            <div class="ilerleme"><div class="ilerleme-dolu <?= butceAsildiMi($butce, $harcanan) ? 'kirmizi-dolu' : 'mavi-dolu' ?>" style="width: <?= min($butceYuzde, 100) ?>%"></div></div>
            <?php if (butceAsildiMi($butce, $harcanan)): ?>
                <p class="sure-gecikti">⚠ Bütçe <?= paraGoster($harcanan - $butce) ?> aşıldı.</p>
            <?php elseif ($butce > 0): ?>
                <p class="bilgi">Kalan bütçe: <?= paraGoster($butce - $harcanan) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ((string) $kayit['aciklama'] !== ''): ?>
        <p class="aciklama"><?= guvenli($kayit['aciklama']) ?></p>
    <?php endif; ?>

    <a href="ekle.php?id=<?= $id ?>" class="buton kucuk">Düzenle</a>
</section>

<div class="iki-panel">
    <!-- Günlük şantiye defteri -->
    <section class="panel" id="defter">
        <h2>Şantiye defteri (<?= count($notlar) ?>)</h2>

        <?php if (count($notHatalari) > 0): ?>
            <div class="uyari uyari-hata">
                <ul>
                    <?php foreach ($notHatalari as $hata): ?>
                        <li><?= guvenli($hata) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="detay.php?id=<?= $id ?>#defter" class="not-formu" novalidate>
            <input type="hidden" name="islem" value="not_ekle">
            <div class="uc-sutun">
                <label>Tarih
                    <input type="date" name="tarih" value="<?= guvenli($not['tarih']) ?>" max="<?= date('Y-m-d') ?>">
                </label>
                <label>İşçi sayısı
                    <input type="number" name="isci_sayisi" min="0" value="<?= guvenli($not['isci_sayisi']) ?>">
                </label>
                <label>Hava
                    <select name="hava">
                        <?php foreach (HAVA_DURUMLARI as $hava): ?>
                            <option value="<?= guvenli($hava) ?>" <?= $hava === $not['hava'] ? 'selected' : '' ?>><?= guvenli($hava) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label>Not
                <textarea name="not_metni" rows="2" placeholder="Bugün yapılanlar, sorunlar, gecikme nedenleri..."><?= guvenli($not['not_metni']) ?></textarea>
            </label>
            <button type="submit" class="buton kucuk">Not Ekle</button>
        </form>

        <?php if (count($notlar) === 0): ?>
            <p class="bilgi">Henüz not yok.</p>
        <?php else: ?>
            <ul class="not-listesi">
                <?php foreach ($notlar as $n): ?>
                    <li>
                        <div class="not-ust">
                            <b><?= tarihGoster($n['tarih']) ?></b>
                            <span><?= (int) $n['isci_sayisi'] ?> işçi · <?= guvenli($n['hava']) ?></span>
                        </div>
                        <p><?= nl2br(guvenli($n['not_metni'])) ?></p>
                        <div class="not-alt">
                            <small>Yazan: <?= guvenli($n['yazan']) ?></small>
                            <?php if (silebilirMi($n)): ?>
                                <form method="post" action="detay.php?id=<?= $id ?>" onsubmit="return confirm('Not silinsin mi?');">
                                    <input type="hidden" name="islem" value="not_sil">
                                    <input type="hidden" name="not_id" value="<?= (int) $n['id'] ?>">
                                    <button type="submit" class="link-buton">Sil</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <!-- Fotoğraflar -->
    <section class="panel" id="fotograflar">
        <h2>Fotoğraflar (<?= count($fotograflar) ?>)</h2>

        <?php if (count($fotoHatalari) > 0): ?>
            <div class="uyari uyari-hata"><?= guvenli(implode(' ', $fotoHatalari)) ?></div>
        <?php endif; ?>

        <!-- Dosya yüklemek için enctype="multipart/form-data" zorunludur -->
        <form method="post" action="detay.php?id=<?= $id ?>#fotograflar" enctype="multipart/form-data" class="not-formu">
            <input type="hidden" name="islem" value="foto_yukle">
            <label>Fotoğraf (JPG, PNG, WEBP — en fazla 5 MB)
                <input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
            </label>
            <label>Açıklama
                <input type="text" name="foto_aciklama" maxlength="200" value="<?= guvenli($fotoAciklama) ?>" placeholder="Örn: Kalıp kurulumu, kuzey cephe">
            </label>
            <button type="submit" class="buton kucuk">Yükle</button>
        </form>

        <?php if (count($fotograflar) === 0): ?>
            <p class="bilgi">Henüz fotoğraf yok.</p>
        <?php else: ?>
            <div class="foto-izgara">
                <?php foreach ($fotograflar as $foto): ?>
                    <figure>
                        <a href="<?= YUKLEME_KLASORU . guvenli($foto['dosya_adi']) ?>" target="_blank">
                            <img src="<?= YUKLEME_KLASORU . guvenli($foto['dosya_adi']) ?>" alt="<?= guvenli($foto['aciklama']) ?>">
                        </a>
                        <figcaption>
                            <?= guvenli($foto['aciklama']) ?>
                            <small><?= guvenli($foto['yukleyen']) ?> · <?= date('d.m.Y', strtotime($foto['olusturma'])) ?></small>
                            <?php if (silebilirMi($foto)): ?>
                                <form method="post" action="detay.php?id=<?= $id ?>" onsubmit="return confirm('Fotoğraf silinsin mi?');">
                                    <input type="hidden" name="islem" value="foto_sil">
                                    <input type="hidden" name="foto_id" value="<?= (int) $foto['id'] ?>">
                                    <button type="submit" class="link-buton">Sil</button>
                                </form>
                            <?php endif; ?>
                        </figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require 'footer.php'; ?>
