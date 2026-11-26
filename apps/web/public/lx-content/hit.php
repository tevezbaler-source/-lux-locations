<?php
// Çerezsiz ziyaret sayacı. Sayfalar her açılışta buraya küçük bir istek gönderir.
// Kimseyi tanımaz: IP adresi, çerez veya kimlik tutulmaz; yalnızca aylık toplamlar
// (gün, sayfa, gelinen site, dil, cihaz, ülke, WhatsApp/telefon tıklamaları) lx-content/data/stats/AAAA-AA.json dosyasına yazılır.
// Sonuçlar ekip panelinde: /admin/site/istatistik.php
require __DIR__ . '/lib.php';

header('Cache-Control: no-store');
header('Content-Type: text/plain; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|headless|lighthouse|pagespeed|facebookexternalhit|whatsapp|telegram|curl|wget|python|monitor/i', $ua)) { http_response_code(204); exit; }

$in = json_decode((string) file_get_contents('php://input', false, null, 0, 2048), true);
if (!is_array($in)) { http_response_code(400); exit; }

// Sayfa: yalnızca yol, en fazla 120 karakter
$path = isset($in['p']) ? (string) $in['p'] : '/';
$path = rawurldecode(parse_url($path, PHP_URL_PATH) ?: '/');
if ($path === '' || $path[0] !== '/' || strlen($path) > 120 || preg_match('#^/(admin|lx-content|panel)/#', $path)) { http_response_code(204); exit; }
$path = preg_replace('#/index\.html$#', '/', $path);

// Gelinen site: yalnızca alan adı; kendi sitemizden geçişler sayılmaz
$ref = '';
if (!empty($in['r'])) {
    $h = strtolower((string) parse_url((string) $in['r'], PHP_URL_HOST));
    $h = preg_replace('/^(www\.|m\.|l\.|lm\.)/', '', $h);
    if ($h !== '' && strpos($h, 'luxlocationstours.com') === false && preg_match('/^[a-z0-9.-]{3,80}$/', $h)) $ref = $h;
}
$ref = $ref === '' ? '(doğrudan)' : $ref;

$lang = lx_lang_of($path);
$dev = (isset($in['w']) && (int) $in['w'] > 0 && (int) $in['w'] < 760) || preg_match('/Mobi|Android|iPhone/i', $ua) ? 'Telefon' : 'Bilgisayar';
$cc = '';
foreach (array('GEOIP_COUNTRY_CODE', 'HTTP_CF_IPCOUNTRY', 'MM_COUNTRY_CODE') as $k) {
    if (!empty($_SERVER[$k]) && preg_match('/^[A-Z]{2}$/', $_SERVER[$k])) { $cc = $_SERVER[$k]; break; }
}
if ($cc === '' && isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && preg_match('/^[a-z]{2,3}-([A-Z]{2})/', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $m)) $cc = $m[1] . '*';

$dir = LX_DATA . '/stats';
if (!is_dir($dir)) @mkdir($dir, 0755, true);
$file = $dir . '/' . date('Y-m') . '.json';
$fp = @fopen($file, 'c+');
if (!$fp) { http_response_code(500); exit; }
flock($fp, LOCK_EX);
$d = json_decode((string) stream_get_contents($fp), true);
if (!is_array($d)) $d = array();
$add = function ($key, $name, $max = 500) use (&$d) {
    if (!isset($d[$key]) || !is_array($d[$key])) $d[$key] = array();
    if (!isset($d[$key][$name])) {
        if (count($d[$key]) >= $max) $name = '(diğer)';
        if (!isset($d[$key][$name])) $d[$key][$name] = 0;
    }
    $d[$key][$name]++;
};
// Tıklama (WhatsApp, telefon, e-posta): sayfa görüntülemesi sayılmaz, hangi sayfadan iletişime geçildiği tutulur
$ev = isset($in['e']) ? (string) $in['e'] : '';
$evNames = array('wa' => 'WhatsApp', 'tel' => 'Telefon', 'mail' => 'E-posta');
if ($ev !== '') {
    if (isset($evNames[$ev])) { $add('clicks', $evNames[$ev] . ' · ' . $path); $add('clickDays', date('Y-m-d')); }
} else {
$add('days', date('Y-m-d'));
$add('pages', $path);
$add('refs', $ref);
$add('langs', $lang);
$add('devices', $dev);
if ($cc !== '') $add('countries', $cc, 250);
}
ftruncate($fp, 0);
rewind($fp);
fwrite($fp, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);
http_response_code(204);
