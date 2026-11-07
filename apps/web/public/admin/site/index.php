<?php
// Lux Locations · Site düzenleme (admin paneli eklentisi)
// Metinleri değiştirme, görselleri değiştirme/gizleme ve galerilere fotoğraf ekleme.
// Değişiklikler lx-content/data/content.json'a yazılır; sayfalar lx-content/render.php ile sunulurken uygulanır.
require dirname(dirname(__DIR__)) . '/lx-content/lib.php';

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

$auth = lx_auth();
$logged = $auth && !empty($_SESSION['ok']) && $_SESSION['ok'] === $auth['hash'];
$langs = lx_langs();
$pages = lx_pages();
$lang = isset($_REQUEST['lang']) && isset($langs[$_REQUEST['lang']]) ? $_REQUEST['lang'] : 'tr';
$page = isset($_REQUEST['page']) && isset($pages[$_REQUEST['page']]) ? $_REQUEST['page'] : '/';
$tab = isset($_REQUEST['tab']) && $_REQUEST['tab'] === 'img' ? 'img' : 'txt';
$back = array('lang' => $lang, 'page' => $page, 'tab' => $tab);

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
                lx_flash($n ? $n . ' metin kaydedildi. Site hemen güncellendi.' : 'Değişen metin yok.');
                lx_redirect($back);
            case 'img_replace':
                $src = (string) $_POST['src'];
                $content['images'][$src] = lx_store_upload('file', true);
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
                $count = 0;
                $files = isset($_FILES['files']) ? $_FILES['files'] : null;
                if (!$files || !is_array($files['name'])) throw new RuntimeException('Fotoğraf seçilmedi.');
                foreach ($files['name'] as $k => $unused) {
                    $_FILES['one'] = array('name' => $files['name'][$k], 'type' => $files['type'][$k], 'tmp_name' => $files['tmp_name'][$k], 'error' => $files['error'][$k], 'size' => $files['size'][$k]);
                    $url = lx_store_upload('one', false);
                    $content['added'][$page][$g][] = array('src' => $url, 'alt' => (string) $_POST['alt']);
                    $count++;
                }
                lx_save_json('content.json', $content);
                lx_flash($count . ' fotoğraf galeriye eklendi (tüm dillerde).');
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
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Site düzenleme · Lux Locations</title>
<style>
:root{--c:#3a5f78;--bg:#f4f6f8;--line:#dde3e8;--mut:#66727c;--ok:#1f7a4d;--err:#b3261e}
*{box-sizing:border-box}body{margin:0;font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:#1c2830}
header{background:var(--c);color:#fff;padding:12px 20px;display:flex;gap:16px;align-items:center;flex-wrap:wrap}
header b{font-size:17px}header a,header button{color:#fff;opacity:.9;background:none;border:0;font:inherit;cursor:pointer;text-decoration:underline}
header .sp{flex:1}
main{max-width:1100px;margin:0 auto;padding:20px 16px 80px}
.card{background:#fff;border:1px solid var(--line);border-radius:10px;padding:18px;margin-bottom:16px}
.narrow{max-width:420px;margin:60px auto}
label{display:block;font-weight:600;margin:10px 0 4px}
input[type=text],input[type=password],select,textarea{width:100%;padding:9px 10px;border:1px solid #c5cdd4;border-radius:7px;font:inherit;background:#fff}
textarea{resize:vertical;min-height:42px}
.btn{display:inline-block;background:var(--c);color:#fff;border:0;border-radius:7px;padding:9px 16px;font:inherit;font-weight:600;cursor:pointer;text-decoration:none}
.btn.sec{background:#fff;color:var(--c);border:1px solid var(--c)}.btn.small{padding:5px 10px;font-size:13px}.btn.danger{background:#fff;color:var(--err);border:1px solid var(--err)}
.flash{padding:10px 14px;border-radius:8px;margin-bottom:16px;font-weight:600}.flash.ok{background:#e3f4ea;color:var(--ok)}.flash.err{background:#fbe5e3;color:var(--err)}
.bar{display:flex;gap:10px;flex-wrap:wrap;align-items:end}.bar>div{flex:1;min-width:180px}
.tabs{display:flex;gap:6px;margin:4px 0 14px}.tabs a{padding:8px 14px;border-radius:7px;text-decoration:none;color:var(--c);border:1px solid var(--line);background:#fff;font-weight:600}.tabs a.on{background:var(--c);color:#fff;border-color:var(--c)}
.hint{color:var(--mut);font-size:13px;margin:4px 0 0}
.t{padding:10px 0;border-top:1px solid var(--line)}.t:first-child{border-top:0}
.t .meta{display:flex;justify-content:space-between;gap:8px;font-size:12px;color:var(--mut);margin-bottom:4px}
.t.changed textarea{border-color:#d29b2a;background:#fffbf0}
.t .orig{font-size:12px;color:var(--mut);margin-top:3px}
.t .orig button{font-size:12px;color:var(--c);background:none;border:0;cursor:pointer;text-decoration:underline;padding:0}
.sticky{position:sticky;bottom:0;background:#fff;border-top:1px solid var(--line);padding:12px 0;margin-top:8px;display:flex;gap:12px;align-items:center}
.imgs{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:14px}
.im{border:1px solid var(--line);border-radius:9px;overflow:hidden;background:#fff;display:flex;flex-direction:column}
.im .pic{aspect-ratio:4/3;background:#e8ecef center/cover no-repeat;position:relative}
.im .pic video{width:100%;height:100%;object-fit:cover}
.im .badge{position:absolute;top:6px;left:6px;background:rgba(0,0,0,.65);color:#fff;font-size:11px;padding:2px 7px;border-radius:20px}
.im.hidden .pic{opacity:.3}
.im .body{padding:10px;display:flex;flex-direction:column;gap:6px;font-size:13px}
.im .body small{color:var(--mut);word-break:break-all}
.im form{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin:0}
.im input[type=file]{font-size:12px;max-width:100%}
h2{margin:0 0 6px;font-size:18px}h3{margin:18px 0 8px;font-size:16px}
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
    $url = lx_page_path($lang, $page);
    $file = lx_file_for($url);
    $html = $file ? (string) file_get_contents($file) : '';
?>
<header>
  <b>Lux Locations · Site düzenleme</b>
  <a href="/admin/">Talepler paneli</a>
  <span class="sp"></span>
  <a href="<?= h($url) ?>" target="_blank" rel="noopener">Sayfayı yeni sekmede aç ↗</a>
  <form method="post" style="margin:0"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="logout"><button>Çıkış</button></form>
</header>
<main>
  <?php if ($flash): ?><div class="flash <?= $flash[1] ? 'ok' : 'err' ?>"><?= h($flash[0]) ?></div><?php endif; ?>

  <form class="card bar" method="get">
    <input type="hidden" name="tab" value="<?= h($tab) ?>">
    <div><label>Sayfa</label><select name="page" onchange="this.form.submit()">
      <?php foreach ($pages as $p => $name): ?><option value="<?= h($p) ?>"<?= $p === $page ? ' selected' : '' ?>><?= h($name) ?></option><?php endforeach; ?>
    </select></div>
    <div><label>Dil</label><select name="lang" onchange="this.form.submit()">
      <?php foreach ($langs as $code => $name): ?><option value="<?= h($code) ?>"<?= $code === $lang ? ' selected' : '' ?>><?= h($name) ?> (<?= strtoupper($code) ?>)</option><?php endforeach; ?>
    </select></div>
    <noscript><button class="btn">Aç</button></noscript>
  </form>

  <nav class="tabs">
    <a href="?<?= h(http_build_query(array('lang' => $lang, 'page' => $page, 'tab' => 'txt'))) ?>" class="<?= $tab === 'txt' ? 'on' : '' ?>">Metinler</a>
    <a href="?<?= h(http_build_query(array('lang' => $lang, 'page' => $page, 'tab' => 'img'))) ?>" class="<?= $tab === 'img' ? 'on' : '' ?>">Görseller</a>
  </nav>

  <?php if ($html === ''): ?>
    <div class="card"><div class="flash err">Bu sayfa sunucuda bulunamadı (<?= h($url) ?>).</div></div>
  <?php elseif ($tab === 'txt'):
      $texts = lx_extract_texts($html);
      $ov = isset($content['texts'][$lang]) ? $content['texts'][$lang] : array();
  ?>
    <form class="card" method="post">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="texts">
      <input type="hidden" name="lang" value="<?= h($lang) ?>"><input type="hidden" name="page" value="<?= h($page) ?>"><input type="hidden" name="tab" value="txt">
      <h2><?= h($pages[$page]) ?> · <?= h($langs[$lang]) ?></h2>
      <p class="hint">Metni değiştirip en alttaki <b>Kaydet</b>'e basın; site hemen güncellenir. Aynı metin bu dildeki başka sayfalarda da geçiyorsa (menü, alt bilgi gibi) orada da değişir. Bir metni silmek için kutuyu boşaltın.</p>
      <?php foreach ($texts as $i => $t):
          $o = $t['text'];
          $cur = array_key_exists($o, $ov) ? $ov[$o] : $o;
          $changed = $cur !== $o;
          $rows = max(1, min(8, (int) ceil((function_exists('mb_strlen') ? mb_strlen($cur, 'UTF-8') : strlen($cur)) / 95)));
      ?>
        <div class="t<?= $changed ? ' changed' : '' ?>">
          <div class="meta"><span><?= h($t['where']) ?></span><?php if ($changed): ?><span>değiştirildi</span><?php endif; ?></div>
          <input type="hidden" name="o[<?= $i ?>]" value="<?= h($o) ?>">
          <textarea name="t[<?= $i ?>]" rows="<?= $rows ?>" dir="auto"><?= h($cur) ?></textarea>
          <?php if ($changed): ?><div class="orig">Orijinali: <span dir="auto"><?= h($o) ?></span> · <button type="button" data-orig="<?= h($o) ?>" onclick="var a=this.closest('.t').querySelector('textarea');a.value=this.dataset.orig">orijinale dön</button></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div class="sticky"><button class="btn">Kaydet</button><span class="hint"><?= count($texts) ?> metin</span></div>
    </form>
  <?php else:
      $imgs = lx_extract_images($html);
      $gals = lx_extract_galleries($html);
      $added = isset($content['added'][$page]) ? $content['added'][$page] : array();
      $hidden_input = '<input type="hidden" name="csrf" value="' . h($csrf) . '"><input type="hidden" name="lang" value="' . h($lang) . '"><input type="hidden" name="page" value="' . h($page) . '"><input type="hidden" name="tab" value="img">';
  ?>
    <div class="card">
      <h2><?= h($pages[$page]) ?> · görseller</h2>
      <p class="hint">Görsel değişiklikleri tüm dillerde geçerlidir. <b>Değiştir</b> yeni dosyayı yükler, <b>Gizle</b> görseli sayfadan kaldırır, <b>Orijinale dön</b> ilk hâline getirir.</p>
    </div>

    <?php foreach ($gals as $g):
        $list = isset($added[(string) $g['index']]) ? $added[(string) $g['index']] : array(); ?>
      <div class="card">
        <h2>Galeriye fotoğraf ekle: <?= h($g['title']) ?></h2>
        <form method="post" enctype="multipart/form-data" class="bar">
          <?= $hidden_input ?><input type="hidden" name="action" value="gal_add"><input type="hidden" name="g" value="<?= (int) $g['index'] ?>">
          <div><label>Fotoğraflar (birden fazla seçebilirsiniz)</label><input type="file" name="files[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple required></div>
          <div><label>Açıklama (isteğe bağlı)</label><input type="text" name="alt" placeholder="Örn. Boğaz'da gün batımı"></div>
          <div style="flex:0"><button class="btn">Ekle</button></div>
        </form>
        <?php if ($list): ?>
          <h3>Eklenen fotoğraflar (galerinin sonunda görünür)</h3>
          <div class="imgs">
            <?php foreach ($list as $i => $it): ?>
              <div class="im"><div class="pic" style="background-image:url('<?= h($it['src']) ?>')"></div>
                <div class="body"><small><?= h($it['alt']) ?></small>
                  <form method="post" onsubmit="return confirm('Bu fotoğraf galeriden kaldırılsın mı?')"><?= $hidden_input ?><input type="hidden" name="action" value="gal_remove"><input type="hidden" name="g" value="<?= (int) $g['index'] ?>"><input type="hidden" name="i" value="<?= (int) $i ?>"><button class="btn small danger">Kaldır</button></form>
                </div></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <div class="card">
      <h2>Sayfadaki görseller (<?= count($imgs) ?>)</h2>
      <div class="imgs">
        <?php foreach ($imgs as $im):
            $src = $im['src'];
            $now = isset($content['images'][$src]) ? $content['images'][$src] : $src;
            $isHidden = !empty($content['hidden'][$src]);
            $isVideo = preg_match('#\.(mp4|webm|mov)$#i', $now);
            $changed = $now !== $src || $isHidden;
        ?>
          <div class="im<?= $isHidden ? ' hidden' : '' ?>">
            <div class="pic"<?= $isVideo ? '' : ' style="background-image:url(\'' . h($now) . '\')"' ?>>
              <?php if ($isVideo): ?><video src="<?= h($now) ?>" muted preload="metadata"></video><?php endif; ?>
              <span class="badge"><?= h($im['kind']) ?><?= $isHidden ? ' · gizli' : ($now !== $src ? ' · değiştirildi' : '') ?></span>
            </div>
            <div class="body">
              <small><?= h(basename($src)) ?></small>
              <form method="post" enctype="multipart/form-data"><?= $hidden_input ?><input type="hidden" name="action" value="img_replace"><input type="hidden" name="src" value="<?= h($src) ?>">
                <input type="file" name="file" accept="<?= $im['kind'] === 'Video' ? 'video/mp4,video/webm' : 'image/jpeg,image/png,image/webp,image/gif' ?>" required onchange="this.form.querySelector('button').disabled=false">
                <button class="btn small" disabled>Değiştir</button></form>
              <form method="post"><?= $hidden_input ?><input type="hidden" name="action" value="img_hide"><input type="hidden" name="src" value="<?= h($src) ?>">
                <?php if ($im['kind'] !== 'Arka plan görseli'): ?><button class="btn small sec"><?= $isHidden ? 'Göster' : 'Gizle' ?></button><?php endif; ?></form>
              <?php if ($changed): ?><form method="post"><?= $hidden_input ?><input type="hidden" name="action" value="img_reset"><input type="hidden" name="src" value="<?= h($src) ?>"><button class="btn small sec">Orijinale dön</button></form><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <details class="card"><summary><b>Bu ekranın şifresini değiştir</b></summary>
    <form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="password">
      <input type="hidden" name="lang" value="<?= h($lang) ?>"><input type="hidden" name="page" value="<?= h($page) ?>"><input type="hidden" name="tab" value="<?= h($tab) ?>">
      <label>Mevcut şifre</label><input type="password" name="current" required autocomplete="current-password">
      <label>Yeni şifre (en az 8 karakter)</label><input type="password" name="password" required minlength="8" autocomplete="new-password">
      <p><button class="btn">Değiştir</button></p>
    </form>
  </details>
</main>
<?php endif; ?>
</body>
</html>
