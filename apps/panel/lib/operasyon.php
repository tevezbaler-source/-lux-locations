<?php
// Operasyon: transfer ve tur planı (grupların ek hizmetleri) ve muhasebe için fatura listesi.
if (!defined('LX_PANEL')) { http_response_code(403); exit; }

/* ---------------- Transfer ve tur planı ---------------- */

/** [from, to) arasındaki hizmetler. Tarihi girilmemiş hizmet grubun giriş gününde görünür. */
function operasyon_liste(string $from, string $to, string $ara = ''): array {
    $rows = q_all("SELECT s.id, s.booking_id, s.kind, s.description, s.currency, " . preg_replace('/(\w+)/', 's.$1', SERVICE_PLAN_COLS) . ",
            b.code, b.check_in, b.check_out, b.status, b.hotel_id, h.name AS hotel, a.name AS agency,
            (SELECT COUNT(*) FROM booking_guests g WHERE g.booking_id = b.id) AS guests,
            (SELECT contact_name FROM booking_extra e WHERE e.booking_id = b.id) AS contact_name,
            (SELECT arrival_info FROM booking_extra e WHERE e.booking_id = b.id) AS arrival_info,
            (SELECT departure_info FROM booking_extra e WHERE e.booking_id = b.id) AS departure_info
        FROM booking_services s JOIN bookings b ON b.id = s.booking_id LEFT JOIN hotels h ON h.id = b.hotel_id LEFT JOIN agencies a ON a.id = b.agency_id
        WHERE b.deleted = 0 AND b.status <> 'iptal' AND COALESCE(s.service_date, b.check_in) >= ? AND COALESCE(s.service_date, b.check_in) < ?
        ORDER BY COALESCE(s.service_date, b.check_in), COALESCE(s.service_time, '99:99'), h.name, s.id", [$from, $to]);
    $q = norm_name($ara);
    if ($q !== '') $rows = array_values(array_filter($rows, fn($r) => str_contains(norm_name(implode(' ', [$r['kind'], $r['description'], $r['code'], $r['hotel'], $r['agency'], $r['driver'], $r['guide'], $r['vehicle'], $r['pickup'], $r['dropoff']])), $q)));
    foreach ($rows as &$r) { $r['day'] = $r['service_date'] ?: $r['check_in']; $r['planned'] = (bool)$r['service_date']; }
    return $rows;
}

/** Tek hizmetin plan bilgisini kaydeder (fiyatlara dokunmaz; konfirme grupta da yapılabilir). */
function operasyon_kaydet(array $in): array {
    need('booking');
    $id = clean_int($in['id'] ?? null);
    $s = $id ? q_one('SELECT s.*, b.code, b.deleted FROM booking_services s JOIN bookings b ON b.id = s.booking_id WHERE s.id = ?', [$id]) : null;
    if (!$s || (int)$s['deleted']) throw new ApiError('Hizmet bulunamadı; grup bu arada değişmiş olabilir. Sayfayı yenileyin.', 404);
    $d = service_plan_input($in);
    $old = array_intersect_key($s, $d);
    q('UPDATE booking_services SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($d))) . ' WHERE id = ?', [...array_values($d), $id]);
    audit('grup', (int)$s['booking_id'], 'plan', "Transfer/tur planı: {$s['kind']}" . ($s['code'] ? " ({$s['code']})" : '') . ($d['service_date'] ? ' ' . $d['service_date'] : '') . ($d['service_time'] ? ' ' . $d['service_time'] : ''), $old, $d);
    return ['ok' => true] + $d;
}

/* ---------------- Fatura listesi (muhasebe) ---------------- */

/** Çıkışı [from, to) arasında olan konfirme grupların acenteye kesilecek fatura tutarları. */
function fatura_liste(string $from, string $to, string $durum = ''): array {
    need('cari');
    $ids = q_all("SELECT id FROM bookings WHERE deleted = 0 AND status IN ('konfirme','konaklamada','kapandi') AND check_out >= ? AND check_out < ? ORDER BY check_out, id", [$from, $to]);
    $rows = []; $tot = [];
    foreach ($ids as $r) {
        $b = booking_get((int)$r['id']);
        if ($durum === 'kesildi' && !$b['invoice_no']) continue;
        if ($durum === 'kesilmedi' && $b['invoice_no']) continue;
        $c = calc_booking($b);
        $amounts = [$c['sell_cur'] => $c['sell_net']];
        foreach ($c['services'] as $cur => $v) if ($v['sell']) $amounts[$cur] = ($amounts[$cur] ?? 0) + $v['sell'];
        $amounts = array_filter(array_map(fn($v) => round($v, 2), $amounts));
        $try = 0.0; $tryOk = true;
        foreach ($amounts as $cur => $v) {
            $k = $cur === 'TRY' ? 1 : ($cur === 'EUR' ? $c['eur_try'] : $c['usd_try']);
            if (!$k) $tryOk = false; else $try += $v * $k;
            $tot[$cur] = round(($tot[$cur] ?? 0) + $v, 2);
        }
        $ag = $b['agency_id'] ? q_one('SELECT name, country, invoice_info FROM agencies WHERE id = ?', [$b['agency_id']]) : null;
        $rows[] = ['id' => (int)$b['id'], 'code' => $b['code'], 'status' => $b['status'], 'check_in' => $b['check_in'], 'check_out' => $b['check_out'],
            'hotel' => q_val('SELECT name FROM hotels WHERE id = ?', [$b['hotel_id']]), 'agency' => $ag['name'] ?? null, 'agency_id' => $b['agency_id'] ? (int)$b['agency_id'] : null,
            'country' => $ag['country'] ?? null, 'invoice_info' => $ag['invoice_info'] ?? null, 'rooms' => $c['rooms'], 'nights' => $c['nights'],
            'amounts' => $amounts ?: new stdClass(), 'eur_try' => $c['eur_try'], 'usd_try' => $c['usd_try'], 'try' => $tryOk ? round($try, 2) : null,
            'invoice_no' => $b['invoice_no'], 'invoice_date' => $b['invoice_date']];
    }
    return ['rows' => $rows, 'totals' => $tot ?: new stdClass(), 'open' => count(array_filter($rows, fn($r) => !$r['invoice_no']))];
}

function fatura_isaretle(array $in): array {
    need('cari');
    $id = clean_int($in['id'] ?? null);
    $b = $id ? q_one('SELECT id, code, invoice_no, invoice_date FROM bookings WHERE id = ? AND deleted = 0', [$id]) : null;
    if (!$b) throw new ApiError('Grup bulunamadı.', 404);
    $no = clean_str($in['invoice_no'] ?? null, 40);
    $date = $no ? (clean_date($in['invoice_date'] ?? '') ?: date('Y-m-d')) : null;
    if ($no && ($dup = q_one('SELECT code FROM bookings WHERE invoice_no = ? AND id <> ? AND deleted = 0', [$no, $id]))) throw new ApiError("$no numaralı fatura başka grupta da yazılı ({$dup['code']}). Aynı fatura birden fazla grubu kapsıyorsa sonuna grup kodunu ekleyin.");
    q('UPDATE bookings SET invoice_no = ?, invoice_date = ? WHERE id = ?', [$no, $date, $id]);
    audit('grup', $id, 'fatura', $no ? "Fatura kesildi: $no ($date)" . ($b['code'] ? " · {$b['code']}" : '') : 'Fatura numarası silindi' . ($b['code'] ? " · {$b['code']}" : ''),
        ['invoice_no' => $b['invoice_no'], 'invoice_date' => $b['invoice_date']], ['invoice_no' => $no, 'invoice_date' => $date]);
    return ['ok' => true, 'invoice_no' => $no, 'invoice_date' => $date];
}

function fatura_csv(string $from, string $to, string $durum): void {
    $d = fatura_liste($from, $to, $durum);
    audit('rapor', null, 'indir', "Fatura listesi Excel'e aktarıldı: $from – $to", null, null, db_main());
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="fatura-listesi-' . $from . '-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $num = fn($v) => $v === null ? '' : str_replace('.', ',', (string)$v);
    fputcsv($out, ['Grup kodu', 'Acente', 'Ülke', 'Fatura bilgileri', 'Otel', 'Giriş', 'Çıkış', 'Oda', 'Gece', 'Tutar', 'Para birimi', 'EUR kuru', 'USD kuru', 'TL karşılığı', 'Fatura no', 'Fatura tarihi'], ';');
    foreach ($d['rows'] as $r) {
        foreach ((array)$r['amounts'] as $cur => $v) {
            fputcsv($out, [$r['code'] ?: '#' . $r['id'], $r['agency'], $r['country'], str_replace(["\r\n", "\n"], ' | ', (string)$r['invoice_info']), $r['hotel'], $r['check_in'], $r['check_out'], $r['rooms'], $r['nights'],
                $num($v), $cur, $num($r['eur_try']), $num($r['usd_try']), $num(count((array)$r['amounts']) === 1 ? $r['try'] : null), $r['invoice_no'], $r['invoice_date']], ';');
        }
    }
    fclose($out);
    exit;
}
