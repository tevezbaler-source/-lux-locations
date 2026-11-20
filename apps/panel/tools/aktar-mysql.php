<?php
// PC'deki SQLite verisini (data/panel.sqlite) sunucudaki MySQL veritabanına aktarır. Bir kez çalıştırılır.
// Sunucuda: config.php 'driver' => 'mysql' olmalı, data/panel.sqlite dosyası yüklenmiş olmalı.
// Çalıştırma: php tools/aktar-mysql.php   (Plesk: Zamanlanmış Görevler → PHP betiği → Şimdi çalıştır)
// MySQL'de kayıt varsa hiçbir şey yapmaz (üzerine yazmaz). Bitince data/panel.sqlite dosyasını silin.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('LX_PANEL', 1);
date_default_timezone_set('Europe/Istanbul');
require dirname(__DIR__) . '/lib/db.php';

$src = $argv[1] ?? dirname(__DIR__) . '/data/panel.sqlite';
if (!is_file($src)) { fwrite(STDERR, "Kaynak dosya yok: $src\n"); exit(1); }
if ((lx_config()['driver'] ?? '') !== 'mysql') { fwrite(STDERR, "config.php içinde 'driver' => 'mysql' değil.\n"); exit(1); }

$from = lx_open_sqlite($src);
$to = db_main(); // tabloları da oluşturur

$tables = ['users', 'hotels', 'hotel_rates', 'hotel_allotments', 'agencies', 'bookings', 'booking_rooms',
    'booking_free', 'booking_services', 'fx_rates', 'notes', 'audit_log'];

foreach ($tables as $t) {
    if ((int)$to->query("SELECT COUNT(*) FROM $t")->fetchColumn() > 0) {
        fwrite(STDERR, "MySQL'de '$t' tablosunda zaten kayıt var. Aktarma yapılmadı (üzerine yazılmaz).\n");
        exit(1);
    }
}

$maxIds = [];
$to->beginTransaction();
try {
    $to->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $t) {
        $exists = $from->query("SELECT name FROM sqlite_master WHERE type='table' AND name=" . $from->quote($t))->fetchColumn();
        if (!$exists) { echo "$t: kaynakta yok, atlandı\n"; continue; }
        $myCols = array_column($to->query("SHOW COLUMNS FROM $t")->fetchAll(), 'Field');
        $rows = $from->query("SELECT * FROM $t ORDER BY id")->fetchAll();
        $n = 0;
        foreach ($rows as $r) {
            $r = array_intersect_key($r, array_flip($myCols));
            $cols = array_keys($r);
            $sql = "INSERT INTO $t (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
            $to->prepare($sql)->execute(array_values($r));
            $n++;
        }
        if ($n) $maxIds[$t] = (int)$to->query("SELECT MAX(id) FROM $t")->fetchColumn();
        echo "$t: $n kayıt\n";
    }
    $to->exec('SET FOREIGN_KEY_CHECKS = 1');
    $to->commit();
} catch (Throwable $e) {
    if ($to->inTransaction()) $to->rollBack();
    fwrite(STDERR, 'Hata: ' . $e->getMessage() . "\n");
    exit(1);
}
// Yeni kayıtların numarası aktarılanların arkasından devam etsin
foreach ($maxIds as $t => $max) $to->exec("ALTER TABLE $t AUTO_INCREMENT = " . ($max + 1));
echo "Aktarma bitti. Şimdi sunucudaki data/panel.sqlite dosyasını silin.\n";
