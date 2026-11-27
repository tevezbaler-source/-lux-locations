<?php
// Acente portalı: acente kullanıcısı yalnız kendi gruplarını (satış fiyatıyla), carisini ve taleplerini görür.
// Alış fiyatı, kâr, iç notlar ve diğer acentelerin bilgileri hiçbir zaman dönmez.
if (!defined('LX_PANEL')) { http_response_code(403); exit; }

const PORTAL_TALEP_DURUM = ['new' => 'Yeni', 'review' => 'İnceleniyor', 'quoted' => 'Teklif verildi', 'confirmed' => 'Onaylandı', 'closed' => 'Kapandı'];

function portal_agency(): array {
    $u = current_user();
    if (($u['role'] ?? '') !== 'acente' || empty($u['agency_id'])) throw new ApiError('Bu hesap bir acenteye bağlı değil. Lux Locations ile iletişime geçin.', 403);
    $a = q_one('SELECT id, name, country, active FROM agencies WHERE id = ?', [(int)$u['agency_id']], db_main());
    if (!$a || !(int)$a['active']) throw new ApiError('Acente hesabı kapalı. Lux Locations ile iletişime geçin.', 403);
    return $a;
}

function portal_ozet(): array {
    $a = portal_agency();
    $pdo = db_main();
    $list = [];
    foreach (q_all('SELECT id FROM bookings WHERE agency_id = ? AND deleted = 0 ORDER BY check_in DESC LIMIT 300', [$a['id']], $pdo) as $r) {
        $b = booking_get((int)$r['id']);
        $c = calc_booking($b);
        $srv = [];
        foreach ($c['services'] as $cur => $v) if ($v['sell']) $srv[$cur] = round($v['sell'], 2);
        $h = q_one('SELECT name, stars, region FROM hotels WHERE id = ?', [$b['hotel_id']], $pdo);
        $list[] = ['id' => (int)$b['id'], 'code' => $b['code'], 'hotel' => $h['name'] ?? '', 'stars' => $h['stars'] ?? null, 'region' => $h['region'] ?? null,
            'check_in' => $b['check_in'], 'check_out' => $b['check_out'], 'nights' => $c['nights'], 'rooms' => $c['rooms'], 'status' => $b['status'],
            'option_until' => in_array($b['status'], ['teklif', 'opsiyon'], true) ? $b['option_until'] : null,
            'sell_net' => $c['sell_net'], 'sell_cur' => $c['sell_cur'], 'services' => $srv ?: new stdClass(), 'voucher' => in_array($b['status'], LOCKED_STATUSES, true)];
    }
    // Cari: tahsilat notları iç bilgi olabilir; yalnız ödeme şekli gösterilir
    $cari = cari_agency((int)$a['id']);
    foreach ($cari['rows'] as &$row) { if ($row['type'] === 'tahsilat') { $row['text'] = PAY_METHODS[q_val('SELECT method FROM payments WHERE id = ?', [$row['id']], $pdo)] ?? 'Ödeme'; unset($row['id']); } }
    unset($row, $cari['methods']);
    $req = q_all('SELECT id, hotel_pref, check_in, check_out, rooms, board, pax, message, status, booking_id, created_at FROM portal_requests WHERE agency_id = ? ORDER BY id DESC LIMIT 100', [$a['id']], $pdo);
    foreach ($req as &$q) { $q['booking_code'] = $q['booking_id'] ? q_val('SELECT code FROM bookings WHERE id = ?', [$q['booking_id']], $pdo) : null; }
    unset($q);
    return ['agency' => ['name' => $a['name'], 'country' => $a['country']], 'bookings' => $list, 'cari' => $cari, 'requests' => $req,
        'statuses' => STATUSES, 'req_statuses' => PORTAL_TALEP_DURUM];
}

function portal_grup(int $id): array {
    $a = portal_agency();
    $pdo = db_main();
    $own = q_one('SELECT id FROM bookings WHERE id = ? AND agency_id = ? AND deleted = 0', [$id, $a['id']], $pdo);
    if (!$own) throw new ApiError('Grup bulunamadı.', 404);
    $b = booking_get($id);
    $c = calc_booking($b);
    $h = q_one('SELECT name, stars, region, address FROM hotels WHERE id = ?', [$b['hotel_id']], $pdo);
    $extra = booking_extra_get($id);
    return [
        'id' => (int)$b['id'], 'code' => $b['code'], 'status' => $b['status'], 'hotel' => $h, 'check_in' => $b['check_in'], 'check_out' => $b['check_out'],
        'nights' => $c['nights'], 'board' => $b['board'], 'option_until' => in_array($b['status'], ['teklif', 'opsiyon'], true) ? $b['option_until'] : null,
        'hotel_conf_code' => in_array($b['status'], LOCKED_STATUSES, true) ? $b['hotel_conf_code'] : null,
        'sell_mode' => $b['sell_mode'], 'sell_cur' => $c['sell_cur'],
        'rooms' => array_map(fn($r, $l) => ['room_type' => $r['room_type'], 'qty' => (int)$r['qty'], 'price' => $r['sell_price'], 'night' => $l['sell_night'] ?? null, 'total' => $l['sell_total'] ?? null], $b['rooms'], array_pad($c['lines'], count($b['rooms']), [])),
        'free_out' => array_values(array_map(fn($f) => ['room_type' => $f['room_type'], 'qty' => (int)$f['qty']], array_filter($b['free'], fn($f) => $f['direction'] === 'out'))),
        'free_out_amount' => $c['free_out'], 'sell_net' => $c['sell_net'],
        'services' => array_map(fn($s) => ['kind' => $s['kind'], 'description' => $s['description'], 'sell' => $s['sell'], 'currency' => $s['currency'],
            'date' => $s['service_date'], 'time' => $s['service_time'], 'pickup' => $s['pickup'], 'dropoff' => $s['dropoff']], $b['services']),
        'guests' => array_map(fn($g) => ['room_no' => $g['room_no'], 'room_type' => $g['room_type'], 'name' => $g['name'], 'pax' => $g['pax']], guests_get($id)),
        'arrival_info' => $extra['arrival_info'] ?? null, 'departure_info' => $extra['departure_info'] ?? null,
        'voucher' => in_array($b['status'], LOCKED_STATUSES, true),
    ];
}

function portal_talep(array $in): array {
    $a = portal_agency();
    $u = current_user();
    $ci = clean_date($in['check_in'] ?? ''); $co = clean_date($in['check_out'] ?? '');
    if (!$ci || !$co || $co <= $ci) throw new ApiError('Giriş ve çıkış tarihlerini girin; çıkış girişten sonra olmalı.');
    if ($ci < date('Y-m-d')) throw new ApiError('Giriş tarihi geçmişte olamaz.');
    $rooms = clean_str($in['rooms'] ?? null, 255);
    if (!$rooms) throw new ApiError('Kaç oda istediğinizi yazın (ör. 20 DBL, 5 TRPL).');
    $board = in_array($in['board'] ?? '', ['BB', 'HB', 'FB', 'RO'], true) ? $in['board'] : 'BB';
    $pax = clean_int($in['pax'] ?? null);
    // Kötüye kullanıma karşı: bir acenteden günde en fazla 30 talep
    $n = (int)q_val('SELECT COUNT(*) FROM portal_requests WHERE agency_id = ? AND created_at >= ?', [$a['id'], date('Y-m-d 00:00:00')], db_main());
    if ($n >= 30) throw new ApiError('Bugün için talep sınırına ulaşıldı. Lütfen bizimle e-postayla iletişime geçin.', 429);
    $data = ['agency_id' => (int)$a['id'], 'user_id' => (int)$u['id'], 'user_name' => $u['name'], 'hotel_pref' => clean_str($in['hotel_pref'] ?? null, 255),
        'check_in' => $ci, 'check_out' => $co, 'rooms' => $rooms, 'board' => $board, 'pax' => $pax && $pax > 0 ? $pax : null,
        'message' => clean_str($in['message'] ?? null, 4000), 'status' => 'new'];
    $id = insert_row('portal_requests', $data + stamp_new());
    audit('portal', $id, 'talep', "Acente portalından talep: {$a['name']} $ci / $co · $rooms", null, $data, db_main());
    // Talep bildirim adreslerine haber ver (ayarlıysa); gönderilemezse talep yine kayıtlıdır
    try {
        $to = function_exists('talep_notify_get') ? (talep_notify_get()['recipients'] ?? []) : [];
        if ($to && mail_settings_public()['ready']) {
            $txt = "Acente portalından yeni talep\n\nAcente: {$a['name']}\nKişi: {$u['name']}\nTarih: $ci / $co\nOda: $rooms\nPansiyon: $board" . ($data['pax'] ? "\nKişi sayısı: {$data['pax']}" : '') . ($data['hotel_pref'] ? "\nOtel tercihi: {$data['hotel_pref']}" : '') . ($data['message'] ? "\n\n{$data['message']}" : '') . "\n\nPanel: Talepler sayfası";
            mail_send($to, [], 'Acente portalı: ' . $a['name'] . " $ci", '<pre style="font-family:Arial,sans-serif;white-space:pre-wrap">' . htmlspecialchars($txt) . '</pre>', $txt);
        }
    } catch (Throwable $e) { error_log('portal talep bildirimi: ' . $e->getMessage()); }
    return ['ok' => true, 'id' => $id];
}

/* ---------------- Personel tarafı: portal talepleri ---------------- */

function portal_talepler_liste(): array {
    need('talep');
    $rows = q_all('SELECT p.*, a.name AS agency, b.code AS booking_code FROM portal_requests p LEFT JOIN agencies a ON a.id = p.agency_id LEFT JOIN bookings b ON b.id = p.booking_id ORDER BY p.id DESC LIMIT 200', [], db_main());
    return ['rows' => $rows, 'statuses' => PORTAL_TALEP_DURUM];
}

function portal_talep_guncelle(array $in): array {
    need('talep');
    if (!empty($_SESSION['egitim'])) throw new ApiError('Eğitim bölümündesiniz; acente talepleri gerçek panelden güncellenir.');
    $id = clean_int($in['id'] ?? null);
    $old = $id ? q_one('SELECT * FROM portal_requests WHERE id = ?', [$id], db_main()) : null;
    if (!$old) throw new ApiError('Talep bulunamadı.', 404);
    $d = [];
    if (isset($in['status'])) { if (!isset(PORTAL_TALEP_DURUM[$in['status']])) throw new ApiError('Geçersiz durum.'); $d['status'] = $in['status']; }
    if (array_key_exists('staff_note', $in)) $d['staff_note'] = clean_str($in['staff_note'], 2000);
    if (array_key_exists('booking_id', $in)) {
        $bid = clean_int($in['booking_id']);
        if ($bid && !q_one('SELECT id FROM bookings WHERE id = ? AND agency_id = ?', [$bid, $old['agency_id']], db_main())) throw new ApiError('Grup bu acenteye ait değil.');
        $d['booking_id'] = $bid;
    }
    if (!$d) return ['ok' => true];
    $u = current_user();
    q('UPDATE portal_requests SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($d))) . ', updated_by = ?, updated_at = ?, version = version + 1 WHERE id = ?', [...array_values($d), $u['id'], now(), $id], db_main());
    audit('portal', $id, 'guncelle', 'Acente talebi güncellendi' . (isset($d['status']) ? ': ' . PORTAL_TALEP_DURUM[$d['status']] : ''), array_intersect_key($old, $d), $d, db_main());
    return ['ok' => true];
}
