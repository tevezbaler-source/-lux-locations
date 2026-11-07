<?php
// Statik sayfayı, admin panelinde yapılan metin/görsel değişiklikleriyle birlikte sunar.
// .htaccess sayfa isteklerini buraya yönlendirir. Bir hata olursa sayfa değişmeden gönderilir.
require __DIR__ . '/lib.php';

$path = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
if (!is_string($path) || $path === '') $path = '/';
$file = lx_file_for($path);

if ($file === null) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    $nf = LX_ROOT . '/404.html';
    if (is_file($nf)) readfile($nf);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
$html = (string) file_get_contents($file);
try {
    $out = lx_apply($html, $path, lx_load_content());
} catch (Throwable $e) {
    $out = $html;
}
echo $out;
