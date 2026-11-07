<?php
// Lux Locations site içerik düzenleyicisi: ortak işlevler.
// Statik Next.js sayfaları değişmeden sunucuda durur; admin panelinde yapılan metin ve görsel
// değişiklikleri (data/content.json) sayfa her istendiğinde render.php tarafından uygulanır.
// Sunucudaki PHP sürümü bilinmediği için PHP 7.2 ile uyumlu yazıldı.

define('LX_ROOT', dirname(__DIR__));                 // httpdocs
define('LX_DIR', __DIR__);                           // httpdocs/lx-content
define('LX_DATA', LX_DIR . '/data');                 // içerik + giriş bilgisi (web'den erişilemez)
define('LX_UPLOADS', LX_DIR . '/uploads');           // yüklenen görseller (herkese açık)
define('LX_UPLOADS_URL', '/lx-content/uploads');

// Önekli diller; Türkçe kökte.
function lx_langs()
{
    return array(
        'tr' => 'Türkçe', 'en' => 'English', 'ar' => 'العربية', 'ru' => 'Русский', 'de' => 'Deutsch',
        'fr' => 'Français', 'es' => 'Español', 'it' => 'Italiano', 'zh' => '中文', 'fa' => 'فارسی', 'ro' => 'Română',
    );
}

// Sayfalar: dil önekisiz yol => Türkçe ad
function lx_pages()
{
    return array(
        '/' => 'Ana sayfa',
        '/bogaz-tekne-turu/' => 'Boğaz yemekli tekne turu',
        '/oteller/' => 'Oteller',
        '/oda-rezervasyonu/' => 'Oda rezervasyonu',
        '/grup-rezervasyonu/' => 'Grup rezervasyonu',
        '/toplanti-kongre/' => 'Toplantı ve kongre',
    );
}

function lx_empty_content()
{
    return array('texts' => array(), 'images' => array(), 'hidden' => array(), 'added' => array());
}

function lx_load_content()
{
    $file = LX_DATA . '/content.json';
    if (!is_file($file)) return lx_empty_content();
    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data)) return lx_empty_content();
    return array_merge(lx_empty_content(), $data);
}

function lx_save_json($name, $data)
{
    if (!is_dir(LX_DATA)) mkdir(LX_DATA, 0755, true);
    $file = LX_DATA . '/' . $name;
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    // Her kayıttan önce eski hâli yedekle (son 20 yedek tutulur)
    if (is_file($file) && $name === 'content.json') {
        $bdir = LX_DATA . '/backup';
        if (!is_dir($bdir)) mkdir($bdir, 0755, true);
        copy($file, $bdir . '/content-' . date('Ymd-His') . '.json');
        $old = glob($bdir . '/content-*.json');
        sort($old);
        while (count($old) > 20) unlink(array_shift($old));
    }
    return rename($tmp, $file);
}

// Bir sayfa yolunun dili: /en/... => en, diğerleri tr
function lx_lang_of($path)
{
    if (preg_match('#^/([a-z]{2})(/|$)#', $path, $m) && $m[1] !== 'tr' && array_key_exists($m[1], lx_langs())) return $m[1];
    return 'tr';
}

// Dil önekini atar: /en/oteller/ => /oteller/
function lx_base_path($path)
{
    $lang = lx_lang_of($path);
    if ($lang === 'tr') return $path;
    $rest = substr($path, 3);
    return $rest === '' ? '/' : $rest;
}

function lx_page_path($lang, $base)
{
    return $lang === 'tr' ? $base : '/' . $lang . $base;
}

// İstek yolundan diskteki statik HTML dosyası (yoksa null)
function lx_file_for($path)
{
    $path = rawurldecode($path);
    if (strpos($path, "\0") !== false || strpos($path, '..') !== false) return null;
    $rel = substr($path, -1) === '/' ? $path . 'index.html' : $path;
    if (substr($rel, -5) !== '.html') return null;
    $file = realpath(LX_ROOT . $rel);
    $root = realpath(LX_ROOT);
    if ($file === false || strpos($file, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($file)) return null;
    return $file;
}

// ---- Kaçış biçimleri ----
// React metni HTML'e bu karakterleri kaçırarak yazar (metin ve öznitelikte aynı).
function lx_esc_html($s)
{
    return strtr($s, array('&' => '&amp;', '"' => '&quot;', "'" => '&#x27;', '<' => '&lt;', '>' => '&gt;'));
}

function lx_json_inner($s)
{
    $j = json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return substr($j, 1, -1);
}

// Next.js sayfanın React verisini (flight) <script>self.__next_f.push([1,"..."])</script> içinde
// iki kez JSON'a çevrilmiş ve HTML için kaçırılmış olarak gömer. Metnin o veri içindeki tırnaklı hâli:
function lx_flight_quoted($s)
{
    if ($s !== '' && $s[0] === '$') $s = '$' . $s; // flight "$" ile başlayan metni "$$" yazar
    $inner = lx_json_inner(lx_json_inner($s));
    $inner = strtr($inner, array('&' => '\\u0026', '<' => '\\u003c', '>' => '\\u003e', "\u{2028}" => '\\u2028', "\u{2029}" => '\\u2029'));
    return '\\"' . $inner . '\\"';
}

// Sayfadaki flight veri bloklarını (script içerikleri) birleştirir
function lx_flight_blob($html)
{
    preg_match_all('#<script>self\.__next_f\.push\(\[1,".*?"\]\)</script>#s', $html, $m);
    return implode("\n", $m[0]);
}

// Flight verisini çözüp istemci bileşenlerine (kayan görsel, form) giden metinleri bulur.
// Bunların bir kısmı sayfa ilk açıldığında HTML'de görünmez (ör. slider'ın 2-4. slaytları).
function lx_flight_client_strings($html)
{
    preg_match_all('#<script>self\.__next_f\.push\(\[1,(".*?")\]\)</script>#s', $html, $m);
    $text = '';
    foreach ($m[1] as $lit) { $d = json_decode($lit); if (is_string($d)) $text .= $d; }
    $out = array();
    $skip = array('href' => 1, 'className' => 1, 'src' => 1, 'poster' => 1, 'style' => 1, 'id' => 1, 'ctaHref' => 1, 'img' => 1, 'lang' => 1, 'dir' => 1, 'type' => 1, 'name' => 1, 'value' => 1, 'key' => 1, 'parallelRouterKey' => 1, 'segmentPath' => 1,
        'notFound' => 1, 'forbidden' => 1, 'unauthorized' => 1, 'templateStyles' => 1, 'errorStyles' => 1, 'dangerouslySetInnerHTML' => 1);
    $walk = function ($v, $inClient, $key) use (&$walk, &$out, $skip) {
        if (is_array($v)) {
            if (isset($v[0], $v[1]) && $v[0] === '$' && is_string($v[1]) && array_key_exists(3, $v)) {
                $walk($v[3], $inClient || strpos($v[1], '$L') === 0, null);
                return;
            }
            foreach ($v as $k => $x) {
                if (is_string($k) && isset($skip[$k])) continue;
                $walk($x, $inClient, is_string($k) ? $k : $key);
            }
            return;
        }
        if (!$inClient || !is_string($v)) return;
        if (!preg_match('/\p{L}/u', $v) || preg_match('#^(\$|/|\#|https?:|mailto:|tel:)#', $v) || strpos($v, '://') !== false) return;
        $out[$v] = true;
    };
    foreach (explode("\n", $text) as $row) {
        $p = strpos($row, ':');
        if ($p === false) continue;
        $payload = substr($row, $p + 1);
        if ($payload === '' || ($payload[0] !== '[' && $payload[0] !== '{')) continue;
        $j = json_decode($payload, true);
        if ($j !== null) $walk($j, false, null);
    }
    return array_keys($out);
}

// ---- Sayfayı okuyup düzenlenebilir parçaları çıkarma (admin paneli için) ----
function lx_dom($html)
{
    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    return $doc;
}

function lx_tag_label($tag)
{
    $labels = array('h1' => 'Ana başlık', 'h2' => 'Başlık', 'h3' => 'Alt başlık', 'h4' => 'Alt başlık', 'h6' => 'Küçük üst yazı',
        'small' => 'Küçük yazı', 'span' => 'Kısa yazı', 'em' => 'Vurgulu başlık', 'p' => 'Paragraf', 'li' => 'Liste',
        'a' => 'Menü / bağlantı / buton', 'button' => 'Buton', 'label' => 'Form alanı adı', 'bdi' => 'Başlık');
    return isset($labels[$tag]) ? $labels[$tag] : 'Metin';
}

// Düzenlenebilir metinler: hem HTML'de hem flight verisinde bire bir bulunanlar.
// (İkisinde birden değişmeyen metin, sayfa açılırken React tarafından eski hâline döndürülür.)
function lx_extract_texts($html)
{
    $flight = lx_flight_blob($html);
    $doc = lx_dom($html);
    $xp = new DOMXPath($doc);
    $out = array();
    $seen = array();
    $add = function ($text, $where, $flightOnly = false) use (&$out, &$seen, $html, $flight) {
        if (isset($seen[$text])) return;
        if (trim($text) === '' || !preg_match('/\p{L}/u', $text)) return; // sadece harf içerenler
        $esc = lx_esc_html($text);
        $inHtml = $flightOnly || strpos($html, '>' . $esc . '<') !== false || strpos($html, '="' . $esc . '"') !== false;
        $inFlight = $flight === '' || strpos($flight, lx_flight_quoted($text)) !== false;
        if (!$inHtml || !$inFlight) return;
        $seen[$text] = true;
        $out[] = array('text' => $text, 'where' => $where);
    };
    foreach ($xp->query('//title') as $n) $add($n->textContent, 'Sekme başlığı (Google)');
    foreach ($xp->query('//meta[@name="description"]/@content') as $n) $add($n->nodeValue, 'Google açıklaması');
    foreach ($xp->query('//body//text()[not(ancestor::script) and not(ancestor::style) and not(ancestor::noscript)]') as $n) {
        $tag = $n->parentNode ? strtolower($n->parentNode->nodeName) : '';
        if ($tag === 'summary' || in_array($n->nodeValue, lx_langs(), true)) continue; // dil seçici
        $add($n->nodeValue, lx_tag_label($tag));
    }
    foreach ($xp->query('//body//img/@alt | //body//*/@aria-label | //body//*/@placeholder') as $n) {
        $add($n->nodeValue, 'Görsel/ekran okuyucu açıklaması');
    }
    foreach (lx_flight_client_strings($html) as $s) $add($s, 'Kayan görsel / form', true);
    return $out;
}

// Sayfadaki görseller (img, video, arka plan) – sıralı, tekil
function lx_extract_images($html)
{
    $doc = lx_dom($html);
    $xp = new DOMXPath($doc);
    $out = array();
    $add = function ($src, $kind) use (&$out) {
        if ($src === '' || strpos($src, '/assets/flags/') === 0 || strpos($src, 'data:') === 0) return;
        if (!preg_match('#^/[^"\'\s<>]+$#', $src)) return;
        if (!isset($out[$src])) $out[$src] = array('src' => $src, 'kind' => $kind);
    };
    foreach ($xp->query('//body//*[@style]') as $el) {
        if (preg_match_all('#url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)#', $el->getAttribute('style'), $m)) {
            foreach ($m[1] as $u) $add($u, 'Arka plan görseli');
        }
    }
    foreach ($xp->query('//body//img | //body//video') as $el) {
        if ($el->nodeName === 'img') $add($el->getAttribute('src'), 'Fotoğraf');
        else {
            $add($el->getAttribute('src'), 'Video');
            if ($el->hasAttribute('poster')) $add($el->getAttribute('poster'), 'Video kapak görseli');
        }
    }
    return array_values($out);
}

// Fotoğraf eklenebilen galeriler (class'ında gallery / boat-media olanlar), sayfadaki sırasıyla
function lx_extract_galleries($html)
{
    $doc = lx_dom($html);
    $xp = new DOMXPath($doc);
    $out = array();
    $i = 0;
    foreach ($xp->query('//body//*[contains(concat(" ", normalize-space(@class), " "), " gallery ") or contains(concat(" ", normalize-space(@class), " "), " boat-media ")]') as $el) {
        $title = '';
        $section = $el->parentNode;
        if ($section) foreach ($xp->query('.//h2', $section) as $h) { $title = trim($h->textContent); break; }
        $out[] = array('index' => $i++, 'title' => $title !== '' ? $title : 'Galeri ' . $i);
    }
    return $out;
}

// ---- Değişiklikleri sayfaya uygulama (render.php) ----
function lx_apply($html, $path, $content)
{
    $lang = lx_lang_of($path);
    $base = lx_base_path($path);

    // 1) Metinler (dil bazında): HTML metni, öznitelik ve flight verisi birlikte değişir
    $texts = isset($content['texts'][$lang]) ? $content['texts'][$lang] : array();
    if ($texts) {
        $map = array();
        foreach ($texts as $old => $new) {
            $old = (string) $old;
            $new = (string) $new;
            if ($old === '' || $old === $new) continue;
            $eo = lx_esc_html($old);
            $en = lx_esc_html($new);
            $map['>' . $eo . '<'] = '>' . $en . '<';
            $map['="' . $eo . '"'] = '="' . $en . '"';
            $map[lx_flight_quoted($old)] = lx_flight_quoted($new);
        }
        if ($map) $html = strtr($html, $map); // strtr: değiştirilen metin tekrar değiştirilmez
    }

    // 2) Değiştirilen görseller (tüm dillerde)
    $images = isset($content['images']) ? $content['images'] : array();
    $imap = array();
    foreach ($images as $old => $new) {
        if (is_string($old) && is_string($new) && $old !== '' && $new !== '' && $old !== $new) $imap[$old] = $new;
    }
    if ($imap) $html = strtr($html, $imap);

    $head = '';
    // 3) Gizlenen görseller
    $hidden = array();
    foreach ((isset($content['hidden']) ? $content['hidden'] : array()) as $src => $on) {
        if (!$on) continue;
        $src = isset($imap[$src]) ? $imap[$src] : $src;
        $q = addcslashes($src, "\"\\");
        $hidden[] = 'img[src="' . $q . '"],video[src="' . $q . '"],video[poster="' . $q . '"]';
    }
    if ($hidden) $head .= '<style id="lx-hidden">' . str_replace('</', '<\/', implode(',', $hidden)) . '{display:none!important}</style>';

    // 4) Galerilere eklenen fotoğraflar: React sayfayı canlandırdıktan sonra eklenir
    $added = isset($content['added'][$base]) ? $content['added'][$base] : array();
    $add = array();
    foreach ($added as $gi => $list) {
        foreach ((array) $list as $item) {
            if (!empty($item['src'])) $add[] = array('g' => (int) $gi, 'src' => $item['src'], 'alt' => isset($item['alt']) ? (string) $item['alt'] : '');
        }
    }
    $foot = '';
    if ($add) {
        $json = json_encode($add, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
        $foot = '<script id="lx-added">(function(){var A=' . $json . ',t=0;'
            . 'function q(){return document.querySelectorAll(".gallery,.boat-media")}'
            . 'function ready(){var g=q();if(!g.length)return false;for(var k in g[0])if(k.indexOf("__reactFiber")===0)return true;return false}'
            . 'function run(){var g=q();A.forEach(function(a){var el=g[a.g];if(!el)return;var i=document.createElement("img");i.src=a.src;i.alt=a.alt;i.loading="lazy";i.setAttribute("data-lx","1");el.appendChild(i)})}'
            . 'function tick(){if(ready()||t++>100)run();else setTimeout(tick,100)}'
            . 'if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",tick);else tick()})()</script>';
    }

    if ($head !== '') {
        $p = stripos($html, '</head>');
        if ($p !== false) $html = substr($html, 0, $p) . $head . substr($html, $p);
    }
    if ($foot !== '') {
        $p = strripos($html, '</body>');
        $html = $p !== false ? substr($html, 0, $p) . $foot . substr($html, $p) : $html . $foot;
    }
    return $html;
}
