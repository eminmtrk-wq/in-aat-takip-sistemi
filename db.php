<?php
/*
 * db.php — Veritabanı bağlantısı
 *
 * Veritabanı olarak SQLite kullanılır: tüm veriler proje klasöründeki
 * veri/insaat_takip.sqlite dosyasında saklanır. Ayrı bir veritabanı sunucusu
 * (MySQL) kurmaya veya phpMyAdmin'den içe aktarmaya gerek yoktur.
 *
 * Dosya yoksa (ilk çalıştırma) veri/kurulum.sql içindeki komutlar çalıştırılır;
 * tablolar ve örnek kayıtlar otomatik oluşturulur.
 *
 * Bağlantı PDO ile yapıldığı için ileride MySQL'e geçmek gerekirse
 * sadece bu dosyadaki bağlantı satırının değiştirilmesi yeterlidir.
 */

const VERITABANI_DOSYASI = __DIR__ . '/veri/insaat_takip.sqlite';
const KURULUM_DOSYASI    = __DIR__ . '/veri/kurulum.sql';

// PHP'de SQLite desteği açık mı?
if (!extension_loaded('pdo_sqlite')) {
    die('<h2>SQLite desteği kapalı.</h2>'
        . '<p>XAMPP Control Panel → Apache satırında <b>Config → PHP (php.ini)</b> dosyasını açın, '
        . '<code>;extension=pdo_sqlite</code> satırının başındaki <b>;</b> işaretini silin, kaydedin ve Apache\'yi yeniden başlatın.</p>');
}

// Dosya henüz yoksa bu ilk çalıştırmadır
$ilkKurulum = !file_exists(VERITABANI_DOSYASI);

try {
    $db = new PDO('sqlite:' . VERITABANI_DOSYASI, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Hata olursa istisna fırlat
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Sonuçları ilişkisel dizi olarak al
    ]);

    // SQLite'ta yabancı anahtar (FOREIGN KEY / ON DELETE CASCADE) kontrolü varsayılan olarak kapalıdır, açıyoruz
    $db->exec('PRAGMA foreign_keys = ON');

    if ($ilkKurulum) {
        // Tabloları ve örnek verileri tek seferde oluştur. Bir hata olursa hiçbiri yazılmasın diye transaction.
        $db->beginTransaction();
        $db->exec(file_get_contents(KURULUM_DOSYASI));
        $db->commit();
    }
} catch (PDOException $e) {
    // Kurulum yarıda kaldıysa bozuk dosyayı sil ki bir sonraki açılışta baştan kurulsun
    if ($ilkKurulum) {
        $db = null;
        @unlink(VERITABANI_DOSYASI);
    }
    die('<h2>Veritabanı açılamadı.</h2>'
        . '<p><b>veri</b> klasörünün proje içinde olduğundan ve yazılabilir olduğundan emin olun.</p>'
        . '<p><small>Hata: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</small></p>');
}
