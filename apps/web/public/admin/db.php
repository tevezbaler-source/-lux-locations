<?php
declare(strict_types=1);
// Form uç noktasının (talep.php) kullandığı talepler veritabanı: bootstrap.php'deki database() ve sabitlerin
// oturum/başlık göndermeyen kopyası. Canlı panel dosyalarına dokunmamak için ayrı tutuldu; değişirse ikisini birlikte güncelleyin.

const CATEGORIES = ['oda'=>'Oda rezervasyonu','grup'=>'Grup rezervasyonu','kongre'=>'Toplantı ve kongre','tur'=>'Özel tur','bogaz'=>'Boğaz tekne turu','transfer'=>'Transfer','organizasyon'=>'Organizasyon','kiralama'=>'Kiralama','rehberlik'=>'Rehberlik','diger'=>'Diğer'];
const STATUSES = ['new'=>'Yeni','review'=>'İnceleniyor','quoted'=>'Teklif iletildi','confirmed'=>'Onaylandı','closed'=>'Kapandı'];

function database(): PDO {
    static $db;
    if ($db) return $db;
    $root = realpath($_SERVER['DOCUMENT_ROOT']);
    if (!$root) throw new RuntimeException('Invalid document root');
    $directory = dirname($root) . '/lux-private';
    if (!is_dir($directory) && !@mkdir($directory, 0700) && !is_dir($directory)) {
        throw new RuntimeException('Private directory unavailable');
    }
    if (realpath($directory) !== $directory) throw new RuntimeException('Invalid private directory');
    $db = new PDO('sqlite:' . $directory . '/requests.sqlite', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA busy_timeout=5000');
    $db->exec("CREATE TABLE IF NOT EXISTS requests (
      id TEXT PRIMARY KEY, created_at INTEGER NOT NULL, category TEXT NOT NULL, name TEXT NOT NULL,
      email TEXT NOT NULL, phone TEXT NOT NULL, travel_date TEXT NOT NULL, message TEXT NOT NULL,
      status TEXT NOT NULL DEFAULT 'new', note TEXT NOT NULL DEFAULT '', revision INTEGER NOT NULL DEFAULT 0
    );
    CREATE INDEX IF NOT EXISTS requests_time ON requests(created_at DESC, id);
    CREATE TABLE IF NOT EXISTS owner (id INTEGER PRIMARY KEY CHECK(id=1), password TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS limits (kind TEXT, bucket INTEGER, client TEXT, hits INTEGER NOT NULL, PRIMARY KEY(kind,bucket,client));");
    return $db;
}
