<?php
// Yazdırılabilir belgeler: otel talep fişi (tip=talep) ve voucher (tip=voucher).
// Tarayıcıda açılır; "Yazdır / PDF" ile PDF olarak kaydedilir.
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

$tip = in_array($_GET['tip'] ?? '', ['voucher', 'teklif'], true) ? $_GET['tip'] : 'talep';
$id = (int)($_GET['id'] ?? 0);
// Acente portalı kullanıcısı yalnız kendi grubunun voucher ve teklifini görür; otel fişi (alış fiyatlı) kapalı
if (($user['role'] ?? '') === 'acente') {
    $own = q_val('SELECT agency_id FROM bookings WHERE id = ? AND deleted = 0', [$id], db_main());
    if ($tip === 'talep' || !$own || (int)$own !== (int)($user['agency_id'] ?? -1) || !empty($_SESSION['egitim'])) { http_response_code(403); echo '<p style="font-family:sans-serif">Bu belge için yetkiniz yok.</p>'; exit; }
}
try {
    extract(belge_data($id, (string)($_GET['durum'] ?? '')));
} catch (Throwable $e) {
    http_response_code(404);
    echo '<p style="font-family:sans-serif">Grup bulunamadı.</p>';
    exit;
}
$title = ($tip === 'voucher' ? 'Voucher ' : ($tip === 'teklif' ? 'Offer ' : 'Otel rezervasyon fişi ')) . $code;
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?></title>
<style>
  @page { size: A4; margin: 12mm 10mm; }
  * { box-sizing: border-box; }
  body { font-family: Tahoma, Verdana, "Segoe UI", Arial, sans-serif; color: #111; font-size: 12.5px; margin: 0; background: #e9ecef; }
  .sheet { background: #fff; width: 210mm; min-height: 297mm; margin: 12px auto 24px; padding: 12mm 10mm; position: relative; box-shadow: 0 2px 12px rgba(0,0,0,.1); }
  .bar { width: 210mm; margin: 12px auto 0; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
  .bar button, .bar a { font: inherit; font-size: 14px; padding: 7px 14px; border-radius: 6px; border: 1px solid #3a5f78; background: #fff; color: #3a5f78; cursor: pointer; text-decoration: none; }
  .bar button.main { background: #3a5f78; color: #fff; }
  .bar .on { background: #dfe8ef; }
  .bar .hint { margin-right: auto; color: #555; font-size: 13px; }
  .warn { width: 210mm; margin: 8px auto 0; background: #fff4e5; border: 1px solid #f0c68a; color: #7a4a00; padding: 7px 12px; border-radius: 6px; font-size: 13px; }
  .logo { width: 150px; height: auto; display: block; }
  table { width: 100%; border-collapse: collapse; }
  .g th { background: #c8c8c8; font-weight: 700; padding: 3px 6px; text-align: center; }
  .g td { padding: 3px 6px; text-align: center; }
  .g .l { text-align: left; } .g .r { text-align: right; }
  .gap { height: 10px; }
  .stamp { position: absolute; top: 42%; left: 50%; transform: translate(-50%, -50%) rotate(-22deg); font-size: 46px; font-weight: 800; color: rgba(192, 57, 43, .18); border: 6px solid rgba(192, 57, 43, .18); padding: 6px 22px; white-space: nowrap; pointer-events: none; }
  .addr { border: 1.5px dashed #444; padding: 6px 10px; text-align: center; margin-top: 26px; line-height: 1.5; }
  .addr u { display: block; }
  .lists { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 2px; font-size: 12px; }
  .lists .h { border-bottom: 1px dashed #444; display: inline-block; margin-bottom: 4px; }
  /* Otel rezervasyon fişi */
  .rf-head { display: grid; grid-template-columns: 190px 1fr 140px; align-items: start; }
  .rf-head .mid { text-align: center; font-family: Arial, Helvetica, sans-serif; font-weight: 700; font-size: 15px; line-height: 1.5; }
  .rf-head .mid .city { margin-top: 10px; font-size: 13.5px; }
  .rf-head .meta { text-align: right; font-size: 11px; line-height: 1.4; }
  .vno { font-family: Arial, Helvetica, sans-serif; font-weight: 700; font-size: 15px; margin-top: 4px; }
  .state { display: grid; grid-template-columns: 1fr auto; align-items: end; font-family: Arial, Helvetica, sans-serif; margin: 2px 0 8px; }
  .state b.big { font-size: 26px; text-align: center; grid-column: 1; }
  .rf { font-family: Arial, Helvetica, sans-serif; font-size: 12.5px; display: grid; grid-template-columns: 1.1fr 1.6fr 1fr; gap: 0 12px; }
  .kv { display: grid; grid-template-columns: auto 1fr; gap: 1px 4px; align-items: baseline; }
  .kv span { text-align: right; } .kv b { font-weight: 700; }
  .kv.right span { text-align: right; } .kv.right { justify-items: end; grid-template-columns: 1fr auto; }
  .rf .desc { color: #222; font-size: 11.5px; margin-left: 18px; }
  .note { font-family: Arial, Helvetica, sans-serif; margin: 14px 0 10px 50px; } .note b { font-size: 13px; }
  .gl { font-family: Arial, Helvetica, sans-serif; font-size: 11.5px; margin-top: 8px; }
  .gl th { text-align: left; font-weight: 400; border-bottom: 1px solid #111; padding: 2px 4px; }
  .gl td { padding: 1px 4px; }
  .tot { font-family: Arial, Helvetica, sans-serif; border-top: 1px solid #111; border-bottom: 1px solid #111; padding: 4px 0 6px 40px; font-size: 11.5px; line-height: 1.5; }
  .muted { color: #666; }
  @media print {
    body { background: #fff; }
    .bar, .warn { display: none; }
    .sheet { margin: 0; padding: 0; box-shadow: none; width: auto; min-height: 0; }
  }
  @media (max-width: 820px) {
    .sheet, .bar, .warn { width: auto; margin-left: 8px; margin-right: 8px; }
    .sheet { padding: 12px; min-height: 0; overflow-x: auto; }
  }
</style>
</head>
<body>
<div class="bar">
  <span class="hint">PDF için: Yazdır → Hedef: "PDF olarak kaydet"</span>
  <?php if ($tip === 'talep' && !$cancelled): ?>
    <a class="<?= $state === 'YENİ' ? 'on' : '' ?>" href="?tip=talep&amp;id=<?= $id ?>">Yeni</a>
    <a class="<?= $state === 'DEĞİŞİKLİK' ? 'on' : '' ?>" href="?tip=talep&amp;id=<?= $id ?>&amp;durum=degisiklik">Değişiklik</a>
  <?php endif; ?>
  <button onclick="window.close()">Kapat</button>
  <button class="main" onclick="window.print()">Yazdır / PDF</button>
</div>
<?php if (!$guests && $tip !== 'teklif'): ?><div class="warn">Misafir listesi boş. Grubun "Misafirler ve belgeler" bölümünden isimleri ekleyebilirsiniz.</div><?php endif; ?>
<?php if ($tip === 'voucher' && !$confirmed && !$cancelled): ?><div class="warn">Grup henüz konfirme değil; voucher "TASLAK" damgasıyla çıkar.</div><?php endif; ?>

<div class="sheet">
<?php if ($cancelled): ?><div class="stamp">İPTAL / CANCELLED</div><?php elseif ($tip === 'voucher' && !$confirmed): ?><div class="stamp">TASLAK / DRAFT</div><?php endif; ?>

<?php if ($tip === 'teklif'):
    $pp = $b['sell_mode'] === 'pp';
    $rawSell = []; foreach ($b['rooms'] as $r) $rawSell[$r['room_type']] = $r['sell_price'];
    $freeOut = array_values(array_filter($b['free'], fn($f) => $f['direction'] === 'out'));
    $srvSell = array_values(array_filter($b['services'], fn($s) => (float)($s['sell'] ?? 0) > 0));
    $totals = [$calc['sell_cur'] => $calc['sell_net']];
    foreach ($srvSell as $s) $totals[$s['currency']] = ($totals[$s['currency']] ?? 0) + (float)$s['sell'];
    $agFull = $b['agency_id'] ? q_one('SELECT name, country, contact FROM agencies WHERE id = ?', [$b['agency_id']]) : null;
?>
  <table><tr>
    <td style="width:190px"><?php if ($co['logo']): ?><img class="logo" src="<?= h($co['logo']) ?>" alt="LUX"><?php else: ?><b style="font-size:22px;color:#3a5f78">LUX LOCATIONS</b><?php endif; ?></td>
    <td style="text-align:center;font-size:20px;padding-top:34px;font-family:Arial,Helvetica,sans-serif"><b>PRICE OFFER</b><div style="font-size:13px;color:#555">Fiyat teklifi</div></td>
    <td style="width:190px;text-align:right;font-size:12px;line-height:1.5;padding-top:20px">Offer No: <b><?= h($code) ?></b><br>Date: <?= date('d/m/Y', $now) ?><br>Prepared by: <?= h($user['name']) ?></td>
  </tr></table>
  <div class="gap"></div>
  <table class="g">
    <tr><th class="l" style="width:50%">To / Acente</th><th class="l">Hotel / Otel</th></tr>
    <tr><td class="l"><b><?= h($agency['name'] ?? '') ?></b><?= !empty($agency['country']) ? ' · ' . h($agency['country']) : '' ?></td>
      <td class="l"><b><?= h(up($hotel['name'])) ?></b> <?= $hotel['stars'] ? str_repeat('★', (int)$hotel['stars']) : '' ?><br><span style="font-size:11px"><?= h($hotel['region'] ?: 'İstanbul') ?><?= $hotel['address'] ? ' · ' . h($hotel['address']) : '' ?></span></td></tr>
  </table>
  <div class="gap"></div>
  <table class="g">
    <tr><th>Check-in</th><th>Check-out</th><th>Nights</th><th>Board</th><th>Rooms</th><th>Currency</th></tr>
    <tr><td><?= d($b['check_in']) ?></td><td><?= d($b['check_out']) ?></td><td><?= $nights ?></td><td><?= h($boardName) ?></td><td><?= $totalRooms ?></td><td><?= h($calc['sell_cur']) ?></td></tr>
  </table>
  <div class="gap"></div>
  <table class="g">
    <tr><th class="l">Room type</th><th>Rooms</th><th><?= $pp ? 'Price per person / night' : 'Price per room / night' ?></th><th>Nights</th><th class="r">Total</th></tr>
    <?php foreach ($lines as $l): ?>
      <tr><td class="l"><?= h($ROOM[$l['room_type']] ?? $l['room_type']) ?> (<?= h(up($cat)) ?>)</td><td><?= (int)$l['qty'] ?></td>
        <td><?= m($pp ? $rawSell[$l['room_type']] ?? null : $l['sell_night']) ?> <?= h($calc['sell_cur']) ?></td><td><?= $nights ?></td><td class="r"><?= m($l['sell_total']) ?> <?= h($calc['sell_cur']) ?></td></tr>
    <?php endforeach; ?>
    <?php if ($freeOut): ?><tr><td class="l" colspan="4">Free rooms / Ücretsiz oda: <?= h(implode(', ', array_map(fn($f) => (int)$f['qty'] . ' ' . $f['room_type'], $freeOut))) ?></td><td class="r">− <?= m($calc['free_out']) ?> <?= h($calc['sell_cur']) ?></td></tr><?php endif; ?>
    <?php foreach ($srvSell as $s): ?><tr><td class="l" colspan="4"><?= h($s['kind']) ?><?= $s['description'] ? ' · ' . h($s['description']) : '' ?></td><td class="r"><?= m($s['sell']) ?> <?= h($s['currency']) ?></td></tr><?php endforeach; ?>
    <?php foreach ($totals as $cur => $v): ?><tr><th class="l" colspan="4" style="text-align:right">TOTAL / TOPLAM</th><th class="r"><?= m(round($v, 2)) ?> <?= h($cur) ?></th></tr><?php endforeach; ?>
  </table>
  <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;margin:12px 0 0;line-height:1.6">
    <?php if ($pp): ?>Per person prices: SGL and DBL room = price x 2, TRPL = x 3, QUAD = x 4.<br><?php endif; ?>
    <?php if ($b['option_until'] && !$confirmed): ?>Option valid until / Opsiyon: <b><?= d($b['option_until']) ?></b><br><?php endif; ?>
    Prices are subject to availability at the time of confirmation. City tax, if any, is paid at the hotel.<br>
    <?php if ($extra['voucher_note']): ?><span style="white-space:pre-wrap">Note: <?= h($extra['voucher_note']) ?></span><br><?php endif; ?>
  </div>
  <div class="addr" style="margin-top:40px">
    <u><?= h($co['name']) ?></u>
    <?= h($co['address']) ?><br>
    Tel: <?= h($co['phone']) ?><br>
    <?= h($co['email']) ?> · <?= h($co['web']) ?>
  </div>

<?php elseif ($tip === 'voucher'): ?>
  <table><tr>
    <td style="width:190px"><?php if ($co['logo']): ?><img class="logo" src="<?= h($co['logo']) ?>" alt="LUX"><?php else: ?><b style="font-size:22px;color:#3a5f78">LUX LOCATIONS</b><?php endif; ?></td>
    <td style="text-align:center;font-size:20px;padding-top:40px">VOUCHER FORMU</td>
    <td style="width:190px"></td>
  </tr></table>
  <div class="gap"></div>
  <table class="g">
    <tr><th class="l">Voucher No</th><th>Acenta Adı</th><th class="r">Basım Tarihi</th><th class="r" style="width:80px">Saat</th></tr>
    <tr><td class="l"><?= h($code) ?></td><td><?= h($co['name']) ?></td><td class="r"><?= date('d/m/Y', $now) ?></td><td class="r"><?= date('H:i:s', $now) ?></td></tr>
  </table>
  <div class="gap"></div>
  <table class="g">
    <tr><th>Müşteri Adı</th><th style="width:70px">Yetişkin</th><th style="width:80px">Extra Bed</th><th style="width:60px">Çocuk</th><th style="width:60px">Bebek</th><th style="width:60px">Toplam</th></tr>
    <tr><td class="l"><?= h($customer) ?></td><td><?= $cnt['ADL'] ?: '' ?></td><td></td><td><?= $cnt['CHD'] ?: '' ?></td><td><?= $cnt['INF'] ?: '' ?></td><td><?= $pax ?: '' ?></td></tr>
  </table>
  <div style="font-weight:700;margin:6px 0 2px">Servis Tanımı</div>
  <table class="g">
    <tr><th style="width:50%">Otel</th><th>Adres</th></tr>
    <tr><td class="l"><?= h(up($hotel['name'])) ?></td><td style="font-size:11px"><?= h($hotel['address']) ?></td></tr>
  </table>
  <div class="gap"></div>
  <table class="g">
    <tr><th>Oda</th><th>Oda Tipi</th><th>Pansiyon</th></tr>
    <?php foreach ($lines as $l): ?>
      <tr><td><?= h($ROOM[$l['room_type']] ?? $l['room_type']) ?><?= count($lines) > 1 ? ' x ' . (int)$l['qty'] : '' ?></td><td><?= h(up($cat)) ?></td><td><?= h($boardName) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <div class="gap"></div>
  <table class="g">
    <tr><th style="width:22%"></th><th>Giriş Tarihi</th><th>Çıkış Tarihi</th><th>Oda Sayısı</th><th>Geceleme</th><th style="width:22%"></th></tr>
    <tr><td></td><td><?= d($b['check_in']) ?></td><td><?= d($b['check_out']) ?></td><td><?= $totalRooms ?></td><td><?= $nights ?></td><td></td></tr>
  </table>
  <div style="margin:8px 0 0 40px">Uçuş Detayları : <?= h(trim(($extra['arrival_info'] ?? '') . (($extra['departure_info'] ?? '') ? ' / ' . $extra['departure_info'] : ''))) ?></div>
  <?php if ($b['hotel_conf_code']): ?><div style="margin:4px 0 0 40px">Otel Konfirme No : <b><?= h($b['hotel_conf_code']) ?></b></div><?php endif; ?>
  <?php if ($extra['voucher_note']): ?><div style="margin:4px 0 0 40px;white-space:pre-wrap">Not : <?= h($extra['voucher_note']) ?></div><?php endif; ?>

  <div class="addr" style="margin-top:<?= $extra['voucher_note'] ? 24 : 60 ?>px">
    <u>Acenta Adres</u>
    <?= h($co['address']) ?><br>
    Telefon : <?= h($co['phone']) ?><br><br>
    <?= h($co['email']) ?><br>
    <?= h($co['web']) ?>
  </div>
  <div style="font-size:12px;margin-top:2px">Voucher: <?= h($code) ?></div>
  <div class="lists">
    <div><span class="h">Müşteri İsim Listesi</span>
      <?php foreach ($guests as $g): ?><div><?= h($g['name']) ?><?= $g['pax'] !== 'ADL' ? ' (' . h($g['pax']) . ')' : '' ?></div><?php endforeach; ?>
    </div>
    <div><span class="h">Uçak Detay Listesi</span>
      <div><?= h($extra['arrival_info'] ?: '. ..') ?></div><div><?= h($extra['departure_info'] ?: '. ..') ?></div>
    </div>
  </div>

<?php else: ?>
  <div class="rf-head">
    <div><?php if ($co['logo']): ?><img class="logo" src="<?= h($co['logo']) ?>" alt="LUX"><?php else: ?><b style="font-size:22px;color:#3a5f78">LUX LOCATIONS</b><?php endif; ?>
      <div class="vno">Voucher No: <?= h($code) ?></div></div>
    <div class="mid">OTEL REZERVASYON FİŞİ<br><?= h(up($hotel['name'])) ?><div class="city"><?= h(up($hotel['region'] ?: 'İSTANBUL')) ?></div></div>
    <div class="meta">Tarih : <?= date('d/m/Y', $now) ?><br>Saat : <?= date('H:i:s', $now) ?><br>Hazırlayan : <?= h($user['name']) ?></div>
  </div>
  <div class="state"><b class="big"><?= h($state) ?></b><span>Satış Tarihi <b><?= d(substr((string)$b['created_at'], 0, 10) ?: date('Y-m-d')) ?></b></span></div>

  <div class="rf">
    <div class="kv">
      <span>Giriş Tarihi :</span><b><?= d($b['check_in']) ?></b>
      <span>Çıkış Tarihi :</span><b><?= d($b['check_out']) ?></b>
      <span>Gün :</span><b><?= $nights ?></b>
      <span>Durum :</span><b><?= h(STATUSES[$b['status']] ?? $b['status']) ?><?= $b['option_until'] && !$confirmed ? ' (opsiyon ' . d($b['option_until']) . ')' : '' ?></b>
      <?php if ($b['hotel_conf_code']): ?><span>Otel Konf. No :</span><b><?= h($b['hotel_conf_code']) ?></b><?php endif; ?>
    </div>
    <div class="kv" style="grid-template-columns:auto auto 1fr">
      <span>Oda Sayısı :</span><b><?= $totalRooms ?></b><i></i>
      <?php foreach ($lines as $l): ?><span>Oda :</span><b><?= h($l['room_type']) ?><?= count($lines) > 1 ? ' x' . (int)$l['qty'] : '' ?></b><span class="desc" style="text-align:left">(<?= h($ROOM[$l['room_type']] ?? $l['room_type']) ?>)</span><?php endforeach; ?>
      <span>Oda Tipi :</span><b><?= h($catShort) ?></b><span class="desc" style="text-align:left">(<?= h(up($cat)) ?>)</span>
      <span>Pansiyon :</span><b><?= h($b['board']) ?></b><span class="desc" style="text-align:left">(<?= h($boardName) ?>)</span>
    </div>
    <div class="kv right">
      <span>Yetişkin :</span><b><?= $cnt['ADL'] ?: '' ?></b>
      <span>Ext. Bed :</span><b></b>
      <span>Çocuk :</span><b><?= $cnt['CHD'] ?: '' ?></b>
      <span>Bebek :</span><b><?= $cnt['INF'] ?: '' ?></b>
      <span>Toplam Pax :</span><b><?= $pax ?: '' ?></b>
      <span>Döviz :</span><b><?= h($b['buy_cur']) ?></b>
    </div>
  </div>
  <div style="font-family:Arial,Helvetica,sans-serif;margin:14px 0 0 60px">Milliyet : <b><?= h($extra['nationality']) ?></b></div>
  <?php if ($priceNote || $extra['hotel_note']): ?>
    <div class="note">NOT: <b><?= h(up(trim($priceNote . ' ' . ($extra['hotel_note'] ?? '')))) ?></b></div>
  <?php endif; ?>

  <table class="gl">
    <tr><th style="width:34%">SOYADI, ADI</th><th>ODA</th><th>YAŞ/D.TAR.</th><th>GELİŞ YERİ</th><th>SAAT</th><th>DÖNÜŞ YERİ</th><th>SAAT</th></tr>
    <?php foreach ($guests as $i => $g): ?>
      <tr><td><?= h($g['name']) ?></td><td><?= (int)$g['room_no'] ?> <?= h($g['room_type']) ?></td><td><?= h($g['pax'] !== 'ADL' ? $g['pax'] . ($g['note'] ? ' ' . $g['note'] : '') : ($g['note'] ?? '')) ?></td>
        <td><?= $i === 0 ? h($extra['arrival_info']) : '' ?></td><td></td><td><?= $i === 0 ? h($extra['departure_info']) : '' ?></td><td></td></tr>
    <?php endforeach; ?>
  </table>
  <div class="tot">
    <b>TOPLAM PAX :</b> <?= $pax ?> <?= $pax ? '(Yetişkin: ' . $cnt['ADL'] . ($cnt['CHD'] ? ', Çocuk: ' . $cnt['CHD'] : '') . ($cnt['INF'] ? ', Bebek: ' . $cnt['INF'] : '') . ' )' : '' ?><br>
    <b>TOPLAM ODA :</b> <?= $totalRooms ?> (<?= h($roomCounts) ?>)
  </div>
  <p style="font-family:Arial,Helvetica,sans-serif;font-size:11.5px;margin-top:14px" class="muted">Lütfen konfirme numaranızı reservation@luxlocationstours.com adresine iletiniz.</p>
<?php endif; ?>
</div>
</body>
</html>
