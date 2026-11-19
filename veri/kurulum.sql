-- İnşaat Takip Sistemi — veritabanı kurulumu (SQLite)
-- Bu dosyayı elle çalıştırmanıza gerek yok: db.php, veritabanı dosyası
-- (veri/insaat_takip.sqlite) yoksa bu komutları otomatik çalıştırır.
-- Örnek verilere dönmek için veri/insaat_takip.sqlite dosyasını silip sayfayı yenileyin.

-- ------------------------------------------------------------
-- Kullanıcılar
-- rol: 'yonetici'     her şeyi yapabilir.
--      'proje_muduru' "insaat" sütunundaki inşaatı yönetir, şef ekler ve atar.
--      'sef'          sadece "insaat" sütunundaki inşaatı görür ve düzenler.
-- insaat NULL ise kişi henüz bir inşaata atanmamıştır.
-- Şifreler password_hash() ile şifrelenmiş olarak saklanır.
-- ------------------------------------------------------------
CREATE TABLE kullanicilar (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    ad_soyad       TEXT NOT NULL,
    kullanici_adi  TEXT NOT NULL UNIQUE,
    sifre          TEXT NOT NULL,
    rol            TEXT NOT NULL,
    insaat         TEXT NULL                 -- Kişinin çalıştığı inşaat (yöneticide boş)
);

-- ------------------------------------------------------------
-- İş kalemleri
-- ------------------------------------------------------------
CREATE TABLE is_kalemleri (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    baslik     TEXT NOT NULL,                -- İş kalemi adı
    insaat     TEXT NOT NULL,                -- Hangi inşaat (proje adı)
    bolge      TEXT NOT NULL,                -- Coğrafi bölge
    konum      TEXT NOT NULL,                -- İl / ilçe / adres
    kategori   TEXT NOT NULL,
    sorumlu    TEXT NOT NULL,
    ilerleme   INTEGER NOT NULL DEFAULT 0,   -- 0-100 arası yüzde
    durum      TEXT NOT NULL,
    baslangic  TEXT NOT NULL,                -- Başlangıç tarihi (YYYY-AA-GG)
    bitis      TEXT NOT NULL,                -- Planlanan bitiş tarihi (YYYY-AA-GG)
    butce      REAL NOT NULL DEFAULT 0,      -- Planlanan bütçe (TL)
    harcanan   REAL NOT NULL DEFAULT 0,      -- Şu ana kadar harcanan (TL)
    aciklama   TEXT,
    olusturma  TEXT DEFAULT (datetime('now', 'localtime'))
);

-- ------------------------------------------------------------
-- Günlük şantiye defteri
-- Her not bir iş kalemine (is_id) ve onu yazan kullanıcıya (kullanici_id) bağlıdır.
-- ON DELETE CASCADE: iş kalemi silinince notları da otomatik silinir.
-- ------------------------------------------------------------
CREATE TABLE gunluk_notlar (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    is_id         INTEGER NOT NULL,
    kullanici_id  INTEGER NOT NULL,
    tarih         TEXT NOT NULL,
    isci_sayisi   INTEGER NOT NULL DEFAULT 0,
    hava          TEXT NOT NULL,
    not_metni     TEXT NOT NULL,
    olusturma     TEXT DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (is_id)        REFERENCES is_kalemleri(id) ON DELETE CASCADE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id)
);

-- ------------------------------------------------------------
-- Fotoğraflar (dosyanın kendisi uploads/ klasöründe, burada sadece adı tutulur)
-- ------------------------------------------------------------
CREATE TABLE fotograflar (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    is_id         INTEGER NOT NULL,
    kullanici_id  INTEGER NOT NULL,
    dosya_adi     TEXT NOT NULL,
    aciklama      TEXT NOT NULL DEFAULT '',
    olusturma     TEXT DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (is_id)        REFERENCES is_kalemleri(id) ON DELETE CASCADE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id)
);

-- ------------------------------------------------------------
-- Örnek veriler
-- Kullanıcılar: admin / admin123, selin / mudur123, mehmet / sef123, burak / sef123
-- ------------------------------------------------------------
INSERT INTO kullanicilar (ad_soyad, kullanici_adi, sifre, rol, insaat) VALUES
('Sistem Yöneticisi', 'admin',  '$2y$10$MWEfUTnKo7EI1tNQcJ8rg.cfmjzWRqTAkbY2jjYiKe6H/COp9KjWG', 'yonetici', NULL),
('Mehmet Kaya',       'mehmet', '$2y$10$v2IlT8fKaTmtpEZnW6cU.Od9rWWPhl1s1kVTfgYrzcnk0yy8izB46', 'sef',      'Yıldız Konutları A Blok'),
('Selin Aydın',       'selin',  '$2y$10$N54mx635WwqhwYh.B4ilI.XX/d7078LO5qyFSyTTnODIagpO7TGi2', 'proje_muduru', 'Yıldız Konutları A Blok'),
('Burak Şahin',       'burak',  '$2y$10$1mIEAhUtyYxKbmqy57IXWOy.kq9ja7p7muUsBHdriXakDLNlEYuja', 'sef',      NULL);
INSERT INTO is_kalemleri (baslik, insaat, bolge, konum, kategori, sorumlu, ilerleme, durum, baslangic, bitis, butce, harcanan, aciklama) VALUES
('Temel betonu dökümü',    'Yıldız Konutları A Blok', 'Marmara',    'İstanbul / Başakşehir, Kayabaşı Mah.', 'Kaba İnşaat',     'Ahmet Yılmaz',  100, 'Tamamlandı',   '2026-09-01', '2026-09-20', 850000, 912500, 'C30 beton kullanıldı, 3 gün kür uygulandı.'),
('2. kat kolon kalıpları', 'Yıldız Konutları A Blok', 'Marmara',    'İstanbul / Başakşehir, Kayabaşı Mah.', 'Kaba İnşaat',     'Mehmet Kaya',   60,  'Devam Ediyor', '2026-10-15', '2026-11-10', 420000, 265000, 'Demir bağlama işi bitti, kalıp kuruluyor.'),
('Çatı izolasyonu',        'Yıldız Konutları A Blok', 'Marmara',    'İstanbul / Başakşehir, Kayabaşı Mah.', 'Kaba İnşaat',     'Mehmet Kaya',   0,   'Planlandı',    '2026-11-20', '2026-11-24', 180000, 0,      'Hava durumuna bağlı.'),
('Elektrik tesisatı',      'Merkez Ofis Binası',      'İç Anadolu', 'Ankara / Çankaya, Kızılay',            'Elektrik',        'Ayşe Demir',    35,  'Devam Ediyor', '2026-11-01', '2026-12-31', 300000, 98000,  'Zemin kat kablo kanalları çekildi.'),
('Sıva ve alçı işleri',    'Merkez Ofis Binası',      'İç Anadolu', 'Ankara / Çankaya, Kızılay',            'İnce İşler',      'Ali Çelik',     0,   'Planlandı',    '2027-01-05', '2027-02-15', 260000, 0,      'Malzeme siparişi verildi.'),
('Bahçe peyzaj düzeni',    'Ege Sahil Villaları',     'Ege',        'İzmir / Urla, İskele Mah.',            'Çevre Düzenleme', 'Zeynep Arslan', 0,   'Planlandı',    '2027-03-01', '2027-04-01', 150000, 0,      'Kaba inşaat bittikten sonra başlanacak.');
INSERT INTO gunluk_notlar (is_id, kullanici_id, tarih, isci_sayisi, hava, not_metni) VALUES
(2, 2, '2026-11-05', 14, 'Güneşli',  '1. ve 2. aks kolon demirleri bağlandı.'),
(2, 2, '2026-11-08', 0,  'Yağmurlu', 'Şiddetli yağmur nedeniyle çalışılamadı. Kalıp malzemesi korumaya alındı.'),
(2, 2, '2026-11-12', 12, 'Bulutlu',  'Kalıp kurulumuna başlandı. Gecikme yağmur ve geç gelen kalıp malzemesinden kaynaklı.'),
(4, 1, '2026-11-15', 6,  'Güneşli',  'Zemin kat kablo kanalları tamamlandı, 1. kata geçildi.');
