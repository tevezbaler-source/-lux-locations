<?php
// Tek giriş (SSO): sitenin diğer PHP sayfaları (/admin/ talepler, /admin/site/ düzenleyici) panelde giriş yapmış kullanıcıyı buradan tanır.
// Kullanım, sayfa kendi oturumunu başlatmadan ÖNCE:
//   require_once $_SERVER['DOCUMENT_ROOT'] . '/panel/sso.php';
//   $pu = lx_panel_sso(['yonetici']);   // null ya da ['id','username','name','role']
// Giriş sayfası bağlantısı: lx_panel_login_url('/admin/')
// Bu dosya panelin başka kodunu yüklemez (çakışma olmasın diye); doğrudan açılırsa hiçbir şey göstermez.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }

if (!function_exists('lx_panel_sso')) {
    /** Panel oturumu açık ve rolü izinliyse kullanıcıyı döner; değilse null. Mevcut oturuma dokunmaz. */
    function lx_panel_sso(array $roles = ['yonetici']): ?array {
        static $cache = [];
        $key = implode(',', $roles);
        if (array_key_exists($key, $cache)) return $cache[$key];
        $cache[$key] = null;

        $sid = (string)($_COOKIE['LXPANEL'] ?? '');
        if (!preg_match('/^[A-Za-z0-9,\-]{16,128}$/', $sid)) return null;
        // Panel oturum dosyasını doğrudan oku (PHP oturum işlevlerine dokunmadan; çağıran sayfanın oturumu bozulmasın)
        if (ini_get('session.save_handler') !== 'files') return null;
        $dir = (string)session_save_path();
        if (str_contains($dir, ';')) $dir = substr($dir, strrpos($dir, ';') + 1);
        if ($dir === '') $dir = sys_get_temp_dir();
        $raw = @file_get_contents(rtrim($dir, '/\\') . '/sess_' . $sid);
        if (!is_string($raw) || $raw === '') return null;
        // Oturum biçimi "ad|değer ad|değer...". Baştaki basit değerleri sırayla oku (metinler uzunluğuyla atlanır; kullanıcı metni ad gibi okunamaz).
        $data = []; $p = 0; $n = strlen($raw);
        while ($p < $n && preg_match('/\G([A-Za-z0-9_]+)\|/', $raw, $mm, 0, $p)) {
            $k = $mm[1]; $p += strlen($mm[0]);
            if (preg_match('/\Gi:(-?\d+);/', $raw, $v, 0, $p)) { $data[$k] = (int)$v[1]; $p += strlen($v[0]); }
            elseif (preg_match('/\Gb:([01]);/', $raw, $v, 0, $p)) { $data[$k] = $v[1] === '1'; $p += strlen($v[0]); }
            elseif (preg_match('/\Gs:(\d+):"/', $raw, $v, 0, $p)) { $p += strlen($v[0]) + (int)$v[1] + 2; }
            elseif (preg_match('/\GN;/', $raw, $v, 0, $p)) { $p += 2; }
            else break; // dizi vb.: uid ve seen her zaman bunlardan önce gelir
        }

        $uid = (int)($data['uid'] ?? 0);
        if (!$uid) return null;
        $cfgFile = __DIR__ . '/config.php';
        if (!is_file($cfgFile)) return null;
        $c = require $cfgFile;
        // Panelin oturum süresi dolduysa geçersiz say
        $max = (int)($c['session_minutes'] ?? 600) * 60;
        if ((time() - (int)($data['seen'] ?? 0)) > $max) return null;
        try {
            if (($c['driver'] ?? 'sqlite') === 'mysql') {
                $pdo = new PDO('mysql:host=' . $c['mysql_host'] . ';dbname=' . $c['mysql_db'] . ';charset=utf8mb4', $c['mysql_user'], $c['mysql_pass']);
            } else {
                if (!is_file($c['sqlite_path'])) return null;
                $pdo = new PDO('sqlite:' . $c['sqlite_path']);
            }
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $st = $pdo->prepare('SELECT id, username, name, role, active FROM users WHERE id = ?');
            $st->execute([$uid]);
            $u = $st->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('panel sso: ' . $e->getMessage());
            return null;
        }
        if (!$u || !(int)$u['active'] || !in_array($u['role'], $roles, true)) return null;
        unset($u['active']);
        return $cache[$key] = $u;
    }

    /** Panelin giriş sayfası; girişten sonra $back adresine döner (yalnız aynı sitedeki /admin/ yolları). */
    function lx_panel_login_url(string $back = '/admin/'): string {
        return '/panel/?next=' . rawurlencode($back);
    }
}
