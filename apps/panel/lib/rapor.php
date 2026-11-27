<?php
// Raporlar: aylık satış ve kâr, acente ve otel bazında; Excel'e (CSV) aktarma.
if (!defined('LX_PANEL')) { http_response_code(403); exit; }

/** $kapsam: 'satis' = konfirme/konaklamada/kapandı, 'hepsi' = iptal hariç hepsi (teklif ve opsiyon dahil). */
function rapor_rows(string $from, string $to, string $kapsam = 'satis'): array {
    need('report');
    $st = $kapsam === 'hepsi' ? ['teklif', 'opsiyon', 'konfirme', 'konaklamada', 'kapandi'] : LOCKED_STATUSES;
    $ids = q_all('SELECT id FROM bookings WHERE deleted = 0 AND check_in >= ? AND check_in < ? AND status IN (' . implode(',', array_fill(0, count($st), '?')) . ') ORDER BY check_in, id',
        array_merge([$from, $to], $st));
    $hotels = array_column(q_all('SELECT id, name FROM hotels'), 'name', 'id');
    $agencies = array_column(q_all('SELECT id, name FROM agencies'), 'name', 'id');
    $rows = [];
    foreach ($ids as $r) {
        $b = booking_get((int)$r['id']);
        $c = calc_booking($b);
        $rows[] = [
            'id' => (int)$b['id'], 'code' => $b['code'], 'status' => $b['status'], 'check_in' => $b['check_in'], 'check_out' => $b['check_out'], 'month' => substr($b['check_in'], 0, 7),
            'hotel' => $hotels[$b['hotel_id']] ?? '?', 'agency' => $agencies[$b['agency_id']] ?? '—',
            'rooms' => $c['rooms'], 'nights' => $c['nights'], 'room_nights' => $c['rooms'] * $c['nights'],
            'buy_net' => $c['buy_net'], 'buy_cur' => $c['buy_cur'], 'sell_net' => $c['sell_net'], 'sell_cur' => $c['sell_cur'],
            'sell_eur' => $c['sell_eur'], 'profit_eur' => $c['profit_eur'], 'profit_try' => $c['profit_try'], 'ok' => $c['profit_eur'] !== null && !$c['has_error'],
        ];
    }
    return $rows;
}

function rapor(string $from, string $to, string $kapsam = 'satis'): array {
    $rows = rapor_rows($from, $to, $kapsam);
    $group = function (string $key) use ($rows): array {
        $g = [];
        foreach ($rows as $r) {
            $k = $r[$key];
            $g[$k] ??= ['key' => $k, 'groups' => 0, 'room_nights' => 0, 'sell_eur' => 0.0, 'profit_eur' => 0.0, 'profit_try' => 0.0, 'unknown' => 0];
            $g[$k]['groups']++;
            $g[$k]['room_nights'] += $r['room_nights'];
            if ($r['ok']) { $g[$k]['sell_eur'] += (float)$r['sell_eur']; $g[$k]['profit_eur'] += (float)$r['profit_eur']; $g[$k]['profit_try'] += (float)$r['profit_try']; }
            else $g[$k]['unknown']++;
        }
        foreach ($g as &$x) foreach (['sell_eur', 'profit_eur', 'profit_try'] as $f) $x[$f] = round($x[$f], 2);
        return array_values($g);
    };
    $byMonth = $group('month');
    usort($byMonth, fn($a, $b) => strcmp($a['key'], $b['key']));
    $byAgency = $group('agency');
    usort($byAgency, fn($a, $b) => $b['profit_eur'] <=> $a['profit_eur']);
    $byHotel = $group('hotel');
    usort($byHotel, fn($a, $b) => $b['room_nights'] <=> $a['room_nights']);
    $tot = ['groups' => count($rows), 'room_nights' => array_sum(array_column($rows, 'room_nights')), 'unknown' => count(array_filter($rows, fn($r) => !$r['ok']))];
    foreach (['sell_eur', 'profit_eur', 'profit_try'] as $f) $tot[$f] = round(array_sum(array_map(fn($r) => $r['ok'] ? (float)$r[$f] : 0, $rows)), 2);
    return ['from' => $from, 'to' => $to, 'kapsam' => $kapsam, 'total' => $tot, 'months' => $byMonth, 'agencies' => $byAgency, 'hotels' => $byHotel];
}

/** Excel'in Türkçe ayarlarında doğrudan açılan CSV (UTF-8 BOM, noktalı virgül, ondalık virgül). */
function rapor_csv(string $from, string $to, string $kapsam): void {
    $rows = rapor_rows($from, $to, $kapsam);
    audit('rapor', null, 'indir', "Rapor Excel'e aktarıldı: $from – $to", null, null, db_main());
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="otel-paneli-rapor-' . $from . '-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $num = fn($v) => $v === null ? '' : str_replace('.', ',', (string)$v);
    fputcsv($out, ['Grup kodu', 'Durum', 'Giriş', 'Çıkış', 'Otel', 'Acente', 'Oda', 'Gece', 'Oda-gece', 'Alış', 'Alış para', 'Satış', 'Satış para', 'Satış EUR', 'Kâr EUR', 'Kâr TL', 'Hesap tam'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [$r['code'] ?: '#' . $r['id'], STATUSES[$r['status']] ?? $r['status'], $r['check_in'], $r['check_out'], $r['hotel'], $r['agency'], $r['rooms'], $r['nights'], $r['room_nights'],
            $num($r['buy_net']), $r['buy_cur'], $num($r['sell_net']), $r['sell_cur'], $num($r['sell_eur']), $num($r['profit_eur']), $num($r['profit_try']), $r['ok'] ? 'Evet' : 'Hayır'], ';');
    }
    fclose($out);
    exit;
}
