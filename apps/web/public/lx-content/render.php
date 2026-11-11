<?php
// Statik sayfayı, admin panelinde yapılan metin/görsel değişiklikleriyle birlikte sunar.
// .htaccess sayfa isteklerini buraya yönlendirir. Bir hata olursa sayfa değişmeden gönderilir.
require __DIR__ . '/lib.php';
require __DIR__ . '/hotels.php';

$path = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
if (!is_string($path) || $path === '') $path = '/';

function lx_not_found()
{
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    $nf = LX_ROOT . '/404.html';
    if (is_file($nf)) readfile($nf);
    exit;
}

// Oteller listesi ve otel sayfaları
if (preg_match('#^(/[a-z]{2})?/oteller/[a-z0-9-]+$#', $path)) { header('Location: ' . $path . '/', true, 301); exit; }
if (preg_match('#^(/[a-z]{2})?/oteller/#', $path)) {
    try {
        $out = lx_render_hotels($path, lx_load_content());
    } catch (Throwable $e) {
        $out = null;
    }
    if ($out === false) lx_not_found();
    if (is_string($out)) {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        echo $out;
        exit;
    }
}

$file = lx_file_for($path);
if ($file === null) lx_not_found();

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
$html = (string) file_get_contents($file);
try {
    $out = lx_apply($html, $path, lx_load_content());
    if (lx_base_path($path) === '/' || lx_base_path($path) === '/index.html') $out = lx_home_rail($out, lx_lang_of($path));
} catch (Throwable $e) {
    $out = $html;
}
echo $out;
