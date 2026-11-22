<?php
// Mobil uygulamanın canlı içerik adresi (yalnızca okuma):
//   https://luxlocationstours.com/lx-content/app-api.php?lang=en
// Panelde (/admin/site/) yapılan değişiklikler uygulamaya mağaza güncellemesi gerekmeden buradan gelir:
//  - yayındaki oteller (data/hotels.json), o dilde
//  - metin değişiklikleri (eski metin => yeni metin) ve değiştirilen/gizlenen görseller
//  - site sayfalarının başlık, açıklama ve kapak görseli
// Giriş bilgisi, yedekler ve yayında olmayan oteller dışarı verilmez.
require __DIR__ . '/lib.php';
require __DIR__ . '/hotels.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');

$langs = lx_langs();
$lang = isset($_GET['lang']) && is_string($_GET['lang']) && isset($langs[$_GET['lang']]) ? $_GET['lang'] : 'tr';

// Veri değişmediyse uygulama 304 alır, hiç indirmez.
$stamp = 0;
foreach (array(LX_DATA . '/hotels.json', LX_DATA . '/content.json', __FILE__, __DIR__ . '/hotels-i18n.php') as $f) {
    if (is_file($f)) $stamp = max($stamp, (int) filemtime($f));
}
foreach (lx_pages() as $base => $name) {
    $f = lx_file_for(lx_page_path($lang, $base));
    if ($f !== null) $stamp = max($stamp, (int) filemtime($f));
}
$etag = '"lx-' . $lang . '-' . $stamp . '"';
header('ETag: ' . $etag);
header('Cache-Control: no-cache');
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    exit;
}

function lx_api_meta($html)
{
    $out = array('title' => '', 'description' => '', 'image' => '');
    if (preg_match('#<title>(.*?)</title>#s', $html, $m)) $out['title'] = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (preg_match('#<meta name="description" content="([^"]*)"#', $html, $m)) $out['description'] = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (preg_match('#<meta property="og:image" content="([^"]+)"#', $html, $m)) {
        $out['image'] = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    } else {
        $body = ($p = strpos($html, '</header>')) !== false ? substr($html, $p) : $html;
        if (preg_match('#<img\b[^>]*\bsrc="([^"]+\.(?:jpe?g|png|webp)[^"]*)"#i', $body, $m)) $out['image'] = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return $out;
}

try {
    $content = lx_load_content();
    $wa = '905396114190';

    // Site sayfaları (uygulamada sayfanın kendisi açılır; listede başlık + görsel gösterilir)
    $pages = array();
    foreach (lx_pages() as $base => $name) {
        if ($base === '/oteller/') continue; // oteller uygulamada kendi ekranında
        $path = lx_page_path($lang, $base);
        $file = lx_file_for($path);
        if ($file === null) continue;
        $html = (string) file_get_contents($file);
        if ($base === '/') $wa = lx_wa_number($html);
        $html = lx_apply($html, $path, $content);
        $pages[] = array('id' => trim($base, '/') === '' ? 'home' : trim($base, '/'), 'path' => $path) + lx_api_meta($html);
    }

    $hotels = array();
    foreach (lx_hotels_published() as $h) {
        list($city) = lx_hotel_parts($h, $lang);
        $desc = trim(lx_tr_field($h['description'], $lang));
        $gals = array();
        foreach ($h['galleries'] as $g) {
            $photos = array();
            foreach ($g['photos'] as $i => $p) $photos[] = array('src' => $p, 'caption' => lx_tr_field($g['caps'][$i], $lang));
            if ($photos) $gals[] = array('title' => lx_tr_field($g['title'], $lang), 'photos' => $photos);
        }
        $hotels[] = array(
            'slug' => $h['slug'],
            'name' => $h['name'],
            'stars' => (int) $h['stars'],
            'city' => $city,
            'summary' => lx_tr_field($h['summary'], $lang),
            'description' => $desc !== '' ? $desc : lx_tr_field($h['summary'], $lang),
            'features' => lx_tr_lines($h['features'], $lang),
            'amenities' => lx_tr_lines($h['amenities'], $lang),
            'rooms' => lx_hotel_rooms($h, $lang),
            'address' => (string) $h['address'],
            'map' => (string) $h['map'],
            'website' => (string) $h['website'],
            'booking' => (string) $h['booking'],
            'cover' => (string) $h['cover'],
            'coverAi' => !empty($h['coverAi']),
            'galleries' => $gals,
            'path' => lx_hotel_url($lang, $h['slug']),
        );
    }

    $hidden = array();
    foreach ((isset($content['hidden']) ? (array) $content['hidden'] : array()) as $src => $on) if ($on) $hidden[] = (string) $src;

    echo json_encode(array(
        'ok' => true,
        'version' => 1,
        'lang' => $lang,
        'updated' => $stamp,
        'whatsapp' => $wa,
        'texts' => isset($content['texts'][$lang]) ? (object) $content['texts'][$lang] : new stdClass(),
        'images' => (object) (isset($content['images']) ? $content['images'] : array()),
        'hidden' => $hidden,
        'ui' => lx_hotel_ui($lang),
        'pages' => $pages,
        'hotels' => $hotels,
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo '{"ok":false}';
}
