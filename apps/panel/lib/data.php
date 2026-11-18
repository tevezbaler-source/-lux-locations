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
    }
    foreach ($rows as &$r) {
        $st = $by[$r['id']] ?? [];
        $r['bookings'] = array_sum($st);
        $r['cancelled'] = $st['iptal'] ?? 0;
        $r['sales'] = $sales[$r['id']] ?? new stdClass();
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
    $b['agency_name'] = $b['agency_id'] ? q_val('SELECT name FROM agencies WHERE id = ?', [$b['agency_id']]) : null;
    $b['calc'] = calc_booking($b);
    $b['history'] = q_all("SELECT id, at, user_name, ip, action, summary, old_json, new_json FROM audit_log WHERE entity = 'grup' AND entity_id = ? ORDER BY id DESC LIMIT 100", [$id]);
    foreach ($b['history'] as &$h) {
        $h['changes'] = audit_diff($h['old_json'], $h['new_json']);
        unset($h['old_json'], $h['new_json']);
    }
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

function hotels_grid(string $from, string $to, string $search = '', string $region = ''): array {
    $hotels = q_all('SELECT * FROM hotels WHERE active = 1');
    usort($hotels, fn($a, $b) => strcmp(norm_name($a['name']), norm_name($b['name'])));
    $res = [];
    foreach ($hotels as $h) {
        if ($region !== '' && ($h['region'] ?? '') !== $region) continue;
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
