<?php
// Acente cari hesabı: konfirme gruplar borç, tahsilatlar alacak; para birimi başına bakiye.
if (!defined('LX_PANEL')) { http_response_code(403); exit; }

const PAY_METHODS = ['havale' => 'Havale / EFT', 'nakit' => 'Nakit', 'kart' => 'Kredi kartı', 'mahsup' => 'Mahsup', 'diger' => 'Diğer'];

/** Hareketler (tarih sırasıyla) ve para birimi başına bakiye. */
function cari_agency(int $agencyId): array {
    $rows = [];
    $ids = q_all("SELECT id FROM bookings WHERE agency_id = ? AND deleted = 0 AND status IN ('konfirme','konaklamada','kapandi') ORDER BY check_in", [$agencyId]);
    foreach ($ids as $r) {
        $b = booking_get((int)$r['id']);
        $c = calc_booking($b);
        $amounts = [$c['sell_cur'] => $c['sell_net']];
        foreach ($c['services'] as $cur => $v) if ($v['sell']) $amounts[$cur] = ($amounts[$cur] ?? 0) + $v['sell'];
        foreach ($amounts as $cur => $amt) {
            if (!$amt) continue;
            $rows[] = ['type' => 'satis', 'date' => $b['check_in'], 'booking_id' => (int)$b['id'], 'code' => $b['code'],
                'text' => q_val('SELECT name FROM hotels WHERE id = ?', [$b['hotel_id']]) . ' · ' . $b['check_in'] . ' / ' . $b['check_out'] . ' · ' . $c['rooms'] . ' oda',
                'currency' => $cur, 'debit' => round($amt, 2), 'credit' => 0];
        }
    }
    $pays = q_all('SELECT p.*, b.code FROM payments p LEFT JOIN bookings b ON b.id = p.booking_id WHERE p.agency_id = ? AND p.deleted = 0', [$agencyId]);
    foreach ($pays as $p) {
        $rows[] = ['type' => 'tahsilat', 'id' => (int)$p['id'], 'date' => $p['pay_date'], 'booking_id' => $p['booking_id'] ? (int)$p['booking_id'] : null, 'code' => $p['code'],
            'text' => (PAY_METHODS[$p['method']] ?? 'Tahsilat') . ($p['note'] ? ' · ' . $p['note'] : ''), 'currency' => $p['currency'], 'debit' => 0, 'credit' => round((float)$p['amount'], 2)];
    }
    usort($rows, fn($a, $b) => [$a['date'], $a['type'] === 'tahsilat'] <=> [$b['date'], $b['type'] === 'tahsilat']);
    $bal = [];
    foreach ($rows as &$r) {
        $bal[$r['currency']] = round(($bal[$r['currency']] ?? 0) + $r['debit'] - $r['credit'], 2);
        $r['balance'] = $bal[$r['currency']];
    }
    unset($r);
    $tot = [];
    foreach ($rows as $r) {
        $t = &$tot[$r['currency']];
        $t ??= ['debit' => 0, 'credit' => 0];
        $t['debit'] += $r['debit']; $t['credit'] += $r['credit'];
        $t['balance'] = round($t['debit'] - $t['credit'], 2);
        unset($t);
    }
    return ['rows' => array_reverse($rows), 'totals' => $tot ?: new stdClass(), 'methods' => PAY_METHODS];
}

function payment_save(array $in): array {
    need('cari');
    $agency = clean_int($in['agency_id'] ?? null);
    if (!$agency || !q_one('SELECT id FROM agencies WHERE id = ?', [$agency])) throw new ApiError('Acente bulunamadı.');
    $raw = trim((string)($in['amount'] ?? ''));
    // "3.000,50" ve "3,000.50" gibi yazımlar
    if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $raw)) $raw = str_replace(['.', ','], ['', '.'], $raw);
    elseif (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $raw)) $raw = str_replace(',', '', $raw);
    $amount = num($raw);
    if (!$amount || $amount <= 0) throw new ApiError('Tutar girin.');
    $cur = strtoupper((string)($in['currency'] ?? ''));
    if (!in_array($cur, CURRENCIES, true)) throw new ApiError('Para birimi seçin.');
    $date = clean_date($in['pay_date'] ?? '') ?: date('Y-m-d');
    $booking = clean_int($in['booking_id'] ?? null);
    if ($booking && !q_one('SELECT id FROM bookings WHERE id = ? AND agency_id = ?', [$booking, $agency])) throw new ApiError('Grup bu acenteye ait değil.');
    $method = isset(PAY_METHODS[$in['method'] ?? '']) ? $in['method'] : 'havale';
    $data = ['agency_id' => $agency, 'booking_id' => $booking, 'pay_date' => $date, 'amount' => round($amount, 2), 'currency' => $cur, 'method' => $method,
        'note' => clean_str($in['note'] ?? null, 255), 'deleted' => 0];
    $id = insert_row('payments', $data + stamp_new());
    $name = q_val('SELECT name FROM agencies WHERE id = ?', [$agency]);
    audit('tahsilat', $id, 'ekle', "Tahsilat: $name " . fmt_num($amount) . " $cur ($date)", null, $data);
    return ['ok' => true, 'id' => $id];
}

function payment_delete(int $id): array {
    need('cari');
    $p = q_one('SELECT * FROM payments WHERE id = ? AND deleted = 0', [$id]);
    if (!$p) throw new ApiError('Tahsilat bulunamadı.', 404);
    q('UPDATE payments SET deleted = 1, updated_by = ?, updated_at = ? WHERE id = ?', [current_user()['id'] ?? null, now(), $id]);
    $name = q_val('SELECT name FROM agencies WHERE id = ?', [$p['agency_id']]);
    audit('tahsilat', $id, 'sil', "Tahsilat silindi: $name " . fmt_num((float)$p['amount']) . " {$p['currency']} ({$p['pay_date']})", $p, null);
    return ['ok' => true];
}
