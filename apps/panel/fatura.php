<?php
// Fatura taslağı (proforma): konfirme gruptan acenteye kesilecek faturanın bilgileri.
// Resmi fatura muhasebede EDM'den kesilir; bu sayfa onun için hazır bilgi ve acenteye gönderilecek proforma.
define('LX_PANEL', 1);
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/calc.php';
require __DIR__ . '/lib/data.php';
require __DIR__ . '/lib/belge.php';
date_default_timezone_set('Europe/Istanbul');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; script-src 'unsafe-inline'");

lx_session_start();
$user = current_user();
if (!$user) { header('Location: index.php'); exit; }
if (!can('cari') && !can('booking')) { http_response_code(403); echo 'Bu belge için yetkiniz yok.'; exit; }

$id = (int)($_GET['id'] ?? 0);
try {
    extract(belge_data($id, ''));
} catch (Throwable $e) {
    http_response_code(404);
    echo '<p style="font-family:sans-serif">Grup bulunamadı.</p>';
    exit;
}
$ag = $b['agency_id'] ? q_one('SELECT name, country, contact, invoice_info FROM agencies WHERE id = ?', [$b['agency_id']]) : null;
$cur = $calc['sell_cur'];
$ROOM_EN = ['SNGL' => 'Single', 'DBL' => 'Double', 'TWIN' => 'Twin', 'TRPL' => 'Triple', 'QUAD' => 'Quad'];
$BOARD_EN = ['BB' => 'Bed & Breakfast', 'HB' => 'Half Board', 'FB' => 'Full Board', 'RO' => 'Room Only', 'AI' => 'All Inclusive'];
$period = d($b['check_in']) . ' – ' . d($b['check_out']);
$items = [];
foreach ($calc['lines'] as $l) {
    if (!(int)$l['qty']) continue;
    $items[] = ['desc' => "Accommodation / Konaklama · {$hotel['name']} · " . ($ROOM_EN[$l['room_type']] ?? $l['room_type']) . ' room · ' . ($BOARD_EN[$b['board']] ?? $b['board']) . " · $period",
        'qty' => (int)$l['qty'] . ' × ' . $nights . ' nt', 'unit' => $l['sell_night'], 'total' => $l['sell_total'], 'cur' => $cur];
}
if ($calc['free_out'] > 0) $items[] = ['desc' => 'Free rooms / Ücretsiz oda (' . $calc['free_out_rooms'] . ')', 'qty' => '', 'unit' => null, 'total' => -$calc['free_out'], 'cur' => $cur];
foreach (($b['services'] ?? []) as $s) {
    $sell = num($s['sell'] ?? null);
    if (!$sell) continue;
    $items[] = ['desc' => trim(($s['kind'] ?? 'Hizmet') . ($s['description'] ? ' · ' . $s['description'] : '')), 'qty' => '', 'unit' => null, 'total' => $sell, 'cur' => $s['currency']];
}
$totals = [];
foreach ($items as $it) $totals[$it['cur']] = ($totals[$it['cur']] ?? 0) + $it['total'];
$rate = ['EUR' => $calc['eur_try'], 'USD' => $calc['usd_try'], 'TRY' => 1];
$tryTotal = 0; $tryOk = true;
foreach ($totals as $c => $v) { if (empty($rate[$c])) $tryOk = false; else $tryTotal += $v * $rate[$c]; }
$money = fn($v) => number_format((float)$v, 2, ',', '.');
$noAgency = !$ag; $noInfo = $ag && !trim((string)$ag['invoice_info']);
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fatura taslağı <?= h($code) ?></title>
<style>
  @page { size: A4; margin: 14mm 12mm; }
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; color: #1d2730; font-size: 12.5px; margin: 0; background: #e9ecef; }
  .sheet { background: #fff; width: 210mm; min-height: 297mm; margin: 12px auto 24px; padding: 14mm 12mm; position: relative; box-shadow: 0 2px 12px rgba(0,0,0,.1); }
  .bar { width: 210mm; margin: 12px auto 0; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
  .bar button { font: inherit; font-size: 14px; padding: 7px 14px; border-radius: 6px; border: 1px solid #3a5f78; background: #fff; color: #3a5f78; cursor: pointer; }
  .bar button.main { background: #3a5f78; color: #fff; }
  .bar .hint { margin-right: auto; color: #555; font-size: 13px; }
  .warn { width: 210mm; margin: 8px auto 0; background: #fff4e5; border: 1px solid #f0c68a; color: #7a4a00; padding: 7px 12px; border-radius: 6px; font-size: 13px; }
  .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; }
  .logo { width: 150px; }
  h1 { font-size: 22px; margin: 0 0 4px; color: #3a5f78; letter-spacing: .5px; }
  .muted { color: #66727d; }
  .co { font-size: 11px; line-height: 1.5; text-align: right; }
  .boxes { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 18px 0; }
  .box { border: 1px solid #dfe4e9; border-radius: 6px; padding: 10px 12px; line-height: 1.5; white-space: pre-wrap; }
  .box b.t { display: block; font-size: 11px; color: #66727d; letter-spacing: .5px; margin-bottom: 4px; white-space: normal; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #3a5f78; color: #fff; font-weight: 600; text-align: left; padding: 6px 8px; font-size: 12px; }
  td { padding: 6px 8px; border-bottom: 1px solid #dfe4e9; vertical-align: top; }
  .r { text-align: right; white-space: nowrap; }
  .tot td { border: 0; font-size: 13.5px; }
  .tot .big { font-size: 16px; font-weight: 700; }
  .stamp { position: absolute; top: 40%; left: 50%; transform: translate(-50%, -50%) rotate(-22deg); font-size: 44px; font-weight: 800; color: rgba(58, 95, 120, .10); border: 6px solid rgba(58, 95, 120, .10); padding: 6px 22px; white-space: nowrap; pointer-events: none; }
  .foot { margin-top: 26px; font-size: 11px; color: #66727d; line-height: 1.5; border-top: 1px solid #dfe4e9; padding-top: 8px; }
  @media print { body { background: #fff; } .bar, .warn { display: none; } .sheet { margin: 0; padding: 0; box-shadow: none; width: auto; min-height: 0; } }
  @media (max-width: 820px) { .sheet, .bar, .warn { width: auto; margin-left: 8px; margin-right: 8px; } .sheet { padding: 12px; min-height: 0; overflow-x: auto; } .boxes { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<div class="bar">
  <span class="hint">PDF için: Yazdır → Hedef: "PDF olarak kaydet"</span>
  <button onclick="window.close()">Kapat</button>
  <button class="main" onclick="window.print()">Yazdır / PDF</button>
</div>
<?php if ($noAgency): ?><div class="warn">Grupta acente seçili değil.</div><?php endif; ?>
<?php if ($noInfo): ?><div class="warn">Acentenin fatura bilgileri (unvan, adres, vergi no) girilmemiş. Acenteler → <?= h($ag['name']) ?> → Düzenle → "Fatura bilgileri".</div><?php endif; ?>
<?php if (!$confirmed && !$cancelled): ?><div class="warn">Grup henüz konfirme değil; bu taslak bilgi amaçlıdır.</div><?php endif; ?>
<?php if ($calc['has_error']): ?><div class="warn">Grubun fiyatlarında eksik var; tutarlar eksik olabilir.</div><?php endif; ?>

<div class="sheet">
  <div class="stamp"><?= $cancelled ? 'İPTAL / CANCELLED' : 'PROFORMA' ?></div>
  <div class="head">
    <div>
      <?php if ($co['logo']): ?><img class="logo" src="<?= h($co['logo']) ?>" alt="LUX"><?php endif; ?>
    </div>
    <div class="co"><b><?= h($co['name']) ?></b><br><?= h($co['address']) ?><br><?= h($co['phone']) ?><br><?= h($co['email']) ?></div>
  </div>
  <div style="margin-top:16px"><h1>PROFORMA INVOICE · FATURA TASLAĞI</h1>
    <span class="muted">No: <b><?= h($code) ?></b> · Date / Tarih: <?= date('d.m.Y', $now) ?> · Currency / Para birimi: <?= h(implode(', ', array_keys($totals)) ?: $cur) ?></span></div>
  <div class="boxes">
    <div class="box"><b class="t">BILL TO / ALICI</b><?= h($ag['invoice_info'] ?? '' ?: ($ag['name'] ?? '—')) ?><?= !empty($ag['country']) && empty($ag['invoice_info']) ? "\n" . h($ag['country']) : '' ?></div>
    <div class="box"><b class="t">RESERVATION / REZERVASYON</b><?= h($hotel['name']) . "\n" . h($period) . ' · ' . $nights . ' nights · ' . $totalRooms . ' rooms' . ($pax ? "\n" . h($customer) . ' · ' . $pax . ' pax' : '') . ($b['hotel_conf_code'] ? "\nHotel conf.: " . h($b['hotel_conf_code']) : '') ?></div>
  </div>
  <table>
    <tr><th>Description / Açıklama</th><th class="r">Qty / Miktar</th><th class="r">Unit / Birim</th><th class="r">Amount / Tutar</th></tr>
    <?php foreach ($items as $it): ?>
      <tr><td><?= h($it['desc']) ?></td><td class="r"><?= h($it['qty']) ?></td><td class="r"><?= $it['unit'] !== null ? $money($it['unit']) . ' ' . h($it['cur']) : '' ?></td><td class="r"><?= $money($it['total']) ?> <?= h($it['cur']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$items): ?><tr><td colspan="4" class="muted">Satış fiyatı girilmemiş.</td></tr><?php endif; ?>
  </table>
  <table class="tot" style="margin-top:8px">
    <?php foreach ($totals as $c => $v): ?>
      <tr><td class="r" style="width:75%">Total / Toplam (<?= h($c) ?>)</td><td class="r big"><?= $money($v) ?> <?= h($c) ?></td></tr>
    <?php endforeach; ?>
    <?php if ($totals && $tryOk && array_keys($totals) !== ['TRY']): ?>
      <tr><td class="r muted">TL karşılığı (kur: <?= $calc['eur_try'] ? '1 € = ' . $money($calc['eur_try']) : '' ?><?= $calc['usd_try'] ? ', 1 $ = ' . $money($calc['usd_try']) : '' ?>)</td><td class="r"><?= $money($tryTotal) ?> TL</td></tr>
    <?php endif; ?>
  </table>
  <div class="foot">
    Bu belge fatura taslağıdır (proforma); resmi fatura e-fatura / e-arşiv olarak ayrıca düzenlenir. KDV ve istisna durumu muhasebe tarafından belirlenir.<br>
    This is a proforma invoice, not a tax invoice. The official invoice will be issued electronically.
  </div>
</div>
</body>
</html>
