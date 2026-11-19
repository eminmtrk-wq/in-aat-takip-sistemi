-- İnşaat Takip Sistemi veritabanı
-- phpMyAdmin > "İçe Aktar" (Import) sekmesinden bu dosyayı seçip çalıştırın.
-- DİKKAT: Tekrar içe aktarmak tüm tabloları silip örnek verilerle yeniden oluşturur.

CREATE DATABASE IF NOT EXISTS insaat_takip
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_turkish_ci;

USE insaat_takip;

-- Bağlı tablolar önce silinmeli (yabancı anahtar sırası)
DROP TABLE IF EXISTS fotograflar;
DROP TABLE IF EXISTS gunluk_notlar;
DROP TABLE IF EXISTS is_kalemleri;
DROP TABLE IF EXISTS kullanicilar;

-- ------------------------------------------------------------
-- Kullanıcılar
-- rol: 'yonetici' her şeyi yapabilir.
--      'sef' sadece "insaat" sütunundaki inşaatı görür ve düzenler.
-- Şifreler password_hash() ile şifrelenmiş olarak saklanır.
-- ------------------------------------------------------------
CREATE TABLE kullanicilar (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    ad_soyad       VARCHAR(100) NOT NULL,
    kullanici_adi  VARCHAR(50)  NOT NULL UNIQUE,
    sifre          VARCHAR(255) NOT NULL,
    rol            VARCHAR(20)  NOT NULL,
    insaat         VARCHAR(150) NULL            -- Şefin sorumlu olduğu inşaat (yöneticide boş)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- admin / admin123   ve   mehmet / sef123
INSERT INTO kullanicilar (ad_soyad, kullanici_adi, sifre, rol, insaat) VALUES
('Sistem Yöneticisi', 'admin',  '$2y$10$MWEfUTnKo7EI1tNQcJ8rg.cfmjzWRqTAkbY2jjYiKe6H/COp9KjWG', 'yonetici', NULL),
('Mehmet Kaya',       'mehmet', '$2y$10$v2IlT8fKaTmtpEZnW6cU.Od9rWWPhl1s1kVTfgYrzcnk0yy8izB46', 'sef',      'Yıldız Konutları A Blok');

-- ------------------------------------------------------------
-- İş kalemleri
-- ------------------------------------------------------------
CREATE TABLE is_kalemleri (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    baslik     VARCHAR(100) NOT NULL,              -- İş kalemi adı
    insaat     VARCHAR(150) NOT NULL,              -- Hangi inşaat (proje adı)
    bolge      VARCHAR(30)  NOT NULL,              -- Coğrafi bölge
    konum      VARCHAR(200) NOT NULL,              -- İl / ilçe / adres
    kategori   VARCHAR(50)  NOT NULL,
    sorumlu    VARCHAR(100) NOT NULL,
    ilerleme   TINYINT UNSIGNED NOT NULL DEFAULT 0, -- 0-100 arası yüzde
    durum      VARCHAR(20)  NOT NULL,
    baslangic  DATE NOT NULL,                      -- Başlangıç tarihi
    bitis      DATE NOT NULL,                      -- Planlanan bitiş tarihi
    butce      DECIMAL(12,2) NOT NULL DEFAULT 0,   -- Planlanan bütçe (TL)
    harcanan   DECIMAL(12,2) NOT NULL DEFAULT 0,   -- Şu ana kadar harcanan (TL)
    aciklama   TEXT,
    olusturma  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

INSERT INTO is_kalemleri (baslik, insaat, bolge, konum, kategori, sorumlu, ilerleme, durum, baslangic, bitis, butce, harcanan, aciklama) VALUES
('Temel betonu dökümü',    'Yıldız Konutları A Blok', 'Marmara',    'İstanbul / Başakşehir, Kayabaşı Mah.', 'Kaba İnşaat',     'Ahmet Yılmaz',  100, 'Tamamlandı',   '2026-09-01', '2026-09-20', 850000, 912500, 'C30 beton kullanıldı, 3 gün kür uygulandı.'),
('2. kat kolon kalıpları', 'Yıldız Konutları A Blok', 'Marmara',    'İstanbul / Başakşehir, Kayabaşı Mah.', 'Kaba İnşaat',     'Mehmet Kaya',   60,  'Devam Ediyor', '2026-10-15', '2026-11-10', 420000, 265000, 'Demir bağlama işi bitti, kalıp kuruluyor.'),
('Çatı izolasyonu',        'Yıldız Konutları A Blok', 'Marmara',    'İstanbul / Başakşehir, Kayabaşı Mah.', 'Kaba İnşaat',     'Mehmet Kaya',   0,   'Planlandı',    '2026-11-20', '2026-11-24', 180000, 0,      'Hava durumuna bağlı.'),
('Elektrik tesisatı',      'Merkez Ofis Binası',      'İç Anadolu', 'Ankara / Çankaya, Kızılay',            'Elektrik',        'Ayşe Demir',    35,  'Devam Ediyor', '2026-11-01', '2026-12-31', 300000, 98000,  'Zemin kat kablo kanalları çekildi.'),
('Sıva ve alçı işleri',    'Merkez Ofis Binası',      'İç Anadolu', 'Ankara / Çankaya, Kızılay',            'İnce İşler',      'Ali Çelik',     0,   'Planlandı',    '2027-01-05', '2027-02-15', 260000, 0,      'Malzeme siparişi verildi.'),
('Bahçe peyzaj düzeni',    'Ege Sahil Villaları',     'Ege',        'İzmir / Urla, İskele Mah.',            'Çevre Düzenleme', 'Zeynep Arslan', 0,   'Planlandı',    '2027-03-01', '2027-04-01', 150000, 0,      'Kaba inşaat bittikten sonra başlanacak.');

-- ------------------------------------------------------------
-- Günlük şantiye defteri
-- Her not bir iş kalemine (is_id) ve onu yazan kullanıcıya (kullanici_id) bağlıdır.
-- ON DELETE CASCADE: iş kalemi silinince notları da otomatik silinir.
-- ------------------------------------------------------------
CREATE TABLE gunluk_notlar (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    is_id         INT NOT NULL,
    kullanici_id  INT NOT NULL,
    tarih         DATE NOT NULL,
    isci_sayisi   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    hava          VARCHAR(20) NOT NULL,
    not_metni     TEXT NOT NULL,
    olusturma     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (is_id)        REFERENCES is_kalemleri(id) ON DELETE CASCADE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

INSERT INTO gunluk_notlar (is_id, kullanici_id, tarih, isci_sayisi, hava, not_metni) VALUES
(2, 2, '2026-11-05', 14, 'Güneşli',  '1. ve 2. aks kolon demirleri bağlandı.'),
(2, 2, '2026-11-08', 0,  'Yağmurlu', 'Şiddetli yağmur nedeniyle çalışılamadı. Kalıp malzemesi korumaya alındı.'),
(2, 2, '2026-11-12', 12, 'Bulutlu',  'Kalıp kurulumuna başlandı. Gecikme yağmur ve geç gelen kalıp malzemesinden kaynaklı.'),
(4, 1, '2026-11-15', 6,  'Güneşli',  'Zemin kat kablo kanalları tamamlandı, 1. kata geçildi.');

-- ------------------------------------------------------------
-- Fotoğraflar (dosyanın kendisi uploads/ klasöründe, burada sadece adı tutulur)
-- ------------------------------------------------------------
CREATE TABLE fotograflar (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    is_id         INT NOT NULL,
    kullanici_id  INT NOT NULL,
    dosya_adi     VARCHAR(100) NOT NULL,
    aciklama      VARCHAR(200) NOT NULL DEFAULT '',
    olusturma     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (is_id)        REFERENCES is_kalemleri(id) ON DELETE CASCADE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
