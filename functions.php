<?php
/*
 * functions.php
 * Projenin ortak fonksiyonları burada tutulur. Her sayfa bu dosyayı require_once ile çağırır.
 *
 * Bölümler:
 *   1. Ayarlar ve sabitler
 *   2. İş kalemi sorguları (listele, bul, ekle, güncelle, sil, filtrele)
 *   3. İnşaat özetleri
 *   4. Günlük şantiye defteri
 *   5. Fotoğraflar
 *   6. Doğrulama
 *   7. Tarih, gecikme ve bütçe hesapları
 *   8. Sayaçlar ve yardımcı fonksiyonlar
 */

// Tarih hesapları (gecikme, kalan gün, rapor saati) Türkiye saatine göre yapılsın
date_default_timezone_set('Europe/Istanbul');

// Oturuma bu projeye özel bir ad veriyoruz. Varsayılan ad (PHPSESSID) localhost'taki
// bütün projelerde ortaktır; başka bir projenin oturum bilgileri buraya karışmasın.
session_name('insaat_takip_oturum');
session_start();
require_once 'db.php';    // $db değişkeni (PDO bağlantısı)
require_once 'yetki.php'; // Giriş ve yetki fonksiyonları

/* ==================================================================
 * 1. AYARLAR VE SABİTLER
 * ================================================================== */

const KATEGORILER = ['Kaba İnşaat', 'İnce İşler', 'Elektrik', 'Sıhhi Tesisat', 'Çevre Düzenleme'];
const DURUMLAR    = ['Planlandı', 'Devam Ediyor', 'Tamamlandı'];
const BOLGELER    = ['Marmara', 'Ege', 'Akdeniz', 'İç Anadolu', 'Karadeniz', 'Doğu Anadolu', 'Güneydoğu Anadolu'];
const HAVA_DURUMLARI = ['Güneşli', 'Bulutlu', 'Yağmurlu', 'Karlı', 'Rüzgarlı'];

// Sıralama seçenekleri: anahtar adres çubuğunda görünür, değer SQL'e yazılır.
// ORDER BY kısmına ? yer tutucusu konamadığı için sadece bu listedeki değerlere izin verilir.
const SIRALAMALAR = [
    'bitis'        => ['ad' => 'Bitiş tarihi (yakın → uzak)', 'sql' => 'bitis ASC, id ASC'],
    'ilerleme_az'  => ['ad' => 'İlerleme (az → çok)',         'sql' => 'ilerleme ASC, bitis ASC'],
    'ilerleme_cok' => ['ad' => 'İlerleme (çok → az)',         'sql' => 'ilerleme DESC, bitis ASC'],
    'ad'           => ['ad' => 'İş adı (A → Z)',              'sql' => 'baslik ASC'],
    'butce'        => ['ad' => 'Bütçe (büyük → küçük)',       'sql' => 'butce DESC'],
];

const YUKLEME_KLASORU = 'uploads/';
const MAKS_FOTO_BOYUTU = 5 * 1024 * 1024; // 5 MB
const YAKLASAN_GUN = 7;                    // Kaç gün içinde bitecek işler "yaklaşan" sayılır

/* ==================================================================
 * 2. İŞ KALEMİ SORGULARI
 * Tüm sorgularda "prepare + execute" kullanılır (hazırlanmış sorgu).
 * Kullanıcıdan gelen değerler sorguya doğrudan yazılmaz, ? yerine
 * ayrıca gönderilir. Bu, SQL Injection saldırısını önler.
 * ================================================================== */

/**
 * Adres çubuğundaki (GET) filtre değerlerini okur ve geçerli olmayanları temizler.
 * index.php, rapor.php ve disa_aktar.php aynı filtreleri kullandığı için ortak fonksiyon.
 */
function filtreleriOku(): array
{
    $filtre = [
        'ara'      => trim($_GET['ara'] ?? ''),
        'kategori' => $_GET['kategori'] ?? '',
        'durum'    => $_GET['durum'] ?? '',
        'bolge'    => $_GET['bolge'] ?? '',
        'insaat'   => trim($_GET['insaat'] ?? ''),
        'geciken'  => isset($_GET['geciken']),
        'sirala'   => $_GET['sirala'] ?? 'bitis',
    ];

    if (!in_array($filtre['kategori'], KATEGORILER, true)) {
        $filtre['kategori'] = '';
    }
    if (!in_array($filtre['durum'], DURUMLAR, true)) {
        $filtre['durum'] = '';
    }
    if (!in_array($filtre['bolge'], BOLGELER, true)) {
        $filtre['bolge'] = '';
    }
    if (!array_key_exists($filtre['sirala'], SIRALAMALAR)) {
        $filtre['sirala'] = 'bitis';
    }
    return $filtre;
}

/**
 * Herhangi bir filtre seçili mi? ("Temizle" butonunu göstermek için)
 */
function filtreSeciliMi(array $filtre): bool
{
    return $filtre['ara'] !== '' || $filtre['kategori'] !== '' || $filtre['durum'] !== ''
        || $filtre['bolge'] !== '' || $filtre['insaat'] !== '' || $filtre['geciken'];
}

/**
 * Filtrelere ve kullanıcının yetkisine göre iş kalemlerini getirir.
 * Boş gelen ölçüt dikkate alınmaz. Koşullar WHERE cümlesine tek tek eklenir.
 */
function kayitlariFiltrele(array $filtre): array
{
    global $db;
    $kosullar = [];
    $degerler = [];

    // Şantiye şefi sadece kendi inşaatını görür
    if (!yoneticiMi()) {
        $kosullar[] = 'insaat = ?';
        $degerler[] = aktifKullanici()['insaat'];
    }

    if ($filtre['kategori'] !== '') {
        $kosullar[] = 'kategori = ?';
        $degerler[] = $filtre['kategori'];
    }
    if ($filtre['durum'] !== '') {
        $kosullar[] = 'durum = ?';
        $degerler[] = $filtre['durum'];
    }
    if ($filtre['bolge'] !== '') {
        $kosullar[] = 'bolge = ?';
        $degerler[] = $filtre['bolge'];
    }
    if ($filtre['insaat'] !== '') {
        $kosullar[] = 'insaat = ?';
        $degerler[] = $filtre['insaat'];
    }
    if ($filtre['ara'] !== '') {
        // Aranan kelime iş adı, inşaat, konum veya sorumlu alanlarından birinde geçiyorsa
        $kosullar[] = '(baslik LIKE ? OR insaat LIKE ? OR konum LIKE ? OR sorumlu LIKE ?)';
        $kalip = '%' . $filtre['ara'] . '%';
        array_push($degerler, $kalip, $kalip, $kalip, $kalip);
    }
    if ($filtre['geciken']) {
        $kosullar[] = "bitis < ? AND durum <> 'Tamamlandı'";
        $degerler[] = date('Y-m-d');
    }

    $sql = 'SELECT * FROM is_kalemleri';
    if (count($kosullar) > 0) {
        $sql .= ' WHERE ' . implode(' AND ', $kosullar);
    }
    $sql .= ' ORDER BY ' . SIRALAMALAR[$filtre['sirala']]['sql'];

    $sorgu = $db->prepare($sql);
    $sorgu->execute($degerler);
    return $sorgu->fetchAll();
}

/**
 * Kullanıcının görebildiği tüm kayıtlar (sayaçlar için).
 */
function tumKayitlar(): array
{
    return kayitlariFiltrele(['ara' => '', 'kategori' => '', 'durum' => '', 'bolge' => '',
                              'insaat' => '', 'geciken' => false, 'sirala' => 'bitis']);
}

/**
 * Id'ye göre tek bir kaydı getirir, yoksa null döner.
 */
function kayitBul(int $id): ?array
{
    global $db;
    $sorgu = $db->prepare('SELECT * FROM is_kalemleri WHERE id = ?');
    $sorgu->execute([$id]);
    $kayit = $sorgu->fetch();
    return $kayit === false ? null : $kayit;
}

/**
 * Doğrulanmış veriyi tabloya yeni satır olarak ekler.
 */
function kayitEkle(array $veri): void
{
    global $db;
    $sorgu = $db->prepare(
        'INSERT INTO is_kalemleri
            (baslik, insaat, bolge, konum, kategori, sorumlu, ilerleme, durum, baslangic, bitis, butce, harcanan, aciklama)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $sorgu->execute(sorguDegerleri($veri));
}

/**
 * Var olan bir kaydı yeni verilerle günceller.
 */
function kayitGuncelle(int $id, array $veri): void
{
    global $db;
    $sorgu = $db->prepare(
        'UPDATE is_kalemleri
         SET baslik = ?, insaat = ?, bolge = ?, konum = ?, kategori = ?, sorumlu = ?,
             ilerleme = ?, durum = ?, baslangic = ?, bitis = ?, butce = ?, harcanan = ?, aciklama = ?
         WHERE id = ?'
    );
    $degerler = sorguDegerleri($veri);
    $degerler[] = $id; // WHERE id = ? için son değer
    $sorgu->execute($degerler);
}

/**
 * Ekleme ve güncellemede ortak kullanılan değer listesi (sütun sırasıyla aynı).
 */
function sorguDegerleri(array $veri): array
{
    return [
        $veri['baslik'], $veri['insaat'], $veri['bolge'], $veri['konum'],
        $veri['kategori'], $veri['sorumlu'], (int) $veri['ilerleme'], $veri['durum'],
        $veri['baslangic'], $veri['bitis'],
        sayiyaCevir($veri['butce']), sayiyaCevir($veri['harcanan']),
        $veri['aciklama'],
    ];
}

/**
 * Kaydı ve ona ait fotoğraf dosyalarını siler. Bir satır silindiyse true döner.
 * Notlar ve fotoğraf satırları veritabanında ON DELETE CASCADE ile otomatik silinir,
 * ama diskteki resim dosyalarını PHP'nin silmesi gerekir.
 */
function kayitSil(int $id): bool
{
    global $db;
    foreach (fotograflariGetir($id) as $foto) {
        dosyaSil($foto['dosya_adi']);
    }
    $sorgu = $db->prepare('DELETE FROM is_kalemleri WHERE id = ?');
    $sorgu->execute([$id]);
    return $sorgu->rowCount() > 0;
}

/**
 * Önümüzdeki YAKLASAN_GUN gün içinde bitmesi gereken, tamamlanmamış işler.
 */
function yaklasanIsler(): array
{
    $bugun = date('Y-m-d');
    $sinir = date('Y-m-d', strtotime('+' . YAKLASAN_GUN . ' days'));
    $sonuc = [];
    foreach (tumKayitlar() as $kayit) {
        if ($kayit['durum'] !== 'Tamamlandı' && $kayit['bitis'] >= $bugun && $kayit['bitis'] <= $sinir) {
            $sonuc[] = $kayit;
        }
    }
    return $sonuc;
}

/* ==================================================================
 * 3. İNŞAAT ÖZETLERİ
 * GROUP BY ile aynı inşaata ait iş kalemleri tek satırda toplanır.
 * ================================================================== */

/**
 * Her inşaat için iş sayısı, ortalama ilerleme, bütçe toplamları ve geciken iş sayısı.
 * $insaatAdi verilirse sadece o inşaatın özeti döner.
 */
function insaatOzetleri(string $insaatAdi = ''): array
{
    global $db;
    $kosullar = [];
    $degerler = [date('Y-m-d')]; // Gecikme hesabındaki ? için

    if (!yoneticiMi()) {
        $kosullar[] = 'insaat = ?';
        $degerler[] = aktifKullanici()['insaat'];
    }
    if ($insaatAdi !== '') {
        $kosullar[] = 'insaat = ?';
        $degerler[] = $insaatAdi;
    }

    $sql = "SELECT insaat,
                   MIN(bolge)  AS bolge,
                   MIN(konum)  AS konum,
                   COUNT(*)    AS is_sayisi,
                   SUM(CASE WHEN durum = 'Tamamlandı' THEN 1 ELSE 0 END) AS tamamlanan,
                   SUM(CASE WHEN bitis < ? AND durum <> 'Tamamlandı' THEN 1 ELSE 0 END) AS geciken,
                   ROUND(AVG(ilerleme)) AS ortalama_ilerleme,
                   SUM(butce)    AS toplam_butce,
                   SUM(harcanan) AS toplam_harcanan,
                   MIN(baslangic) AS ilk_baslangic,
                   MAX(bitis)     AS son_bitis
            FROM is_kalemleri";
    if (count($kosullar) > 0) {
        $sql .= ' WHERE ' . implode(' AND ', $kosullar);
    }
    $sql .= ' GROUP BY insaat ORDER BY insaat';

    $sorgu = $db->prepare($sql);
    $sorgu->execute($degerler);
    return $sorgu->fetchAll();
}

/* ==================================================================
 * 4. GÜNLÜK ŞANTİYE DEFTERİ
 * ================================================================== */

/**
 * Bir iş kalemine ait notları, yazan kişinin adıyla birlikte getirir.
 * JOIN: gunluk_notlar tablosundaki kullanici_id ile kullanicilar tablosu birleştirilir.
 */
function notlariGetir(int $isId): array
{
    global $db;
    $sorgu = $db->prepare(
        'SELECT n.*, k.ad_soyad AS yazan
         FROM gunluk_notlar n
         JOIN kullanicilar k ON k.id = n.kullanici_id
         WHERE n.is_id = ?
         ORDER BY n.tarih DESC, n.id DESC'
    );
    $sorgu->execute([$isId]);
    return $sorgu->fetchAll();
}

/**
 * Bir inşaatın tüm iş kalemlerine ait son notlar (inşaat özet sayfası için).
 */
function insaatSonNotlari(string $insaatAdi, int $adet = 5): array
{
    global $db;
    $sorgu = $db->prepare(
        'SELECT n.*, k.ad_soyad AS yazan, i.baslik AS is_adi
         FROM gunluk_notlar n
         JOIN kullanicilar k ON k.id = n.kullanici_id
         JOIN is_kalemleri i ON i.id = n.is_id
         WHERE i.insaat = ?
         ORDER BY n.tarih DESC, n.id DESC
         LIMIT ' . (int) $adet
    );
    $sorgu->execute([$insaatAdi]);
    return $sorgu->fetchAll();
}

function notBul(int $id): ?array
{
    global $db;
    $sorgu = $db->prepare('SELECT * FROM gunluk_notlar WHERE id = ?');
    $sorgu->execute([$id]);
    $not = $sorgu->fetch();
    return $not === false ? null : $not;
}

function notDogrula(array $veri): array
{
    $hatalar = [];
    if (!gecerliTarihMi($veri['tarih'])) {
        $hatalar[] = 'Lütfen geçerli bir not tarihi girin.';
    } elseif ($veri['tarih'] > date('Y-m-d')) {
        $hatalar[] = 'Gelecek bir tarihe günlük not yazılamaz.';
    }
    if ($veri['isci_sayisi'] === '' || !ctype_digit($veri['isci_sayisi']) || (int) $veri['isci_sayisi'] > 1000) {
        $hatalar[] = 'İşçi sayısı 0 ile 1000 arasında bir tam sayı olmalıdır.';
    }
    if (!in_array($veri['hava'], HAVA_DURUMLARI, true)) {
        $hatalar[] = 'Lütfen listeden bir hava durumu seçin.';
    }
    if ($veri['not_metni'] === '') {
        $hatalar[] = 'Not alanı boş bırakılamaz.';
    }
    return $hatalar;
}

function notEkle(int $isId, array $veri): void
{
    global $db;
    $sorgu = $db->prepare(
        'INSERT INTO gunluk_notlar (is_id, kullanici_id, tarih, isci_sayisi, hava, not_metni)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $sorgu->execute([$isId, aktifKullanici()['id'], $veri['tarih'], (int) $veri['isci_sayisi'], $veri['hava'], $veri['not_metni']]);
}

function notSil(int $id): void
{
    global $db;
    $db->prepare('DELETE FROM gunluk_notlar WHERE id = ?')->execute([$id]);
}

/* ==================================================================
 * 5. FOTOĞRAFLAR
 * ================================================================== */

function fotograflariGetir(int $isId): array
{
    global $db;
    $sorgu = $db->prepare(
        'SELECT f.*, k.ad_soyad AS yukleyen
         FROM fotograflar f
         JOIN kullanicilar k ON k.id = f.kullanici_id
         WHERE f.is_id = ?
         ORDER BY f.id DESC'
    );
    $sorgu->execute([$isId]);
    return $sorgu->fetchAll();
}

function fotoBul(int $id): ?array
{
    global $db;
    $sorgu = $db->prepare('SELECT * FROM fotograflar WHERE id = ?');
    $sorgu->execute([$id]);
    $foto = $sorgu->fetch();
    return $foto === false ? null : $foto;
}

/**
 * Formdan gelen resmi kontrol edip uploads/ klasörüne kaydeder.
 * Başarılıysa boş dizi, sorun varsa hata mesajları döner.
 * $dosya: $_FILES['foto'] dizisi.
 */
function fotoYukle(int $isId, array $dosya, string $aciklama): array
{
    global $db;

    if ($dosya['error'] === UPLOAD_ERR_NO_FILE) {
        return ['Lütfen bir fotoğraf seçin.'];
    }
    if ($dosya['error'] === UPLOAD_ERR_INI_SIZE || $dosya['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['Fotoğraf çok büyük. En fazla 5 MB olabilir.'];
    }
    if ($dosya['error'] !== UPLOAD_ERR_OK) {
        return ['Fotoğraf yüklenirken bir hata oluştu (kod: ' . $dosya['error'] . ').'];
    }
    if ($dosya['size'] > MAKS_FOTO_BOYUTU) {
        return ['Fotoğraf çok büyük. En fazla 5 MB olabilir.'];
    }

    // Dosya adına değil, içeriğine bakarak gerçekten resim mi diye kontrol et.
    // getimagesize resim olmayan dosyalarda false döner.
    $bilgi = @getimagesize($dosya['tmp_name']);
    $izinliTurler = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if ($bilgi === false || !isset($izinliTurler[$bilgi[2]])) {
        return ['Sadece JPG, PNG veya WEBP resim yüklenebilir.'];
    }
    if (mb_strlen($aciklama) > 200) {
        return ['Fotoğraf açıklaması en fazla 200 karakter olabilir.'];
    }

    // Kullanıcının verdiği dosya adını kullanmıyoruz; rastgele ve benzersiz bir ad üretiyoruz.
    $yeniAd = 'is' . $isId . '_' . bin2hex(random_bytes(8)) . '.' . $izinliTurler[$bilgi[2]];

    if (!is_dir(YUKLEME_KLASORU)) {
        mkdir(YUKLEME_KLASORU, 0755, true);
    }
    if (!move_uploaded_file($dosya['tmp_name'], YUKLEME_KLASORU . $yeniAd)) {
        return ['Fotoğraf sunucuya kaydedilemedi. uploads klasörünün yazma iznini kontrol edin.'];
    }

    $sorgu = $db->prepare('INSERT INTO fotograflar (is_id, kullanici_id, dosya_adi, aciklama) VALUES (?, ?, ?, ?)');
    $sorgu->execute([$isId, aktifKullanici()['id'], $yeniAd, $aciklama]);
    return [];
}

function fotoSil(array $foto): void
{
    global $db;
    dosyaSil($foto['dosya_adi']);
    $db->prepare('DELETE FROM fotograflar WHERE id = ?')->execute([$foto['id']]);
}

/**
 * uploads/ klasöründeki bir dosyayı siler. basename() ile klasör dışına çıkılması engellenir.
 */
function dosyaSil(string $dosyaAdi): void
{
    $yol = YUKLEME_KLASORU . basename($dosyaAdi);
    if (is_file($yol)) {
        unlink($yol);
    }
}

/* ==================================================================
 * 6. DOĞRULAMA
 * ================================================================== */

/**
 * Metnin YYYY-AA-GG biçiminde geçerli bir tarih olup olmadığını kontrol eder.
 * Örn: 2026-02-30 geçersizdir (Şubat 30 çekmez).
 */
function gecerliTarihMi(string $tarih): bool
{
    $nesne = DateTime::createFromFormat('Y-m-d', $tarih);
    return $nesne !== false && $nesne->format('Y-m-d') === $tarih;
}

/**
 * Para tutarı geçerli mi? "1500", "1500,50", "1500.50" kabul edilir; negatif kabul edilmez.
 */
function gecerliTutarMi(string $tutar): bool
{
    return preg_match('/^\d{1,10}([.,]\d{1,2})?$/', $tutar) === 1;
}

/**
 * "1500,50" gibi virgüllü tutarı 1500.50 sayısına çevirir.
 */
function sayiyaCevir(string $tutar): float
{
    return (float) str_replace(',', '.', $tutar);
}

/**
 * Formdan gelen iş kalemi verisini kontrol eder. Hata yoksa boş dizi döner.
 */
function kayitDogrula(array $veri): array
{
    $hatalar = [];

    if ($veri['baslik'] === '') {
        $hatalar[] = 'İş kalemi adı boş bırakılamaz.';
    } elseif (mb_strlen($veri['baslik']) > 100) {
        $hatalar[] = 'İş kalemi adı en fazla 100 karakter olabilir.';
    }

    if ($veri['insaat'] === '') {
        $hatalar[] = 'İnşaat (proje) adı boş bırakılamaz.';
    }

    if ($veri['konum'] === '') {
        $hatalar[] = 'İnşaatın konumu (il / ilçe / adres) boş bırakılamaz.';
    }

    if (!in_array($veri['bolge'], BOLGELER, true)) {
        $hatalar[] = 'Lütfen listeden geçerli bir bölge seçin.';
    }

    if ($veri['sorumlu'] === '') {
        $hatalar[] = 'Sorumlu kişi / firma boş bırakılamaz.';
    }

    if (!in_array($veri['kategori'], KATEGORILER, true)) {
        $hatalar[] = 'Lütfen listeden geçerli bir kategori seçin.';
    }

    if (!in_array($veri['durum'], DURUMLAR, true)) {
        $hatalar[] = 'Lütfen listeden geçerli bir durum seçin.';
    }

    // İlerleme 0 ile 100 arasında tam sayı olmalı
    if ($veri['ilerleme'] === '' || !ctype_digit($veri['ilerleme']) || (int) $veri['ilerleme'] > 100) {
        $hatalar[] = 'İlerleme yüzdesi 0 ile 100 arasında bir tam sayı olmalıdır.';
    } else {
        // Durum ile ilerleme birbiriyle tutarlı olmalı
        $yuzde = (int) $veri['ilerleme'];
        if ($veri['durum'] === 'Tamamlandı' && $yuzde !== 100) {
            $hatalar[] = '"Tamamlandı" durumundaki bir işin ilerlemesi %100 olmalıdır.';
        } elseif ($veri['durum'] === 'Planlandı' && $yuzde !== 0) {
            $hatalar[] = '"Planlandı" durumundaki bir işin ilerlemesi %0 olmalıdır.';
        }
    }

    // Tarih kontrolleri
    $baslangicGecerli = gecerliTarihMi($veri['baslangic']);
    $bitisGecerli     = gecerliTarihMi($veri['bitis']);

    if (!$baslangicGecerli) {
        $hatalar[] = 'Lütfen geçerli bir başlangıç tarihi girin.';
    }
    if (!$bitisGecerli) {
        $hatalar[] = 'Lütfen geçerli bir planlanan bitiş tarihi girin.';
    }
    // YYYY-AA-GG biçimindeki tarihler metin olarak doğru sırayla karşılaştırılabilir
    if ($baslangicGecerli && $bitisGecerli && $veri['bitis'] < $veri['baslangic']) {
        $hatalar[] = 'Bitiş tarihi, başlangıç tarihinden önce olamaz.';
    }

    // Bütçe kontrolleri
    if (!gecerliTutarMi($veri['butce'])) {
        $hatalar[] = 'Planlanan bütçe 0 veya daha büyük bir tutar olmalıdır (örn: 150000 veya 150000,50).';
    }
    if (!gecerliTutarMi($veri['harcanan'])) {
        $hatalar[] = 'Harcanan tutar 0 veya daha büyük bir tutar olmalıdır.';
    }

    return $hatalar;
}

/* ==================================================================
 * 7. TARİH, GECİKME VE BÜTÇE HESAPLARI
 * ================================================================== */

/**
 * İş gecikmiş mi? Bitiş tarihi geçmiş ve iş hâlâ tamamlanmamışsa true.
 */
function gecikmeVarMi(array $kayit): bool
{
    return $kayit['durum'] !== 'Tamamlandı' && $kayit['bitis'] < date('Y-m-d');
}

/**
 * Bitiş tarihine kalan gün sayısı. Geçmişse negatif sayı döner.
 */
function kalanGun(array $kayit): int
{
    $bugun = new DateTime(date('Y-m-d'));
    $bitis = new DateTime($kayit['bitis']);
    $fark  = $bugun->diff($bitis);
    return $fark->invert === 1 ? -$fark->days : $fark->days;
}

/**
 * Kartta gösterilecek süre bilgisini metin olarak hazırlar.
 */
function sureMetni(array $kayit): string
{
    if ($kayit['durum'] === 'Tamamlandı') {
        return 'Tamamlandı';
    }
    $gun = kalanGun($kayit);
    if ($gun < 0) {
        return abs($gun) . ' gün gecikti';
    }
    if ($gun === 0) {
        return 'Bugün bitmeli';
    }
    return $gun . ' gün kaldı';
}

/**
 * 2026-11-19 biçimindeki tarihi 19.11.2026 olarak gösterir.
 */
function tarihGoster(string $tarih): string
{
    return date('d.m.Y', strtotime($tarih));
}

/**
 * Harcanan tutar bütçeyi geçti mi? (Bütçe 0 girilmişse kontrol yapılmaz.)
 */
function butceAsildiMi(float $butce, float $harcanan): bool
{
    return $butce > 0 && $harcanan > $butce;
}

/**
 * Bütçenin yüzde kaçı harcandı? Bütçe 0 ise 0 döner (sıfıra bölme hatası olmasın).
 */
function butceKullanimYuzdesi(float $butce, float $harcanan): int
{
    if ($butce <= 0) {
        return 0;
    }
    return (int) round($harcanan / $butce * 100);
}

/**
 * 1234567.5 → "1.234.567,50 ₺"
 */
function paraGoster($tutar): string
{
    return number_format((float) $tutar, 2, ',', '.') . ' ₺';
}

/* ==================================================================
 * 8. SAYAÇLAR VE YARDIMCI FONKSİYONLAR
 * ================================================================== */

/**
 * Belirli bir durumdaki kayıt sayısını hesaplar (sayaçlar için).
 */
function durumSay(array $kayitlar, string $durum): int
{
    $sayac = 0;
    foreach ($kayitlar as $kayit) {
        if ($kayit['durum'] === $durum) {
            $sayac++;
        }
    }
    return $sayac;
}

/**
 * Geciken iş sayısını hesaplar.
 */
function gecikenSay(array $kayitlar): int
{
    $sayac = 0;
    foreach ($kayitlar as $kayit) {
        if (gecikmeVarMi($kayit)) {
            $sayac++;
        }
    }
    return $sayac;
}

/**
 * Kullanıcıdan gelen metni ekrana güvenli basmak için htmlspecialchars kısayolu.
 * <script> gibi etiketleri çalıştırmak yerine düz metin olarak gösterir (XSS koruması).
 */
function guvenli($metin): string
{
    return htmlspecialchars((string) $metin, ENT_QUOTES, 'UTF-8');
}

/**
 * Duruma göre CSS sınıfı döndürür (rozet renkleri için).
 */
function durumSinifi(string $durum): string
{
    switch ($durum) {
        case 'Tamamlandı':
            return 'rozet-yesil';
        case 'Devam Ediyor':
            return 'rozet-turuncu';
        default:
            return 'rozet-gri';
    }
}

/**
 * Mevcut filtreleri koruyarak adres oluşturur. Örn: rapor.php?bolge=Ege&sirala=ad
 */
function filtreliAdres(string $sayfa, array $filtre): string
{
    $parametreler = [];
    foreach (['ara', 'kategori', 'durum', 'bolge', 'insaat', 'sirala'] as $anahtar) {
        if ($filtre[$anahtar] !== '') {
            $parametreler[$anahtar] = $filtre[$anahtar];
        }
    }
    if ($filtre['geciken']) {
        $parametreler['geciken'] = 1;
    }
    $sorgu = http_build_query($parametreler);
    return $sorgu === '' ? $sayfa : $sayfa . '?' . $sorgu;
}

/**
 * Bir sonraki sayfada gösterilecek tek seferlik mesajı kaydeder.
 */
function mesajAyarla(string $tur, string $metin): void
{
    $_SESSION['mesaj'] = ['tur' => $tur, 'metin' => $metin];
}

/**
 * Kayıtlı mesajı okur ve siler (bir kez gösterilsin diye).
 */
function mesajAl(): ?array
{
    if (isset($_SESSION['mesaj'])) {
        $mesaj = $_SESSION['mesaj'];
        unset($_SESSION['mesaj']);
        return $mesaj;
    }
    return null;
}

/**
 * Mesaj varsa ekrana basar (sayfalarda tekrar eden kodu azaltmak için).
 */
function mesajGoster(): void
{
    $mesaj = mesajAl();
    if ($mesaj !== null) {
        echo '<div class="uyari uyari-' . guvenli($mesaj['tur']) . '">' . guvenli($mesaj['metin']) . '</div>';
    }
}

/* ==================================================================
 * 9. KULLANICILAR, EKİP VE ATAMALAR
 * ================================================================== */

/**
 * Sistemde geçen tüm inşaat adları (iş kalemlerinden ve kullanıcı atamalarından).
 * UNION iki sorgunun sonucunu birleştirir ve tekrar edenleri bir kez yazar.
 */
function insaatAdlari(): array
{
    global $db;
    $sql = 'SELECT insaat FROM is_kalemleri
            UNION
            SELECT insaat FROM kullanicilar WHERE insaat IS NOT NULL
            ORDER BY insaat';
    return $db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Bir inşaatta görevli proje müdürü ve şefler (inşaat özet sayfası için).
 */
function insaatEkibi(string $insaatAdi): array
{
    global $db;
    $sorgu = $db->prepare(
        "SELECT id, ad_soyad, rol FROM kullanicilar
         WHERE insaat = ?
         ORDER BY CASE rol WHEN 'proje_muduru' THEN 1 ELSE 2 END, ad_soyad"
    );
    $sorgu->execute([$insaatAdi]);
    return $sorgu->fetchAll();
}

/**
 * Ekip sayfasında listelenecek kullanıcılar.
 * Yönetici herkesi görür. Müdür kendi inşaatındakileri ve henüz atanmamış şefleri görür.
 */
function ekipListesi(): array
{
    global $db;
    if (yoneticiMi()) {
        $sorgu = $db->query(
            "SELECT id, ad_soyad, kullanici_adi, rol, insaat FROM kullanicilar
             ORDER BY CASE rol WHEN 'yonetici' THEN 1 WHEN 'proje_muduru' THEN 2 ELSE 3 END, insaat, ad_soyad"
        );
        return $sorgu->fetchAll();
    }
    $sorgu = $db->prepare(
        "SELECT id, ad_soyad, kullanici_adi, rol, insaat FROM kullanicilar
         WHERE insaat = ? OR (rol = 'sef' AND insaat IS NULL)
         ORDER BY CASE rol WHEN 'proje_muduru' THEN 1 ELSE 2 END, insaat DESC, ad_soyad"
    );
    $sorgu->execute([aktifKullanici()['insaat']]);
    return $sorgu->fetchAll();
}

function kullaniciBul(int $id): ?array
{
    global $db;
    $sorgu = $db->prepare('SELECT id, ad_soyad, kullanici_adi, rol, insaat FROM kullanicilar WHERE id = ?');
    $sorgu->execute([$id]);
    $kullanici = $sorgu->fetch();
    return $kullanici === false ? null : $kullanici;
}

/**
 * Yeni kişi formunu kontrol eder. Hata yoksa boş dizi döner.
 */
function kullaniciDogrula(array $veri): array
{
    global $db;
    $hatalar = [];

    if ($veri['ad_soyad'] === '') {
        $hatalar[] = 'Ad soyad boş bırakılamaz.';
    } elseif (mb_strlen($veri['ad_soyad']) > 100) {
        $hatalar[] = 'Ad soyad en fazla 100 karakter olabilir.';
    }

    // Kullanıcı adı: küçük harf, rakam, nokta veya alt çizgi; 3-30 karakter
    if (preg_match('/^[a-z0-9._]{3,30}$/', $veri['kullanici_adi']) !== 1) {
        $hatalar[] = 'Kullanıcı adı 3-30 karakter olmalı; sadece küçük harf (Türkçe karakter olmadan), rakam, nokta ve alt çizgi içerebilir.';
    } else {
        $sorgu = $db->prepare('SELECT COUNT(*) FROM kullanicilar WHERE kullanici_adi = ?');
        $sorgu->execute([$veri['kullanici_adi']]);
        if ((int) $sorgu->fetchColumn() > 0) {
            $hatalar[] = '"' . $veri['kullanici_adi'] . '" kullanıcı adı zaten kullanılıyor.';
        }
    }

    if (mb_strlen($veri['sifre']) < 6) {
        $hatalar[] = 'Şifre en az 6 karakter olmalıdır.';
    }

    // Arayüzden yönetici eklenemez; müdür sadece şef ekleyebilir
    $izinliRoller = yoneticiMi() ? ['proje_muduru', 'sef'] : ['sef'];
    if (!in_array($veri['rol'], $izinliRoller, true)) {
        $hatalar[] = 'Lütfen geçerli bir görev seçin.';
    }

    if ($veri['rol'] === 'proje_muduru' && $veri['insaat'] === '') {
        $hatalar[] = 'Proje müdürünün sorumlu olacağı inşaatı girin.';
    }
    if (mb_strlen($veri['insaat']) > 150) {
        $hatalar[] = 'İnşaat adı en fazla 150 karakter olabilir.';
    }

    return $hatalar;
}

/**
 * Yeni kullanıcı ekler. Şifre password_hash() ile şifrelenerek saklanır.
 * İnşaat boşsa NULL yazılır (şef henüz bir inşaata atanmamış demektir).
 */
function kullaniciEkle(array $veri): void
{
    global $db;
    $sorgu = $db->prepare('INSERT INTO kullanicilar (ad_soyad, kullanici_adi, sifre, rol, insaat) VALUES (?, ?, ?, ?, ?)');
    $sorgu->execute([
        $veri['ad_soyad'],
        $veri['kullanici_adi'],
        password_hash($veri['sifre'], PASSWORD_DEFAULT),
        $veri['rol'],
        $veri['insaat'] === '' ? null : $veri['insaat'],
    ]);
}

/**
 * Aktif kullanıcı bu kişinin atamasını değiştirebilir mi?
 *   Yönetici: kendisi hariç herkesin.
 *   Müdür: sadece kendi inşaatındaki veya henüz atanmamış şeflerin.
 */
function atamaYapabilirMi(array $hedef): bool
{
    $ben = aktifKullanici();
    if ($ben === null || (int) $hedef['id'] === $ben['id'] || $hedef['rol'] === 'yonetici') {
        return false;
    }
    if (yoneticiMi()) {
        return true;
    }
    if (mudurMu()) {
        return $hedef['rol'] === 'sef' && ($hedef['insaat'] === null || $hedef['insaat'] === $ben['insaat']);
    }
    return false;
}

/**
 * Kişiyi bir inşaata atar. $insaat boş ise görevden alınır (NULL).
 */
function kullaniciAta(int $id, string $insaat): void
{
    global $db;
    $sorgu = $db->prepare('UPDATE kullanicilar SET insaat = ? WHERE id = ?');
    $sorgu->execute([$insaat === '' ? null : $insaat, $id]);
}
