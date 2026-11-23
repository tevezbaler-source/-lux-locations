<?php
declare(strict_types=1);
// Site ve uygulamadaki rezervasyon formlarının talep kopyası buraya gelir ve "Gelen talepler" panelinde listelenir.
// Müşteriye asıl gönderim WhatsApp'tan yapılır; burası yalnızca kayıt tutar ve e-posta bildirimi yollar.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notifications.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

function reply(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body);
    exit;
}

function text($value, int $max): string {
    if (!is_string($value)) return '';
    $value = trim(str_replace("\0", '', $value));
    return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(405, ['ok' => false]);
$raw = file_get_contents('php://input', false, null, 0, 20000);
$in = json_decode((string) $raw, true);
if (!is_array($in)) reply(400, ['ok' => false]);

$name = text($in['full_name'] ?? '', 120);
$email = text($in['email'] ?? '', 160);
$phone = text($in['phone'] ?? '', 40);
$message = text($in['message'] ?? '', 4000);
if ($name === '' || ($email === '' && $phone === '') || $message === '') reply(422, ['ok' => false]);
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';

$category = text($in['category'] ?? '', 30);
if ($category === 'konaklama') $category = 'oda';
if (!isset(CATEGORIES[$category])) $category = 'diger';
$source = ($in['source'] ?? '') === 'app' ? 'Uygulama' : 'Site';
$lang = preg_replace('/[^a-z]/', '', strtolower(text($in['lang'] ?? '', 5)));
$title = text($in['title'] ?? '', 120);
$header = $source . ($lang ? ' · ' . strtoupper($lang) : '') . ($title ? ' · ' . $title : '');

try {
    $db = database();
    // Kaba kuvvet ve spam koruması: aynı IP'den 15 dakikada en fazla 10 talep
    $bucket = intdiv(time(), 900);
    $client = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
    $db->prepare('DELETE FROM limits WHERE bucket < ?')->execute([$bucket - 1]);
    $db->prepare('INSERT OR IGNORE INTO limits VALUES (?, ?, ?, 0)')->execute(['request', $bucket, $client]);
    $db->prepare('UPDATE limits SET hits=hits+1 WHERE kind=? AND bucket=? AND client=?')->execute(['request', $bucket, $client]);
    $read = $db->prepare('SELECT hits FROM limits WHERE kind=? AND bucket=? AND client=?');
    $read->execute(['request', $bucket, $client]);
    if ((int) $read->fetchColumn() > 10) reply(429, ['ok' => false]);

    $id = bin2hex(random_bytes(12));
    $db->prepare('INSERT INTO requests (id, created_at, category, name, email, phone, travel_date, message) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$id, time(), $category, $name, $email, $phone, text($in['travel_date'] ?? '', 60), $header . "\n\n" . $message]);
} catch (Throwable $e) {
    error_log('Lux request save failed: ' . get_class($e));
    reply(503, ['ok' => false]);
}

// Yanıtı hemen gönder, e-posta bildirimi arkadan gitsin (müşteri beklemesin)
echo json_encode(['ok' => true, 'id' => $id]);
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
ignore_user_abort(true);
notifyNewRequest($db, $id, $category);
