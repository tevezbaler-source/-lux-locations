<?php
// Kayıt okuma/yazma: gruplar, oteller, acenteler, kurlar ve özet hesaplar.
if (!defined('LX_PANEL')) { http_response_code(403); exit; }

class ApiError extends Exception {
    public int $status; public array $extra;
    public function __construct(string $msg, int $status = 400, array $extra = []) { parent::__construct($msg); $this->status = $status; $this->extra = $extra; }
}

function need(string $perm): void {
    if (!can($perm)) throw new ApiError('Bu işlem için yetkiniz yok.', 403);
}

function clean_date($v): ?string {
    $v = trim((string)$v);
    if ($v === '') return null;
    $d = DateTime::createFromFormat('!Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) throw new ApiError("Geçersiz tarih: $v");
    return $v;
}
function clean_str($v, int $max = 255): ?string { $v = trim((string)($v ?? '')); return $v === '' ? null : mb_substr($v, 0, $max); }
function clean_int($v): ?int { if ($v === null || $v === '') return null; if (!is_numeric($v)) throw new ApiError("Sayı bekleniyor: $v"); return (int)$v; }
function clean_dec($v): ?float { $n = num($v); if ($v !== null && $v !== '' && $n === null) throw new ApiError("Sayı bekleniyor: $v"); return $n; }

function stamp_new(): array { $u = current_user(); return ['created_by' => $u['id'], 'created_at' => now(), 'updated_by' => $u['id'], 'updated_at' => now(), 'version' => 1]; }

function insert_row(string $table, array $data): int {
    $cols = array_keys($data);
    q("INSERT INTO $table (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($data));
    return (int)db()->lastInsertId();
}

/** Sürüm kontrollü güncelleme: başkası arada kaydettiyse 409 döner. */
function update_row(string $table, int $id, array $data, ?int $version): void {
    $u = current_user();
    $data['updated_by'] = $u['id']; $data['updated_at'] = now();
    $set = implode(',', array_map(fn($c) => "$c = ?", array_keys($data)));
    $params = array_values($data);
    $sql = "UPDATE $table SET $set, version = version + 1 WHERE id = ?";
    $params[] = $id;
    if ($version !== null) { $sql .= ' AND version = ?'; $params[] = $version; }
    $n = q($sql, $params)->rowCount();
    if ($n === 0) {
        $cur = q_one("SELECT t.version, t.updated_at, u.user_name FROM $table t LEFT JOIN (SELECT entity_id, user_name FROM audit_log WHERE entity = ? AND entity_id = ? ORDER BY id DESC LIMIT 1) u ON u.entity_id = t.id WHERE t.id = ?", [audit_entity($table), $id, $id]);
        if (!$cur) throw new ApiError('Kayıt bulunamadı.', 404);
        throw new ApiError('Bu kayıt siz düzenlerken ' . ($cur['user_name'] ?: 'başka biri') . ' tarafından değiştirildi (' . $cur['updated_at'] . '). Sayfayı yenileyip değişiklikleri tekrar uygulayın.', 409);
    }
}

function audit_entity(string $table): string {
    return ['bookings' => 'grup', 'hotels' => 'otel', 'agencies' => 'acente', 'hotel_rates' => 'sozlesme', 'hotel_allotments' => 'kontenjan', 'fx_rates' => 'kur', 'users' => 'user'][$table] ?? $table;
}

/* ---------------- Oteller ---------------- */

function hotel_get(int $id): array {
    $h = q_one('SELECT * FROM hotels WHERE id = ?', [$id]);
    if (!$h) throw new ApiError('Otel bulunamadı.', 404);
    return $h;
}

function hotel_save(array $in): array {
    need('hotel');
    $name = clean_str($in['name'] ?? '', 160);
    if (!$name) throw new ApiError('Otel adı gerekli.');
    $data = [
        'name' => $name, 'stars' => clean_int($in['stars'] ?? null), 'region' => clean_str($in['region'] ?? null, 60),
        'address' => clean_str($in['address'] ?? null), 'contacts' => clean_str($in['contacts'] ?? null, 4000), 'notes' => clean_str($in['notes'] ?? null, 4000),
        'email' => implode(', ', mail_addr_list((string)($in['email'] ?? ''), 'Otel e-postası')) ?: null,
        'active' => isset($in['active']) ? (int)!!$in['active'] : 1,
    ];
    $id = clean_int($in['id'] ?? null);
    if ($id) {
        $old = hotel_get($id);
        update_row('hotels', $id, $data, clean_int($in['version'] ?? null));
        audit('otel', $id, 'guncelle', "Otel güncellendi: $name", $old, $data);
    } else {
        $dup = q_one('SELECT id FROM hotels WHERE LOWER(name) = LOWER(?)', [$name]);
        if ($dup) throw new ApiError("\"$name\" adında bir otel zaten var.");
        // Sitedeki bir otel seçildiyse siteye bağla ve kapak görselini al
        $slug = clean_str($in['site_slug'] ?? null, 160);
        if ($slug && function_exists('site_hotels_list')) {
            foreach (site_hotels_list() as $s) if ($s['slug'] === $slug) {
                if (!q_one('SELECT id FROM hotels WHERE site_slug = ?', [$slug])) { $data['site_slug'] = $slug; $data['cover'] = $s['cover']; }
                break;
            }
        }
        $id = insert_row('hotels', $data + stamp_new());
        audit('otel', $id, 'ekle', "Otel eklendi: $name", null, $data);
    }
    return hotel_get($id);
}

function rate_save(array $in): array {
    need('hotel');
    $hotelId = clean_int($in['hotel_id'] ?? null);
    hotel_get((int)$hotelId);
    $from = clean_date($in['date_from'] ?? ''); $to = clean_date($in['date_to'] ?? '');
    if (!$from || !$to || $to < $from) throw new ApiError('Sözleşme dönemi tarihleri hatalı.');
    $cur = $in['currency'] ?? '';
    if (!in_array($cur, CURRENCIES, true)) throw new ApiError('Döviz seçin.');
    $data = [
        'hotel_id' => $hotelId, 'date_from' => $from, 'date_to' => $to, 'currency' => $cur, 'board' => clean_str($in['board'] ?? 'BB', 4),
        'sngl' => clean_dec($in['sngl'] ?? null), 'dbl' => clean_dec($in['dbl'] ?? null), 'twin' => clean_dec($in['twin'] ?? null),
        'trpl' => clean_dec($in['trpl'] ?? null), 'quad' => clean_dec($in['quad'] ?? null),
        'invoiced' => isset($in['invoiced']) ? (int)!!$in['invoiced'] : 1,
        'free_every' => clean_int($in['free_every'] ?? null), 'free_type' => clean_str($in['free_type'] ?? null, 5),
        'contact' => clean_str($in['contact'] ?? null, 120), 'note' => clean_str($in['note'] ?? null, 2000),
    ];
    if ($data['dbl'] === null && $data['sngl'] === null) throw new ApiError('En az DBL fiyatı girin.');
    $id = clean_int($in['id'] ?? null);
    $label = "$from / $to " . ($data['dbl'] ?? '') . " $cur";
    if ($id) {
        $old = q_one('SELECT * FROM hotel_rates WHERE id = ?', [$id]);
        update_row('hotel_rates', $id, $data, clean_int($in['version'] ?? null));
        audit('sozlesme', $id, 'guncelle', "Sözleşme fiyatı güncellendi: $label", $old, $data);
    } else {
        $id = insert_row('hotel_rates', $data + stamp_new());
        audit('sozlesme', $id, 'ekle', "Sözleşme fiyatı eklendi: $label", null, $data);
    }
    return q_one('SELECT * FROM hotel_rates WHERE id = ?', [$id]);
}

function rate_delete(int $id): void {
    need('hotel');
    $old = q_one('SELECT * FROM hotel_rates WHERE id = ?', [$id]);
    if (!$old) throw new ApiError('Kayıt bulunamadı.', 404);
    q('DELETE FROM hotel_rates WHERE id = ?', [$id]);
    audit('sozlesme', $id, 'sil', "Sözleşme fiyatı silindi: {$old['date_from']} / {$old['date_to']}", $old, null);
}

function allotment_save(array $in): array {
    need('hotel');
    $hotelId = clean_int($in['hotel_id'] ?? null);
    hotel_get((int)$hotelId);
    $from = clean_date($in['date_from'] ?? ''); $to = clean_date($in['date_to'] ?? '');
    if (!$from || !$to || $to < $from) throw new ApiError('Kontenjan tarihleri hatalı.');
    $rooms = clean_int($in['rooms'] ?? null);
    if ($rooms === null || $rooms < 0) throw new ApiError('Oda sayısı girin.');
    $data = ['hotel_id' => $hotelId, 'date_from' => $from, 'date_to' => $to, 'rooms' => $rooms, 'note' => clean_str($in['note'] ?? null, 2000)];
    $id = clean_int($in['id'] ?? null);
    if ($id) {
        $old = q_one('SELECT * FROM hotel_allotments WHERE id = ?', [$id]);
        update_row('hotel_allotments', $id, $data, clean_int($in['version'] ?? null));
        audit('kontenjan', $id, 'guncelle', "Kontenjan güncellendi: $from / $to $rooms oda", $old, $data);
    } else {
        $id = insert_row('hotel_allotments', $data + stamp_new());
        audit('kontenjan', $id, 'ekle', "Kontenjan eklendi: $from / $to $rooms oda", null, $data);
    }
    return q_one('SELECT * FROM hotel_allotments WHERE id = ?', [$id]);
}

function allotment_delete(int $id): void {
    need('hotel');
    $old = q_one('SELECT * FROM hotel_allotments WHERE id = ?', [$id]);
    if (!$old) throw new ApiError('Kayıt bulunamadı.', 404);
    q('DELETE FROM hotel_allotments WHERE id = ?', [$id]);
    audit('kontenjan', $id, 'sil', "Kontenjan silindi: {$old['date_from']} / {$old['date_to']}", $old, null);
}

/* ---------------- Acenteler ---------------- */

function agency_save(array $in): array {
    need('agency');
    $name = clean_str($in['name'] ?? '', 160);
    if (!$name) throw new ApiError('Acente adı gerekli.');
    $data = [
        'name' => $name, 'country' => clean_str($in['country'] ?? null, 60), 'aliases' => clean_str($in['aliases'] ?? null),
        'owner' => clean_str($in['owner'] ?? null, 120), 'contact' => clean_str($in['contact'] ?? null, 4000),
        'payment_terms' => clean_str($in['payment_terms'] ?? null, 4000), 'notes' => clean_str($in['notes'] ?? null, 4000),
        'invoice_info' => clean_str($in['invoice_info'] ?? null, 4000),
        'active' => isset($in['active']) ? (int)!!$in['active'] : 1,
    ];
    $id = clean_int($in['id'] ?? null);
    if ($id) {
        $old = q_one('SELECT * FROM agencies WHERE id = ?', [$id]);
        if (!$old) throw new ApiError('Acente bulunamadı.', 404);
        update_row('agencies', $id, $data, clean_int($in['version'] ?? null));
        audit('acente', $id, 'guncelle', "Acente güncellendi: $name", $old, $data);
    } else {
        $dup = agency_find_by_name($name);
        if ($dup) throw new ApiError("\"$name\" zaten var: {$dup['name']}" . ($dup['name'] !== $name ? ' (farklı yazımı)' : '') . '.');
        $id = insert_row('agencies', $data + stamp_new());
        audit('acente', $id, 'ekle', "Acente eklendi: $name", null, $data);
    }
    return q_one('SELECT * FROM agencies WHERE id = ?', [$id]);
}

function norm_name(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['ı' => 'i', 'İ' => 'i', 'ş' => 's', 'ğ' => 'g', 'ü' => 'u', 'ö' => 'o', 'ç' => 'c', 'â' => 'a']);
    return preg_replace('/[^a-z0-9]+/', '', $s);
}

function agency_find_by_name(string $name): ?array {
    $n = norm_name($name);
    foreach (q_all('SELECT id, name, aliases FROM agencies') as $a) {
        $names = array_merge([$a['name']], array_map('trim', explode(',', (string)$a['aliases'])));
        foreach ($names as $x) if ($x !== '' && norm_name($x) === $n) return $a;
    }
    return null;
}

function agencies_list(): array {
    $rows = q_all('SELECT * FROM agencies');
    usort($rows, fn($a, $b) => [(int)$b['active'], norm_name($a['name'])] <=> [(int)$a['active'], norm_name($b['name'])]);
    $stats = q_all("SELECT agency_id, status, COUNT(*) AS n FROM bookings WHERE deleted = 0 GROUP BY agency_id, status");
    $by = [];
    foreach ($stats as $s) { $by[$s['agency_id']][$s['status']] = (int)$s['n']; }
    $sales = [];
    $bookIds = q_all("SELECT id, agency_id FROM bookings WHERE deleted = 0 AND status IN ('konfirme','konaklamada','kapandi')");
    foreach ($bookIds as $b) {
        $c = calc_booking(booking_get((int)$b['id']));
        $cur = $c['sell_cur'];
        $sales[$b['agency_id']][$cur] = ($sales[$b['agency_id']][$cur] ?? 0) + $c['sell_net'];
        foreach ($c['services'] as $sc => $v) if ($v['sell']) $sales[$b['agency_id']][$sc] = ($sales[$b['agency_id']][$sc] ?? 0) + $v['sell'];
    }
    $paid = [];
    foreach (q_all('SELECT agency_id, currency, SUM(amount) AS t FROM payments WHERE deleted = 0 GROUP BY agency_id, currency') as $p) $paid[$p['agency_id']][$p['currency']] = (float)$p['t'];
    foreach ($rows as &$r) {
        $st = $by[$r['id']] ?? [];
        $r['bookings'] = array_sum($st);
        $r['cancelled'] = $st['iptal'] ?? 0;
        $r['sales'] = $sales[$r['id']] ?? new stdClass();
        $bal = [];
        foreach ($sales[$r['id']] ?? [] as $cur => $v) $bal[$cur] = $v;
        foreach ($paid[$r['id']] ?? [] as $cur => $v) $bal[$cur] = ($bal[$cur] ?? 0) - $v;
        $r['balance'] = array_filter(array_map(fn($v) => round($v, 2), $bal), fn($v) => abs($v) >= 0.01) ?: new stdClass();
    }
    return $rows;
}

/* ---------------- Kurlar ---------------- */

function fx_save(array $in): array {
    need('rate');
    $d = clean_date($in['rate_date'] ?? '') ?: date('Y-m-d');
    $eur = clean_dec($in['eur_try'] ?? null); $usd = clean_dec($in['usd_try'] ?? null);
    if (!$eur || !$usd || $eur <= 0 || $usd <= 0) throw new ApiError('EUR/TRY ve USD/TRY kurlarını girin.');
    $data = ['rate_date' => $d, 'eur_try' => $eur, 'usd_try' => $usd, 'note' => clean_str($in['note'] ?? null)];
    $id = insert_row('fx_rates', $data + stamp_new());
    audit('kur', $id, 'ekle', "Kur girildi: $d EUR $eur / USD $usd TL", null, $data);
    return q_one('SELECT * FROM fx_rates WHERE id = ?', [$id]);
}

/* ---------------- Misafir listesi ve belge bilgileri ---------------- */

const EXTRA_FIELDS = ['contact_name', 'room_category', 'nationality', 'arrival_info', 'departure_info', 'hotel_note', 'voucher_note'];

function guests_get(int $bookingId): array {
    return q_all('SELECT room_no, room_type, name, pax, note FROM booking_guests WHERE booking_id = ? ORDER BY room_no, id', [$bookingId]);
}

function booking_extra_get(int $bookingId): array {
    $e = q_one('SELECT ' . implode(', ', EXTRA_FIELDS) . ' FROM booking_extra WHERE booking_id = ?', [$bookingId]);
    return $e ?: array_fill_keys(EXTRA_FIELDS, null);
}

/** Misafir listesi ve belge notlarını kaydeder. Fiyat kilidinden bağımsızdır; konfirme grupta da düzenlenir. */
function guests_save(array $in): array {
    need('booking');
    $id = clean_int($in['booking_id'] ?? null);
    $b = $id ? q_one('SELECT id, code, hotel_id, check_in, deleted FROM bookings WHERE id = ?', [$id]) : null;
    if (!$b || (int)$b['deleted']) throw new ApiError('Grup bulunamadı.', 404);
    $guests = [];
    foreach ((array)($in['guests'] ?? []) as $g) {
        $name = clean_str($g['name'] ?? null, 160);
        if (!$name) continue;
        $type = strtoupper((string)($g['room_type'] ?? ''));
        $guests[] = [
            'room_no' => max(1, min(999, (int)($g['room_no'] ?? 1))),
            'room_type' => in_array($type, ROOM_TYPES, true) ? $type : null,
            'name' => $name,
            'pax' => in_array($g['pax'] ?? 'ADL', ['ADL', 'CHD', 'INF'], true) ? $g['pax'] : 'ADL',
            'note' => clean_str($g['note'] ?? null, 255),
        ];
    }
    if (count($guests) > 1000) throw new ApiError('Misafir listesi çok uzun.');
    $extra = [];
    foreach (EXTRA_FIELDS as $f) $extra[$f] = clean_str($in['extra'][$f] ?? null, in_array($f, ['hotel_note', 'voucher_note'], true) ? 2000 : 255);
    $oldG = guests_get($id); $oldE = booking_extra_get($id);
    $u = current_user();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('DELETE FROM booking_guests WHERE booking_id = ?', [$id]);
        foreach ($guests as $g) insert_row('booking_guests', ['booking_id' => $id] + $g);
        q('DELETE FROM booking_extra WHERE booking_id = ?', [$id]);
        insert_row('booking_extra', ['booking_id' => $id] + $extra + ['updated_by' => $u['id'], 'updated_at' => now()]);
        audit('grup', $id, 'misafir', 'Misafir listesi / belge bilgisi güncellendi' . ($b['code'] ? " ({$b['code']})" : '') . ': ' . count($guests) . ' kişi',
            ['guests' => $oldG, 'extra' => $oldE], ['guests' => $guests, 'extra' => $extra]);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    return ['guests' => guests_get($id), 'extra' => booking_extra_get($id)];
}

/* ---------------- Gruplar ---------------- */

function booking_get(int $id): array {
    $b = q_one('SELECT * FROM bookings WHERE id = ?', [$id]);
    if (!$b) throw new ApiError('Grup bulunamadı.', 404);
    $b['rooms'] = q_all('SELECT room_type, qty, buy_price, sell_price FROM booking_rooms WHERE booking_id = ? ORDER BY id', [$id]);
    $b['free'] = q_all('SELECT direction, room_type, qty FROM booking_free WHERE booking_id = ? ORDER BY id', [$id]);
    $b['services'] = q_all('SELECT kind, description, buy, sell, currency FROM booking_services WHERE booking_id = ? ORDER BY id', [$id]);
    return $b;
}

function booking_full(int $id): array {
    $b = booking_get($id);
    $b['hotel_name'] = q_val('SELECT name FROM hotels WHERE id = ?', [$b['hotel_id']]);
    $b['hotel_email'] = q_val('SELECT email FROM hotels WHERE id = ?', [$b['hotel_id']]);
    $b['agency_name'] = $b['agency_id'] ? q_val('SELECT name FROM agencies WHERE id = ?', [$b['agency_id']]) : null;
    $b['calc'] = calc_booking($b);
    $b['history'] = q_all("SELECT id, at, user_name, ip, action, summary, old_json, new_json FROM audit_log WHERE entity = 'grup' AND entity_id = ? ORDER BY id DESC LIMIT 100", [$id]);
    foreach ($b['history'] as &$h) {
        $h['changes'] = audit_diff($h['old_json'], $h['new_json']);
        unset($h['old_json'], $h['new_json']);
    }
    $b['guests'] = guests_get($id);
    $b['extra'] = booking_extra_get($id);
    $b['mail_log'] = mail_log_get($id);
    $b['mail_ready'] = function_exists('mail_settings_public') ? mail_settings_public()['ready'] : false;
    if ($b['prev_booking_id']) $b['prev'] = q_one('SELECT id, code, agency_id, (SELECT name FROM agencies WHERE id = bookings.agency_id) AS agency_name FROM bookings WHERE id = ?', [$b['prev_booking_id']]);
    $b['next'] = q_one('SELECT id, code, (SELECT name FROM agencies WHERE id = bookings.agency_id) AS agency_name FROM bookings WHERE prev_booking_id = ? AND deleted = 0', [$id]);
    $b['created_by_name'] = $b['created_by'] ? q_val('SELECT name FROM users WHERE id = ?', [$b['created_by']], db_main()) : null;
    $b['updated_by_name'] = $b['updated_by'] ? q_val('SELECT name FROM users WHERE id = ?', [$b['updated_by']], db_main()) : null;
    return $b;
}

/** İki anlık görüntü arasındaki farkları okunur satırlara çevirir. */
function audit_diff(?string $old, ?string $new): array {
    if (!$old || !$new) return [];
    $a = json_decode($old, true) ?: []; $b = json_decode($new, true) ?: [];
    $labels = ['code' => 'Kod', 'hotel_id' => 'Otel', 'agency_id' => 'Acente', 'check_in' => 'Giriş', 'check_out' => 'Çıkış', 'status' => 'Durum',
        'rooms_reserved' => 'Ayrılan oda', 'rooms_option' => 'Opsiyon oda', 'option_until' => 'Opsiyon bitiş', 'hotel_conf_code' => 'Otel konfirme kodu',
        'buy_cur' => 'Alış dövizi', 'sell_cur' => 'Satış dövizi', 'sell_mode' => 'Satış şekli', 'eur_try' => 'EUR kuru', 'usd_try' => 'USD kuru',
        'buy_note' => 'Alış notu', 'notes' => 'Notlar', 'board' => 'Pansiyon', 'cancel_reason' => 'İptal nedeni',
        'rooms' => 'Odalar', 'free' => 'Free', 'services' => 'Ek hizmetler',
        'name' => 'Ad', 'stars' => 'Yıldız', 'region' => 'Bölge', 'address' => 'Adres', 'contacts' => 'İletişim', 'active' => 'Aktif'];
    $out = [];
    foreach ($b as $k => $v) {
        if (in_array($k, ['version', 'updated_at', 'updated_by', 'created_at', 'created_by', 'id'], true)) continue;
        $ov = $a[$k] ?? null;
        $norm = fn($x) => is_array($x) ? json_encode($x, JSON_UNESCAPED_UNICODE) : (is_numeric($x) ? (string)(float)$x : (string)$x);
        if ($norm($ov) === $norm($v)) continue;
        $fmt = function ($x) use ($k) {
            if ($x === null || $x === '') return '—';
            if ($k === 'rooms' && is_array($x)) return implode(', ', array_map(fn($r) => "{$r['qty']} {$r['room_type']} " . ($r['buy_price'] ?? '?') . '/' . ($r['sell_price'] ?? '?'), $x)) ?: '—';
            if ($k === 'free' && is_array($x)) return implode(', ', array_map(fn($r) => ($r['direction'] === 'in' ? 'al ' : 'ver ') . "{$r['qty']} {$r['room_type']}", $x)) ?: '—';
            if ($k === 'services' && is_array($x)) return implode(', ', array_map(fn($r) => "{$r['kind']} " . ($r['buy'] ?? '') . '/' . ($r['sell'] ?? '') . " {$r['currency']}", $x)) ?: '—';
            if ($k === 'status') return STATUSES[$x] ?? $x;
            if ($k === 'hotel_id') return (string)(q_val('SELECT name FROM hotels WHERE id = ?', [$x]) ?? $x);
            if ($k === 'agency_id') return (string)(q_val('SELECT name FROM agencies WHERE id = ?', [$x]) ?? $x);
            return is_array($x) ? json_encode($x, JSON_UNESCAPED_UNICODE) : (string)$x;
        };
        $out[] = ['field' => $labels[$k] ?? $k, 'from' => $fmt($ov), 'to' => $fmt($v)];
    }
    return $out;
}

function next_code(): string {
    $max = 0;
    foreach (q_all("SELECT code FROM bookings WHERE code IS NOT NULL") as $r) {
        if (preg_match('/(\d+)/', $r['code'], $m)) $max = max($max, (int)$m[1]);
    }
    return 'LUX' . ($max + 1);
}

function booking_input(array $in): array {
    $b = [
        'hotel_id' => clean_int($in['hotel_id'] ?? null),
        'agency_id' => clean_int($in['agency_id'] ?? null),
        'code' => clean_str($in['code'] ?? null, 30),
        'check_in' => clean_date($in['check_in'] ?? ''),
        'check_out' => clean_date($in['check_out'] ?? ''),
        'board' => clean_str($in['board'] ?? 'BB', 4),
        'status' => $in['status'] ?? 'teklif',
        'rooms_reserved' => clean_int($in['rooms_reserved'] ?? null),
        'rooms_option' => clean_int($in['rooms_option'] ?? null),
        'option_until' => clean_date($in['option_until'] ?? ''),
        'hotel_conf_code' => clean_str($in['hotel_conf_code'] ?? null, 60),
        'buy_cur' => $in['buy_cur'] ?? '',
        'sell_cur' => $in['sell_cur'] ?? '',
        'sell_mode' => ($in['sell_mode'] ?? 'room') === 'pp' ? 'pp' : 'room',
        'eur_try' => clean_dec($in['eur_try'] ?? null),
        'usd_try' => clean_dec($in['usd_try'] ?? null),
        'buy_note' => clean_str($in['buy_note'] ?? null, 2000),
        'notes' => clean_str($in['notes'] ?? null, 8000),
    ];
    if (!isset(STATUSES[$b['status']])) throw new ApiError('Geçersiz durum.');
    $b['rooms'] = [];
    foreach (($in['rooms'] ?? []) as $r) {
        $qty = clean_int($r['qty'] ?? null);
        if (!$qty) continue;
        if (!in_array($r['room_type'] ?? '', ROOM_TYPES, true)) throw new ApiError('Oda tipi hatalı.');
        $b['rooms'][] = ['room_type' => $r['room_type'], 'qty' => $qty, 'buy_price' => clean_dec($r['buy_price'] ?? null), 'sell_price' => clean_dec($r['sell_price'] ?? null)];
    }
    $b['free'] = [];
    foreach (($in['free'] ?? []) as $f) {
        $qty = clean_int($f['qty'] ?? null);
        if (!$qty) continue;
        if (!in_array($f['room_type'] ?? '', ROOM_TYPES, true)) throw new ApiError('Free oda tipi hatalı.');
        $b['free'][] = ['direction' => ($f['direction'] ?? '') === 'out' ? 'out' : 'in', 'room_type' => $f['room_type'], 'qty' => $qty];
    }
    $b['services'] = [];
    foreach (($in['services'] ?? []) as $s) {
        $kind = clean_str($s['kind'] ?? null, 30);
        if (!$kind) continue;
        $cur = $s['currency'] ?? 'EUR';
        if (!in_array($cur, CURRENCIES, true)) throw new ApiError('Ek hizmet dövizi hatalı.');
        $b['services'][] = ['kind' => $kind, 'description' => clean_str($s['description'] ?? null), 'buy' => clean_dec($s['buy'] ?? null), 'sell' => clean_dec($s['sell'] ?? null), 'currency' => $cur];
    }
    return $b;
}

function price_signature(array $b): string {
    $r = array_map(fn($x) => [$x['room_type'], (int)$x['qty'], (float)$x['buy_price'], (float)$x['sell_price']], $b['rooms'] ?? []);
    $f = array_map(fn($x) => [$x['direction'], $x['room_type'], (int)$x['qty']], $b['free'] ?? []);
    $s = array_map(fn($x) => [$x['kind'], (float)$x['buy'], (float)$x['sell'], $x['currency']], $b['services'] ?? []);
    return json_encode([$r, $f, $s, $b['buy_cur'], $b['sell_cur'], $b['sell_mode'], (float)$b['eur_try'], (float)$b['usd_try']]);
}

function booking_snapshot(array $b): array {
    $keep = ['code', 'hotel_id', 'agency_id', 'check_in', 'check_out', 'board', 'status', 'rooms_reserved', 'rooms_option', 'option_until', 'hotel_conf_code',
        'buy_cur', 'sell_cur', 'sell_mode', 'eur_try', 'usd_try', 'buy_note', 'notes', 'cancel_reason', 'rooms', 'free', 'services'];
    $o = [];
    foreach ($keep as $k) $o[$k] = $b[$k] ?? null;
    return $o;
}

function booking_save(array $in): array {
    need('booking');
    $u = current_user();
    $b = booking_input($in);
    if (!$b['hotel_id']) throw new ApiError('Otel seçin.');
    hotel_get($b['hotel_id']);
    if (!$b['agency_id']) throw new ApiError('Acente seçin.');
    if (!q_one('SELECT id FROM agencies WHERE id = ?', [$b['agency_id']])) throw new ApiError('Acente bulunamadı.');
    $id = clean_int($in['id'] ?? null);
    $old = $id ? booking_get($id) : null;
    if ($old && (int)$old['deleted']) throw new ApiError('Bu grup silinmiş.', 410);

    if ($b['code']) {
        $dup = q_one('SELECT id FROM bookings WHERE code = ? AND deleted = 0 AND id <> ?', [$b['code'], $id ?: 0]);
        if ($dup) throw new ApiError("{$b['code']} kodu başka bir grupta kullanılıyor.");
    }

    $isAdmin = can('unlock');
    if ($old && in_array($old['status'], LOCKED_STATUSES, true) && !$isAdmin) {
        $ob = $old; foreach (['eur_try', 'usd_try'] as $k) $ob[$k] = $ob[$k] ?? null;
        if (price_signature($ob) !== price_signature($b)) {
            throw new ApiError('Konfirme edilmiş grubun fiyatları, odaları ve kurları kilitli. Değişiklik için Yönetici gerekir.', 403);
        }
    }
    if ($old && $old['status'] === 'iptal' && $b['status'] !== 'iptal' && !$isAdmin) throw new ApiError('İptal edilmiş grubu yalnız Yönetici geri açabilir.', 403);

    // Konfirmeye geçerken kuru sabitle
    if (in_array($b['status'], LOCKED_STATUSES, true) && (!$b['eur_try'] || !$b['usd_try'])) {
        $lr = latest_rate();
        if ($lr) { $b['eur_try'] = $b['eur_try'] ?: (float)$lr['eur_try']; $b['usd_try'] = $b['usd_try'] ?: (float)$lr['usd_try']; }
    }

    $calc = calc_booking($b);
    $codes = array_column(array_filter($calc['warnings'], fn($w) => $w['level'] === 'error'), 'code');
    $hard = array_intersect($codes, ['tarih', 'doviz', 'oda']);
    if ($hard) throw new ApiError(implode(' ', array_column(array_filter($calc['warnings'], fn($w) => in_array($w['code'], $hard, true)), 'msg')), 422, ['calc' => $calc]);
    $goingLocked = in_array($b['status'], LOCKED_STATUSES, true) && (!$old || !in_array($old['status'], LOCKED_STATUSES, true));
    if ($goingLocked) {
        $blocking = array_values(array_diff($codes, $isAdmin ? ['zarar'] : []));
        if ($blocking) {
            $msgs = array_column(array_filter($calc['warnings'], fn($w) => in_array($w['code'], $blocking, true) && $w['level'] === 'error'), 'msg');
            throw new ApiError('Konfirme edilemez: ' . implode(' ', $msgs), 422, ['calc' => $calc]);
        }
    }

    if (!$old && empty($in['force'])) {
        $dup = q_one("SELECT id, code FROM bookings WHERE hotel_id = ? AND agency_id = ? AND check_in = ? AND deleted = 0 AND status <> 'iptal'", [$b['hotel_id'], $b['agency_id'], $b['check_in']]);
        if ($dup) throw new ApiError('Bu otelde bu acenteye aynı giriş tarihli bir grup zaten var' . ($dup['code'] ? " ({$dup['code']})" : '') . '. Yine de yeni grup açılsın mı?', 409, ['duplicate' => (int)$dup['id']]);
    }

    $row = $b; unset($row['rooms'], $row['free'], $row['services']);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($old) {
            update_row('bookings', $id, $row, clean_int($in['version'] ?? null));
        } else {
            $id = insert_row('bookings', $row + stamp_new());
        }
        q('DELETE FROM booking_rooms WHERE booking_id = ?', [$id]);
        q('DELETE FROM booking_free WHERE booking_id = ?', [$id]);
        q('DELETE FROM booking_services WHERE booking_id = ?', [$id]);
        foreach ($b['rooms'] as $r) insert_row('booking_rooms', ['booking_id' => $id] + $r);
        foreach ($b['free'] as $f) insert_row('booking_free', ['booking_id' => $id] + $f);
        foreach ($b['services'] as $s) insert_row('booking_services', ['booking_id' => $id] + $s);
        $hotel = q_val('SELECT name FROM hotels WHERE id = ?', [$b['hotel_id']]);
        $agency = q_val('SELECT name FROM agencies WHERE id = ?', [$b['agency_id']]);
        $label = "$hotel / $agency {$b['check_in']}";
        if ($old) {
            $act = $old['status'] !== $b['status'] ? 'durum' : 'guncelle';
            $sum = $old['status'] !== $b['status'] ? "Durum: " . STATUSES[$old['status']] . ' → ' . STATUSES[$b['status']] . " ($label)" : "Grup güncellendi: $label";
            audit('grup', $id, $act, $sum, booking_snapshot($old), booking_snapshot($b));
        } else {
            audit('grup', $id, 'ekle', "Grup eklendi: $label", null, booking_snapshot($b));
        }
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    return booking_full($id);
}

function booking_cancel(int $id, string $reason, ?int $version): array {
    need('booking');
    $old = booking_get($id);
    if ($old['status'] === 'iptal') throw new ApiError('Grup zaten iptal.');
    $reason = trim($reason);
    if ($reason === '') throw new ApiError('İptal nedenini yazın (ön ödeme ne olacak?).');
    update_row('bookings', $id, ['status' => 'iptal', 'cancel_reason' => mb_substr($reason, 0, 2000)], $version);
    $new = $old; $new['status'] = 'iptal'; $new['cancel_reason'] = $reason;
    audit('grup', $id, 'iptal', 'Grup iptal edildi: ' . mb_substr($reason, 0, 150), booking_snapshot($old), booking_snapshot($new));
    return booking_full($id);
}

/** Aynı odaları başka acenteye devret: eski grup iptal olur, yeni grup açılır. */
function booking_transfer(int $id, int $agencyId, ?string $in, ?string $out, ?int $version): array {
    need('booking');
    $old = booking_get($id);
    if ($old['status'] === 'iptal') throw new ApiError('İptal edilmiş grup devredilemez.');
    $ag = q_one('SELECT id, name FROM agencies WHERE id = ?', [$agencyId]);
    if (!$ag) throw new ApiError('Acente bulunamadı.');
    if ($agencyId === (int)$old['agency_id']) throw new ApiError('Grup zaten bu acentede.');
    $oldAgency = q_val('SELECT name FROM agencies WHERE id = ?', [$old['agency_id']]);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $reason = "{$ag['name']} acentesine devredildi.";
        update_row('bookings', $id, ['status' => 'iptal', 'cancel_reason' => $reason], $version);
        $copy = $old;
        foreach (['id', 'version', 'created_by', 'created_at', 'updated_by', 'updated_at', 'rooms', 'free', 'services', 'cancel_reason'] as $k) unset($copy[$k]);
        $copy['agency_id'] = $agencyId; $copy['prev_booking_id'] = $id; $copy['code'] = next_code();
        $copy['status'] = in_array($old['status'], LOCKED_STATUSES, true) ? 'opsiyon' : $old['status'];
        if ($in) $copy['check_in'] = $in;
        if ($out) $copy['check_out'] = $out;
        $copy['notes'] = trim(($old['notes'] ?? '') . "\n$oldAgency grubundan devralındı; satış fiyatını kontrol edin.");
        $newId = insert_row('bookings', $copy + stamp_new());
        foreach ($old['rooms'] as $r) insert_row('booking_rooms', ['booking_id' => $newId] + $r);
        foreach ($old['free'] as $f) insert_row('booking_free', ['booking_id' => $newId] + $f);
        foreach ($old['services'] as $s) insert_row('booking_services', ['booking_id' => $newId] + $s);
        audit('grup', $id, 'devir', "Grup $oldAgency → {$ag['name']} devredildi", null, null);
        audit('grup', $newId, 'ekle', "Devir ile açıldı: $oldAgency → {$ag['name']}", null, booking_snapshot(booking_get($newId)));
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    return booking_full($newId);
}

/** Grubu başka otele taşı: alış fiyatları yeni otelin sözleşmesinden yeniden gelir. */
function booking_move(int $id, int $hotelId, ?int $version): array {
    need('booking');
    $old = booking_get($id);
    if (in_array($old['status'], LOCKED_STATUSES, true) && !can('unlock')) throw new ApiError('Konfirme grubu yalnız Yönetici taşıyabilir.', 403);
    $h = hotel_get($hotelId);
    if ($hotelId === (int)$old['hotel_id']) throw new ApiError('Grup zaten bu otelde.');
    $oldHotel = q_val('SELECT name FROM hotels WHERE id = ?', [$old['hotel_id']]);
    $c = contract_for($hotelId, $old['check_in']);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $upd = ['hotel_id' => $hotelId, 'hotel_conf_code' => null];
        if ($c) $upd['buy_cur'] = $c['currency'];
        $note = "Otel değişti: $oldHotel → {$h['name']}" . ($c ? '; alış yeni sözleşmeden alındı.' : '; yeni otelin sözleşmesi yok, alışı girin.');
        $upd['notes'] = trim(($old['notes'] ?? '') . "\n" . $note);
        update_row('bookings', $id, $upd, $version);
        q('DELETE FROM booking_rooms WHERE booking_id = ?', [$id]);
        foreach ($old['rooms'] as $r) {
            $r['buy_price'] = $c ? contract_price($c, $r['room_type']) : null;
            insert_row('booking_rooms', ['booking_id' => $id] + $r);
        }
        $new = booking_get($id);
        audit('grup', $id, 'tasi', $note, booking_snapshot($old), booking_snapshot($new));
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    return booking_full($id);
}

function booking_delete(int $id): void {
    need('booking');
    $old = booking_get($id);
    if (in_array($old['status'], LOCKED_STATUSES, true) && !can('unlock')) throw new ApiError('Konfirme grubu yalnız Yönetici silebilir; iptal edin.', 403);
    q('UPDATE bookings SET deleted = 1, updated_by = ?, updated_at = ?, version = version + 1 WHERE id = ?', [current_user()['id'], now(), $id]);
    audit('grup', $id, 'sil', 'Grup silindi (kayıt saklanıyor)', booking_snapshot($old), null);
}

/* ---------------- Otel ızgarası ve otel detayı ---------------- */

function date_add_days(string $d, int $n): string { return date('Y-m-d', strtotime("$d +$n day")); }

function booking_room_count(array $b): int {
    $n = array_sum(array_map(fn($r) => (int)$r['qty'], $b['rooms']));
    return max($n, (int)($b['rooms_reserved'] ?? 0));
}

/** [from, to) aralığında her gece için ayrılan oda ve kontenjan. */
function nights_usage(int $hotelId, string $from, string $to, array $bookings): array {
    $allot = q_all('SELECT date_from, date_to, rooms FROM hotel_allotments WHERE hotel_id = ? AND date_from < ? AND date_to >= ?', [$hotelId, $to, $from]);
    $out = [];
    for ($d = $from, $i = 0; $d < $to && $i < 400; $d = date_add_days($d, 1), $i++) {
        $res = 0;
        foreach ($bookings as $b) {
            if (!in_array($b['status'], ACTIVE_STATUSES, true)) continue;
            if ($b['check_in'] <= $d && $b['check_out'] > $d) $res += booking_room_count($b);
        }
        $al = null;
        foreach ($allot as $a) if ($a['date_from'] <= $d && $a['date_to'] >= $d) $al = ($al ?? 0) + (int)$a['rooms'];
        $out[] = ['date' => $d, 'reserved' => $res, 'allotment' => $al];
    }
    return $out;
}

function hotel_bookings(int $hotelId, string $from, string $to): array {
    $ids = q_all('SELECT id FROM bookings WHERE hotel_id = ? AND deleted = 0 AND check_in < ? AND check_out > ? ORDER BY check_in, id', [$hotelId, $to, $from]);
    $out = [];
    foreach ($ids as $r) {
        $b = booking_get((int)$r['id']);
        $b['agency_name'] = $b['agency_id'] ? q_val('SELECT name FROM agencies WHERE id = ?', [$b['agency_id']]) : null;
        $c = calc_booking($b);
        $b['calc'] = ['nights' => $c['nights'], 'rooms' => $c['rooms'], 'buy_net' => $c['buy_net'], 'buy_cur' => $c['buy_cur'], 'sell_net' => $c['sell_net'], 'sell_cur' => $c['sell_cur'],
            'buy_eur' => $c['buy_eur'], 'sell_eur' => $c['sell_eur'], 'profit_eur' => $c['profit_eur'], 'profit_usd' => $c['profit_usd'], 'profit_try' => $c['profit_try'],
            'warnings' => $c['warnings'], 'has_error' => $c['has_error']];
        $out[] = $b;
    }
    return $out;
}

function hotels_grid(string $from, string $to, string $search = '', string $region = '', bool $all = false, ?int &$hidden = null): array {
    $hotels = q_all('SELECT * FROM hotels WHERE active = 1');
    usort($hotels, fn($a, $b) => strcmp(norm_name($a['name']), norm_name($b['name'])));
    // Kalabalık olmasın: aranmıyorsa yalnız sitede sayfası, grubu ya da sözleşmesi olan oteller kart olarak gelir
    $used = [];
    foreach (q_all('SELECT DISTINCT hotel_id FROM bookings WHERE deleted = 0 UNION SELECT DISTINCT hotel_id FROM hotel_rates UNION SELECT DISTINCT hotel_id FROM hotel_allotments') as $r) $used[(int)$r['hotel_id']] = true;
    $hidden = 0;
    $res = [];
    foreach ($hotels as $h) {
        if ($region !== '' && ($h['region'] ?? '') !== $region) continue;
        if (!$all && $search === '' && empty($h['site_slug']) && empty($used[(int)$h['id']])) { $hidden++; continue; }
        $books = hotel_bookings((int)$h['id'], $from, $to);
        if ($search !== '') {
            $s = norm_name($search);
            $hit = str_contains(norm_name($h['name']), $s);
            foreach ($books as $b) if (str_contains(norm_name((string)$b['agency_name']), $s) || str_contains(norm_name((string)$b['code']), $s)) $hit = true;
            if (!$hit) continue;
        }
        $sum = ['groups' => 0, 'cancelled' => 0, 'buy_eur' => 0.0, 'sell_eur' => 0.0, 'profit_eur' => 0.0, 'profit_usd' => 0.0, 'profit_try' => 0.0, 'unknown' => 0, 'errors' => 0, 'pending' => 0];
        foreach ($books as $b) {
            if ($b['status'] === 'iptal') { $sum['cancelled']++; continue; }
            $sum['groups']++;
            if (in_array($b['status'], ['teklif', 'opsiyon'], true)) $sum['pending']++;
            if ($b['calc']['has_error']) $sum['errors']++;
            if ($b['calc']['profit_eur'] === null) { $sum['unknown']++; continue; }
            $sum['buy_eur'] += $b['calc']['buy_eur']; $sum['sell_eur'] += $b['calc']['sell_eur'];
            $sum['profit_eur'] += $b['calc']['profit_eur']; $sum['profit_usd'] += (float)$b['calc']['profit_usd']; $sum['profit_try'] += $b['calc']['profit_try'];
        }
        $usage = nights_usage((int)$h['id'], $from, $to, $books);
        $maxRes = 0; $maxAl = null; $over = 0;
        foreach ($usage as $n) {
            $maxRes = max($maxRes, $n['reserved']);
            if ($n['allotment'] !== null) $maxAl = max($maxAl ?? 0, $n['allotment']);
            if ($n['allotment'] !== null && $n['reserved'] > $n['allotment']) $over++;
        }
        $h['summary'] = $sum + ['max_reserved' => $maxRes, 'max_allotment' => $maxAl, 'over_nights' => $over];
        $res[] = $h;
    }
    return $res;
}

function hotel_detail(int $id, string $from, string $to): array {
    $h = hotel_get($id);
    $h['rates'] = q_all('SELECT * FROM hotel_rates WHERE hotel_id = ? ORDER BY date_from DESC', [$id]);
    $h['allotments'] = q_all('SELECT * FROM hotel_allotments WHERE hotel_id = ? ORDER BY date_from DESC', [$id]);
    $h['bookings'] = hotel_bookings($id, $from, $to);
    $h['nights'] = nights_usage($id, $from, $to, $h['bookings']);
    $h['history'] = q_all("SELECT at, user_name, action, summary FROM audit_log WHERE (entity = 'otel' AND entity_id = ?) ORDER BY id DESC LIMIT 30", [$id]);
    return $h;
}

/* ---------------- Not defteri ---------------- */

function notes_list(bool $withDone = false, ?array $filter = null): array {
    $w = $withDone ? '1=1' : 'done = 0'; $p = [];
    foreach (['hotel_id', 'agency_id', 'booking_id'] as $k) if (!empty($filter[$k])) { $w .= " AND $k = ?"; $p[] = (int)$filter[$k]; }
    $rows = q_all("SELECT * FROM notes WHERE $w ORDER BY done, CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date, id DESC LIMIT 300", $p);
    $names = [];
    foreach (q_all('SELECT id, name FROM users', [], db_main()) as $u) $names[$u['id']] = $u['name'];
    foreach ($rows as &$r) {
        $r['created_by_name'] = $names[$r['created_by']] ?? null;
        $r['done_by_name'] = $names[$r['done_by']] ?? null;
        $r['hotel_name'] = $r['hotel_id'] ? q_val('SELECT name FROM hotels WHERE id = ?', [$r['hotel_id']]) : null;
        $r['agency_name'] = $r['agency_id'] ? q_val('SELECT name FROM agencies WHERE id = ?', [$r['agency_id']]) : null;
        $r['booking_code'] = $r['booking_id'] ? q_val('SELECT code FROM bookings WHERE id = ?', [$r['booking_id']]) : null;
    }
    return $rows;
}

function note_save(array $in): array {
    $text = clean_str($in['text'] ?? '', 4000);
    if (!$text) throw new ApiError('Not boş olamaz.');
    $data = ['text' => $text, 'due_date' => clean_date($in['due_date'] ?? ''), 'hotel_id' => clean_int($in['hotel_id'] ?? null),
        'agency_id' => clean_int($in['agency_id'] ?? null), 'booking_id' => clean_int($in['booking_id'] ?? null)];
    if ($data['hotel_id']) hotel_get($data['hotel_id']);
    if ($data['agency_id'] && !q_one('SELECT id FROM agencies WHERE id = ?', [$data['agency_id']])) throw new ApiError('Acente bulunamadı.');
    if ($data['booking_id']) { $b = booking_get($data['booking_id']); $data['hotel_id'] = $data['hotel_id'] ?: (int)$b['hotel_id']; $data['agency_id'] = $data['agency_id'] ?: ($b['agency_id'] ? (int)$b['agency_id'] : null); }
    $id = clean_int($in['id'] ?? null);
    if ($id) {
        $old = q_one('SELECT * FROM notes WHERE id = ?', [$id]);
        if (!$old) throw new ApiError('Not bulunamadı.', 404);
        update_row('notes', $id, $data, clean_int($in['version'] ?? null));
        audit('not', $id, 'guncelle', 'Not güncellendi: ' . mb_substr($text, 0, 120), $old, $data);
    } else {
        $data['via'] = !empty($GLOBALS['LX_VIA_ASSISTANT']) ? 'asistan' : null;
        $id = insert_row('notes', $data + stamp_new());
        audit('not', $id, 'ekle', 'Not: ' . mb_substr($text, 0, 150), null, $data);
    }
    return q_one('SELECT * FROM notes WHERE id = ?', [$id]);
}

function note_done(int $id, bool $done = true): array {
    $old = q_one('SELECT * FROM notes WHERE id = ?', [$id]);
    if (!$old) throw new ApiError('Not bulunamadı.', 404);
    $u = current_user();
    q('UPDATE notes SET done = ?, done_by = ?, done_at = ?, updated_by = ?, updated_at = ?, version = version + 1 WHERE id = ?',
        [$done ? 1 : 0, $done ? $u['id'] : null, $done ? now() : null, $u['id'], now(), $id]);
    audit('not', $id, $done ? 'tamam' : 'geri_ac', ($done ? 'Not tamamlandı: ' : 'Not yeniden açıldı: ') . mb_substr($old['text'], 0, 120));
    return q_one('SELECT * FROM notes WHERE id = ?', [$id]);
}


/* ---------------- Otele e-posta ---------------- */

function mail_log_get(int $bookingId): array {
    return q_all('SELECT id, kind, state, to_addr, cc, subject, ok, error, user_name, at FROM mail_log WHERE booking_id = ? ORDER BY id DESC LIMIT 20', [$bookingId]);
}

/** Otel rezervasyon fişini otele e-postayla gönderir ve kaydeder. */
function talep_send(array $in): array {
    need('booking');
    $id = (int)($in['booking_id'] ?? 0);
    $durum = ($in['state'] ?? '') === 'degisiklik' ? 'degisiklik' : 'yeni';
    $D = belge_data($id, $durum);
    $to = mail_addr_list((string)($in['to'] ?? ''), 'Alıcı');
    if (!$to) throw new ApiError('Otelin e-posta adresini yazın.');
    $cc = mail_addr_list((string)($in['cc'] ?? ''), 'Kopya (CC)');
    $u = current_user();
    $msg = clean_str($in['message'] ?? '', 2000) ?? '';
    $m = talep_mail($D, $u, $msg);
    $s = mail_settings();
    $log = ['booking_id' => $id, 'kind' => 'talep', 'state' => $D['state'], 'to_addr' => implode(', ', $to), 'cc' => implode(', ', $cc) ?: null,
        'subject' => mb_substr($m['subject'], 0, 255), 'user_id' => $u['id'], 'user_name' => $u['name'], 'at' => now()];
    if (!empty($_SESSION['egitim'])) {
        // Eğitim bölümünde gerçek e-posta gitmez; yalnız kayıt düşülür.
        insert_row('mail_log', $log + ['ok' => 1, 'error' => 'Eğitim: gönderilmedi (deneme)']);
        return ['ok' => true, 'egitim' => true, 'log' => mail_log_get($id)];
    }
    try {
        mail_send($to, $cc, $m['subject'], $m['html'], $m['text'], $s['user']);
    } catch (ApiError $e) {
        insert_row('mail_log', $log + ['ok' => 0, 'error' => mb_substr($e->getMessage(), 0, 500)]);
        throw $e;
    }
    insert_row('mail_log', $log + ['ok' => 1]);
    audit('grup', $id, 'eposta', "Otel fişi gönderildi ({$D['state']}): " . implode(', ', $to), null, null);
    // Otelin e-postası kayıtlı değilse ilk gönderimde otele kaydet
    if (empty($D['hotel']['email'])) q('UPDATE hotels SET email = ? WHERE id = ?', [implode(', ', $to), (int)$D['hotel']['id']]);
    return ['ok' => true, 'log' => mail_log_get($id)];
}

/** Ayar testi: kendine (gönderen adrese) kısa bir e-posta. */
function mail_test(): array {
    need('users');
    $s = mail_settings();
    mail_send([$s['user']], [], 'Otel paneli e-posta testi', '<p style="font-family:Arial">Otel panelinden gönderilen deneme e-postası. Bu e-postayı görüyorsanız ayar doğru.</p>', 'Otel panelinden gönderilen deneme e-postası. Bu e-postayı görüyorsanız ayar doğru.');
    audit('ayar', null, 'eposta', 'E-posta testi gönderildi: ' . $s['user'], null, null, db_main());
    return ['ok' => true, 'to' => $s['user']];
}

/* ---------- Toplu otel ekleme (Excel / liste yapıştırarak) ---------- */
const HOTEL_ACRONYMS = ['AHC', 'CVK', 'MLS', 'TNR', 'NL', 'MB', 'BVS', 'ESS', 'SPA', 'CIP', 'TEM', 'II', 'III'];

/** "GRAND MARCELLO HOTEL" -> "Grand Marcello Hotel"; kısaltmalar büyük kalır. */
function hotel_pretty_name(string $s): string {
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    if ($s === '' || mb_strtoupper($s, 'UTF-8') !== $s) return $s; // zaten karışık yazılmışsa dokunma
    $words = explode(' ', $s);
    foreach ($words as &$w) {
        if (in_array($w, HOTEL_ACRONYMS, true) || preg_match('/^\d/', $w)) continue;
        $lower = mb_strtolower(strtr($w, ['I' => 'ı', 'İ' => 'i']), 'UTF-8');
        $first = mb_substr($lower, 0, 1, 'UTF-8');
        $first = $first === 'i' ? 'İ' : ($first === 'ı' ? 'I' : mb_strtoupper($first, 'UTF-8'));
        $w = $first . mb_substr($lower, 1, null, 'UTF-8');
    }
    unset($w);
    $out = implode(' ', $words);
    // Yabancı adlarda "ı" yerine "i" (Istanbul, Hilton, Taksim...): yalnız Türkçe karakter içermeyen kelimelerde
    return preg_replace_callback('/\S+/u', fn($m) => preg_match('/[şğüöçŞĞÜÖÇ]/u', $m[0]) ? $m[0] : strtr($m[0], ['ı' => 'i', 'İ' => 'I']), $out);
}

/** Karşılaştırma için kelime kümesi (otel, hotel, the, istanbul gibi dolgu kelimeler hariç). */
function hotel_tokens(string $s): array {
    $s = mb_strtolower(strtr($s, ['İ' => 'i', 'I' => 'ı']), 'UTF-8');
    $s = strtr($s, ['ı' => 'i', 'ş' => 's', 'ğ' => 'g', 'ü' => 'u', 'ö' => 'o', 'ç' => 'c', 'â' => 'a', '&' => ' ']);
    $t = preg_split('/[^a-z0-9]+/', $s, -1, PREG_SPLIT_NO_EMPTY);
    $t = array_values(array_unique(array_diff($t, ['hotel', 'hotels', 'otel', 'oteli', 'the', 'istanbul', 'and', 'by'])));
    sort($t);
    return $t;
}

/** Yapıştırılan listeyi çözer; $commit false ise yalnız önizleme döner. $addSimilar: benzer olsa da eklenecek adlar. */
function hotels_import(string $text, bool $commit, array $addSimilar = []): array {
    need('hotel');
    $panel = q_all('SELECT id, name, region FROM hotels');
    $pt = array_map(fn($h) => ['id' => (int)$h['id'], 'name' => $h['name'], 'region' => $h['region'], 't' => hotel_tokens($h['name']), 'n' => norm_name($h['name'])], $panel);
    $new = []; $exists = []; $similar = []; $seen = [];
    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        $raw = trim(explode("\t", $line)[0]);
        if ($raw === '' || preg_match('/^(otel|hotel)\s*ad/iu', $raw)) continue;
        $name = mb_substr(hotel_pretty_name($raw), 0, 160);
        $n = norm_name($name); $t = hotel_tokens($name);
        if ($n === '' || isset($seen[$n])) continue;
        $seen[$n] = true;
        $hit = null; $near = null; $nearScore = 0;
        foreach ($pt as $p) {
            if ($p['n'] === $n || ($t && $p['t'] === $t)) { $hit = $p; break; }
            if (!$t || !$p['t']) continue;
            $sub = !array_diff($p['t'], $t) || !array_diff($t, $p['t']);
            $score = count(array_intersect($p['t'], $t)) / max(count($p['t']), count($t));
            if ($sub && $score >= 0.5 && $score > $nearScore) { $near = $p; $nearScore = $score; }
        }
        if ($hit) $exists[] = ['name' => $name, 'panel' => $hit['name'], 'id' => $hit['id']];
        elseif ($near) {
            // Muhtemelen aynı otel: liste adı paneldekinin kısaltması, ya da fazlası yalnız semt / "deluxe, spa, suites" gibi ekler
            $extra = array_diff($t, $near['t']);
            $same = !$extra || !array_diff($extra, ['laleli', 'yenikapi', 'beyazit', 'topkapi', 'sultanahmet', 'taksim', 'sirkeci', 'old', 'city', 'deluxe', 'spa', 'suites', 'suite', 'boutique']);
            $rg = hotel_region_guess($name);
            if ($same && $rg && $near['region'] && norm_name($rg) !== norm_name($near['region'])) $same = false; // başka semtteki şube
            $similar[] = ['name' => $name, 'panel' => $near['name'], 'id' => $near['id'], 'same' => $same];
        }
        else $new[] = $name;
    }
    $res = ['new' => $new, 'exists' => $exists, 'similar' => $similar, 'added' => 0];
    if (!$commit) return $res;
    $want = array_flip(array_map('strval', $addSimilar));
    $toAdd = array_merge($new, array_values(array_filter(array_column($similar, 'name'), fn($x) => isset($want[$x]))));
    if (count($toAdd) > 1000) throw new ApiError('Tek seferde en fazla 1000 otel eklenebilir.');
    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($toAdd as $name) {
            if (q_one('SELECT id FROM hotels WHERE LOWER(name) = LOWER(?)', [$name])) continue;
            insert_row('hotels', ['name' => $name, 'stars' => null, 'region' => hotel_region_guess($name), 'active' => 1] + stamp_new());
            $res['added']++;
        }
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    if ($res['added']) audit('otel', null, 'ekle', "Toplu otel ekleme: {$res['added']} otel eklendi", null, ['oteller' => $toAdd]);
    return $res;
}

/** Otel adındaki semt ipucundan bölge tahmini. */
function hotel_region_guess(string $name): ?string {
    $t = ' ' . implode(' ', hotel_tokens($name)) . ' ';
    $map = ['laleli' => 'Laleli', 'taksim' => 'Taksim', 'taxim' => 'Taksim', 'sultanahmet' => 'Sultanahmet', 'sirkeci' => 'Sirkeci', 'beyazit' => 'Beyazıt', 'topkapi' => 'Topkapı',
        'yenikapi' => 'Yenikapı', 'vatan' => 'Fındıkzade', 'findikzade' => 'Fındıkzade', 'bakirkoy' => 'Bakırköy', 'atakoy' => 'Ataköy', 'merter' => 'Merter', 'sisli' => 'Şişli', 'bomonti' => 'Şişli',
        'harbiye' => 'Şişli', 'nisantasi' => 'Şişli', 'pera' => 'Beyoğlu', 'beyoglu' => 'Beyoğlu', 'galata' => 'Beyoğlu', 'galataport' => 'Beyoğlu', 'karakoy' => 'Beyoğlu', 'kadikoy' => 'Anadolu yakası',
        'kozyatagi' => 'Anadolu yakası', 'umraniye' => 'Anadolu yakası', 'kucukyali' => 'Anadolu yakası', 'tuzla' => 'Anadolu yakası', 'airport' => 'Havalimanı', 'arnavutkoy' => 'Havalimanı',
        'kusadasi' => 'Şehir dışı', 'bursa' => 'Şehir dışı', 'yalova' => 'Şehir dışı', 'silivri' => 'Şehir dışı', 'esenyurt' => 'Avrupa yakası', 'avcilar' => 'Avrupa yakası', 'gunesli' => 'Avrupa yakası',
        'mahmutbey' => 'Avrupa yakası', 'kayasehir' => 'Avrupa yakası', 'zeytinburnu' => 'Avrupa yakası', 'florya' => 'Avrupa yakası', 'maslak' => 'Avrupa yakası', 'alibeykoy' => 'Avrupa yakası'];
    foreach ($map as $k => $v) if (str_contains($t, " $k ")) return $v;
    if (str_contains($t, ' old ') && str_contains($t, ' city ')) return 'Sultanahmet';
    return null;
}

/** Sunucuya (data/yildiz-semt.json: [{name, stars, region}]) bırakılan yıldız/semt listesini, otel yetkisi olan biri paneli açınca bir kez işler.
 *  Yıldız yalnız boşsa yazılır; semt boşsa ya da yalnız addan tahmin edilmişse yazılır. */
function hotels_stars_pending(): ?array {
    $f = dirname(__DIR__) . '/data/yildiz-semt.json';
    if (!is_file($f) || !can('hotel')) return null;
    $done = $f . '.' . date('Ymd-His') . '.yapildi';
    if (!@rename($f, $done)) return null;
    $list = json_decode((string)file_get_contents($done), true);
    if (!is_array($list)) return null;
    $panel = q_all('SELECT id, name, stars, region FROM hotels');
    $byN = []; $byT = [];
    foreach ($panel as $h) { $byN[norm_name($h['name'])] = $h; $t = hotel_tokens($h['name']); if ($t) $byT[implode(' ', $t)] = $h; }
    $res = ['stars' => 0, 'region' => 0, 'missing' => []]; $log = [];
    $pdo = db(); $pdo->beginTransaction();
    try {
        foreach ($list as $r) {
            $name = hotel_pretty_name((string)($r['name'] ?? ''));
            $h = $byN[norm_name($name)] ?? $byT[implode(' ', hotel_tokens($name))] ?? null;
            if (!$h) { $res['missing'][] = $r['name'] ?? ''; continue; }
            $set = [];
            $st = (int)($r['stars'] ?? 0);
            if ($h['stars'] === null && $st >= 1 && $st <= 5) { $set['stars'] = $st; $res['stars']++; }
            $rg = trim((string)($r['region'] ?? ''));
            if ($rg !== '' && (trim((string)$h['region']) === '' || $h['region'] === hotel_region_guess($h['name'])) && $h['region'] !== $rg) { $set['region'] = mb_substr($rg, 0, 80); $res['region']++; }
            if (!$set) continue;
            $u = current_user();
            $sql = 'UPDATE hotels SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($set))) . ', updated_by = ?, updated_at = ?, version = version + 1 WHERE id = ?';
            $pdo->prepare($sql)->execute([...array_values($set), $u['id'], now(), (int)$h['id']]);
            $log[] = ['id' => (int)$h['id'], 'otel' => $h['name']] + $set;
        }
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    if ($log) audit('otel', null, 'duzenle', "Bakanlık listesinden yıldız/semt: {$res['stars']} yıldız, {$res['region']} semt", null, ['oteller' => $log, 'bulunamayan' => $res['missing']]);
    return $res;
}
