<?php
// Oteller: panelden eklenen oteller (data/hotels.json) /oteller/ sayfasında kart olarak,
// /oteller/<otel>/ adresinde de kendi sayfasıyla gösterilir. Bu sayfalarda React betikleri çıkarılır
// (sayfada etkileşimli bileşen yok), böylece eklenen içerik açılışta silinmez.

function lx_hotels_load()
{
    $f = LX_DATA . '/hotels.json';
    if (!is_file($f)) return array();
    $d = json_decode((string) file_get_contents($f), true);
    return is_array($d) ? array_values($d) : array();
}

function lx_hotel_default_galleries()
{
    return array(
        array('title' => array('tr' => 'Odalar', 'en' => 'Rooms'), 'photos' => array()),
        array('title' => array('tr' => 'Kahvaltı', 'en' => 'Breakfast'), 'photos' => array()),
        array('title' => array('tr' => 'Lobi', 'en' => 'Lobby'), 'photos' => array()),
        array('title' => array('tr' => 'Havuz / Spa', 'en' => 'Pool / Spa'), 'photos' => array()),
        array('title' => array('tr' => 'Dış görünüm', 'en' => 'Exterior'), 'photos' => array()),
    );
}

// Çok dilli alan: seçili dil, yoksa İngilizce, yoksa Türkçe
function lx_tr_field($v, $lang)
{
    if (!is_array($v)) return (string) $v;
    foreach (array($lang, 'en', 'tr') as $l) if (isset($v[$l]) && trim($v[$l]) !== '') return $v[$l];
    return '';
}

function lx_slugify($s)
{
    $s = strtr($s, array('ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'İ' => 'i', 'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u', 'â' => 'a', 'î' => 'i', 'û' => 'u'));
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return $s !== '' ? $s : 'otel';
}

function lx_hotel_ui($lang)
{
    $tr = array('all' => '← Tüm oteller', 'ask' => 'WhatsApp\'tan teklif al ↗', 'msg' => 'Merhaba, %s için teklif almak istiyorum.', 'more' => 'İncele ↗');
    $en = array('all' => '← All hotels', 'ask' => 'Get a quote on WhatsApp ↗', 'msg' => 'Hello, I would like a quote for %s.', 'more' => 'View ↗');
    return $lang === 'tr' ? $tr : $en;
}

function lx_stars($n)
{
    $n = (int) $n;
    return $n > 0 ? str_repeat('★', min(7, $n)) : '';
}

// Sayfadaki React betiklerini çıkarır (yalnızca otel sayfalarında)
function lx_strip_scripts($html)
{
    $html = preg_replace('#<script\b[^>]*>.*?</script>#s', '', $html);
    $html = preg_replace('#<link\b[^>]*rel="(?:preload|modulepreload)"[^>]*as="script"[^>]*/?>#', '', $html);
    return $html;
}

// Sayfanın ana bölümünün (header'dan sonraki ilk <section>) içini değiştirir
function lx_replace_main_section($html, $inner)
{
    $hp = strpos($html, '</header>');
    $s = strpos($html, '<section', $hp === false ? 0 : $hp);
    if ($s === false) return $html;
    $open = strpos($html, '>', $s);
    $e = strpos($html, '</section>', $open);
    if ($open === false || $e === false) return $html;
    return substr($html, 0, $open + 1) . $inner . substr($html, $e);
}

function lx_wa_base($html)
{
    return preg_match('#https://wa\.me/(\d+)#', $html, $m) ? 'https://wa.me/' . $m[1] : 'https://wa.me/905396114190';
}

function lx_hotel_url($lang, $slug)
{
    return lx_page_path($lang, '/oteller/' . $slug . '/');
}

// /oteller/ listesi ve /oteller/<slug>/ detay sayfası. Uygulanmadıysa null döner.
function lx_render_hotels($path, $content)
{
    $lang = lx_lang_of($path);
    $base = lx_base_path($path);
    if (!preg_match('#^/oteller/(?:([a-z0-9-]+)/)?$#', $base, $m)) return null;
    $slug = isset($m[1]) ? $m[1] : '';
    $hotels = array_values(array_filter(lx_hotels_load(), function ($h) { return !empty($h['published']); }));
    if ($slug === '' && !$hotels) return null; // otel yoksa sayfa olduğu gibi
    $tpl = lx_file_for(lx_page_path($lang, '/oteller/'));
    if ($tpl === null) return null;
    $html = lx_apply((string) file_get_contents($tpl), lx_page_path($lang, '/oteller/'), $content);
    $ui = lx_hotel_ui($lang);
    $e = 'lx_esc_html';

    if ($slug === '') {
        // Başlık ve üst yazı mevcut sayfadan korunur, "yakında" paragrafı yerine kartlar gelir
        $hp = strpos($html, '</header>');
        $s = strpos($html, '<section', $hp === false ? 0 : $hp);
        $h2 = $s === false ? false : strpos($html, '</h2>', $s);
        $e2 = $s === false ? false : strpos($html, '</section>', $s);
        $cards = '<div class="services">';
        foreach ($hotels as $h) {
            $name = (string) $h['name'];
            $meta = trim(lx_tr_field(isset($h['city']) ? $h['city'] : '', $lang) . (lx_stars(isset($h['stars']) ? $h['stars'] : 0) !== '' ? ' · ' . lx_stars($h['stars']) : ''));
            $cards .= '<a class="service" href="' . $e(lx_hotel_url($lang, $h['slug'])) . '">'
                . (!empty($h['cover']) ? '<img src="' . $e($h['cover']) . '" alt="' . $e($name) . '" loading="lazy"/>' : '')
                . '<div><small>' . $e($meta) . '</small><h3>' . $e($name) . '</h3><p>' . $e(lx_tr_field(isset($h['summary']) ? $h['summary'] : '', $lang)) . '</p>'
                . '<span class="btn">' . $e($ui['more']) . '</span></div></a>';
        }
        $cards .= '</div>';
        if ($h2 !== false && $e2 !== false && $h2 < $e2) $html = substr($html, 0, $h2 + 5) . $cards . substr($html, $e2);
        return lx_strip_scripts($html);
    }

    $hotel = null;
    foreach ($hotels as $h) if ($h['slug'] === $slug) $hotel = $h;
    if ($hotel === null) return false; // 404
    $name = (string) $hotel['name'];
    $city = lx_tr_field(isset($hotel['city']) ? $hotel['city'] : '', $lang);
    $stars = lx_stars(isset($hotel['stars']) ? $hotel['stars'] : 0);
    $desc = lx_tr_field(isset($hotel['description']) ? $hotel['description'] : '', $lang);
    if ($desc === '') $desc = lx_tr_field(isset($hotel['summary']) ? $hotel['summary'] : '', $lang);
    $wa = lx_wa_base($html) . '?text=' . rawurlencode(sprintf($ui['msg'], $name));

    $inner = '<p><a href="' . $e(lx_page_path($lang, '/oteller/')) . '">' . $e($ui['all']) . '</a></p>'
        . '<span class="eyebrow">' . $e(trim($city . ($stars !== '' ? ' · ' . $stars : ''))) . '</span>'
        . '<h2>' . $e($name) . '</h2>';
    if (!empty($hotel['cover'])) $inner .= '<img src="' . $e($hotel['cover']) . '" alt="' . $e($name) . '" style="width:100%;max-height:520px;object-fit:cover;display:block;margin:0 0 24px"/>';
    foreach (preg_split('/\n\s*\n/', trim($desc)) as $para) if (trim($para) !== '') $inner .= '<p class="lead">' . nl2br($e(trim($para))) . '</p>';
    $inner .= '<p><a class="btn" href="' . $e($wa) . '">' . $e($ui['ask']) . '</a></p>';
    foreach ((array) (isset($hotel['galleries']) ? $hotel['galleries'] : array()) as $g) {
        if (empty($g['photos'])) continue;
        $title = lx_tr_field(isset($g['title']) ? $g['title'] : '', $lang);
        $inner .= '<h3 style="margin:36px 0 14px">' . $e($title) . '</h3><div class="gallery">';
        foreach ($g['photos'] as $i => $p) $inner .= '<img src="' . $e($p) . '" alt="' . $e($name . ' — ' . $title . ' ' . ($i + 1)) . '" loading="lazy"/>';
        $inner .= '</div>';
    }
    $inner .= '<p style="margin-top:36px"><a class="btn" href="' . $e($wa) . '">' . $e($ui['ask']) . '</a></p>';
    // Başlık, açıklama ve dil seçici bağlantıları bu otele göre
    $html = preg_replace('#<title>.*?</title>#s', '<title>' . $e($name . ' | Lux Locations Tours') . '</title>', $html, 1);
    $html = preg_replace('#<meta name="description" content="[^"]*"#', '<meta name="description" content="' . $e(lx_tr_field(isset($hotel['summary']) ? $hotel['summary'] : '', $lang)) . '"', $html, 1);
    $html = preg_replace('#href="((?:/[a-z]{2})?/oteller)/?"( hrefLang=)#', 'href="$1/' . $slug . '/"$2', $html);
    $html = lx_replace_main_section($html, $inner);
    return lx_strip_scripts($html);
}
