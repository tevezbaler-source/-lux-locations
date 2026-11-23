<?php
// Yazdırılabilir belgeler: otel talep fişi (tip=talep) ve voucher (tip=voucher).
// Tarayıcıda açılır; "Yazdır / PDF" ile PDF olarak kaydedilir.
define('LX_PANEL', 1);
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/calc.php';
require __DIR__ . '/lib/data.php';
date_default_timezone_set('Europe/Istanbul');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; script-src 'unsafe-inline'");

lx_session_start();
$user = current_user();
if (!$user) { header('Location: index.php'); exit; }

$tip = ($_GET['tip'] ?? '') === 'voucher' ? 'voucher' : 'talep';
$id = (int)($_GET['id'] ?? 0);
try {
    $b = booking_get($id);
    if ((int)$b['deleted']) throw new ApiError('Bu grup silinmiş.', 410);
    $hotel = hotel_get((int)$b['hotel_id']);
} catch (Throwable $e) {
    http_response_code(404);
    echo '<p style="font-family:sans-serif">Grup bulunamadı.</p>';
    exit;
}
$agency = $b['agency_id'] ? q_one('SELECT name, country FROM agencies WHERE id = ?', [$b['agency_id']]) : null;
$guests = guests_get($id);
$extra = booking_extra_get($id);
$calc = calc_booking($b);
$nights = nights_between($b['check_in'], $b['check_out']);

$co = (lx_config()['company'] ?? []) + [
    'name' => 'Lux Locations Tours',
    'address' => 'İstanbul, Türkiye',
    'phone' => '+90 212 523 23 10',
    'email' => 'reservation@luxlocationstours.com',
    'web' => 'www.luxlocationstours.com',
    'logo' => '',
];

function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function d($iso): string { return $iso ? date('d.m.Y', strtotime($iso)) : ''; }
function m($v): string { return $v === null || $v === '' ? '—' : number_format((float)$v, 2, ',', '.'); }

$BOARD = [
    'BB' => ['Oda + Kahvaltı', 'Bed & Breakfast'], 'HB' => ['Yarım Pansiyon', 'Half Board'],
    'FB' => ['Tam Pansiyon', 'Full Board'], 'RO' => ['Sadece Oda', 'Room Only'], 'AI' => ['Her Şey Dahil', 'All Inclusive'],
];
$board = $BOARD[$b['board'] ?? ''] ?? [$b['board'], $b['board']];
$PAX = ['ADL' => ['Yetişkin', 'Adult'], 'CHD' => ['Çocuk', 'Child'], 'INF' => ['Bebek', 'Infant']];

$freeIn = array_values(array_filter($b['free'], fn($f) => $f['direction'] === 'in'));
$roomSummary = implode(', ', array_map(fn($l) => $l['qty'] . ' ' . $l['room_type'], $calc['lines'] ?? []));
$totalRooms = array_sum(array_map(fn($l) => (int)$l['qty'], $calc['lines'] ?? []));
$paxCount = count($guests);
$adl = count(array_filter($guests, fn($g) => $g['pax'] === 'ADL'));

$confirmed = in_array($b['status'], LOCKED_STATUSES, true);
$cancelled = $b['status'] === 'iptal';
$stamp = $cancelled ? 'İPTAL / CANCELLED' : (($tip === 'voucher' && !$confirmed) ? 'TASLAK / DRAFT – NOT CONFIRMED' : '');
$title = $tip === 'voucher' ? 'Voucher ' . ($b['code'] ?: '#' . $id) : 'Otel talep fişi ' . ($b['code'] ?: '#' . $id);
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?></title>
<style>
  @page { size: A4; margin: 14mm 12mm; }
  * { box-sizing: border-box; }
  body { font-family: "Segoe UI", Arial, Helvetica, sans-serif; color: #1c2530; font-size: 12.5px; margin: 0; background: #eef1f4; }
  .sheet { background: #fff; width: 210mm; min-height: 297mm; margin: 16px auto; padding: 14mm 12mm; position: relative; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
  .bar { width: 210mm; margin: 16px auto 0; display: flex; gap: 8px; justify-content: flex-end; }
  .bar button { font: inherit; font-size: 14px; padding: 8px 16px; border-radius: 6px; border: 1px solid #3a5f78; background: #3a5f78; color: #fff; cursor: pointer; }
  .bar button.alt { background: #fff; color: #3a5f78; }
  .bar .hint { margin-right: auto; color: #5b6875; font-size: 13px; align-self: center; }
  header.top { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #3a5f78; padding-bottom: 10px; margin-bottom: 14px; }
  .brand { font-size: 22px; font-weight: 700; letter-spacing: .5px; color: #3a5f78; }
  .brand small { display: block; font-size: 11px; font-weight: 400; color: #5b6875; letter-spacing: 0; margin-top: 2px; }
  .brand img { max-height: 54px; display: block; }
  .doc { text-align: right; }
  .doc h1 { margin: 0; font-size: 18px; letter-spacing: .5px; }
  .doc .sub { color: #5b6875; font-size: 11.5px; }
  .doc .no { font-size: 15px; font-weight: 700; margin-top: 4px; }
  .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
  .box { border: 1px solid #cfd8e0; border-radius: 6px; padding: 8px 10px; }
  .box h3 { margin: 0 0 4px; font-size: 11px; letter-spacing: .2px; color: #5b6875; font-weight: 600; }
  .box .big { font-size: 15px; font-weight: 700; }
  .box div { line-height: 1.45; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
  th, td { border: 1px solid #cfd8e0; padding: 5px 7px; text-align: left; vertical-align: top; }
  th { background: #eef3f7; font-size: 11px; font-weight: 600; color: #3d4b58; }
  td.n, th.n { text-align: right; white-space: nowrap; }
  tr.tot td { font-weight: 700; background: #f6f8fa; }
  h2 { font-size: 13px; letter-spacing: .2px; margin: 14px 0 6px; color: #3a5f78; }
  .note { border-left: 3px solid #3a5f78; background: #f6f8fa; padding: 7px 10px; white-space: pre-wrap; margin-bottom: 12px; }
  .muted { color: #5b6875; }
  .sign { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 26px; }
  .sign div { border-top: 1px solid #8996a2; padding-top: 4px; font-size: 11px; color: #5b6875; }
  footer { position: absolute; left: 12mm; right: 12mm; bottom: 10mm; border-top: 1px solid #cfd8e0; padding-top: 6px; font-size: 10.5px; color: #5b6875; display: flex; justify-content: space-between; gap: 10px; }
  .stamp { position: absolute; top: 44%; left: 50%; transform: translate(-50%, -50%) rotate(-24deg); font-size: 44px; font-weight: 800; color: rgba(192, 57, 43, .16); border: 6px solid rgba(192, 57, 43, .16); padding: 6px 22px; white-space: nowrap; pointer-events: none; }
  .warn { width: 210mm; margin: 8px auto 0; background: #fff4e5; border: 1px solid #f0c68a; color: #7a4a00; padding: 8px 12px; border-radius: 6px; font-size: 13px; }
  @media print {
    body { background: #fff; }
    .bar, .warn { display: none; }
    .sheet { margin: 0; padding: 0; box-shadow: none; width: auto; min-height: 0; }
    footer { position: fixed; left: 0; right: 0; bottom: 0; }
  }
  @media (max-width: 820px) {
    .sheet, .bar, .warn { width: auto; margin-left: 8px; margin-right: 8px; }
    .sheet { padding: 16px; min-height: 0; }
    .grid2 { grid-template-columns: 1fr; }
    footer { position: static; margin-top: 20px; }
  }
</style>
</head>
<body>
<div class="bar">
  <span class="hint">PDF için: Yazdır → Hedef: "PDF olarak kaydet"</span>
  <button class="alt" onclick="window.close()">Kapat</button>
  <button onclick="window.print()">Yazdır / PDF</button>
</div>
<?php if (!$guests): ?><div class="warn">Misafir listesi boş. Grubun "Misafirler ve belgeler" bölümünden isimleri ekleyebilirsiniz.</div><?php endif; ?>
<?php if ($tip === 'voucher' && !$confirmed && !$cancelled): ?><div class="warn">Grup henüz konfirme değil; voucher "TASLAK" damgasıyla çıkar.</div><?php endif; ?>

<div class="sheet">
  <?php if ($stamp): ?><div class="stamp"><?= h($stamp) ?></div><?php endif; ?>
  <header class="top">
    <div class="brand">
      <?php if ($co['logo']): ?><img src="<?= h($co['logo']) ?>" alt="<?= h($co['name']) ?>"><?php else: ?>LUX LOCATIONS<small><?= h($co['name']) ?></small><?php endif; ?>
    </div>
    <div class="doc">
      <?php if ($tip === 'voucher'): ?>
        <h1>HOTEL VOUCHER</h1><div class="sub">Otel Voucher'ı</div>
        <div class="no">No: <?= h($b['code'] ?: '#' . $id) ?></div>
      <?php else: ?>
        <h1>REZERVASYON TALEBİ</h1><div class="sub">Hotel Reservation Request</div>
        <div class="no">Ref: <?= h($b['code'] ?: '#' . $id) ?></div>
      <?php endif; ?>
      <div class="sub"><?= h(date('d.m.Y H:i')) ?></div>
    </div>
  </header>

<?php if ($tip === 'talep'): ?>
  <div class="grid2">
    <div class="box">
      <h3>Kime</h3>
      <div class="big"><?= h($hotel['name']) ?></div>
      <?php if ($hotel['address']): ?><div><?= h($hotel['address']) ?></div><?php endif; ?>
      <?php if ($hotel['contacts']): ?><div class="muted" style="white-space:pre-wrap"><?= h($hotel['contacts']) ?></div><?php endif; ?>
    </div>
    <div class="box">
      <h3>Kimden</h3>
      <div class="big"><?= h($co['name']) ?></div>
      <div><?= h($co['phone']) ?> · <?= h($co['email']) ?></div>
      <div class="muted">Hazırlayan: <?= h($user['name']) ?></div>
    </div>
  </div>

  <table>
    <tr><th>Giriş</th><th>Çıkış</th><th class="n">Gece</th><th>Pansiyon</th><th class="n">Oda</th><th>Durum</th></tr>
    <tr><td><?= d($b['check_in']) ?></td><td><?= d($b['check_out']) ?></td><td class="n"><?= $nights ?></td>
      <td><?= h($b['board']) ?> – <?= h($board[0]) ?></td><td class="n"><?= $totalRooms ?></td><td><?= h(STATUSES[$b['status']] ?? $b['status']) ?><?= $b['option_until'] ? ' (opsiyon ' . d($b['option_until']) . ')' : '' ?></td></tr>
  </table>

  <h2>Odalar ve fiyat</h2>
  <table>
    <tr><th>Oda tipi</th><th class="n">Adet</th><th class="n">Gece</th><th class="n">Gecelik fiyat</th><th class="n">Toplam</th></tr>
    <?php foreach ($calc['lines'] ?? [] as $l): ?>
      <tr><td><?= h($l['room_type']) ?></td><td class="n"><?= (int)$l['qty'] ?></td><td class="n"><?= $nights ?></td>
        <td class="n"><?= m($l['buy_night']) ?> <?= h($b['buy_cur']) ?></td><td class="n"><?= m($l['buy_total']) ?> <?= h($b['buy_cur']) ?></td></tr>
    <?php endforeach; ?>
    <?php foreach ($freeIn as $f): ?>
      <tr><td colspan="4">Free (otelden): <?= (int)$f['qty'] ?> <?= h($f['room_type']) ?></td><td class="n">dahil</td></tr>
    <?php endforeach; ?>
    <?php if (($calc['free_in'] ?? 0) > 0): ?>
      <tr><td colspan="4">Free indirimi</td><td class="n">−<?= m($calc['free_in']) ?> <?= h($b['buy_cur']) ?></td></tr>
    <?php endif; ?>
    <tr class="tot"><td colspan="4">Toplam</td><td class="n"><?= m($calc['buy_net'] ?? null) ?> <?= h($b['buy_cur']) ?></td></tr>
  </table>

  <?php $srv = array_values(array_filter($b['services'], fn($s) => (float)($s['buy'] ?? 0) > 0)); if ($srv): ?>
    <h2>Ek hizmetler</h2>
    <table>
      <tr><th>Hizmet</th><th>Açıklama</th><th class="n">Tutar</th></tr>
      <?php foreach ($srv as $s): ?><tr><td><?= h($s['kind']) ?></td><td><?= h($s['description']) ?></td><td class="n"><?= m($s['buy']) ?> <?= h($s['currency']) ?></td></tr><?php endforeach; ?>
    </table>
  <?php endif; ?>
<?php else: ?>
  <div class="grid2">
    <div class="box">
      <h3>Hotel / Otel</h3>
      <div class="big"><?= h($hotel['name']) ?></div>
      <?php if ($hotel['address']): ?><div><?= h($hotel['address']) ?></div><?php endif; ?>
      <?php if ($hotel['contacts']): ?><div class="muted" style="white-space:pre-wrap"><?= h($hotel['contacts']) ?></div><?php endif; ?>
    </div>
    <div class="box">
      <h3>Agency / Acente</h3>
      <div class="big"><?= h($agency['name'] ?? '') ?></div>
      <?php if (!empty($agency['country'])): ?><div><?= h($agency['country']) ?></div><?php endif; ?>
      <?php if ($extra['contact_name']): ?><div>Lead guest / Grup sorumlusu: <b><?= h($extra['contact_name']) ?></b></div><?php endif; ?>
    </div>
  </div>

  <table>
    <tr><th>Check-in</th><th>Check-out</th><th class="n">Nights / Gece</th><th>Board / Pansiyon</th><th>Rooms / Oda</th><th class="n">Pax</th></tr>
    <tr><td><?= d($b['check_in']) ?></td><td><?= d($b['check_out']) ?></td><td class="n"><?= $nights ?></td>
      <td><?= h($b['board']) ?> – <?= h($board[1]) ?></td><td><?= h($roomSummary) ?></td><td class="n"><?= $paxCount ?: '—' ?></td></tr>
  </table>
  <?php if ($b['hotel_conf_code']): ?>
    <div class="box" style="margin-bottom:12px"><h3>Hotel confirmation no / Otel konfirme no</h3><div class="big"><?= h($b['hotel_conf_code']) ?></div></div>
  <?php endif; ?>
<?php endif; ?>

  <h2><?= $tip === 'voucher' ? 'Guests / Misafirler' : 'Misafir listesi (rooming list)' ?></h2>
  <?php if ($guests): ?>
    <table>
      <tr><th class="n">#</th><th class="n"><?= $tip === 'voucher' ? 'Room' : 'Oda' ?></th><th><?= $tip === 'voucher' ? 'Type' : 'Tip' ?></th><th><?= $tip === 'voucher' ? 'Name / Ad Soyad' : 'Ad Soyad' ?></th><th>Pax</th><th><?= $tip === 'voucher' ? 'Note' : 'Not' ?></th></tr>
      <?php foreach ($guests as $i => $g): ?>
        <tr><td class="n"><?= $i + 1 ?></td><td class="n"><?= (int)$g['room_no'] ?></td><td><?= h($g['room_type']) ?></td><td><?= h($g['name']) ?></td>
          <td><?= h($PAX[$g['pax']][$tip === 'voucher' ? 1 : 0] ?? $g['pax']) ?></td><td><?= h($g['note']) ?></td></tr>
      <?php endforeach; ?>
    </table>
    <div class="muted" style="margin-top:-6px;margin-bottom:10px"><?php if ($tip === 'voucher'): ?>Total / Toplam: <?= $paxCount ?> pax<?= $adl !== $paxCount ? " ($adl adult / yetişkin)" : '' ?><?php else: ?>Toplam <?= $paxCount ?> kişi<?= $adl !== $paxCount ? " ($adl yetişkin)" : '' ?><?php endif; ?></div>
  <?php else: ?>
    <div class="muted" style="margin-bottom:12px"><?= $tip === 'voucher' ? 'Guest names to follow. / İsim listesi ayrıca iletilecektir.' : 'İsim listesi ayrıca iletilecektir.' ?></div>
  <?php endif; ?>

  <?php if ($extra['arrival_info'] || $extra['departure_info']): ?>
    <table>
      <tr><th><?= $tip === 'voucher' ? 'Arrival / Varış' : 'Varış' ?></th><th><?= $tip === 'voucher' ? 'Departure / Dönüş' : 'Dönüş' ?></th></tr>
      <tr><td><?= h($extra['arrival_info']) ?></td><td><?= h($extra['departure_info']) ?></td></tr>
    </table>
  <?php endif; ?>

  <?php $note = $tip === 'voucher' ? $extra['voucher_note'] : $extra['hotel_note']; if ($note): ?>
    <h2><?= $tip === 'voucher' ? 'Notes / Notlar' : 'Notlar' ?></h2>
    <div class="note"><?= h($note) ?></div>
  <?php endif; ?>

  <?php if ($tip === 'talep'): ?>
    <p>Yukarıdaki rezervasyonun konfirme edilmesini rica ederiz. Lütfen konfirme numaranızı <b><?= h($co['email']) ?></b> adresine iletiniz.</p>
    <div class="sign"><div>Otel konfirme no / tarih</div><div>Otel kaşe / imza</div></div>
  <?php else: ?>
    <p class="muted">Please present this voucher at hotel check-in. / Lütfen bu voucher'ı otele girişte gösteriniz.<br>
      Extras (minibar, phone, etc.) are payable by guests directly to the hotel. / Ekstralar misafir tarafından otele ödenir.</p>
  <?php endif; ?>

  <footer>
    <span><?= h($co['name']) ?> · <?= h($co['address']) ?></span>
    <span><?= h($co['phone']) ?> · <?= h($co['email']) ?> · <?= h($co['web']) ?></span>
  </footer>
</div>
</body>
</html>
