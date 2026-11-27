<?php
// Otel carisi: konfirme gruplarımızın alışı otele borcumuz, otele yaptığımız ödemeler alacak; para birimi başına bakiye.
if (!defined('LX_PANEL')) { http_response_code(403); exit; }

/** Otelin hareketleri ve bakiyesi. Borç tarihi çıkış tarihidir (otel konaklama bitince fatura keser). */
function otel_cari(int $hotelId): array {
    need('cari');
    $rows = [];
    $today = date('Y-m-d');
    $ids = q_all("SELECT id FROM bookings WHERE hotel_id = ? AND deleted = 0 AND status IN ('konfirme','konaklamada','kapandi') ORDER BY check_out", [$hotelId]);
    foreach ($ids as $r) {
        $b = booking_get((int)$r['id']);
        $c = calc_booking($b);
        if (!$c['buy_net']) continue;
        $rows[] = ['type' => 'alis', 'date' => $b['check_out'], 'booking_id' => (int)$b['id'], 'code' => $b['code'], 'due' => $b['check_out'] <= $today,
            'text' => (q_val('SELECT name FROM agencies WHERE id = ?', [$b['agency_id']]) ?: '—') . ' · ' . $b['check_in'] . ' / ' . $b['check_out'] . ' · ' . $c['rooms'] . ' oda',
            'currency' => $c['buy_cur'], 'debit' => round($c['buy_net'], 2), 'credit' => 0];
    }
    foreach (q_all('SELECT * FROM hotel_payments WHERE hotel_id = ? AND deleted = 0', [$hotelId]) as $p) {
        $rows[] = ['type' => 'odeme', 'id' => (int)$p['id'], 'date' => $p['pay_date'], 'booking_id' => null, 'code' => null, 'statement_id' => $p['statement_id'] ? (int)$p['statement_id'] : null,
            'text' => (PAY_METHODS[$p['method']] ?? 'Ödeme') . ($p['note'] ? ' · ' . $p['note'] : ''), 'currency' => $p['currency'], 'debit' => 0, 'credit' => round((float)$p['amount'], 2)];
    }
    usort($rows, fn($a, $b) => [$a['date'], $a['type'] === 'odeme'] <=> [$b['date'], $b['type'] === 'odeme']);
    $bal = []; $tot = [];
    foreach ($rows as &$r) {
        $cur = $r['currency'];
        $bal[$cur] = round(($bal[$cur] ?? 0) + $r['debit'] - $r['credit'], 2);
        $r['balance'] = $bal[$cur];
        $tot[$cur] ??= ['debit' => 0, 'credit' => 0, 'balance' => 0, 'due' => 0];
        $tot[$cur]['debit'] += $r['debit']; $tot[$cur]['credit'] += $r['credit'];
        if ($r['type'] === 'alis' && $r['due']) $tot[$cur]['due'] += $r['debit'];
    }
    unset($r);
    foreach ($tot as &$t) {
        $t['balance'] = round($t['debit'] - $t['credit'], 2);
        // Vadesi gelen borç: konaklaması bitmiş alışlar eksi ödemeler (eksiye düşmez)
        $t['due'] = round(max(0, $t['due'] - $t['credit']), 2);
        $t['debit'] = round($t['debit'], 2); $t['credit'] = round($t['credit'], 2);
    }
    unset($t);
    return ['rows' => array_reverse($rows), 'totals' => $tot ?: new stdClass(), 'methods' => PAY_METHODS];
}

/** Grubu veya ödemesi olan tüm otellerin bakiyesi. */
function otel_cari_liste(): array {
    need('cari');
    $ids = q_all("SELECT DISTINCT hotel_id FROM bookings WHERE deleted = 0 AND status IN ('konfirme','konaklamada','kapandi') UNION SELECT DISTINCT hotel_id FROM hotel_payments WHERE deleted = 0");
    $out = [];
    foreach ($ids as $r) {
        $h = q_one('SELECT id, name, region FROM hotels WHERE id = ?', [(int)$r['hotel_id']]);
        if (!$h) continue;
        $c = otel_cari((int)$h['id']);
        $tot = (array)$c['totals'];
        if (!$tot) continue;
        $last = q_val('SELECT MAX(pay_date) FROM hotel_payments WHERE hotel_id = ? AND deleted = 0', [$h['id']]);
        $out[] = $h + ['totals' => $tot, 'last_payment' => $last];
    }
    usort($out, fn($a, $b) => strcmp(norm_name($a['name']), norm_name($b['name'])));
    return $out;
}

function otel_odeme_kaydet(array $in): array {
    need('cari');
    $hotel = clean_int($in['hotel_id'] ?? null);
    if (!$hotel || !q_one('SELECT id FROM hotels WHERE id = ?', [$hotel])) throw new ApiError('Otel bulunamadı.');
    $raw = trim((string)($in['amount'] ?? ''));
    if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $raw)) $raw = str_replace(['.', ','], ['', '.'], $raw);
    elseif (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $raw)) $raw = str_replace(',', '', $raw);
    $amount = num($raw);
    if (!$amount || $amount <= 0) throw new ApiError('Tutar girin.');
    $cur = strtoupper((string)($in['currency'] ?? ''));
    if (!in_array($cur, CURRENCIES, true)) throw new ApiError('Para birimi seçin.');
    $stmt = clean_int($in['statement_id'] ?? null);
    $data = ['hotel_id' => $hotel, 'pay_date' => clean_date($in['pay_date'] ?? '') ?: date('Y-m-d'), 'amount' => round($amount, 2), 'currency' => $cur,
        'method' => isset(PAY_METHODS[$in['method'] ?? '']) ? $in['method'] : 'havale', 'note' => clean_str($in['note'] ?? null, 255), 'statement_id' => $stmt, 'deleted' => 0];
    $id = insert_row('hotel_payments', $data + stamp_new());
    $name = q_val('SELECT name FROM hotels WHERE id = ?', [$hotel]);
    audit('otel_odeme', $id, 'ekle', "Otele ödeme: $name " . fmt_num($amount) . " $cur ({$data['pay_date']})", null, $data);
    return ['ok' => true, 'id' => $id];
}

function otel_odeme_sil(int $id): array {
    need('cari');
    $p = q_one('SELECT * FROM hotel_payments WHERE id = ? AND deleted = 0', [$id]);
    if (!$p) throw new ApiError('Ödeme bulunamadı.', 404);
    q('UPDATE hotel_payments SET deleted = 1, updated_by = ?, updated_at = ? WHERE id = ?', [current_user()['id'] ?? null, now(), $id]);
    $name = q_val('SELECT name FROM hotels WHERE id = ?', [$p['hotel_id']]);
    audit('otel_odeme', $id, 'sil', "Otele ödeme silindi: $name " . fmt_num((float)$p['amount']) . " {$p['currency']} ({$p['pay_date']})", $p, null);
    return ['ok' => true];
}
