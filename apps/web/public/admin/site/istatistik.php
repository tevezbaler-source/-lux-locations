<?php
// Lux Locations · Ziyaret istatistiği (ekip paneli)
// Veriler çerezsiz sayaçtan gelir (lx-content/hit.php → lx-content/data/stats/AAAA-AA.json).
// Giriş, site düzenleme paneliyle aynı oturumu kullanır (/admin/site/).
require dirname(dirname(__DIR__)) . '/lx-content/lib.php';

session_name('LXSITE');
session_set_cookie_params(0, '/admin/site/', '', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
session_start();
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: no-store');

$authFile = LX_DATA . '/auth.json';
$auth = is_file($authFile) ? json_decode((string) file_get_contents($authFile), true) : null;
if (!$auth || empty($_SESSION['ok']) || !hash_equals((string) $auth['hash'], (string) $_SESSION['ok'])) {
    header('Location: ./');
    exit;
}
function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

$dir = LX_DATA . '/stats';
$months = array();
foreach ((array) glob($dir . '/*.json') as $f) if (preg_match('/(\d{4}-\d{2})\.json$/', $f, $m)) $months[] = $m[1];
rsort($months);
$month = isset($_GET['ay']) && in_array($_GET['ay'], $months, true) ? $_GET['ay'] : ($months ? $months[0] : date('Y-m'));
$d = is_file("$dir/$month.json") ? json_decode((string) file_get_contents("$dir/$month.json"), true) : array();
if (!is_array($d)) $d = array();
foreach (array('days', 'pages', 'refs', 'langs', 'devices', 'countries', 'clicks', 'clickDays') as $k) {
    if (!isset($d[$k]) || !is_array($d[$k])) $d[$k] = array();
    if ($k !== 'days' && $k !== 'clickDays') arsort($d[$k]);
}
ksort($d['days']);
$total = array_sum($d['days']);
$clicks = array_sum($d['clicks']);
$max = $d['days'] ? max($d['days']) : 1;
$langNames = lx_langs();

$pageName = function ($p) {
    $names = array('/' => 'Ana sayfa', '/bogaz-tekne-turu/' => 'Boğaz tekne turu', '/oteller/' => 'Oteller', '/oda-rezervasyonu/' => 'Oda rezervasyonu',
        '/grup-rezervasyonu/' => 'Grup rezervasyonu', '/toplanti-kongre/' => 'Toplantı ve kongre', '/gizlilik/' => 'Gizlilik', '/kvkk/' => 'KVKK');
    $base = lx_base_path($p);
    $lang = lx_lang_of($p);
    $n = isset($names[$base]) ? $names[$base] : (preg_match('#^/oteller/([a-z0-9-]+)/$#', $base, $m) ? 'Otel: ' . $m[1] : $base);
    return $n . ' (' . strtoupper($lang) . ')';
};
$table = function ($title, $rows, $label = null, $limit = 15) use ($total) {
    echo '<div class="card"><h3>' . h($title) . '</h3>';
    if (!$rows) { echo '<p class="hint">Henüz veri yok.</p></div>'; return; }
    $sum = array_sum($rows);
    echo '<table>';
    $i = 0;
    foreach ($rows as $k => $v) {
        if ($i++ >= $limit) break;
        $pct = $sum ? round($v * 100 / $sum) : 0;
        echo '<tr><td>' . h($label ? $label($k) : $k) . '</td><td class="n">' . number_format($v, 0, ',', '.') . '</td><td class="bar"><span style="width:' . $pct . '%"></span></td></tr>';
    }
    echo '</table></div>';
};
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>İstatistik · Lux Locations</title>
<style>
:root{--c:#3a5f78;--bg:#f4f6f8;--line:#dde3e8;--mut:#66727c}
*{box-sizing:border-box}body{margin:0;font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:#1c2830}
a{color:var(--c)}
.top{background:var(--c);color:#fff;padding:12px 20px;display:flex;gap:16px;align-items:center;flex-wrap:wrap}
.top b{font-size:17px}.top a{color:#fff}
main{max-width:1100px;margin:0 auto;padding:20px 16px 60px}
.card{background:#fff;border:1px solid var(--line);border-radius:10px;padding:18px;margin-bottom:14px}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:14px}
.kpi{background:#fff;border:1px solid var(--line);border-radius:10px;padding:14px 18px}.kpi b{display:block;font-size:28px;color:var(--c)}.kpi span{color:var(--mut);font-size:13px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}@media (max-width:800px){.grid{grid-template-columns:1fr}}
.grid .card{margin:0}
h2{margin:0 0 12px;font-size:20px}h3{margin:0 0 10px;font-size:16px}
table{width:100%;border-collapse:collapse;font-size:14px}td{padding:5px 4px;border-top:1px solid var(--line)}tr:first-child td{border-top:0}
td.n{text-align:right;white-space:nowrap;width:70px;font-variant-numeric:tabular-nums}td.bar{width:30%}td.bar span{display:block;height:8px;background:var(--c);border-radius:4px;opacity:.75}
.days{display:flex;align-items:flex-end;gap:3px;height:160px;padding-top:8px}
.days div{flex:1;background:var(--c);border-radius:3px 3px 0 0;min-height:2px;position:relative;opacity:.85}
.days div:hover{opacity:1}
.dl{display:flex;justify-content:space-between;color:var(--mut);font-size:12px;margin-top:4px}
.hint{color:var(--mut);font-size:13px}
select{padding:6px 8px;border-radius:7px;border:1px solid #c5cdd4;font:inherit}
</style>
</head>
<body>
<div class="top"><b>Lux Locations · İstatistik</b><a href="./">← Panele dön</a></div>
<main>
  <form method="get" style="margin-bottom:14px">
    <label>Ay: <select name="ay" onchange="this.form.submit()">
      <?php foreach ($months ?: array($month) as $m): ?><option value="<?= h($m) ?>"<?= $m === $month ? ' selected' : '' ?>><?= h($m) ?></option><?php endforeach; ?>
    </select></label>
  </form>
  <div class="kpis">
    <div class="kpi"><b><?= number_format($total, 0, ',', '.') ?></b><span>sayfa görüntüleme</span></div>
    <div class="kpi"><b><?= $d['days'] ? number_format(round($total / count($d['days'])), 0, ',', '.') : 0 ?></b><span>günlük ortalama</span></div>
    <div class="kpi"><b><?= number_format($clicks, 0, ',', '.') ?></b><span>WhatsApp / telefon / e-posta tıklaması</span></div>
    <div class="kpi"><b><?= $total ? round($clicks * 1000 / $total) / 10 : 0 ?>%</b><span>iletişime geçme oranı</span></div>
  </div>
  <div class="card">
    <h3>Günlük ziyaret</h3>
    <?php if ($d['days']): ?>
      <div class="days"><?php foreach ($d['days'] as $day => $v): ?><div style="height:<?= max(1, round($v * 100 / $max)) ?>%" title="<?= h($day . ': ' . $v . ' görüntüleme' . (isset($d['clickDays'][$day]) ? ', ' . $d['clickDays'][$day] . ' tıklama' : '')) ?>"></div><?php endforeach; ?></div>
      <div class="dl"><span><?php $ks = array_keys($d['days']); ?><?= h($ks[0]) ?></span><span><?= h(end($ks)) ?></span></div>
    <?php else: ?><p class="hint">Bu ay için henüz veri yok. Sayaç site yüklendikten sonra saymaya başlar.</p><?php endif; ?>
  </div>
  <div class="grid">
    <?php $table('En çok açılan sayfalar', $d['pages'], $pageName); ?>
    <?php $table('Nereden geldiler', $d['refs']); ?>
    <?php $table('İletişime geçilen sayfa', $d['clicks'], function ($k) use ($pageName) { $p = explode(' · ', $k, 2); return count($p) === 2 ? $p[0] . ' · ' . $pageName($p[1]) : $k; }); ?>
    <?php $table('Dil', $d['langs'], function ($k) use ($langNames) { return isset($langNames[$k]) ? $langNames[$k] : $k; }); ?>
    <?php $table('Ülke', $d['countries'], function ($k) { return substr($k, -1) === '*' ? substr($k, 0, 2) . ' (tarayıcı diline göre)' : $k; }); ?>
    <?php $table('Cihaz', $d['devices']); ?>
  </div>
  <p class="hint">Sayaç çerez kullanmaz ve IP adresi tutmaz; arama motoru botları sayılmaz. Google aramalarında hangi kelimelerle bulunduğunuzu Google Search Console gösterir.</p>
</main>
</body>
</html>
