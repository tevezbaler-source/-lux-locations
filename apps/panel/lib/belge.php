<?php
// Belgelerin (otel talep fişi, voucher) ortak verisi ve yardımcıları. belge.php ve e-posta gönderimi kullanır.
if (!defined('LX_PANEL')) { http_response_code(403); exit; }

function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function d($iso): string { return $iso ? date('d/m/Y', strtotime($iso)) : ''; }
function up($v): string { return mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], (string)($v ?? '')), 'UTF-8'); }
function m($v): string { return $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float)$v, 2, ',', '.'), '0'), ','); }

/** Bir grubun belge verisi. $durum: 'yeni' | 'degisiklik' (iptal grupta kendiliğinden İPTAL). */
function belge_data(int $id, string $durum = 'yeni'): array {
    $b = booking_get($id);
    if ((int)$b['deleted']) throw new ApiError('Bu grup silinmiş.', 410);
    $hotel = hotel_get((int)$b['hotel_id']);
    $agency = $b['agency_id'] ? q_one('SELECT name, country FROM agencies WHERE id = ?', [$b['agency_id']]) : null;
    $guests = guests_get($id);
    $extra = booking_extra_get($id);
    $calc = calc_booking($b);
    $nights = nights_between($b['check_in'], $b['check_out']);


    $co = (lx_config()['company'] ?? []) + [
        'name' => 'LUX LOCATIONS',
        'address' => 'EVLİYA ÇELEBİ MAHALLESİ, MEŞRUTİYET CADDESİ, DAİRE APT. NO:92 KAT:02 D:05 İSTANBUL/BEYOĞLU 34430 ISTANBUL',
        'phone' => '0212 523 2310 - 0212 523 2311',
        'email' => 'reservation@luxlocationstours.com / info@luxlocationstours.com',
        'web' => 'www.luxlocationstours.com',
        'logo' => 'assets/logo.jpg',
    ];

    $BOARD = ['BB' => 'BED AND BREAKFAST', 'HB' => 'HALF BOARD', 'FB' => 'FULL BOARD', 'RO' => 'ROOM ONLY', 'AI' => 'ALL INCLUSIVE'];
    $ROOM = ['SNGL' => 'SINGLE ROOM', 'DBL' => 'DOUBLE ROOM', 'TWIN' => 'TWIN ROOM', 'TRPL' => 'TRIPLE ROOM', 'QUAD' => 'QUAD ROOM'];
    $cat = $extra['room_category'] ?: 'STANDART ROOM';
    $CAT_SHORT = ['STANDART' => 'STD', 'STANDARD' => 'STD', 'SUPERIOR' => 'SUP', 'DELUXE' => 'DLX', 'FAMILY' => 'FAM', 'SUITE' => 'SUI', 'ECONOMY' => 'ECO'];
    $catWord = strtoupper(explode(' ', trim($cat))[0]);
    $catShort = $CAT_SHORT[$catWord] ?? (mb_substr($catWord, 0, 3) ?: 'STD');
    $boardName = $BOARD[$b['board'] ?? ''] ?? (string)$b['board'];

    $lines = $calc['lines'] ?? [];
    $totalRooms = array_sum(array_map(fn($l) => (int)$l['qty'], $lines));
    $roomCounts = implode(', ', array_map(fn($l) => $l['room_type'] . ':' . (int)$l['qty'], $lines));
    $cnt = ['ADL' => 0, 'CHD' => 0, 'INF' => 0];
    foreach ($guests as $g) $cnt[$g['pax']] = ($cnt[$g['pax']] ?? 0) + 1;
    $pax = array_sum($cnt);

    // Müşteri adı: az kişide isimler, kalabalık grupta sorumlu + kişi sayısı
    if ($pax && $pax <= 4) $customer = implode('-', array_column($guests, 'name'));
    elseif ($pax) $customer = ($extra['contact_name'] ?: $guests[0]['name']) . " + " . ($pax - 1) . ' pax';
    else $customer = (string)($extra['contact_name'] ?? '');

    // Otele giden fiyat satırı (Sejour'daki "NOT: DBL 40 USD DEN KONFIRMESINI RICA EDERIM" gibi)
    $priceParts = array_map(fn($l) => $l['room_type'] . ' ' . m($l['buy_night']) . ' ' . $b['buy_cur'], array_filter($lines, fn($l) => $l['buy_night'] !== null));
    $freeIn = array_values(array_filter($b['free'], fn($f) => $f['direction'] === 'in'));
    $priceNote = $priceParts ? implode(', ', $priceParts) . "'DEN KONFİRMESİNİ RİCA EDERİM." : '';
    if ($freeIn) $priceNote .= ' FREE: ' . implode(', ', array_map(fn($f) => (int)$f['qty'] . ' ' . $f['room_type'], $freeIn)) . '.';

    $cancelled = $b['status'] === 'iptal';
    $confirmed = in_array($b['status'], LOCKED_STATUSES, true);
    $state = $cancelled ? 'İPTAL' : ($durum === 'degisiklik' ? 'DEĞİŞİKLİK' : 'YENİ');
    $code = $b['code'] ?: '#' . $id;
    $now = time();
    return get_defined_vars();
}

/**
 * Otel rezervasyon fişinin e-posta hâli (Outlook dahil her istemcide düzgün görünsün diye tablo düzeni, satır içi stil).
 * Döner: ['subject' => ..., 'html' => ..., 'text' => ...]
 */
function talep_mail(array $D, array $user, string $message = ''): array {
    extract($D);
    $base = rtrim((string)(lx_config()['public_url'] ?? 'https://luxlocationstours.com/panel'), '/') . '/';
    $logo = $co['logo'] ? (preg_match('~^https?://~', $co['logo']) ? $co['logo'] : $base . ltrim($co['logo'], '/')) : '';
    $F = 'font-family:Arial,Helvetica,sans-serif;';
    $td = "style=\"{$F}font-size:13px;padding:2px 6px;vertical-align:top\"";
    $kv = fn($k, $v, $extra = '') => "<tr><td $td align=\"right\">" . h($k) . " :</td><td $td><b>" . h($v) . '</b>' . ($extra ? ' <span style="color:#444;font-size:12px">' . h($extra) . '</span>' : '') . '</td></tr>';

    $roomRows = '';
    foreach ($lines as $l) $roomRows .= $kv('Oda', $l['room_type'] . (count($lines) > 1 ? ' x' . (int)$l['qty'] : ''), '(' . ($ROOM[$l['room_type']] ?? $l['room_type']) . ')');
    $noteTxt = up(trim($priceNote . ' ' . ($extra['hotel_note'] ?? '')));

    $gRows = '';
    foreach ($guests as $i => $g) {
        $age = $g['pax'] !== 'ADL' ? $g['pax'] . ($g['note'] ? ' ' . $g['note'] : '') : ($g['note'] ?? '');
        $gRows .= "<tr><td $td>" . h($g['name']) . "</td><td $td>" . (int)$g['room_no'] . ' ' . h($g['room_type']) . "</td><td $td>" . h($age) . "</td><td $td>"
            . ($i === 0 ? h($extra['arrival_info']) : '') . "</td><td $td>" . ($i === 0 ? h($extra['departure_info']) : '') . '</td></tr>';
    }
    $th = "style=\"{$F}font-size:12px;text-align:left;border-bottom:1px solid #111;padding:3px 6px\"";
    $stateColor = $state === 'İPTAL' ? '#c0392b' : ($state === 'DEĞİŞİKLİK' ? '#b9770e' : '#111');

    $html = '<!doctype html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:16px;background:#fff">'
        . ($message !== '' ? "<p style=\"{$F}font-size:14px;margin:0 0 14px\">" . nl2br(h($message)) . '</p>' : '')
        . '<table role="presentation" width="720" cellpadding="0" cellspacing="0" style="border:1px solid #ccc;padding:14px;max-width:720px"><tr><td style="padding:14px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
        . '<td width="170" valign="top">' . ($logo ? '<img src="' . h($logo) . '" width="140" alt="LUX LOCATIONS" style="display:block;border:0">' : "<b style=\"{$F}font-size:20px;color:#3a5f78\">LUX LOCATIONS</b>")
        . "<div style=\"{$F}font-weight:bold;font-size:15px;margin-top:6px\">Voucher No: " . h($code) . '</div></td>'
        . "<td align=\"center\" valign=\"top\" style=\"{$F}font-weight:bold;font-size:16px;line-height:1.5\">OTEL REZERVASYON FİŞİ<br>" . h(up($hotel['name'])) . '<br><span style="font-size:14px">' . h(up($hotel['region'] ?: 'İSTANBUL')) . '</span>'
        . "<div style=\"font-size:26px;color:$stateColor;margin-top:8px\">" . h($state) . '</div></td>'
        . "<td width=\"150\" align=\"right\" valign=\"top\" style=\"{$F}font-size:11px;line-height:1.5\">Tarih : " . date('d/m/Y', $now) . '<br>Saat : ' . date('H:i', $now) . '<br>Hazırlayan : ' . h($user['name'])
        . '<br><br>Satış Tarihi : <b>' . d(substr((string)$b['created_at'], 0, 10) ?: date('Y-m-d')) . '</b></td></tr></table>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px"><tr>'
        . '<td valign="top" width="34%"><table cellpadding="0" cellspacing="0">'
        . $kv('Giriş Tarihi', d($b['check_in'])) . $kv('Çıkış Tarihi', d($b['check_out'])) . $kv('Gün', (string)$nights)
        . $kv('Durum', (STATUSES[$b['status']] ?? $b['status']) . ($b['option_until'] && !$confirmed ? ' (opsiyon ' . d($b['option_until']) . ')' : ''))
        . ($b['hotel_conf_code'] ? $kv('Otel Konf. No', $b['hotel_conf_code']) : '') . '</table></td>'
        . '<td valign="top" width="40%"><table cellpadding="0" cellspacing="0">' . $kv('Oda Sayısı', (string)$totalRooms) . $roomRows
        . $kv('Oda Tipi', $catShort, '(' . up($cat) . ')') . $kv('Pansiyon', (string)$b['board'], '(' . $boardName . ')') . '</table></td>'
        . '<td valign="top" align="right"><table cellpadding="0" cellspacing="0">' . $kv('Yetişkin', (string)($cnt['ADL'] ?: '')) . $kv('Çocuk', (string)($cnt['CHD'] ?: ''))
        . $kv('Bebek', (string)($cnt['INF'] ?: '')) . $kv('Toplam Pax', (string)($pax ?: '')) . $kv('Döviz', (string)$b['buy_cur']) . '</table></td></tr></table>'
        . ($extra['nationality'] ? "<p style=\"{$F}font-size:13px;margin:10px 0 0 40px\">Milliyet : <b>" . h($extra['nationality']) . '</b></p>' : '')
        . ($noteTxt !== '' ? "<p style=\"{$F}font-size:13px;margin:12px 0 10px 40px\">NOT: <b>" . h($noteTxt) . '</b></p>' : '')
        . ($guests ? '<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:8px"><tr>'
            . "<th $th>SOYADI, ADI</th><th $th>ODA</th><th $th>YAŞ / NOT</th><th $th>GELİŞ</th><th $th>DÖNÜŞ</th></tr>$gRows</table>" : '')
        . "<p style=\"{$F}font-size:12px;border-top:1px solid #111;border-bottom:1px solid #111;padding:5px 0 5px 40px;margin:8px 0\"><b>TOPLAM PAX :</b> " . $pax
        . ($pax ? ' (Yetişkin: ' . $cnt['ADL'] . ($cnt['CHD'] ? ', Çocuk: ' . $cnt['CHD'] : '') . ($cnt['INF'] ? ', Bebek: ' . $cnt['INF'] : '') . ')' : '')
        . '<br><b>TOPLAM ODA :</b> ' . $totalRooms . ' (' . h($roomCounts) . ')</p>'
        . "<p style=\"{$F}font-size:12px;color:#555\">Lütfen konfirme numaranızı bu e-postayı yanıtlayarak iletiniz.<br>"
        . h($co['name']) . ' · ' . h($co['phone']) . ' · ' . h($co['web']) . '</p>'
        . '</td></tr></table></body></html>';

    $t = [];
    if ($message !== '') $t[] = $message . "\n";
    $t[] = "OTEL REZERVASYON FİŞİ - $state";
    $t[] = up($hotel['name']) . ' / Voucher No: ' . $code;
    $t[] = 'Giriş: ' . d($b['check_in']) . '  Çıkış: ' . d($b['check_out']) . "  ($nights gece)";
    $t[] = "Oda: $roomCounts  ($catShort, {$b['board']})";
    $t[] = "Pax: $pax" . ($extra['nationality'] ? '  Milliyet: ' . $extra['nationality'] : '');
    if ($noteTxt !== '') $t[] = "NOT: $noteTxt";
    if ($guests) { $t[] = ''; foreach ($guests as $g) $t[] = (int)$g['room_no'] . ' ' . $g['room_type'] . ' - ' . $g['name'] . ($g['pax'] !== 'ADL' ? ' (' . $g['pax'] . ')' : ''); }
    if ($extra['arrival_info'] || $extra['departure_info']) $t[] = "\nGeliş: " . ($extra['arrival_info'] ?? '') . '  Dönüş: ' . ($extra['departure_info'] ?? '');
    $t[] = "\nLütfen konfirme numaranızı bu e-postayı yanıtlayarak iletiniz.\n" . $co['name'] . ' · ' . $co['phone'];

    $subject = ($state === 'YENİ' ? 'Rezervasyon talebi' : ($state === 'DEĞİŞİKLİK' ? 'Rezervasyon değişikliği' : 'Rezervasyon iptali'))
        . " $code - " . $hotel['name'] . ' - ' . d($b['check_in']) . ' / ' . d($b['check_out']) . " - $totalRooms oda";
    return ['subject' => $subject, 'html' => $html, 'text' => implode("\n", $t)];
}
