<?php
// Lux Locations · Site düzenleme (admin paneli eklentisi)
// Metinleri değiştirme, görselleri değiştirme/gizleme ve galerilere fotoğraf ekleme.
// Değişiklikler lx-content/data/content.json'a yazılır; sayfalar lx-content/render.php ile sunulurken uygulanır.
require dirname(dirname(__DIR__)) . '/lx-content/lib.php';
require dirname(dirname(__DIR__)) . '/lx-content/hotels.php';

// İlk kurulumda şifre belirlemek için gereken tek seferlik kod (kodun kendisi depoda yok, yalnızca özeti)
define('LX_SETUP_HASH', '$2y$10$gdjcSIq.oA2C5jQWIORF9e6tLMdHsj.hO3kTdqX.dcOvYFfmLmWXC');
define('LX_MAX_UPLOAD', 50 * 1024 * 1024);

session_name('LXSITE');
session_set_cookie_params(0, '/admin/site/', '', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
session_start();
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: no-store');

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

function lx_auth() { $f = LX_DATA . '/auth.json'; return is_file($f) ? json_decode((string) file_get_contents($f), true) : null; }

function lx_csrf()
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function lx_flash($msg, $ok = true) { $_SESSION['flash'] = array($msg, $ok); }

function lx_redirect($params)
{
    header('Location: ./' . ($params ? '?' . http_build_query($params) : ''));
    exit;
}

// Basit kaba kuvvet koruması: 15 dakikada 8 hatalı denemeden sonra o IP kilitlenir
function lx_fail_state($record = false)
{
    $f = LX_DATA . '/login-fail.json';
    $all = is_file($f) ? json_decode((string) file_get_contents($f), true) : array();
    if (!is_array($all)) $all = array();
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '-';
    $now = time();
    foreach ($all as $k => $v) if ($v['t'] < $now - 900) unset($all[$k]);
    if ($record) {
        $all[$ip] = array('n' => (isset($all[$ip]) ? $all[$ip]['n'] : 0) + 1, 't' => $now);
        lx_save_json('login-fail.json', $all);
    }
    return isset($all[$ip]) ? $all[$ip]['n'] : 0;
}

// Yüklenen dosyayı doğrulayıp uploads/ altına kaydeder, herkese açık yolunu döndürür
function lx_store_upload($field, $allowVideo)
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        $err = empty($_FILES[$field]) ? -1 : $_FILES[$field]['error'];
        throw new RuntimeException($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE
            ? 'Dosya sunucunun izin verdiği boyuttan büyük.' : 'Dosya seçilmedi ya da yüklenemedi.');
    }
    $tmp = $_FILES[$field]['tmp_name'];
    if (filesize($tmp) > LX_MAX_UPLOAD) throw new RuntimeException('Dosya 50 MB\'tan büyük olamaz.');
    $ext = null;
    $info = @getimagesize($tmp);
    if ($info) {
        $types = array(IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif');
        if (defined('IMAGETYPE_WEBP')) $types[IMAGETYPE_WEBP] = 'webp';
        if (isset($types[$info[2]])) $ext = $types[$info[2]];
    } elseif ($allowVideo) {
        $head = (string) file_get_contents($tmp, false, null, 0, 16);
        if (substr($head, 4, 4) === 'ftyp') $ext = 'mp4';
        elseif (substr($head, 0, 4) === "\x1A\x45\xDF\xA3") $ext = 'webm';
    }
    if ($ext === null) throw new RuntimeException($allowVideo ? 'Yalnızca JPG, PNG, WEBP, GIF görsel ya da MP4/WEBM video yüklenebilir.' : 'Yalnızca JPG, PNG, WEBP veya GIF görsel yüklenebilir.');
    if (!is_dir(LX_UPLOADS)) mkdir(LX_UPLOADS, 0755, true);
    $name = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($tmp, LX_UPLOADS . '/' . $name)) throw new RuntimeException('Dosya kaydedilemedi (klasör izni?).');
    return LX_UPLOADS_URL . '/' . $name;
}

// Büyük fotoğrafları küçültür (GD varsa); yükleme hızını ve sayfa ağırlığını düşürür
function lx_resize_upload($url, $max = 1800)
{
    if (!function_exists('imagecreatetruecolor')) return;
    $path = LX_UPLOADS . '/' . basename($url);
    $info = @getimagesize($path);
    if (!$info || ($info[0] <= $max && $info[1] <= $max)) return;
    $types = array(IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng');
    if (defined('IMAGETYPE_WEBP') && function_exists('imagecreatefromwebp')) $types[IMAGETYPE_WEBP] = 'imagecreatefromwebp';
    if (!isset($types[$info[2]])) return;
    $src = @call_user_func($types[$info[2]], $path);
    if (!$src) return;
    $r = min($max / $info[0], $max / $info[1]);
    $w = (int) round($info[0] * $r); $hh = (int) round($info[1] * $r);
    $dst = imagecreatetruecolor($w, $hh);
    imagealphablending($dst, false); imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $hh, $info[0], $info[1]);
    if ($info[2] === IMAGETYPE_JPEG) imagejpeg($dst, $path, 84);
    elseif ($info[2] === IMAGETYPE_PNG) imagepng($dst, $path, 7);
    else imagewebp($dst, $path, 84);
    imagedestroy($src); imagedestroy($dst);
}

// $_FILES içindeki çoklu dosya alanını tek tek yükler
function lx_store_many($name, $k = null)
{
    $urls = array();
    if (empty($_FILES[$name])) return $urls;
    $f = $_FILES[$name];
    $names = $k === null ? $f['name'] : (isset($f['name'][$k]) ? $f['name'][$k] : array());
    foreach ((array) $names as $j => $unused) {
        $pick = function ($key) use ($f, $k, $j) { return $k === null ? $f[$key][$j] : $f[$key][$k][$j]; };
        if ($pick('error') === UPLOAD_ERR_NO_FILE) continue;
        $_FILES['one'] = array('name' => $pick('name'), 'type' => $pick('type'), 'tmp_name' => $pick('tmp_name'), 'error' => $pick('error'), 'size' => $pick('size'));
        $u = lx_store_upload('one', false);
        lx_resize_upload($u);
        $urls[] = $u;
    }
    return $urls;
}

$auth = lx_auth();
$logged = $auth && !empty($_SESSION['ok']) && $_SESSION['ok'] === $auth['hash'];
$langs = lx_langs();
$pages = lx_pages();
$sections = array('genel' => 'Genel (menü ve alt bilgi)') + $pages + array('ayarlar' => 'Ayarlar');
$lang = isset($_REQUEST['lang']) && isset($langs[$_REQUEST['lang']]) ? $_REQUEST['lang'] : 'tr';
$page = isset($_REQUEST['page']) && isset($sections[$_REQUEST['page']]) ? $_REQUEST['page'] : '/';
$open = isset($_REQUEST['open']) ? preg_replace('/[^a-z0-9]/', '', (string) $_REQUEST['open']) : '';
$hotelSel = isset($_REQUEST['hotel']) ? preg_replace('/[^a-z0-9]/', '', (string) $_REQUEST['hotel']) : '';
$back = array('lang' => $lang, 'page' => $page, 'open' => $open, 'hotel' => $hotelSel);

// Çok büyük gönderimde PHP $_POST'u boş bırakır
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) {
    lx_flash('Gönderilen dosyalar sunucunun bir seferde kabul ettiğinden büyük (' . ini_get('post_max_size') . '). Fotoğrafları birkaç parti hâlinde yükleyin.', false);
    lx_redirect($back);
}

// ---------------- İşlemler ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if (!isset($_POST['csrf']) || !hash_equals(lx_csrf(), (string) $_POST['csrf'])) {
        lx_flash('Oturum süresi doldu, lütfen tekrar deneyin.', false);
        lx_redirect($back);
    }
    if ($action === 'setup' && !$auth) {
        $p1 = (string) $_POST['password'];
        if (lx_fail_state() >= 8) lx_flash('Çok fazla hatalı deneme. 15 dakika sonra tekrar deneyin.', false);
        elseif (!password_verify(strtoupper(trim((string) $_POST['code'])), LX_SETUP_HASH)) { lx_fail_state(true); lx_flash('Kurulum kodu yanlış.', false); }
        elseif (strlen($p1) < 8) lx_flash('Şifre en az 8 karakter olmalı.', false);
        elseif ($p1 !== (string) $_POST['password2']) lx_flash('Şifreler aynı değil.', false);
        else {
            $hash = password_hash($p1, PASSWORD_DEFAULT);
            lx_save_json('auth.json', array('hash' => $hash, 'created' => date('c')));
            session_regenerate_id(true);
            $_SESSION['ok'] = $hash;
            lx_flash('Şifre kaydedildi. Artık siteyi düzenleyebilirsiniz.');
        }
        lx_redirect(array());
    }
    if ($action === 'login' && $auth) {
        if (lx_fail_state() >= 8) lx_flash('Çok fazla hatalı deneme. 15 dakika sonra tekrar deneyin.', false);
        elseif (password_verify((string) $_POST['password'], $auth['hash'])) {
            session_regenerate_id(true);
            $_SESSION['ok'] = $auth['hash'];
        } else { lx_fail_state(true); sleep(1); lx_flash('Şifre yanlış.', false); }
        lx_redirect(array());
    }
    if ($action === 'logout') { $_SESSION = array(); session_destroy(); lx_redirect(array()); }
    if (!$logged) lx_redirect(array());

    $content = lx_load_content();
    try {
        switch ($action) {
            case 'password':
                if (!password_verify((string) $_POST['current'], $auth['hash'])) throw new RuntimeException('Mevcut şifre yanlış.');
                if (strlen((string) $_POST['password']) < 8) throw new RuntimeException('Yeni şifre en az 8 karakter olmalı.');
                $hash = password_hash((string) $_POST['password'], PASSWORD_DEFAULT);
                lx_save_json('auth.json', array('hash' => $hash, 'created' => date('c')));
                $_SESSION['ok'] = $hash;
                lx_flash('Şifre değiştirildi.');
                lx_redirect($back);
            case 'texts':
                $orig = isset($_POST['o']) ? (array) $_POST['o'] : array();
                $vals = isset($_POST['t']) ? (array) $_POST['t'] : array();
                $n = 0;
                if (!isset($content['texts'][$lang])) $content['texts'][$lang] = array();
                foreach ($orig as $i => $o) {
                    if (!isset($vals[$i])) continue;
                    $o = (string) $o;
                    $v = str_replace(array("\r\n", "\r", "\n"), ' ', (string) $vals[$i]);
                    $cur = isset($content['texts'][$lang][$o]) ? $content['texts'][$lang][$o] : $o;
                    if ($v === $cur) continue;
                    if ($v === $o) unset($content['texts'][$lang][$o]);
                    else $content['texts'][$lang][$o] = $v;
                    $n++;
                }
                if ($n) lx_save_json('content.json', $content);
                lx_flash($n ? $n . ' yazı kaydedildi. Site hemen güncellendi.' : 'Değişen yazı yok.');
                lx_redirect($back);
            case 'img_replace':
                $src = (string) $_POST['src'];
                $u = lx_store_upload('file', true);
                lx_resize_upload($u, 2400);
                $content['images'][$src] = $u;
                lx_save_json('content.json', $content);
                lx_flash('Görsel değiştirildi (tüm dillerde).');
                lx_redirect($back);
            case 'img_reset':
                $src = (string) $_POST['src'];
                unset($content['images'][$src], $content['hidden'][$src]);
                lx_save_json('content.json', $content);
                lx_flash('Görsel orijinal hâline döndü.');
                lx_redirect($back);
            case 'img_hide':
                $src = (string) $_POST['src'];
                if (!empty($content['hidden'][$src])) unset($content['hidden'][$src]);
                else $content['hidden'][$src] = true;
                lx_save_json('content.json', $content);
                lx_flash(!empty($content['hidden'][$src]) ? 'Görsel gizlendi (tüm dillerde).' : 'Görsel tekrar gösteriliyor.');
                lx_redirect($back);
            case 'gal_add':
                $g = (string) (int) $_POST['g'];
                $urls = lx_store_many('files');
                if (!$urls) throw new RuntimeException('Fotoğraf seçilmedi.');
                foreach ($urls as $u) $content['added'][$page][$g][] = array('src' => $u, 'alt' => (string) $_POST['alt']);
                lx_save_json('content.json', $content);
                lx_flash(count($urls) . ' fotoğraf galeriye eklendi (tüm dillerde).');
                lx_redirect($back);
            case 'gal_remove':
                $g = (string) (int) $_POST['g'];
                $i = (int) $_POST['i'];
                if (isset($content['added'][$page][$g][$i])) {
                    array_splice($content['added'][$page][$g], $i, 1);
                    lx_save_json('content.json', $content);
                }
                lx_flash('Fotoğraf galeriden kaldırıldı.');
                lx_redirect($back);

            // ----- Oteller -----
            case 'hotel_save':
                $hotels = lx_hotels_load();
                $id = preg_replace('/[^a-z0-9]/', '', (string) $_POST['id']);
                $idx = null;
                foreach ($hotels as $k => $h) if ($h['id'] === $id) $idx = $k;
                $name = trim((string) $_POST['name']);
                if ($name === '') throw new RuntimeException('Otel adı boş olamaz.');
                $h = $idx === null ? array('id' => bin2hex(random_bytes(5)), 'slug' => '', 'published' => true, 'galleries' => lx_hotel_default_galleries()) : $hotels[$idx];
                $h['name'] = $name;
                $h['stars'] = max(0, min(7, (int) $_POST['stars']));
                $h['published'] = !empty($_POST['published']);
                foreach (array('city', 'summary', 'description') as $fld) {
                    $vals = isset($_POST[$fld]) ? (array) $_POST[$fld] : array();
                    $h[$fld] = array();
                    foreach ($vals as $l => $v) if (isset($langs[$l]) && trim((string) $v) !== '') $h[$fld][$l] = trim((string) $v);
                }
                // Adres (slug): ilk kayıtta addan, sonra sabit
                if ($h['slug'] === '') {
                    $base = lx_slugify($name); $slug = $base; $n = 2;
                    $taken = array(); foreach ($hotels as $o) $taken[$o['slug']] = true;
                    while (isset($taken[$slug])) $slug = $base . '-' . $n++;
                    $h['slug'] = $slug;
                }
                $cover = lx_store_many('cover');
                if ($cover) $h['cover'] = $cover[0];
                // Galeriler
                $gal = array();
                $titles = isset($_POST['gtitle']) ? (array) $_POST['gtitle'] : array();
                foreach ($titles as $k => $t) {
                    $k = (int) $k;
                    $old = isset($h['galleries'][$k]) ? $h['galleries'][$k] : array('title' => array(), 'photos' => array());
                    if (!empty($_POST['gremove'][$k])) continue;
                    $tt = array();
                    foreach ((array) $t as $l => $v) if (trim((string) $v) !== '') $tt[$l] = trim((string) $v);
                    $photos = array();
                    foreach ($old['photos'] as $j => $p) if (empty($_POST['gdel'][$k][$j])) $photos[] = $p;
                    $photos = array_merge($photos, lx_store_many('gfiles', $k));
                    if (!$tt && !$photos) continue; // boş yeni galeri
                    if (!$tt) $tt = array('tr' => 'Fotoğraflar', 'en' => 'Photos');
                    $gal[] = array('title' => $tt, 'photos' => $photos);
                }
                $h['galleries'] = $gal;
                if (empty($h['cover'])) foreach ($gal as $g) if ($g['photos']) { $h['cover'] = $g['photos'][0]; break; }
                if ($idx === null) $hotels[] = $h; else $hotels[$idx] = $h;
                lx_save_json('hotels.json', $hotels);
                lx_flash('"' . $name . '" kaydedildi.' . ($h['published'] ? ' Sitede yayında.' : ' Gizli (sitede görünmüyor).'));
                $back['hotel'] = $h['id'];
                lx_redirect($back);
            case 'hotel_toggle':
            case 'hotel_delete':
            case 'hotel_up':
            case 'hotel_down':
                $hotels = lx_hotels_load();
                $id = (string) $_POST['id'];
                foreach ($hotels as $k => $h) {
                    if ($h['id'] !== $id) continue;
                    if ($action === 'hotel_toggle') { $hotels[$k]['published'] = empty($h['published']); lx_flash(empty($h['published']) ? 'Otel yayına alındı.' : 'Otel gizlendi.'); }
                    if ($action === 'hotel_delete') { array_splice($hotels, $k, 1); lx_flash('Otel silindi.'); $back['hotel'] = ''; }
                    if ($action === 'hotel_up' && $k > 0) { $t = $hotels[$k - 1]; $hotels[$k - 1] = $h; $hotels[$k] = $t; }
                    if ($action === 'hotel_down' && $k < count($hotels) - 1) { $t = $hotels[$k + 1]; $hotels[$k + 1] = $h; $hotels[$k] = $t; }
                    break;
                }
                lx_save_json('hotels.json', array_values($hotels));
                lx_redirect($back);
        }
    } catch (RuntimeException $e) {
        lx_flash($e->getMessage(), false);
        lx_redirect($back);
    }
    lx_redirect($back);
}

$flash = isset($_SESSION['flash']) ? $_SESSION['flash'] : null;
unset($_SESSION['flash']);
$csrf = lx_csrf();

function q($extra) { global $lang, $page; return '?' . http_build_query(array_merge(array('lang' => $lang, 'page' => $page), $extra)); }
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Site düzenleme · Lux Locations</title>
<style>
:root{--c:#3a5f78;--bg:#f4f6f8;--line:#dde3e8;--mut:#66727c;--ok:#1f7a4d;--err:#b3261e;--warn:#d29b2a}
*{box-sizing:border-box}body{margin:0;font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:#1c2830}
a{color:var(--c)}
.top{background:var(--c);color:#fff;padding:12px 20px;display:flex;gap:16px;align-items:center;flex-wrap:wrap;position:sticky;top:0;z-index:5}
.top b{font-size:17px}.top a,.top button{color:#fff;opacity:.92;background:none;border:0;font:inherit;cursor:pointer;text-decoration:underline}.top .sp{flex:1}
.wrap{display:grid;grid-template-columns:230px 1fr;gap:20px;max-width:1240px;margin:0 auto;padding:20px 16px 80px}
.side{position:sticky;top:70px;align-self:start;background:#fff;border:1px solid var(--line);border-radius:10px;padding:8px}
.side a{display:block;padding:9px 12px;border-radius:7px;text-decoration:none;color:#1c2830;font-weight:600}
.side a.on{background:var(--c);color:#fff}.side a:hover:not(.on){background:#eef2f5}
.side hr{border:0;border-top:1px solid var(--line);margin:6px 0}
@media (max-width:820px){.wrap{grid-template-columns:1fr}.side{position:static;display:flex;flex-wrap:wrap;gap:4px}.side hr{display:none}}
.card{background:#fff;border:1px solid var(--line);border-radius:10px;padding:18px;margin-bottom:14px}
.narrow{max-width:420px;margin:60px auto}
label{display:block;font-weight:600;margin:10px 0 4px}
input[type=text],input[type=password],select,textarea{width:100%;padding:9px 10px;border:1px solid #c5cdd4;border-radius:7px;font:inherit;background:#fff}
textarea{resize:vertical;min-height:42px}
.btn{display:inline-block;background:var(--c);color:#fff;border:0;border-radius:7px;padding:9px 16px;font:inherit;font-weight:600;cursor:pointer;text-decoration:none}
.btn.big{padding:12px 22px;font-size:16px}.btn.sec{background:#fff;color:var(--c);border:1px solid var(--c)}.btn.small{padding:5px 10px;font-size:13px}.btn.danger{background:#fff;color:var(--err);border:1px solid var(--err)}
.flash{padding:10px 14px;border-radius:8px;margin-bottom:14px;font-weight:600}.flash.ok{background:#e3f4ea;color:var(--ok)}.flash.err{background:#fbe5e3;color:var(--err)}
.hint{color:var(--mut);font-size:13px;margin:4px 0 0}
.langs{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px}.langs a{padding:6px 11px;border:1px solid var(--line);border-radius:20px;background:#fff;text-decoration:none;font-weight:600;font-size:13px;color:var(--c)}.langs a.on{background:var(--c);color:#fff;border-color:var(--c)}
details.blk{background:#fff;border:1px solid var(--line);border-radius:10px;margin-bottom:12px}
details.blk>summary{list-style:none;cursor:pointer;padding:14px 18px;font-weight:700;font-size:16px;display:flex;align-items:center;gap:10px}
details.blk>summary::-webkit-details-marker{display:none}
details.blk>summary:before{content:"▸";color:var(--c);transition:transform .15s}details.blk[open]>summary:before{transform:rotate(90deg)}
details.blk>summary .cnt{margin-left:auto;font-weight:500;font-size:12px;color:var(--mut)}
.blk .in{padding:0 18px 16px;border-top:1px solid var(--line)}
.item{display:grid;grid-template-columns:220px 1fr;gap:16px;padding:14px 0;border-top:1px dashed var(--line)}
.item:first-child{border-top:0}.item.noimg{grid-template-columns:1fr}
.item h4{margin:0 0 6px;font-size:14px;color:var(--c)}
@media (max-width:700px){.item{grid-template-columns:1fr}}
.f{margin:0 0 10px}.f .meta{display:flex;justify-content:space-between;gap:8px;font-size:12px;color:var(--mut);margin-bottom:3px}
.f.changed textarea{border-color:var(--warn);background:#fffbf0}
.f .orig,.f .trhint{font-size:12px;color:var(--mut);margin-top:3px}.f .orig button{font-size:12px;color:var(--c);background:none;border:0;cursor:pointer;text-decoration:underline;padding:0}
.save{display:flex;gap:12px;align-items:center;padding-top:10px;border-top:1px solid var(--line);margin-top:6px}
.imgs{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-top:8px}
.im{border:1px solid var(--line);border-radius:9px;overflow:hidden;background:#fff;display:flex;flex-direction:column}
.im .pic{aspect-ratio:4/3;background:#e8ecef center/cover no-repeat;position:relative}
.im .pic video{width:100%;height:100%;object-fit:cover}
.im .badge{position:absolute;top:6px;left:6px;background:rgba(0,0,0,.65);color:#fff;font-size:11px;padding:2px 7px;border-radius:20px}
.im.hidden .pic{opacity:.3}
.im .body{padding:8px;display:flex;flex-direction:column;gap:6px;font-size:12px}
.im form{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin:0}.im input[type=file]{font-size:12px;max-width:100%}
.addgal{background:#f3f7fa;border:1px dashed #b9c8d4;border-radius:9px;padding:12px;margin-top:12px}
h2{margin:0 0 6px;font-size:20px}h3{margin:18px 0 8px;font-size:16px}
.hotels{display:grid;gap:10px}
.hrow{display:grid;grid-template-columns:120px 1fr auto;gap:14px;align-items:center;border:1px solid var(--line);border-radius:9px;padding:10px;background:#fff}
.hrow .pic{aspect-ratio:4/3;background:#e8ecef center/cover no-repeat;border-radius:6px}
.hrow .acts{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}.hrow .acts form{margin:0}
.tag{display:inline-block;font-size:11px;padding:1px 8px;border-radius:20px;background:#e3f4ea;color:var(--ok);font-weight:700}.tag.off{background:#eee;color:#777}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}@media (max-width:700px){.grid2{grid-template-columns:1fr}}
.gbox{border:1px solid var(--line);border-radius:9px;padding:12px;margin-top:10px;background:#fafcfd}
.thumbs{display:flex;flex-wrap:wrap;gap:8px;margin:8px 0}.thumbs label{margin:0;font-weight:400;font-size:12px;display:flex;flex-direction:column;align-items:center;gap:3px}
.thumbs .t{width:110px;aspect-ratio:4/3;background:#e8ecef center/cover no-repeat;border-radius:6px}
</style>
</head>
<body>
<?php if (!$auth || !$logged): ?>
<main>
  <div class="card narrow">
    <h2>Lux Locations · Site düzenleme</h2>
    <?php if ($flash): ?><div class="flash <?= $flash[1] ? 'ok' : 'err' ?>"><?= h($flash[0]) ?></div><?php endif; ?>
    <?php if (!$auth): ?>
      <p class="hint">İlk kurulum: size iletilen kurulum kodunu girin ve bu ekran için bir şifre belirleyin.</p>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="setup">
        <label>Kurulum kodu</label><input type="text" name="code" required autocomplete="off" placeholder="XXXX-XXXX-XXXX">
        <label>Yeni şifre (en az 8 karakter)</label><input type="password" name="password" required minlength="8" autocomplete="new-password">
        <label>Yeni şifre (tekrar)</label><input type="password" name="password2" required minlength="8" autocomplete="new-password">
        <p><button class="btn">Şifreyi kaydet</button></p>
      </form>
    <?php else: ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="login">
        <label>Şifre</label><input type="password" name="password" required autofocus autocomplete="current-password">
        <p><button class="btn">Giriş</button></p>
      </form>
    <?php endif; ?>
    <p class="hint"><a href="/admin/">← Talepler paneline dön</a></p>
  </div>
</main>
<?php else:
    $content = lx_load_content();
    $isPage = isset($pages[$page]);
    $previewBase = $page === 'genel' ? '/' : ($isPage ? $page : '/');
    $url = lx_page_path($lang, $previewBase);
    $hidden = '<input type="hidden" name="csrf" value="' . h($csrf) . '"><input type="hidden" name="lang" value="' . h($lang) . '"><input type="hidden" name="page" value="' . h($page) . '">';

    // Bir görsel kutusu (değiştir / gizle / orijinale dön)
    $imgTile = function ($im, $openId) use ($content, $hidden) {
        $src = $im['src'];
        $now = isset($content['images'][$src]) ? $content['images'][$src] : $src;
        $isHidden = !empty($content['hidden'][$src]);
        $isVideo = preg_match('#\.(mp4|webm|mov)$#i', $now);
        $hid = $hidden . '<input type="hidden" name="open" value="' . h($openId) . '"><input type="hidden" name="src" value="' . h($src) . '">';
        $o = '<div class="im' . ($isHidden ? ' hidden' : '') . '"><div class="pic"' . ($isVideo ? '' : ' style="background-image:url(\'' . h($now) . '\')"') . '>';
        if ($isVideo) $o .= '<video src="' . h($now) . '" muted preload="metadata"></video>';
        $o .= '<span class="badge">' . h($im['kind']) . ($isHidden ? ' · gizli' : ($now !== $src ? ' · değiştirildi' : '')) . '</span></div><div class="body">';
        $o .= '<form method="post" enctype="multipart/form-data">' . $hid . '<input type="hidden" name="action" value="img_replace"><input type="file" name="file" accept="' . ($im['kind'] === 'Video' ? 'video/mp4,video/webm' : 'image/jpeg,image/png,image/webp,image/gif') . '" required onchange="this.form.submit()"><span class="hint">Yeni dosya seçince hemen değişir</span></form>';
        $o .= '<form method="post">' . $hid;
        if ($im['kind'] !== 'Arka plan görseli') $o .= '<button class="btn small sec" name="action" value="img_hide">' . ($isHidden ? 'Göster' : 'Gizle') . '</button>';
        if ($now !== $src || $isHidden) $o .= '<button class="btn small sec" name="action" value="img_reset">Orijinale dön</button>';
        $o .= '</form></div></div>';
        return $o;
    };
    // Bir yazı alanı
    $field = function ($t, $i, $formId, $ov, $trText) {
        $o = $t['text'];
        $cur = array_key_exists($o, $ov) ? $ov[$o] : $o;
        $changed = $cur !== $o;
        $len = function_exists('mb_strlen') ? mb_strlen($cur, 'UTF-8') : strlen($cur);
        $rows = max(1, min(8, (int) ceil($len / 80)));
        $r = '<div class="f' . ($changed ? ' changed' : '') . '"><div class="meta"><span>' . h($t['where']) . '</span>' . ($changed ? '<span>değiştirildi</span>' : '') . '</div>';
        $r .= '<input type="hidden" form="' . $formId . '" name="o[' . $i . ']" value="' . h($o) . '">';
        $r .= '<textarea form="' . $formId . '" name="t[' . $i . ']" rows="' . $rows . '" dir="auto">' . h($cur) . '</textarea>';
        if ($trText !== null && $trText !== $o) $r .= '<div class="trhint">Türkçesi: <span dir="auto">' . h($trText) . '</span></div>';
        if ($changed) $r .= '<div class="orig">Orijinali: <span dir="auto">' . h($o) . '</span> · <button type="button" data-orig="' . h($o) . '" onclick="this.closest(\'.f\').querySelector(\'textarea\').value=this.dataset.orig">orijinale dön</button></div>';
        return $r . '</div>';
    };
?>
<div class="top">
  <b>Lux Locations · Site düzenleme</b>
  <a href="/admin/">Talepler paneli</a>
  <span class="sp"></span>
  <a href="<?= h($url) ?>" target="_blank" rel="noopener">Sayfayı sitede aç ↗</a>
  <form method="post" style="margin:0"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="logout"><button>Çıkış</button></form>
</div>
<div class="wrap">
  <nav class="side">
    <?php foreach ($sections as $key => $name):
        if ($key === 'ayarlar') echo '<hr>'; ?>
      <a href="?<?= h(http_build_query(array('lang' => $lang, 'page' => $key))) ?>" class="<?= $key === $page ? 'on' : '' ?>"><?= h($key === '/oteller/' ? 'Oteller' : $name) ?></a>
      <?php if ($key === 'genel') echo '<hr>'; ?>
    <?php endforeach; ?>
  </nav>
  <main>
  <?php if ($flash): ?><div class="flash <?= $flash[1] ? 'ok' : 'err' ?>"><?= h($flash[0]) ?></div><?php endif; ?>

  <?php if ($page === 'ayarlar'): ?>
    <div class="card"><h2>Ayarlar</h2>
      <form method="post"><?= $hidden ?><input type="hidden" name="action" value="password">
        <label>Mevcut şifre</label><input type="password" name="current" required autocomplete="current-password">
        <label>Yeni şifre (en az 8 karakter)</label><input type="password" name="password" required minlength="8" autocomplete="new-password">
        <p><button class="btn">Şifreyi değiştir</button></p>
      </form>
    </div>
  <?php else: ?>

  <?php
  // ---------- Oteller yöneticisi ----------
  if ($page === '/oteller/'):
      $hotels = lx_hotels_load();
      $edit = null;
      if ($hotelSel === 'new') $edit = array('id' => '', 'name' => '', 'stars' => 0, 'published' => true, 'city' => array(), 'summary' => array(), 'description' => array(), 'cover' => '', 'galleries' => lx_hotel_default_galleries(), 'slug' => '');
      else foreach ($hotels as $hh) if ($hh['id'] === $hotelSel) $edit = $hh;
  ?>
    <?php if ($edit === null): ?>
      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
          <div><h2>Oteller</h2><p class="hint">Sitedeki "Oteller" sayfasında görünen oteller. Sıralama sitede de aynıdır.</p></div>
          <a class="btn big" href="<?= h(q(array('hotel' => 'new'))) ?>">+ Yeni otel ekle</a>
        </div>
        <?php if (!$hotels): ?><p class="hint" style="margin-top:14px">Henüz otel yok. "+ Yeni otel ekle" ile başlayın; ilk otel eklenince sitedeki "çok yakında" yazısı yerine otel kartları görünür.</p><?php endif; ?>
        <div class="hotels" style="margin-top:14px">
        <?php foreach ($hotels as $hh): $cnt = 0; foreach ($hh['galleries'] as $g) $cnt += count($g['photos']); ?>
          <div class="hrow">
            <div class="pic" style="background-image:url('<?= h(isset($hh['cover']) ? $hh['cover'] : '') ?>')"></div>
            <div><b><?= h($hh['name']) ?></b> <?= h(lx_stars($hh['stars'])) ?> <span class="tag<?= empty($hh['published']) ? ' off' : '' ?>"><?= empty($hh['published']) ? 'Gizli' : 'Yayında' ?></span>
              <div class="hint"><?= h(lx_tr_field($hh['city'], 'tr')) ?> · <?= $cnt ?> fotoğraf · <a href="<?= h(lx_hotel_url('tr', $hh['slug'])) ?>" target="_blank">sitede gör ↗</a></div></div>
            <div class="acts">
              <a class="btn small" href="<?= h(q(array('hotel' => $hh['id']))) ?>">Düzenle</a>
              <form method="post"><?= $hidden ?><input type="hidden" name="id" value="<?= h($hh['id']) ?>"><button class="btn small sec" name="action" value="hotel_up" title="Yukarı">↑</button><button class="btn small sec" name="action" value="hotel_down" title="Aşağı">↓</button><button class="btn small sec" name="action" value="hotel_toggle"><?= empty($hh['published']) ? 'Yayına al' : 'Gizle' ?></button></form>
              <form method="post" onsubmit="return confirm('<?= h(addslashes($hh['name'])) ?> silinsin mi? Bu geri alınamaz.')"><?= $hidden ?><input type="hidden" name="id" value="<?= h($hh['id']) ?>"><button class="btn small danger" name="action" value="hotel_delete">Sil</button></form>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
      </div>
    <?php else: ?>
      <form class="card" method="post" enctype="multipart/form-data">
        <?= $hidden ?><input type="hidden" name="action" value="hotel_save"><input type="hidden" name="id" value="<?= h($edit['id']) ?>"><input type="hidden" name="hotel" value="<?= h($edit['id'] === '' ? 'new' : $edit['id']) ?>">
        <p><a href="<?= h(q(array())) ?>">← Otel listesine dön</a></p>
        <h2><?= $edit['id'] === '' ? 'Yeni otel ekle' : h($edit['name']) ?></h2>
        <p class="hint">Türkçe yazmanız yeterli. Başka bir dilde yazılmayan alanlar o dilde İngilizce, o da yoksa Türkçe görünür.</p>
        <div class="grid2">
          <div><label>Otel adı *</label><input type="text" name="name" required value="<?= h($edit['name']) ?>" placeholder="Örn. Pera Palace Hotel"></div>
          <div><label>Şehir / semt</label><input type="text" name="city[tr]" value="<?= h(isset($edit['city']['tr']) ? $edit['city']['tr'] : '') ?>" placeholder="Örn. İstanbul, Beyoğlu"></div>
          <div><label>Yıldız</label><select name="stars"><?php for ($s = 0; $s <= 5; $s++): ?><option value="<?= $s ?>"<?= (int) $edit['stars'] === $s ? ' selected' : '' ?>><?= $s ? str_repeat('★', $s) . ' (' . $s . ')' : 'Belirtme' ?></option><?php endfor; ?></select></div>
          <div><label>Durum</label><label style="font-weight:400"><input type="checkbox" name="published" value="1"<?= !empty($edit['published']) ? ' checked' : '' ?>> Sitede yayında</label></div>
        </div>
        <label>Kısa açıklama (otel listesinde görünür)</label><textarea name="summary[tr]" rows="2" placeholder="Örn. Tarihi yarımadaya yürüme mesafesinde, Boğaz manzaralı butik otel."><?= h(isset($edit['summary']['tr']) ? $edit['summary']['tr'] : '') ?></textarea>
        <label>Detaylı açıklama (otelin sayfasında görünür)</label><textarea name="description[tr]" rows="6" placeholder="Odalar, kahvaltı, konum, olanaklar… Paragraf için bir satır boş bırakın."><?= h(isset($edit['description']['tr']) ? $edit['description']['tr'] : '') ?></textarea>
        <details style="margin-top:10px"><summary style="cursor:pointer;font-weight:600;color:var(--c)">Diğer dillerde yazılar (isteğe bağlı)</summary>
          <?php foreach ($langs as $l => $ln): if ($l === 'tr') continue; ?>
            <div class="gbox"><b><?= h($ln) ?></b>
              <div class="grid2"><div><label>Şehir</label><input type="text" dir="auto" name="city[<?= $l ?>]" value="<?= h(isset($edit['city'][$l]) ? $edit['city'][$l] : '') ?>"></div>
              <div><label>Kısa açıklama</label><input type="text" dir="auto" name="summary[<?= $l ?>]" value="<?= h(isset($edit['summary'][$l]) ? $edit['summary'][$l] : '') ?>"></div></div>
              <label>Detaylı açıklama</label><textarea dir="auto" name="description[<?= $l ?>]" rows="3"><?= h(isset($edit['description'][$l]) ? $edit['description'][$l] : '') ?></textarea>
            </div>
          <?php endforeach; ?>
        </details>

        <h3>Kapak fotoğrafı</h3>
        <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
          <?php if (!empty($edit['cover'])): ?><div class="thumbs"><div class="t" style="width:180px;background-image:url('<?= h($edit['cover']) ?>')"></div></div><?php endif; ?>
          <div><input type="file" name="cover[]" accept="image/jpeg,image/png,image/webp"><p class="hint"><?= empty($edit['cover']) ? 'Seçmezseniz ilk galerinin ilk fotoğrafı kullanılır.' : 'Değiştirmek için yeni dosya seçin.' ?></p></div>
        </div>

        <h3>Fotoğraf galerileri</h3>
        <p class="hint">Her başlığa birden çok fotoğraf seçebilirsiniz. Fotoğrafı kaldırmak için altındaki kutuyu işaretleyin. Fotoğrafı olmayan galeri sitede görünmez.</p>
        <?php $gals = $edit['galleries']; $gals[] = array('title' => array(), 'photos' => array(), 'new' => true);
        foreach ($gals as $k => $g): ?>
          <div class="gbox">
            <div class="grid2">
              <div><label><?= empty($g['new']) ? 'Galeri başlığı' : '+ Yeni galeri başlığı' ?></label><input type="text" name="gtitle[<?= $k ?>][tr]" value="<?= h(isset($g['title']['tr']) ? $g['title']['tr'] : '') ?>" placeholder="Örn. Restoran"></div>
              <div><label>Başlığın İngilizcesi</label><input type="text" name="gtitle[<?= $k ?>][en]" value="<?= h(isset($g['title']['en']) ? $g['title']['en'] : '') ?>" placeholder="Örn. Restaurant"></div>
            </div>
            <?php if ($g['photos']): ?><div class="thumbs">
              <?php foreach ($g['photos'] as $j => $p): ?><label><span class="t" style="background-image:url('<?= h($p) ?>')"></span><span><input type="checkbox" name="gdel[<?= $k ?>][<?= $j ?>]" value="1"> kaldır</span></label><?php endforeach; ?>
            </div><?php endif; ?>
            <label style="font-weight:400">Fotoğraf ekle: <input type="file" name="gfiles[<?= $k ?>][]" multiple accept="image/jpeg,image/png,image/webp"></label>
            <?php if (empty($g['new'])): ?><label style="font-weight:400;color:var(--err)"><input type="checkbox" name="gremove[<?= $k ?>]" value="1"> Bu galeriyi tamamen sil</label><?php endif; ?>
          </div>
        <?php endforeach; ?>
        <div class="save" style="position:sticky;bottom:0;background:#fff;padding:12px 0"><button class="btn big">Kaydet</button><span class="hint">Büyük fotoğraflar otomatik küçültülür. Bir seferde çok fazla fotoğraf yüklenemezse birkaç seferde ekleyin.</span></div>
      </form>
    <?php endif; ?>
  <?php endif; // oteller ?>

  <?php if ($hotelSel === '' || $page !== '/oteller/'):
      $srcBase = $page === 'genel' ? '/' : $page;
      $file = lx_file_for(lx_page_path($lang, $srcBase));
      $html = $file ? (string) file_get_contents($file) : '';
      $mode = $page === 'genel' ? 'common' : 'page';
      $blocks = $html !== '' ? lx_page_blocks($html, $mode) : array();
      $trBlocks = array();
      if ($lang !== 'tr') { $tf = lx_file_for($srcBase); if ($tf) $trBlocks = lx_page_blocks((string) file_get_contents($tf), $mode); }
      $ov = isset($content['texts'][$lang]) ? $content['texts'][$lang] : array();
      $added = isset($content['added'][$srcBase]) ? $content['added'][$srcBase] : array();
      // Türkçe karşılık: aynı bölüm/kart/sıra
      $trOf = function ($bi, $ii, $ti) use ($trBlocks, $blocks) {
          if (!isset($trBlocks[$bi])) return null;
          $a = $ii === null ? $blocks[$bi]['texts'] : $blocks[$bi]['items'][$ii]['texts'];
          $b = $ii === null ? $trBlocks[$bi]['texts'] : (isset($trBlocks[$bi]['items'][$ii]) ? $trBlocks[$bi]['items'][$ii]['texts'] : array());
          return count($a) === count($b) && isset($b[$ti]) ? $b[$ti]['text'] : null;
      };
  ?>
    <div class="card" style="padding:14px 18px">
      <h2><?= h($page === '/oteller/' ? 'Oteller sayfasının yazıları' : $sections[$page]) ?></h2>
      <p class="hint"><?= $page === 'genel' ? 'Üst menü ve alt bilgi her sayfada aynıdır; burada bir kez değiştirince tüm sayfalarda değişir.' : 'Bir bölüme tıklayın, yazıyı değiştirip o bölümün <b>Kaydet</b> düğmesine basın. Görseller tüm dillerde ortaktır.' ?></p>
      <div class="langs" style="margin:12px 0 0">
        <?php foreach ($langs as $code => $name): ?><a href="?<?= h(http_build_query(array('lang' => $code, 'page' => $page, 'open' => $open))) ?>" class="<?= $code === $lang ? 'on' : '' ?>"><?= h($name) ?></a><?php endforeach; ?>
      </div>
    </div>
    <?php if ($html === ''): ?><div class="card"><div class="flash err">Bu sayfa sunucuda bulunamadı.</div></div><?php endif; ?>
    <?php $fi = 0; foreach ($blocks as $bi => $b):
        $bid = $b['id'];
        $formId = 'f-' . $bid;
        $nText = count($b['texts']); $nImg = count($b['images']);
        foreach ($b['items'] as $it) { $nText += count($it['texts']); $nImg += count($it['images']); }
        $isOpen = $open === $bid || ($open === '' && $bi === 0);
    ?>
      <details class="blk" id="<?= h($bid) ?>"<?= $isOpen ? ' open' : '' ?>>
        <summary><?= h($b['title']) ?><span class="cnt"><?= $nText ?> yazı<?= $nImg ? ' · ' . $nImg . ' görsel' : '' ?></span></summary>
        <div class="in">
          <form id="<?= $formId ?>" method="post"><?= $hidden ?><input type="hidden" name="action" value="texts"><input type="hidden" name="open" value="<?= h($bid) ?>"></form>
          <?php if (!empty($b['note'])): ?><p class="hint"><?= h($b['note']) ?></p><?php endif; ?>
          <?php if ($b['texts']): ?><div style="padding-top:12px"><?php foreach ($b['texts'] as $ti => $t) echo $field($t, $fi++, $formId, $ov, $trOf($bi, null, $ti)); ?></div><?php endif; ?>
          <?php foreach ($b['items'] as $ii => $it): ?>
            <div class="item<?= $it['images'] ? '' : ' noimg' ?>">
              <?php if ($it['images']): ?><div><?php foreach ($it['images'] as $im) echo $imgTile($im, $bid); ?></div><?php endif; ?>
              <div><h4><?= h($it['title']) ?></h4><?php foreach ($it['texts'] as $ti => $t) echo $field($t, $fi++, $formId, $ov, $trOf($bi, $ii, $ti)); ?></div>
            </div>
          <?php endforeach; ?>
          <?php if ($b['texts'] || array_filter($b['items'], function ($i) { return $i['texts']; })): ?>
            <div class="save"><button class="btn" form="<?= $formId ?>">Kaydet</button><span class="hint">Bu bölümün yazılarını <?= h($langs[$lang]) ?> olarak kaydeder.</span></div>
          <?php endif; ?>
          <?php if ($b['images']): ?><h3>Görseller</h3><div class="imgs"><?php foreach ($b['images'] as $im) echo $imgTile($im, $bid); ?></div><?php endif; ?>
          <?php foreach ($b['galleries'] as $gi): $list = isset($added[(string) $gi]) ? $added[(string) $gi] : array(); ?>
            <div class="addgal">
              <b>Bu galeriye fotoğraf ekle</b> <span class="hint">(eklenenler galerinin sonunda, tüm dillerde görünür)</span>
              <form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-top:8px">
                <?= $hidden ?><input type="hidden" name="action" value="gal_add"><input type="hidden" name="g" value="<?= (int) $gi ?>"><input type="hidden" name="open" value="<?= h($bid) ?>">
                <input type="file" name="files[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple required>
                <input type="text" name="alt" placeholder="Açıklama (isteğe bağlı)" style="max-width:260px">
                <button class="btn small">Ekle</button>
              </form>
              <?php if ($list): ?><div class="imgs">
                <?php foreach ($list as $i => $it): ?>
                  <div class="im"><div class="pic" style="background-image:url('<?= h($it['src']) ?>')"><span class="badge">Eklendi</span></div>
                    <div class="body"><form method="post" onsubmit="return confirm('Bu fotoğraf galeriden kaldırılsın mı?')"><?= $hidden ?><input type="hidden" name="action" value="gal_remove"><input type="hidden" name="g" value="<?= (int) $gi ?>"><input type="hidden" name="i" value="<?= (int) $i ?>"><input type="hidden" name="open" value="<?= h($bid) ?>"><button class="btn small danger">Kaldır</button></form></div></div>
                <?php endforeach; ?>
              </div><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endforeach; ?>
  <?php endif; ?>
  <?php endif; // ayarlar değil ?>
  </main>
</div>
<script>
// Açılan bölümü adres çubuğuna yaz, sayfa yenilenince açık kalsın
document.querySelectorAll('details.blk').forEach(function(d){d.addEventListener('toggle',function(){if(d.open){var u=new URL(location);u.searchParams.set('open',d.id);history.replaceState(null,'',u)}})});
var o=new URL(location).searchParams.get('open');if(o){var el=document.getElementById(o);if(el)el.scrollIntoView({block:'start'})}
</script>
<?php endif; ?>
</body>
</html>
