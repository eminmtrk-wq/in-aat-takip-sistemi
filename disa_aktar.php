<?php
/*
 * disa_aktar.php — Filtrelenmiş iş kalemlerini Excel'de açılabilen CSV dosyası olarak indirir.
 * Ana sayfadaki filtreler adres çubuğu (GET) üzerinden buraya da aktarılır.
 */
require_once 'functions.php';
girisGerekli();

$kayitlar = kayitlariFiltrele(filtreleriOku());

$dosyaAdi = 'insaat_is_kalemleri_' . date('Y-m-d') . '.csv';

// Tarayıcıya bunun bir sayfa değil, indirilecek bir dosya olduğunu söyleyen başlıklar
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $dosyaAdi . '"');

// php://output: yazılanlar doğrudan tarayıcıya gönderilir
$cikti = fopen('php://output', 'w');

// BOM: Excel'in Türkçe karakterleri (ş, ğ, İ...) doğru göstermesi için
fwrite($cikti, "\xEF\xBB\xBF");

// Türkçe Excel ayırıcı olarak noktalı virgül (;) bekler
fputcsv($cikti, ['No', 'İş Kalemi', 'İnşaat', 'Bölge', 'Konum', 'Kategori', 'Sorumlu', 'Durum',
                 'İlerleme (%)', 'Başlangıç', 'Bitiş', 'Süre', 'Bütçe (TL)', 'Harcanan (TL)', 'Bütçe Kullanımı (%)', 'Açıklama'], ';');

foreach ($kayitlar as $kayit) {
    fputcsv($cikti, [
        $kayit['id'],
        $kayit['baslik'],
        $kayit['insaat'],
        $kayit['bolge'],
        $kayit['konum'],
        $kayit['kategori'],
        $kayit['sorumlu'],
        $kayit['durum'],
        $kayit['ilerleme'],
        tarihGoster($kayit['baslangic']),
        tarihGoster($kayit['bitis']),
        sureMetni($kayit),
        number_format((float) $kayit['butce'], 2, ',', ''),
        number_format((float) $kayit['harcanan'], 2, ',', ''),
        butceKullanimYuzdesi((float) $kayit['butce'], (float) $kayit['harcanan']),
        $kayit['aciklama'],
    ], ';');
}

fclose($cikti);
exit;
