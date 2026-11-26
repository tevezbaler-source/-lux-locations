<?php
// /sitemap.xml: Google'a sitedeki tüm sayfaları 11 dilde bildirir (.htaccess buraya yönlendirir).
// Panelden eklenen/yayından kaldırılan oteller her istekte güncel listelenir; elle bir şey yapmak gerekmez.
require __DIR__ . '/lib.php';
require __DIR__ . '/hotels.php';

$abs = 'https://luxlocationstours.com';
$langs = array_keys(lx_langs());

// Sayfa yolu (dil önekisiz) => son değişiklik tarihi (diskteki Türkçe HTML'den)
$bases = array();
foreach (array_keys(lx_pages()) as $base) {
    $f = lx_file_for($base);
    if ($f === null && $base !== '/oteller/') continue;
    $bases[$base] = $f !== null ? filemtime($f) : time();
}
// Hizmet ve destinasyon sayfaları (panelden düzenlenmez, lx_pages() listesinde yok): diskte ne varsa eklenir.
$extra = array('/destinasyonlar/');
foreach (array('hizmetler', 'destinasyonlar') as $dir) {
    foreach ((array) glob(LX_ROOT . '/' . $dir . '/*/index.html') as $f) $extra[] = '/' . $dir . '/' . basename(dirname($f)) . '/';
}
foreach ($extra as $base) {
    $f = lx_file_for($base);
    if ($f !== null) $bases[$base] = filemtime($f);
}
try {
    $hotelsFile = LX_DATA . '/hotels.json';
    $hm = is_file($hotelsFile) ? filemtime($hotelsFile) : time();
    $hotels = lx_hotels_published();
    if ($hotels) $bases['/oteller/'] = max(isset($bases['/oteller/']) ? $bases['/oteller/'] : 0, $hm);
    foreach ($hotels as $h) $bases['/oteller/' . $h['slug'] . '/'] = $hm;
} catch (Throwable $e) {
}
$cf = LX_DATA . '/content.json';
$cm = is_file($cf) ? filemtime($cf) : 0;

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
$x = function ($s) { return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8'); };
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
foreach ($bases as $base => $mtime) {
    $alts = '';
    foreach ($langs as $l) $alts .= '    <xhtml:link rel="alternate" hreflang="' . $l . '" href="' . $x($abs . lx_page_path($l, $base)) . '"/>' . "\n";
    $alts .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . $x($abs . lx_page_path('en', $base)) . '"/>' . "\n";
    $last = date('Y-m-d', max($mtime, $cm));
    foreach ($langs as $l) {
        echo "  <url>\n    <loc>" . $x($abs . lx_page_path($l, $base)) . "</loc>\n    <lastmod>" . $last . "</lastmod>\n" . $alts . "  </url>\n";
    }
}
echo "</urlset>\n";
