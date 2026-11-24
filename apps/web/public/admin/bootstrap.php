<?php
declare(strict_types=1);

const LUX_SETUP_HASH = 'e8413689870ffe669e5945a82c6bb033766343a0df36ac854baca01fdac75831';
const CATEGORIES = ['oda'=>'Oda rezervasyonu','grup'=>'Grup rezervasyonu','kongre'=>'Toplantı ve kongre','tur'=>'Özel tur','bogaz'=>'Boğaz tekne turu','transfer'=>'Transfer','organizasyon'=>'Organizasyon','kiralama'=>'Kiralama','rehberlik'=>'Rehberlik','diger'=>'Diğer'];
const STATUSES = ['new'=>'Yeni','review'=>'İnceleniyor','quoted'=>'Teklif iletildi','confirmed'=>'Onaylandı','closed'=>'Kapandı'];

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
session_name('LuxRequests');
session_start(['cookie_secure'=>true,'cookie_httponly'=>true,'cookie_samesite'=>'Lax','use_strict_mode'=>true]);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

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

function escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function field(string $key, int $max = 4000): string {
    $value = $_POST[$key] ?? '';
    return is_string($value) && strlen($value) <= $max ? trim($value) : '';
}

function post(): bool {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return false;
    if (!hash_equals($_SESSION['csrf'], field('csrf', 64))) {
        http_response_code(403);
        exit('Oturum süresi dolmuş olabilir. Sayfayı yenileyip tekrar deneyin.');
    }
    return true;
}

function csrf(): void {
    echo '<input type="hidden" name="csrf" value="' . escape($_SESSION['csrf']) . '">';
}

function limited(PDO $db, string $kind, int $max): bool {
    $bucket = intdiv(time(), 900);
    $client = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
    $db->prepare('DELETE FROM limits WHERE bucket < ?')->execute([$bucket - 1]);
    $db->prepare('INSERT OR IGNORE INTO limits VALUES (?, ?, ?, 0)')->execute([$kind,$bucket,$client]);
    $db->prepare('UPDATE limits SET hits=hits+1 WHERE kind=? AND bucket=? AND client=?')->execute([$kind,$bucket,$client]);
    $read = $db->prepare('SELECT hits FROM limits WHERE kind=? AND bucket=? AND client=?');
    $read->execute([$kind,$bucket,$client]);
    return (int)$read->fetchColumn() > $max;
}

function redirect(string $url): void {
    header('Location: ' . $url, true, 303);
    exit;
}

function startPage(string $title, string $section): void {
    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . escape($title) . ' | Lux Locations</title><link rel="stylesheet" href="/admin/panel.css"></head><body><header><a href="/">LUX LOCATIONS <small>TOURS &amp; TRAVEL</small></a><a href="/">Siteye dön</a></header><main><p class="eyebrow">' . escape($section) . '</p><h1>' . escape($title) . '</h1>';
}

function options(array $options, string $selected = ''): void {
    foreach ($options as $value => $label) {
        echo '<option value="' . escape($value) . '"' . ($selected === $value ? ' selected' : '') . '>' . escape($label) . '</option>';
    }
}


