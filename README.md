"""3.sınıfım alttan alıyorum dersi bilgilendirmek için yazdım bu kismı"""

# İnşaat Takip Sistemi

**Ders:** İnternet Programcılığı I (PHP)
**Öğrenci:** _[Öğrenci No – Ad Soyad]_

## Projenin amacı

İnşaat Takip Sistemi, şantiyelerdeki iş kalemlerini (temel dökümü, elektrik tesisatı, sıva vb.) kaydetmek; ilerlemelerini, bütçelerini ve teslim tarihlerini izlemek için hazırlanmış PHP tabanlı bir kayıt ve takip uygulamasıdır. Kayıtlar proje klasörünün içindeki bir **SQLite** veritabanı dosyasında (`veri/insaat_takip.sqlite`) saklanır; ayrı bir veritabanı sunucusu kurmaya gerek yoktur. Yönetici, proje müdürü ve şantiye şefi olmak üzere üç kullanıcı rolü vardır.

## Özellikler

### Ödevin zorunlu özellikleri

| Özellik | Açıklama | Dosya |
|---|---|---|
| Ana sayfa | Uygulama adı, açıklaması ve menü | `index.php`, `header.php` |
| Listeleme | Kayıtlar veritabanından çekilip `foreach` ile kart olarak listelenir. Her kayıtta 13 alan vardır | `index.php` |
| Kayıt ekleme | HTML formu, **POST** yöntemiyle gönderilir, `INSERT` ile eklenir | `ekle.php` |
| Veri doğrulama | Boş alanlar, geçersiz seçimler, 0–100 dışı ilerleme, durum–ilerleme tutarsızlığı, geçersiz tarih/tutar PHP tarafında kontrol edilir | `functions.php` → `kayitDogrula()` |
| Kayıt silme | **POST** ile, `DELETE` sorgusu | `sil.php` |
| Filtreleme | Bölge, kategori, durum ve "sadece gecikenler" — **GET** yöntemiyle | `index.php` |
| Özet bilgiler | Toplam, tamamlanan, devam eden, planlanan ve geciken sayaçları | `index.php` |
| Güvenli çıktı | Kullanıcı verileri `htmlspecialchars()` ile gösterilir | `functions.php` → `guvenli()` |

### Ek özellikler

| Özellik | Açıklama | Dosya |
|---|---|---|
| SQLite veritabanı | Tek dosyalık veritabanı, ilk açılışta otomatik kurulur. PDO ve hazırlanmış sorgular (SQL Injection koruması) | `db.php`, `veri/kurulum.sql`, `functions.php` |
| Kullanıcı girişi | `password_hash` / `password_verify` ile şifre kontrolü, oturum yönetimi | `giris.php`, `cikis.php`, `yetki.php` |
| Yetkilendirme | **Yönetici** her şeyi yapar. **Proje müdürü** kendi inşaatında iş ekler/siler, şef ekler ve şefleri inşaatına atar. **Şantiye şefi** sadece atandığı inşaatı görür; iş kalemlerini düzenler, not ve fotoğraf ekler | `yetki.php` |
| Ekip ve atamalar | Yönetici proje müdürü ve şef ekler, herkesi her inşaata atar. Proje müdürü yeni şef ekler (kendi inşaatına), boştaki şefi "Ekibime ata" ile alır, kendi şefini görevden alır. Atama değişikliği hemen geçerli olur | `kullanicilar.php` |
| Kayıt düzenleme | `ekle.php?id=3` formu doldurur, `UPDATE` çalışır | `ekle.php` |
| Arama | İş adı, inşaat, konum veya sorumlu içinde `LIKE` ile arama | `index.php` |
| Sıralama | Bitiş tarihi, ilerleme, ad veya bütçeye göre | `index.php`, `SIRALAMALAR` sabiti |
| Tarih ve gecikme | Bitiş tarihi geçmiş, tamamlanmamış işler kırmızı ve "X gün gecikti" ile gösterilir | `gecikmeVarMi()`, `sureMetni()` |
| Yaklaşan işler | 7 gün içinde bitmesi gereken işler ana sayfanın üstünde listelenir | `yaklasanIsler()` |
| Bütçe takibi | Planlanan / harcanan tutar, kullanım yüzdesi, bütçe aşımı uyarısı | `butceAsildiMi()`, `paraGoster()` |
| İnşaat özetleri | Her inşaatın ekibi, genel ilerlemesi, bütçesi, geciken işleri ve son notları (`GROUP BY` ile) | `insaatlar.php`, `insaat.php` |
| Şantiye defteri | İş kalemine tarihli not (işçi sayısı, hava durumu, açıklama), kim yazdığıyla (`JOIN`) | `detay.php` |
| Fotoğraf yükleme | JPG/PNG/WEBP, en fazla 5 MB, içerik `getimagesize()` ile kontrol edilir, rastgele dosya adı verilir | `detay.php`, `fotoYukle()` |
| Excel'e aktarma | Filtrelenmiş liste CSV olarak indirilir (Türkçe Excel uyumlu) | `disa_aktar.php` |
| PDF rapor | Yazdırılabilir rapor sayfası; tarayıcıda "PDF olarak kaydet" | `rapor.php` |

## Tasarım

Arayüz bir şantiyenin görsel dilinden yola çıkar:

- **Renkler:** beton grisi zemin, çelik koyu gri üst çubuk, iş güvenliği sarısı butonlar ve ilerleme, pafta (mavi kopya) mavisi bütçe, sinyal kırmızısı sadece gecikme ve bütçe aşımı için.
- **Şerit metre:** ilerleme çubuklarında her %10'da bir çentik, %50'de uzun çentik vardır.
- **İkaz şeridi:** sarı-siyah çapraz şerit yalnızca geciken iş kalemlerinde ve gecikmesi olan inşaatlarda görünür; anlam taşır, süs değildir.
- **Yazı tipleri:** büyük başlıklar ve sayaç rakamları için Anton (şantiye tabelası), gövde için Fira Sans. Dosyalar `assets/fonts/` içindedir; internet bağlantısı olmadan da çalışır (SIL Open Font License).
- **Erişilebilirlik:** klavye odağı her zaman görünür, "hareketi azalt" ayarı açık olanlarda çubuk animasyonu kapanır, mobil uyumludur.

## Veritabanı tabloları

| Tablo | İçerik | İlişki |
|---|---|---|
| `kullanicilar` | Ad soyad, kullanıcı adı, şifre (hash), rol (yonetici / proje_muduru / sef), çalıştığı inşaat (boşsa atanmamış) | — |
| `is_kalemleri` | İş kalemlerinin tüm bilgileri | — |
| `gunluk_notlar` | Şantiye defteri notları | `is_id` → `is_kalemleri.id`, `kullanici_id` → `kullanicilar.id` |
| `fotograflar` | Yüklenen fotoğrafların dosya adları | `is_id` → `is_kalemleri.id`, `kullanici_id` → `kullanicilar.id` |

İş kalemi silindiğinde notları ve fotoğraf kayıtları `ON DELETE CASCADE` ile otomatik silinir; diskteki resim dosyalarını `kayitSil()` fonksiyonu siler.

## Klasör yapısı

```
insaat-takip/
├── index.php            Ana sayfa: sayaçlar, yaklaşan işler, arama/filtre/sıralama, liste
├── ekle.php             Kayıt ekleme/düzenleme formu + Düzenle/Sil butonlu tablo
├── sil.php              Kayıt silme (sadece yönetici, sadece POST)
├── detay.php            İş kalemi detayı, şantiye defteri ve fotoğraflar
├── insaatlar.php        Tüm inşaatların özeti
├── insaat.php           Tek inşaatın özet sayfası
├── rapor.php            Yazdırılabilir / PDF rapor
├── disa_aktar.php       Excel (CSV) indirme
├── kullanicilar.php     Ekip: kişi ekleme ve inşaata atama
├── giris.php            Kullanıcı girişi
├── cikis.php            Çıkış
├── functions.php        Sabitler ve tüm fonksiyonlar (bölümlere ayrılmış)
├── yetki.php            Giriş ve yetki fonksiyonları
├── db.php               Veritabanı bağlantısı (PDO + SQLite) ve otomatik kurulum
├── veri/
│   ├── kurulum.sql      Tablolar ve örnek veriler (ilk açılışta otomatik çalışır)
│   ├── insaat_takip.sqlite   Veritabanı dosyası (ilk açılışta oluşur)
│   └── .htaccess        Veritabanı dosyasının tarayıcıdan indirilmesini engeller
├── header.php / footer.php   Ortak üst ve alt kısım
├── assets/
│   ├── style.css        Tasarım (mobil uyumlu, yazdırma görünümü dahil)
│   └── fonts/           Anton ve Fira Sans yazı tipleri + lisansları
├── uploads/             Yüklenen fotoğraflar (.htaccess ile PHP çalıştırma kapalı)
├── ekran_goruntuleri/   Uygulama ekran görüntüleri
└── README.md
```

## XAMPP üzerinde çalıştırma

1. XAMPP Control Panel'de **Apache**'yi başlatın (MySQL gerekmez).
2. `insaat-takip` klasörünü `C:\xampp\htdocs\` içine kopyalayın.
3. `http://localhost/insaat-takip/` adresini açın. İlk açılışta veritabanı dosyası, 4 tablo ve örnek veriler otomatik oluşturulur. Giriş yapın:
   - **admin** / admin123 — Yönetici
   - **selin** / mudur123 — Proje Müdürü (Yıldız Konutları A Blok)
   - **mehmet** / sef123 — Şantiye Şefi (Yıldız Konutları A Blok)
   - **burak** / sef123 — Şantiye Şefi (henüz atanmamış; atama özelliğini denemek için)

Örnek verilere dönmek için `veri/insaat_takip.sqlite` dosyasını silip sayfayı yenileyin.

"SQLite desteği kapalı" uyarısı çıkarsa: XAMPP Control Panel → Apache → **Config → PHP (php.ini)** → `;extension=pdo_sqlite` satırının başındaki `;` işaretini silin, kaydedin, Apache'yi yeniden başlatın.

## Ekran görüntüleri

| Dosya | İçerik |
|---|---|
| `0_giris.png` | Giriş sayfası |
| `1_ana_sayfa_listeleme.png` | Ana sayfa, yaklaşan işler, sayaçlar, gecikme ve bütçe aşımı uyarıları |
| `2a_hatali_giris.png` | Boş form gönderildiğinde hata mesajları |
| `2b_yeni_kayit_formu.png` | Doldurulmuş yeni kayıt formu |
| `2c_basarili_kayit_ve_tablo.png` | Başarılı kayıt ve Düzenle/Sil butonlu tablo |
| `3a_duzenleme.png` | Düzenleme ekranı |
| `3b_filtre_ve_siralama.png` | Marmara bölgesi, ilerlemeye göre sıralı |
| `4_arama.png` | "Ankara" araması |
| `5_detay_defter_fotograf.png` | Detay sayfası: bütçe, şantiye defteri, fotoğraf |
| `6_insaatlar.png` | Tüm inşaatların özeti |
| `7_insaat_ozet.png` | Tek inşaatın özet sayfası |
| `8_rapor.png`, `8b_rapor_yazdirma_gorunumu.png` | Rapor ve PDF/yazdırma görünümü |
| `9_sef_gorunumu.png` | Şantiye şefinin sadece kendi inşaatını görmesi |
| `10_mobil_gorunum.png` | Telefon ekranında ana sayfa |
| `11_ekip_yonetici.png` | Yöneticinin ekip ve atama ekranı |
| `12_ekip_proje_muduru.png` | Proje müdürünün ekibi: şef ekleme, ekibe atama, görevden alma |

## Bilinen eksikler

- Kullanıcı silme ve şifre değiştirme ekranı yok.
- Bir proje müdürü veya şef aynı anda yalnızca bir inşaata atanabiliyor.
- Formlarda CSRF koruması yok.
- PDF, tarayıcının yazdırma özelliğiyle üretiliyor (ayrı bir PDF kütüphanesi kullanılmadı).

## Yapay zekâ kullanımı

- **Kullanılan araç:** Claude (Anthropic)
- **Kullanım amacı:** Proje iskeleti, PHP fonksiyonları, SQL sorguları ve tablo tasarımı, arayüz tasarımı ve CSS, kod açıklamaları ve README taslağı.
- **Örnek istemler:** "Ödev belgesindeki zorunlu özelliklere göre PHP ile bir inşaat takip sistemi yapar mısın?", "Kayıtlar yerel veritabanında kalsın; tarih, gecikme uyarısı ve arama ekle.", "Bütçe takibi, inşaat özeti, şantiye defteri, fotoğraf, kullanıcı girişi, rapor, sıralama ve yaklaşan işler özelliklerini ekle."
- **Kendi katkım:** _[Burayı kendiniz doldurun: örneğin kodu satır satır inceleyip anladığınız kısımlar, değiştirdiğiniz kategoriler/örnek kayıtlar, eklediğiniz alanlar, düzelttiğiniz hatalar, yaptığınız testler.]_




