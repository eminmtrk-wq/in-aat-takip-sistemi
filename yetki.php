<?php
/*
 * yetki.php — Kullanıcı girişi, roller ve yetki fonksiyonları
 * functions.php tarafından çağrılır.
 *
 * Roller:
 *   yonetici      → Her şeyi görür ve yapar; her kullanıcıyı her inşaata atayabilir.
 *   proje_muduru  → Sorumlu olduğu inşaatı görür; o inşaatta iş kalemi ekler, düzenler, siler;
 *                   yeni şantiye şefi ekler ve şefleri kendi inşaatına atar / görevden alır.
 *   sef           → Sadece atandığı inşaatı görür; iş kalemlerini düzenler,
 *                   günlük not ve fotoğraf ekler. Yeni iş ekleyemez, silemez.
 */

const ROLLER = [
    'yonetici'     => 'Yönetici',
    'proje_muduru' => 'Proje Müdürü',
    'sef'          => 'Şantiye Şefi',
];

/**
 * Kullanıcı adı ve şifreyi kontrol eder. Doğruysa kullanıcının id'sini oturuma kaydeder.
 */
function girisYap(string $kullaniciAdi, string $sifre): bool
{
    global $db;
    $sorgu = $db->prepare('SELECT * FROM kullanicilar WHERE kullanici_adi = ?');
    $sorgu->execute([$kullaniciAdi]);
    $kullanici = $sorgu->fetch();

    // password_verify: girilen şifreyi veritabanındaki şifrelenmiş (hash) değerle karşılaştırır
    if ($kullanici === false || !password_verify($sifre, $kullanici['sifre'])) {
        return false;
    }

    // Oturum kimliğini yenile (oturum sabitleme saldırısına karşı)
    session_regenerate_id(true);

    // Oturumda sadece id tutulur; ad, rol ve inşaat her sayfada veritabanından okunur.
    // Böylece bir şef başka inşaata atandığında değişiklik hemen geçerli olur.
    $_SESSION['kullanici_id'] = (int) $kullanici['id'];
    aktifKullanici(true); // Bu sayfada daha önce "giriş yok" diye okunmuş olabilir, yeniden oku
    return true;
}

/**
 * Giriş yapmış kullanıcıyı veritabanından okuyup döndürür, yoksa null.
 * static değişken: aynı sayfa içinde ikinci kez çağrıldığında veritabanına tekrar gitmez.
 * $yenile = true verilirse bilgiyi veritabanından yeniden okur (örneğin girişten hemen sonra).
 */
function aktifKullanici(bool $yenile = false): ?array
{
    global $db;
    static $kullanici = false; // false = henüz okunmadı

    if ($kullanici === false || $yenile) {
        $kullanici = null;
        $id = $_SESSION['kullanici_id'] ?? null;
        if (is_int($id)) {
            $sorgu = $db->prepare('SELECT id, ad_soyad, rol, insaat FROM kullanicilar WHERE id = ?');
            $sorgu->execute([$id]);
            $satir = $sorgu->fetch();
            if ($satir !== false && array_key_exists($satir['rol'], ROLLER)) {
                $satir['id'] = (int) $satir['id'];
                $kullanici = $satir;
            }
        }
    }
    return $kullanici;
}

function yoneticiMi(): bool
{
    $kullanici = aktifKullanici();
    return $kullanici !== null && $kullanici['rol'] === 'yonetici';
}

function mudurMu(): bool
{
    $kullanici = aktifKullanici();
    return $kullanici !== null && $kullanici['rol'] === 'proje_muduru';
}

/**
 * İş kalemi ekleyip silebilir mi? Yönetici ve proje müdürü.
 * (Müdür zaten sadece kendi inşaatının kayıtlarını görebildiği için başka inşaata dokunamaz.)
 */
function isYonetebilirMi(): bool
{
    return yoneticiMi() || mudurMu();
}

/**
 * Ekip sayfasını görebilir ve şef ekleyebilir mi? Yönetici ve proje müdürü.
 */
function ekipYonetebilirMi(): bool
{
    return yoneticiMi() || mudurMu();
}

/**
 * Giriş yapılmamışsa giriş sayfasına gönderir. Korunan her sayfanın başında çağrılır.
 */
function girisGerekli(): void
{
    if (aktifKullanici() === null) {
        header('Location: giris.php');
        exit;
    }
}

/**
 * Yetkisi olmayan kullanıcıyı mesajla ana sayfaya gönderir.
 */
function yetkiGerekli(bool $yetkiVar): void
{
    girisGerekli();
    if (!$yetkiVar) {
        mesajAyarla('hata', 'Bu işlem için yetkiniz yok.');
        header('Location: index.php');
        exit;
    }
}

/**
 * Kullanıcı bu iş kalemini görebilir/düzenleyebilir mi?
 * Yönetici hepsini, müdür ve şef sadece kendi inşaatındakileri.
 */
function kayitErisimVarMi(array $kayit): bool
{
    if (yoneticiMi()) {
        return true;
    }
    $kullanici = aktifKullanici();
    return $kullanici !== null && $kullanici['insaat'] !== null && $kayit['insaat'] === $kullanici['insaat'];
}

/**
 * Not veya fotoğrafı silebilir mi? Yönetici ve müdür hepsini, diğerleri kendi eklediğini.
 * (Müdür zaten sadece kendi inşaatının notlarını görür.)
 */
function silebilirMi(array $satir): bool
{
    $kullanici = aktifKullanici();
    return isYonetebilirMi() || ($kullanici !== null && (int) $satir['kullanici_id'] === $kullanici['id']);
}

function rolAdi(string $rol): string
{
    return ROLLER[$rol] ?? $rol;
}
