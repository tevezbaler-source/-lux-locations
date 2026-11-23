<?php
// Veritabanı bağlantısı ve şema. SQLite (PC/eğitim) ve MySQL (sunucu) ile aynı SQL çalışır.
if (!defined('LX_PANEL')) { http_response_code(403); exit; }

function lx_config(): array {
    static $cfg = null;
    if ($cfg === null) {
        $file = dirname(__DIR__) . '/config.php';
        if (!is_file($file)) $file = dirname(__DIR__) . '/config.sample.php';
        $cfg = require $file;
    }
    return $cfg;
}

function lx_open_sqlite(string $path): PDO {
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    return $pdo;
}

/** Ana (gerçek) veritabanı: kullanıcılar ve gerçek kayıtlar. */
function db_main(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = lx_config();
    if (($c['driver'] ?? 'sqlite') === 'mysql') {
        $dsn = 'mysql:host=' . $c['mysql_host'] . ';dbname=' . $c['mysql_db'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $c['mysql_user'], $c['mysql_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } else {
        $pdo = lx_open_sqlite($c['sqlite_path']);
    }
    lx_migrate($pdo, true);
    return $pdo;
}

/** Eğitim veritabanı: her zaman ayrı SQLite dosyası, örnek verilerle. */
function db_training(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $path = lx_config()['training_path'];
    $new = !is_file($path);
    $pdo = lx_open_sqlite($path);
    lx_migrate($pdo, false);
    if ($new) {
        require_once __DIR__ . '/sample.php';
        lx_load_sample($pdo);
    }
    return $pdo;
}

function training_reset(): void {
    $path = lx_config()['training_path'];
    foreach ([$path, $path . '-wal', $path . '-shm'] as $f) if (is_file($f)) @unlink($f);
}

/** İş verileri için bağlantı: oturum eğitim modundaysa eğitim veritabanı. */
function db(): PDO {
    return (!empty($_SESSION['egitim'])) ? db_training() : db_main();
}

function is_mysql(PDO $pdo): bool {
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
}

function now(): string { return date('Y-m-d H:i:s'); }

function q(string $sql, array $p = [], ?PDO $pdo = null): PDOStatement {
    $pdo = $pdo ?: db();
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return $st;
}
function q_all(string $sql, array $p = [], ?PDO $pdo = null): array { return q($sql, $p, $pdo)->fetchAll(); }
function q_one(string $sql, array $p = [], ?PDO $pdo = null): ?array { $r = q($sql, $p, $pdo)->fetch(); return $r ?: null; }
function q_val(string $sql, array $p = [], ?PDO $pdo = null) { $r = q($sql, $p, $pdo)->fetchColumn(); return $r === false ? null : $r; }

function lx_migrate(PDO $pdo, bool $withUsers): void {
    $my = is_mysql($pdo);
    $pk = $my ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $txt = $my ? 'TEXT' : 'TEXT';
    $tail = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci' : '';
    // Her iş tablosunda kim/ne zaman/sürüm alanları
    $meta = "version INT NOT NULL DEFAULT 1, created_by INT NULL, created_at VARCHAR(19) NULL, updated_by INT NULL, updated_at VARCHAR(19) NULL";

    $tables = [];
    if ($withUsers) {
        $tables['users'] = "id $pk, username VARCHAR(60) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL, pass_hash VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL, active INT NOT NULL DEFAULT 1, created_at VARCHAR(19) NULL, last_login VARCHAR(19) NULL";
        $tables['login_attempts'] = "id $pk, ip VARCHAR(64) NOT NULL, username VARCHAR(60) NULL, at VARCHAR(19) NOT NULL";
    }
    $tables['hotels'] = "id $pk, name VARCHAR(160) NOT NULL, stars INT NULL, region VARCHAR(60) NULL, address VARCHAR(255) NULL, contacts $txt NULL, email VARCHAR(255) NULL, notes $txt NULL, active INT NOT NULL DEFAULT 1, $meta";
    $tables['hotel_rates'] = "id $pk, hotel_id INT NOT NULL, date_from VARCHAR(10) NOT NULL, date_to VARCHAR(10) NOT NULL, currency VARCHAR(3) NOT NULL, board VARCHAR(4) NULL, sngl DECIMAL(12,2) NULL, dbl DECIMAL(12,2) NULL, twin DECIMAL(12,2) NULL, trpl DECIMAL(12,2) NULL, quad DECIMAL(12,2) NULL, invoiced INT NOT NULL DEFAULT 1, free_every INT NULL, free_type VARCHAR(5) NULL, contact VARCHAR(120) NULL, note $txt NULL, $meta";
    $tables['hotel_allotments'] = "id $pk, hotel_id INT NOT NULL, date_from VARCHAR(10) NOT NULL, date_to VARCHAR(10) NOT NULL, rooms INT NOT NULL, note $txt NULL, $meta";
    $tables['agencies'] = "id $pk, name VARCHAR(160) NOT NULL, country VARCHAR(60) NULL, aliases VARCHAR(255) NULL, owner VARCHAR(120) NULL, contact $txt NULL, payment_terms $txt NULL, notes $txt NULL, active INT NOT NULL DEFAULT 1, $meta";
    $tables['bookings'] = "id $pk, code VARCHAR(30) NULL, hotel_id INT NOT NULL, agency_id INT NULL, check_in VARCHAR(10) NOT NULL, check_out VARCHAR(10) NOT NULL, board VARCHAR(4) NULL, status VARCHAR(20) NOT NULL, rooms_reserved INT NULL, rooms_option INT NULL, option_until VARCHAR(10) NULL, hotel_conf_code VARCHAR(60) NULL, buy_cur VARCHAR(3) NOT NULL, sell_cur VARCHAR(3) NOT NULL, sell_mode VARCHAR(4) NOT NULL DEFAULT 'room', eur_try DECIMAL(12,4) NULL, usd_try DECIMAL(12,4) NULL, buy_note $txt NULL, notes $txt NULL, cancel_reason $txt NULL, prev_booking_id INT NULL, deleted INT NOT NULL DEFAULT 0, $meta";
    $tables['booking_rooms'] = "id $pk, booking_id INT NOT NULL, room_type VARCHAR(5) NOT NULL, qty INT NOT NULL, buy_price DECIMAL(12,2) NULL, sell_price DECIMAL(12,2) NULL";
    $tables['booking_free'] = "id $pk, booking_id INT NOT NULL, direction VARCHAR(3) NOT NULL, room_type VARCHAR(5) NOT NULL, qty INT NOT NULL";
    $tables['booking_services'] = "id $pk, booking_id INT NOT NULL, kind VARCHAR(30) NOT NULL, description VARCHAR(255) NULL, buy DECIMAL(12,2) NULL, sell DECIMAL(12,2) NULL, currency VARCHAR(3) NOT NULL";
    $tables['booking_guests'] = "id $pk, booking_id INT NOT NULL, room_no INT NOT NULL DEFAULT 1, room_type VARCHAR(5) NULL, name VARCHAR(160) NOT NULL, pax VARCHAR(3) NOT NULL DEFAULT 'ADL', note VARCHAR(255) NULL";
    // Belgeler için ek bilgiler (fiyat kilidinden bağımsız, konfirme sonrası da düzenlenir)
    $tables['booking_extra'] = "booking_id INT NOT NULL PRIMARY KEY, contact_name VARCHAR(160) NULL, room_category VARCHAR(60) NULL, nationality VARCHAR(60) NULL, arrival_info VARCHAR(255) NULL, departure_info VARCHAR(255) NULL, hotel_note $txt NULL, voucher_note $txt NULL, updated_by INT NULL, updated_at VARCHAR(19) NULL";
    $tables['fx_rates'] = "id $pk, rate_date VARCHAR(10) NOT NULL, eur_try DECIMAL(12,4) NOT NULL, usd_try DECIMAL(12,4) NOT NULL, note VARCHAR(255) NULL, $meta";
    $tables['notes'] = "id $pk, text $txt NOT NULL, hotel_id INT NULL, agency_id INT NULL, booking_id INT NULL, due_date VARCHAR(10) NULL, done INT NOT NULL DEFAULT 0, done_by INT NULL, done_at VARCHAR(19) NULL, via VARCHAR(20) NULL, $meta";
    $tables['mail_log'] = "id $pk, booking_id INT NOT NULL, kind VARCHAR(20) NOT NULL, state VARCHAR(20) NULL, to_addr VARCHAR(500) NOT NULL, cc VARCHAR(500) NULL, subject VARCHAR(255) NULL, ok INT NOT NULL DEFAULT 0, error VARCHAR(500) NULL, user_id INT NULL, user_name VARCHAR(120) NULL, at VARCHAR(19) NOT NULL";
    $tables['audit_log'] = "id $pk, at VARCHAR(19) NOT NULL, user_id INT NULL, user_name VARCHAR(120) NULL, ip VARCHAR(64) NULL, entity VARCHAR(30) NOT NULL, entity_id INT NULL, action VARCHAR(30) NOT NULL, summary VARCHAR(255) NULL, old_json $txt NULL, new_json $txt NULL";

    foreach ($tables as $name => $cols) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS $name ($cols)$tail");
    }
    // Sonradan eklenen sütunlar (eski veritabanlarında yoksa eklenir)
    foreach (['hotels' => ['email' => 'VARCHAR(255) NULL']] as $t => $cols) {
        foreach ($cols as $c => $def) {
            try { $pdo->query("SELECT $c FROM $t LIMIT 1"); } catch (PDOException $e) { $pdo->exec("ALTER TABLE $t ADD COLUMN $c $def"); }
        }
    }
    $idx = [
        'ix_book_hotel' => 'bookings(hotel_id, check_in)',
        'ix_book_agency' => 'bookings(agency_id)',
        'ix_rooms_book' => 'booking_rooms(booking_id)',
        'ix_free_book' => 'booking_free(booking_id)',
        'ix_srv_book' => 'booking_services(booking_id)',
        'ix_guest_book' => 'booking_guests(booking_id)',
        'ix_mail_book' => 'mail_log(booking_id)',
        'ix_rates_hotel' => 'hotel_rates(hotel_id, date_from)',
        'ix_allot_hotel' => 'hotel_allotments(hotel_id, date_from)',
        'ix_audit_entity' => 'audit_log(entity, entity_id)',
    ];
    foreach ($idx as $n => $def) {
        try {
            $pdo->exec($my ? "CREATE INDEX $n ON $def" : "CREATE INDEX IF NOT EXISTS $n ON $def");
        } catch (PDOException $e) { /* MySQL: dizin zaten var */ }
    }
}
