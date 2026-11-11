<?php
// Oteller: panelden yönetilen oteller (data/hotels.json) 11 dilde
//  - /oteller/ listesi, /oteller/<otel>/ sayfası (her dil için /en/oteller/... vb.)
//  - ana sayfadaki "İstanbul Otelleri" kayan şeridi
// Otel bir kez panele eklenir; sayfalar her istekte bu veriden, o dilin üst menüsü ve alt bilgisiyle kurulur.
// Bir alan o dilde yazılmamışsa İngilizcesi, o da yoksa Türkçesi görünür.
require_once __DIR__ . '/hotels-i18n.php';

function lx_hotels_load()
{
    $f = LX_DATA . '/hotels.json';
    if (!is_file($f)) return array();
    $d = json_decode((string) file_get_contents($f), true);
    if (!is_array($d)) return array();
    $out = array();
    foreach (array_values($d) as $h) if (is_array($h) && !empty($h['slug'])) $out[] = lx_hotel_normalize($h);
    return $out;
}

function lx_hotel_normalize($h)
{
    $def = array('id' => '', 'slug' => '', 'name' => '', 'stars' => 0, 'published' => true, 'city' => array(), 'summary' => array(),
        'description' => array(), 'features' => array(), 'amenities' => array(), 'rooms' => array(), 'address' => '', 'map' => '',
        'website' => '', 'booking' => '', 'cover' => '', 'coverAi' => false, 'galleries' => array());
    $h = array_merge($def, $h);
    $gals = array();
    foreach ((array) $h['galleries'] as $g) {
        $g = array_merge(array('title' => array(), 'photos' => array(), 'caps' => array()), (array) $g);
        $g['photos'] = array_values(array_filter((array) $g['photos'], 'is_string'));
        $caps = array();
        foreach ($g['photos'] as $i => $p) $caps[$i] = isset($g['caps'][$i]) && is_array($g['caps'][$i]) ? $g['caps'][$i] : array();
        $g['caps'] = $caps;
        $gals[] = $g;
    }
    $h['galleries'] = $gals;
    return $h;
}

function lx_hotel_default_galleries()
{
    return array(
        array('title' => array('tr' => 'Odalar', 'en' => 'Rooms', 'ar' => 'الغرف', 'ru' => 'Номера', 'de' => 'Zimmer', 'fr' => 'Chambres', 'es' => 'Habitaciones', 'it' => 'Camere', 'zh' => '客房', 'fa' => 'اتاق‌ها', 'ro' => 'Camere'), 'photos' => array(), 'caps' => array()),
        array('title' => array('tr' => 'Otel ve ortak alanlar', 'en' => 'Hotel & common areas', 'ar' => 'الفندق والمرافق العامة', 'ru' => 'Отель и общие зоны', 'de' => 'Hotel & Gemeinschaftsbereiche', 'fr' => 'Hôtel & espaces communs', 'es' => 'Hotel y zonas comunes', 'it' => 'Hotel e aree comuni', 'zh' => '酒店及公共区域', 'fa' => 'هتل و فضاهای عمومی', 'ro' => 'Hotel și spații comune'), 'photos' => array(), 'caps' => array()),
    );
}

// Çok dilli alan: seçili dil, yoksa İngilizce, yoksa Türkçe
function lx_tr_field($v, $lang)
{
    if (!is_array($v)) return (string) $v;
    foreach (array($lang, 'en', 'tr') as $l) if (isset($v[$l]) && trim((string) $v[$l]) !== '') return (string) $v[$l];
    return '';
}

// Satır satır alan (olanaklar, kart etiketleri, odalar)
function lx_tr_lines($v, $lang)
{
    $out = array();
    foreach (preg_split('/\r?\n/', lx_tr_field($v, $lang)) as $l) if (trim($l) !== '') $out[] = trim($l);
    return $out;
}

// Oda satırı: "Ad | 20 m² | Açıklama"
function lx_hotel_rooms($h, $lang)
{
    $out = array();
    foreach (lx_tr_lines($h['rooms'], $lang) as $l) {
        $p = array_map('trim', explode('|', $l));
        $out[] = array('name' => $p[0], 'size' => isset($p[1]) ? $p[1] : '', 'desc' => isset($p[2]) ? implode(' | ', array_slice($p, 2)) : '');
    }
    return $out;
}

function lx_slugify($s)
{
    $s = strtr($s, array('ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'İ' => 'i', 'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u', 'â' => 'a', 'î' => 'i', 'û' => 'u', '&' => ' '));
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return $s !== '' ? $s : 'otel';
}

function lx_hotel_ui($lang)
{
    $all = lx_hotel_strings();
    return isset($all[$lang]) ? $all[$lang] + $all['en'] : $all['en'];
}

function lx_stars($n)
{
    $n = (int) $n;
    return $n > 0 ? str_repeat('★', min(7, $n)) : '';
}

function lx_upper($s, $lang)
{
    if ($lang === 'tr') $s = strtr($s, array('i' => 'İ', 'ı' => 'I'));
    return function_exists('mb_strtoupper') ? mb_strtoupper($s, 'UTF-8') : strtoupper($s);
}

function lx_hotel_url($lang, $slug)
{
    return lx_page_path($lang, '/oteller/' . $slug . '/');
}

function lx_hotels_published()
{
    $list = array_values(array_filter(lx_hotels_load(), function ($h) { return !empty($h['published']); }));
    // Yıldız sayısı yüksekten düşüğe; aynı yıldızda paneldeki sıra
    $i = 0;
    foreach ($list as $k => $h) $list[$k]['_o'] = $i++;
    usort($list, function ($a, $b) { return $a['stars'] === $b['stars'] ? $a['_o'] - $b['_o'] : $b['stars'] - $a['stars']; });
    return $list;
}

function lx_wa_number($html)
{
    return preg_match('#https://wa\.me/(\d+)#', $html, $m) ? $m[1] : '905396114190';
}

// Konum satırının parçaları: "Laleli · Fatih · İstanbul"
function lx_hotel_parts($h, $lang)
{
    $city = lx_tr_field($h['city'], $lang);
    $parts = array_values(array_filter(array_map('trim', preg_split('/·|,/u', $city)), 'strlen'));
    return array($city, $parts);
}

// ---- Şablon: o dildeki /oteller/ sayfasının üst menüsü ve alt bilgisi ----
function lx_hotels_shell($lang, $content)
{
    $path = lx_page_path($lang, '/oteller/');
    $file = lx_file_for($path);
    if ($file === null) {
        $file = lx_file_for(lx_page_path($lang, '/'));
        if ($file === null) return null;
    }
    $html = lx_apply((string) file_get_contents($file), $path, $content);
    $open = preg_match('#<html\b[^>]*>#i', $html, $m) ? $m[0] : '<html lang="' . $lang . '">';
    $head = preg_match('#<head\b[^>]*>(.*?)</head>#is', $html, $m) ? $m[1] : '';
    $header = preg_match('#<header\b[^>]*class="site-head"[^>]*>.*?</header>#is', $html, $m) ? $m[0] : '';
    $footer = preg_match('#<footer\b.*?</footer>#is', $html, $m) ? $m[0] : '';
    // Head: betikler, betik ön yüklemeleri ve sayfaya özel etiketler çıkar; stiller ve yazı tipleri kalır
    $head = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $head);
    $head = preg_replace('#<link\b[^>]*\bas="script"[^>]*/?>#i', '', $head);
    $head = preg_replace('#<title>.*?</title>#is', '', $head);
    $head = preg_replace('#<meta\b[^>]*(name="description"|property="og:[a-z:]+")[^>]*/?>#i', '', $head);
    $head = preg_replace('#<link\b[^>]*rel="(canonical|alternate)"[^>]*/?>#i', '', $head);
    $head = preg_replace('#<link\b[^>]*hotels-v1\.css[^>]*/?>#i', '', $head);
    $head = preg_replace('#<noscript\b.*?</noscript>#is', '', $head);
    return array('open' => $open, 'head' => $head, 'header' => $header, 'footer' => $footer, 'wa' => lx_wa_number($html));
}

// Dil seçicideki bağlantıları bu sayfanın diğer dillerine yönlendirir
function lx_fix_lang_links($header, $base)
{
    return preg_replace_callback('#<a\b([^>]*?)\bhref="[^"]*"([^>]*?\bhreflang="([a-z]{2})"[^>]*)>#i', function ($m) use ($base) {
        return '<a' . $m[1] . 'href="' . lx_esc_html(lx_page_path($m[3], $base)) . '"' . $m[2] . '>';
    }, $header);
}

function lx_hotels_page($shell, $lang, $base, $title, $desc, $image, $main)
{
    $e = 'lx_esc_html';
    $abs = 'https://luxlocationstours.com';
    $meta = '<title>' . $e($title) . ' | Lux Locations Tours</title>'
        . '<meta name="description" content="' . $e($desc) . '"/>'
        . '<link rel="canonical" href="' . $e($abs . lx_page_path($lang, $base)) . '"/>';
    foreach (array_keys(lx_langs()) as $l) $meta .= '<link rel="alternate" hreflang="' . $l . '" href="' . $e($abs . lx_page_path($l, $base)) . '"/>';
    $meta .= '<link rel="alternate" hreflang="x-default" href="' . $e($abs . $base) . '"/>'
        . '<meta property="og:title" content="' . $e($title) . '"/><meta property="og:description" content="' . $e($desc) . '"/>'
        . ($image !== '' ? '<meta property="og:image" content="' . $e(strpos($image, 'http') === 0 ? $image : $abs . $image) . '"/>' : '')
        . '<link rel="stylesheet" href="/assets/hotels/hotels-v1.css?v=20261104-2"/>'
        . '<style>.hotel-inline-rooms{grid-template-columns:repeat(auto-fit,minmax(min(100%,160px),1fr));gap:12px;margin:18px 0 24px}.hotel-gallery figure[hidden]{display:none}[dir=rtl] .hotel-breadcrumb span{display:inline-block;transform:scaleX(-1)}</style>';
    $header = lx_fix_lang_links($shell['header'], $base);
    return '<!DOCTYPE html>' . $shell['open'] . '<head>' . $shell['head'] . $meta . '</head><body>'
        . $header . $main . $shell['footer'] . lx_hotels_script() . '</body></html>';
}

function lx_hotels_script()
{
    return '<script>(function(){'
        . 'var rb=[].slice.call(document.querySelectorAll("[data-stars]"));'
        . 'function f(s){rb.forEach(function(b){b.setAttribute("aria-pressed",String(b.dataset.stars===s))});var n=0;'
        . '[].forEach.call(document.querySelectorAll("[data-rating]"),function(c){c.hidden=s!=="all"&&c.dataset.rating!==s;if(!c.hidden)n++});'
        . 'var ce=document.querySelector("#hotel-count");if(ce)ce.textContent=ce.dataset.tpl.replace("%d",n);'
        . 'var em=document.querySelector("#hotel-empty");if(em)em.hidden=n>0}'
        . 'rb.forEach(function(b){b.addEventListener("click",function(){f(b.dataset.stars)})});'
        . 'var rs=document.querySelector("[data-reset-stars]");if(rs)rs.addEventListener("click",function(){f("all")});'
        . 'var cb=[].slice.call(document.querySelectorAll("[data-category-filter]"));'
        . 'cb.forEach(function(b){b.addEventListener("click",function(){cb.forEach(function(x){x.setAttribute("aria-pressed",String(x===b))});'
        . '[].forEach.call(document.querySelectorAll("[data-category]"),function(el){el.hidden=b.dataset.categoryFilter!=="all"&&el.dataset.category!==b.dataset.categoryFilter})})});'
        . 'var d=document.querySelector("#hotel-gallery-data");if(!d)return;var P=JSON.parse(d.textContent),dl=document.querySelector(".hotel-lightbox"),im=document.querySelector("#lightbox-image"),cur=0,op;'
        . 'if(!dl||!dl.showModal)return;'
        . 'function sh(i){cur=(i+P.length)%P.length;im.src=P[cur].src;im.alt=P[cur].title;document.querySelector("#lightbox-caption").textContent=P[cur].title+(P[cur].note?" · "+P[cur].note:"");document.querySelector("#lightbox-count").textContent=(cur+1)+" / "+P.length}'
        . '[].forEach.call(document.querySelectorAll("[data-gallery-index]"),function(b){b.addEventListener("click",function(){op=b;sh(Number(b.dataset.galleryIndex));dl.showModal();document.body.classList.add("gallery-is-open")})});'
        . 'document.querySelector("#lightbox-close").addEventListener("click",function(){dl.close()});'
        . 'var rtl=document.documentElement.dir==="rtl";'
        . 'document.querySelector("#lightbox-prev").addEventListener("click",function(){sh(cur-1)});document.querySelector("#lightbox-next").addEventListener("click",function(){sh(cur+1)});'
        . 'dl.addEventListener("click",function(e){if(e.target===dl)dl.close()});'
        . 'dl.addEventListener("keydown",function(e){if(e.key==="ArrowRight")sh(rtl?cur-1:cur+1);if(e.key==="ArrowLeft")sh(rtl?cur+1:cur-1)});'
        . 'dl.addEventListener("close",function(){document.body.classList.remove("gallery-is-open");if(op)op.focus()})'
        . '})()</script>';
}

function lx_wa_link($num, $text)
{
    return 'https://wa.me/' . $num . '?text=' . rawurlencode($text);
}

// ---- Liste ----
function lx_hotels_list_main($hotels, $lang, $ui, $wa)
{
    $e = 'lx_esc_html';
    $home = lx_page_path($lang, '/');
    $counts = array();
    foreach ($hotels as $h) if ($h['stars'] > 0) $counts[$h['stars']] = true;
    krsort($counts);
    $o = '<main class="hotels-page"><section class="hotel-intro hotel-wrap"><div class="hotel-breadcrumb"><a href="' . $e($home) . '">' . $e($ui['home']) . '</a><span>/</span>' . $e($ui['hotels']) . '</div>'
        . '<p class="hotel-eyebrow">' . $e($ui['list_eyebrow']) . '</p><h1>' . $e($ui['list_h1a']) . '<br/><em>' . $e($ui['list_h1b']) . '</em></h1>'
        . '<p class="hotel-lead">' . $e($ui['list_lead']) . '</p></section>'
        . '<section class="hotel-wrap hotel-list" aria-label="' . $e($ui['list_label']) . '"><div class="hotel-toolbar"><div class="hotel-filters" role="group" aria-label="' . $e($ui['filter_label']) . '">'
        . '<button data-stars="all" aria-pressed="true">' . $e($ui['all_hotels']) . '</button>';
    foreach (array_keys($counts) as $s) $o .= '<button data-stars="' . $s . '" aria-pressed="false">' . $e(sprintf($ui['n_star_filter'], $s)) . '</button>';
    $o .= '</div><span class="hotel-sort">' . $e($ui['sort']) . '</span></div>'
        . '<p id="hotel-count" class="hotel-count" role="status" data-tpl="' . $e($ui['count']) . '">' . $e(sprintf($ui['count'], count($hotels))) . '</p><div class="hotel-grid" id="hotel-grid">';
    foreach ($hotels as $i => $h) {
        $url = lx_hotel_url($lang, $h['slug']);
        list($city, $parts) = lx_hotel_parts($h, $lang);
        $tag = $parts ? lx_upper(end($parts) . (count($parts) > 1 ? ' · ' . $parts[0] : ''), $lang) : '';
        $o .= '<article class="hotel-card" data-rating="' . (int) $h['stars'] . '"><a class="hotel-card-photo" href="' . $e($url) . '" aria-label="' . $e(sprintf($ui['card_aria'], $h['name'], $h['stars'])) . '">';
        if ($h['cover'] !== '') $o .= '<img src="' . $e($h['cover']) . '" alt="' . $e(sprintf($ui['cover_alt'], $h['name'])) . '" width="1448" height="1086"' . ($i < 2 ? ' fetchpriority="high"' : ' loading="lazy"') . '/>';
        if ($tag !== '') $o .= '<span class="hotel-photo-tag">' . $e($tag) . '</span>';
        $o .= '</a><div class="hotel-card-body">';
        if ($h['stars'] > 0) $o .= '<p class="hotel-stars" aria-label="' . $e(sprintf($ui['stars_aria'], $h['stars'])) . '">' . lx_stars($h['stars']) . ' <span>' . $e(sprintf($ui['stars_short'], $h['stars'])) . '</span></p>';
        $o .= '<h2><a href="' . $e($url) . '">' . $e($h['name']) . '</a></h2>';
        $sum = lx_tr_field($h['summary'], $lang);
        if ($sum !== '') $o .= '<p>' . $e($sum) . '</p>';
        $feat = lx_tr_lines($h['features'], $lang);
        if ($feat) { $o .= '<div class="hotel-card-features">'; foreach (array_slice($feat, 0, 3) as $f) $o .= '<span>' . $e($f) . '</span>'; $o .= '</div>'; }
        $o .= '<a class="hotel-detail-link" href="' . $e($url) . '">' . $e($ui['view']) . ' <span>↗</span></a></div></article>';
    }
    $o .= '</div><div id="hotel-empty" class="hotel-empty" hidden><h2>' . $e($ui['empty_h']) . '</h2><p>' . $e($ui['empty_p']) . '</p><button data-reset-stars class="hotel-button">' . $e($ui['show_all']) . '</button></div></section>'
        . '<section class="hotel-help hotel-wrap"><div><p class="hotel-eyebrow">' . $e($ui['help_eyebrow']) . '</p><h2>' . $e($ui['help_h2']) . '</h2><p>' . $e($ui['help_p']) . '</p></div>'
        . '<a href="' . $e(lx_wa_link($wa, $ui['help_wa'])) . '" class="hotel-button">' . $e($ui['help_btn']) . '</a></section></main>';
    return $o;
}

// ---- Otel sayfası ----
function lx_hotel_detail_main($h, $lang, $ui, $wa)
{
    $e = 'lx_esc_html';
    $name = $h['name'];
    list($city, $parts) = lx_hotel_parts($h, $lang);
    $area = $parts ? $parts[0] : '';
    $waMain = lx_wa_link($wa, sprintf($ui['wa_msg'], $name));
    $desc = trim(lx_tr_field($h['description'], $lang));
    if ($desc === '') $desc = lx_tr_field($h['summary'], $lang);
    $rooms = lx_hotel_rooms($h, $lang);
    $amen = lx_tr_lines($h['amenities'], $lang);

    // Galeri: kapak + galerilerdeki tüm fotoğraflar (lightbox sırası)
    $photos = array();
    $roomPhotos = array();
    if ($h['cover'] !== '') $photos[] = array('src' => $h['cover'], 'title' => sprintf($ui['cover_alt'], $name), 'note' => $h['coverAi'] ? $ui['ai_note'] : '', 'cat' => 'g-cover');
    foreach ($h['galleries'] as $gi => $g) {
        $gt = lx_tr_field($g['title'], $lang);
        $isRooms = isset($g['title']['tr']) && preg_match('/^oda/iu', trim($g['title']['tr']));
        foreach ($g['photos'] as $pi => $p) {
            $cap = lx_tr_field($g['caps'][$pi], $lang);
            $photos[] = array('src' => $p, 'title' => $cap !== '' ? $cap : $gt, 'note' => '', 'cat' => 'g' . $gi);
            if ($isRooms) $roomPhotos[] = count($photos) - 1;
        }
    }
    $fig = function ($i, $w = 1024, $hgt = 768) use ($photos, $ui, $e) {
        $p = $photos[$i];
        return '<figure data-category="' . $e($p['cat']) . '"><button class="gallery-open" data-gallery-index="' . $i . '" aria-label="' . $e(sprintf($ui['enlarge'], $p['title'])) . '">'
            . '<img src="' . $e($p['src']) . '" alt="' . $e($p['title']) . '" loading="lazy" width="' . $w . '" height="' . $hgt . '"/><span aria-hidden="true">↗</span></button><figcaption>' . $e($p['title']) . '</figcaption></figure>';
    };

    $o = '<main class="hotels-page"><div class="hotel-wrap hotel-detail-top"><div class="hotel-breadcrumb"><a href="' . $e(lx_page_path($lang, '/')) . '">' . $e($ui['home']) . '</a><span>/</span>'
        . '<a href="' . $e(lx_page_path($lang, '/oteller/')) . '">' . $e($ui['hotels']) . '</a><span>/</span>' . $e($name) . '</div>'
        . '<div class="hotel-title-row"><div>';
    if ($h['stars'] > 0) $o .= '<p class="hotel-stars" aria-label="' . $e(sprintf($ui['stars_aria'], $h['stars'])) . '">' . lx_stars($h['stars']) . ' <span>' . $e(sprintf($ui['stars_hotel'], $h['stars'])) . '</span></p>';
    $o .= '<h1>' . $e($name) . '</h1>' . ($city !== '' ? '<p class="hotel-location">' . $e($city) . '</p>' : '') . '</div>'
        . '<a class="hotel-button" href="' . $e($waMain) . '">' . $e($ui['reserve']) . '</a></div>';
    if ($h['cover'] !== '') {
        $o .= '<div class="hotel-cover-only"><img src="' . $e($h['cover']) . '" alt="' . $e(sprintf($ui['cover_alt'], $name)) . '" fetchpriority="high" width="1536" height="1024"/></div>';
        if ($h['coverAi']) $o .= '<p class="hotel-image-note">' . $e($ui['ai_note']) . '</p>';
    }
    $nav = array('hakkinda' => $ui['nav_about']);
    if ($rooms || $roomPhotos) $nav['odalar'] = $ui['nav_rooms'];
    if ($amen) $nav['olanaklar'] = $ui['nav_amen'];
    if ($h['address'] !== '' || $h['map'] !== '') $nav['konum'] = $ui['nav_loc'];
    if (count($photos) > 0) $nav['galeri'] = $ui['nav_gallery'];
    $o .= '<nav class="hotel-anchor-nav" aria-label="' . $e($ui['sections_label']) . '">';
    foreach ($nav as $id => $t) $o .= '<a href="#' . $id . '">' . $e($t) . '</a>';
    $o .= '</nav></div><div class="hotel-wrap hotel-detail-layout"><div>';

    // Hakkında
    $o .= '<section class="hotel-content-section" id="hakkinda">' . ($area !== '' ? '<p class="hotel-eyebrow">' . $e(lx_upper(implode(' · ', array_slice($parts, 0, 2)), $lang)) . '</p>' : '')
        . '<h2>' . $e(sprintf($ui['about_h2'], $name)) . '</h2>';
    foreach (preg_split('/\n\s*\n/', $desc) as $para) if (trim($para) !== '') $o .= '<p>' . nl2br($e(trim($para))) . '</p>';
    $o .= '<div class="hotel-facts">';
    if ($h['stars'] > 0) $o .= '<div><strong>' . $e(sprintf($ui['stars_aria'], $h['stars'])) . '</strong>' . $e($ui['category']) . '</div>';
    if ($area !== '') $o .= '<div><strong>' . $e($area) . '</strong>' . $e(implode(' · ', array_reverse(array_slice($parts, 1)))) . '</div>';
    if ($rooms) $o .= '<div><strong>' . $e(sprintf($ui['n_options'], count($rooms))) . '</strong>' . $e($ui['room_types']) . '</div>';
    $o .= '</div></section>';

    // Odalar
    if ($rooms || $roomPhotos) {
        $o .= '<section class="hotel-content-section" id="odalar"><p class="hotel-eyebrow">' . $e($ui['rooms_eyebrow']) . '</p><h2>' . $e($ui['rooms_h2']) . '</h2>';
        if ($roomPhotos) {
            $o .= '<div class="hotel-gallery hotel-inline-rooms">';
            foreach (array_slice($roomPhotos, 0, 4) as $i) $o .= str_replace('<figure data-category="' . $e($photos[$i]['cat']) . '">', '<figure>', $fig($i, 136, 88));
            $o .= '</div>';
        }
        if ($rooms) {
            $o .= '<div class="room-types">';
            foreach ($rooms as $r) {
                $o .= '<article class="room-type"><div><h3>' . $e($r['name']) . '</h3>' . ($r['size'] !== '' ? '<span>' . $e($r['size']) . '</span>' : '') . '</div>'
                    . ($r['desc'] !== '' ? '<p>' . $e($r['desc']) . '</p>' : '')
                    . '<a href="' . $e(lx_wa_link($wa, sprintf($ui['wa_room'], $name, $r['name']))) . '">' . $e($ui['room_quote']) . '</a></article>';
            }
            $o .= '</div>';
        }
        $o .= '</section>';
    }
    // Olanaklar
    if ($amen) {
        $o .= '<section class="hotel-content-section" id="olanaklar"><p class="hotel-eyebrow">' . $e($ui['amen_eyebrow']) . '</p><h2>' . $e($ui['amen_h2']) . '</h2><div class="hotel-amenities">';
        foreach ($amen as $a) $o .= '<span>' . $e($a) . '</span>';
        $o .= '</div></section>';
    }
    $o .= '</div>';
    // Rezervasyon kutusu
    $o .= '<aside class="hotel-reservation"><p class="hotel-eyebrow">LUX LOCATIONS</p><h2>' . $ui['res_h2'] . '</h2><p>' . $e($ui['res_p']) . '</p>'
        . '<a class="hotel-button" href="' . $e($waMain) . '">' . $e($ui['res_wa']) . '</a>'
        . '<a class="hotel-secondary-link" href="tel:+' . $e($wa) . '">+' . $e(preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{2})(\d{2})$/', '$1 $2 $3 $4 $5', $wa)) . '</a>'
        . '<a class="hotel-secondary-link" href="mailto:reservation@luxlocationstours.com?subject=' . $e(rawurlencode(sprintf($ui['mail_subj'], $name))) . '">' . $e($ui['res_mail']) . '</a>';
    if ($h['booking'] !== '') $o .= '<a class="hotel-secondary-link" href="' . $e($h['booking']) . '" target="_blank" rel="noopener">' . $e($ui['res_booking']) . '</a>';
    $o .= '<p class="hotel-small">' . $e($ui['res_small']) . '</p></aside></div>';

    // Konum
    if ($h['address'] !== '' || $h['map'] !== '') {
        $map = $h['map'] !== '' ? $h['map'] : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($name . ' ' . $h['address']);
        $o .= '<section class="hotel-wrap hotel-content-section hotel-location-section" id="konum"><div><p class="hotel-eyebrow">' . $e(lx_upper($area !== '' ? $area . ' · ' . end($parts) : $city, $lang)) . '</p><h2>' . $e($ui['loc_h2']) . '</h2>'
            . ($h['address'] !== '' ? '<p>' . $e($h['address']) . '</p>' : '')
            . '<a class="hotel-detail-link" href="' . $e($map) . '" target="_blank" rel="noopener">' . $e($ui['map']) . '</a></div></section>';
    }
    // Galeri
    if ($photos) {
        $o .= '<section class="hotel-wrap hotel-content-section" id="galeri"><p class="hotel-eyebrow">' . $e($ui['gal_eyebrow']) . '</p><h2>' . $e($ui['gal_h2']) . '</h2>';
        $cats = array();
        foreach ($h['galleries'] as $gi => $g) if ($g['photos']) $cats['g' . $gi] = lx_tr_field($g['title'], $lang);
        if (count($cats) > 1) {
            $o .= '<div class="hotel-filters gallery-filters" role="group" aria-label="' . $e($ui['photo_cat']) . '"><button data-category-filter="all" aria-pressed="true">' . $e($ui['all_photos']) . '</button>';
            foreach ($cats as $c => $t) $o .= '<button data-category-filter="' . $c . '" aria-pressed="false">' . $e($t) . '</button>';
            $o .= '</div>';
        }
        $o .= '<div class="hotel-gallery">';
        foreach ($photos as $i => $p) $o .= $fig($i);
        $o .= '</div></section>';
        $o .= '<dialog class="hotel-lightbox" aria-label="' . $e($ui['gallery_label']) . '"><div class="lightbox-top"><span id="lightbox-count"></span><button id="lightbox-close" aria-label="' . $e($ui['close']) . '">×</button></div>'
            . '<div class="lightbox-stage"><button id="lightbox-prev" aria-label="' . $e($ui['prev']) . '">‹</button><img id="lightbox-image" alt=""/><button id="lightbox-next" aria-label="' . $e($ui['next']) . '">›</button></div><p id="lightbox-caption"></p></dialog>';
        $data = array();
        foreach ($photos as $p) $data[] = array('src' => $p['src'], 'title' => $p['title'], 'note' => $p['note']);
        $o .= '<script type="application/json" id="hotel-gallery-data">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
    }
    if ($h['website'] !== '') $o .= '<div class="hotel-wrap hotel-source-note"><a href="' . $e($h['website']) . '" target="_blank" rel="noopener">' . $e($ui['website']) . '</a></div>';
    return $o . '</main>';
}

// /oteller/ listesi ve /oteller/<slug>/ sayfası. Uygulanmadıysa null, otel yoksa false (404) döner.
function lx_render_hotels($path, $content)
{
    $lang = lx_lang_of($path);
    $base = lx_base_path($path);
    if (!preg_match('#^/oteller/(?:([a-z0-9-]+)/)?$#', $base, $m)) return null;
    $slug = isset($m[1]) ? $m[1] : '';
    $hotels = lx_hotels_published();
    if (!$hotels) return null; // panelde otel yoksa sayfalar olduğu gibi
    $shell = lx_hotels_shell($lang, $content);
    if ($shell === null) return null;
    $ui = lx_hotel_ui($lang);
    if ($slug === '') {
        return lx_hotels_page($shell, $lang, '/oteller/', $ui['hotels'], $ui['list_meta'], $hotels[0]['cover'], lx_hotels_list_main($hotels, $lang, $ui, $shell['wa']));
    }
    $hotel = null;
    foreach ($hotels as $h) if ($h['slug'] === $slug) $hotel = $h;
    if ($hotel === null) return false;
    list($city, $parts) = lx_hotel_parts($hotel, $lang);
    $title = $hotel['name'] . ($hotel['stars'] > 0 ? ' ' . lx_stars($hotel['stars']) . ' — ' . sprintf($ui['stars_short'], $hotel['stars']) : '');
    $desc = sprintf($ui['detail_meta'], $hotel['name'], $hotel['stars'], $parts ? $parts[0] : $city);
    return lx_hotels_page($shell, $lang, '/oteller/' . $slug . '/', $title, $desc, $hotel['cover'], lx_hotel_detail_main($hotel, $lang, $ui, $shell['wa']));
}

// ---- Ana sayfa: kayan otel şeridi (her dilde) ----
// React sayfayı canlandırdıktan sonra slider'ın altına eklenir; JS yoksa noscript içindeki liste görünür.
function lx_home_rail($html, $lang)
{
    // Eski (elle eklenmiş) şerit parçaları
    $html = preg_replace('#<style id="home-hotel-rail-style">.*?</style>#s', '', $html);
    $html = preg_replace('#<script id="home-hotel-rail-script">.*?</script>#s', '', $html);
    $html = preg_replace('#<noscript id="home-hotel-rail-fallback">.*?</noscript>#s', '', $html);
    $hotels = lx_hotels_published();
    if (!$hotels || strpos($html, 'class="slider') === false) return $html;
    $ui = lx_hotel_ui($lang);
    $e = 'lx_esc_html';
    $cards = '';
    foreach ($hotels as $h) {
        $cards .= '<a class="hr-card" href="' . $e(lx_hotel_url($lang, $h['slug'])) . '"><img src="' . $e($h['cover']) . '" width="240" height="138" loading="lazy" decoding="async" alt=""/>'
            . '<span class="hr-info">' . ($h['stars'] > 0 ? '<span class="hr-stars" aria-label="' . $e(sprintf($ui['stars_aria'], $h['stars'])) . '">' . lx_stars($h['stars']) . '</span>' : '') . '<strong>' . $e($h['name']) . '</strong></span></a>';
    }
    $head = '<div class="hr-head"><div><span class="hr-kicker">' . $e($ui['rail_kicker']) . '</span><h2 id="home-hotel-title">' . $e($ui['rail_h2']) . '</h2></div><div class="hr-actions">';
    $tail = '<a href="' . $e(lx_page_path($lang, '/oteller/')) . '">' . $e($ui['rail_all']) . '</a></div></div><div class="hr-viewport"><div class="hr-track"><div class="hr-group">' . $cards . '</div></div></div></section>';
    $markup = '<section class="home-hotel-rail" aria-labelledby="home-hotel-title">' . $head . '<button type="button" class="hr-toggle" aria-pressed="false">' . $e($ui['pause']) . '</button>' . $tail;
    $noscript = '<noscript id="home-hotel-rail-fallback"><section class="home-hotel-rail hr-manual" aria-labelledby="home-hotel-title">' . $head . $tail . '</noscript>';
    $css = '<style id="home-hotel-rail-style">'
        . '.home-hotel-rail{background:#f6f4ee;padding:24px 0 26px;color:#22362f;overflow:hidden}'
        . '.hr-head{max-width:1220px;margin:0 auto 14px;padding:0 22px;display:flex;align-items:end;justify-content:space-between;gap:14px;flex-wrap:wrap}'
        . '.hr-kicker{font-size:.67rem;font-weight:700;letter-spacing:.18em;color:#75603e}'
        . '.hr-head h2{margin:2px 0 0;font:600 clamp(1.55rem,3vw,2.2rem)/1.1 Georgia,serif}'
        . '.hr-actions{display:flex;align-items:center;gap:12px;font-size:.83rem}'
        . '.hr-actions a,.hr-toggle{border:1px solid #bdb39d;border-radius:999px;padding:8px 12px;background:transparent;color:#22362f;text-decoration:none;font:inherit;cursor:pointer}'
        . '.hr-viewport{overflow:hidden;direction:ltr}.hr-track{display:flex;width:max-content;animation:lux-hotel-flow 125s linear infinite}'
        . '.hr-group{display:flex;gap:12px;padding-right:12px}'
        . '.hr-card{display:block;flex:0 0 clamp(170px,24vw,240px);width:clamp(170px,24vw,240px);border:1px solid #ded7c8;border-radius:10px;overflow:hidden;background:#fff;color:inherit;text-decoration:none}'
        . '.hr-card img{display:block;width:100%;height:clamp(101px,14vw,138px);object-fit:cover}'
        . '.hr-info{display:grid;gap:3px;padding:8px 11px 10px;min-height:66px}'
        . '.hr-info strong{font-size:.83rem;line-height:1.25}.hr-stars{font-size:.76rem;color:#886126;letter-spacing:.1em}'
        . '.hr-card:focus-visible{outline:3px solid #9d6b28;outline-offset:-3px}'
        . '@keyframes lux-hotel-flow{from{transform:translateX(-50%)}to{transform:translateX(0)}}'
        . '.hr-viewport:hover .hr-track,.home-hotel-rail.hr-paused .hr-track{animation-play-state:paused}'
        . '.home-hotel-rail.hr-manual .hr-track{animation:none}'
        . '.home-hotel-rail.hr-manual .hr-viewport{overflow-x:auto;padding-inline:22px;scrollbar-width:thin}'
        . '.home-hotel-rail.hr-manual .hr-copy{display:none}'
        . '@media(prefers-reduced-motion:reduce){.hr-track{animation:none}.hr-viewport{overflow-x:auto;padding-inline:22px}.hr-copy,.hr-toggle{display:none}}'
        . '</style>';
    $js = '<script id="home-hotel-rail-script">(function(){var M=' . json_encode($markup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ',P=' . json_encode($ui['pause'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ',S=' . json_encode($ui['play'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';'
        . 'function mount(){var sl=document.querySelector("section.slider");if(!sl||document.querySelector(".home-hotel-rail:not(.hr-manual)"))return;sl.insertAdjacentHTML("afterend",M);'
        . 'var r=document.querySelector(".home-hotel-rail"),g=r.querySelector(".hr-group"),c=g.cloneNode(true);c.classList.add("hr-copy");c.setAttribute("aria-hidden","true");'
        . '[].forEach.call(c.querySelectorAll("a"),function(a){a.tabIndex=-1});g.after(c);var t=r.querySelector(".hr-toggle");'
        . 'function man(on){r.classList.toggle("hr-manual",on);t.setAttribute("aria-pressed",String(on));t.textContent=on?S:P}'
        . 't.addEventListener("click",function(){man(!r.classList.contains("hr-manual"))});'
        . 'g.addEventListener("focusin",function(ev){man(true);ev.target.scrollIntoView({block:"nearest",inline:"nearest"})})}'
        . 'if(document.readyState==="complete")mount();else window.addEventListener("load",mount,{once:true})})()</script>';
    $p = stripos($html, '</head>');
    if ($p !== false) $html = substr($html, 0, $p) . $css . substr($html, $p);
    // noscript: slider bölümünün hemen ardına
    if (preg_match('#<section\b[^>]*class="slider[^"]*"[^>]*>#', $html, $m, PREG_OFFSET_CAPTURE)) {
        $end = strpos($html, '</section>', $m[0][1]);
        // slider içinde iç içe section yok; ilk kapanış slider'ın kapanışı
        if ($end !== false) $html = substr($html, 0, $end + 10) . $noscript . substr($html, $end + 10);
    }
    $p = strripos($html, '</body>');
    $html = $p !== false ? substr($html, 0, $p) . $js . substr($html, $p) : $html . $js;
    return $html;
}
