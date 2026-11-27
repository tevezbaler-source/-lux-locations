<?php
// Otel ödeme kontrolü: otelin gönderdiği ödeme ekstresini (foto/PDF) okur, satırları paneldeki gruplarla karşılaştırır.
if (!defined('LX_PANEL')) { http_response_code(403); exit; }

const EKSTRE_TYPES = ['SNGL', 'DBL', 'TWIN', 'TRPL', 'QUAD'];

/** Ekstre fotoğrafını / PDF'ini Claude ile tabloya çevirir. Görsel yalnız okumak için API'ye gider, sunucuda saklanmaz. */
function ekstre_oku(string $b64, string $mime): array {
    need('cari');
    $mime = strtolower($mime);
    $ok = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
    if (!in_array($mime, $ok, true)) throw new ApiError('Yalnız fotoğraf (JPG, PNG) veya PDF yükleyin.');
    if ($b64 === '' || strlen($b64) > 14_000_000) throw new ApiError('Dosya boş ya da çok büyük (en fazla ~10 MB).');
    require_once __DIR__ . '/assistant.php';
    $client = asst_client();
    $c = lx_config();
    $model = $c['ekstre_model'] ?? ($c['assistant_model'] ?? 'claude-haiku-5-5');
    $src = ['type' => 'base64', 'mediaType' => $mime, 'data' => $b64];
    $file = $mime === 'application/pdf' ? ['type' => 'document', 'source' => $src] : ['type' => 'image', 'source' => $src];
    $line = ['type' => 'object', 'properties' => (object)[
        'check_in' => ['type' => 'string', 'description' => 'Giriş tarihi YYYY-AA-GG'],
        'check_out' => ['type' => 'string', 'description' => 'Çıkış tarihi YYYY-AA-GG'],
        'nights' => ['type' => 'number', 'description' => 'Gece sayısı (NIGHT / STAY D.)'],
        'room_type' => ['type' => 'string', 'description' => 'Oda tipi sütunu olduğu gibi: SNG, SGL, DBL, DBL+CHD, TWN, TRPL, QUAD'],
        'qty' => ['type' => 'number', 'description' => 'Oda adedi; free/indirim satırında eksi (ör. -1)'],
        'rate' => ['type' => 'number', 'description' => 'Gecelik oda fiyatı (PRICE / RATE)'],
        'total' => ['type' => 'number', 'description' => 'Satır toplamı; eksi olabilir'],
        'free' => ['type' => 'boolean', 'description' => '"1 DBL FREE" gibi free oda satırı mı'],
    ], 'required' => ['room_type', 'qty']];
    $schema = ['type' => 'object', 'properties' => (object)[
        'hotel' => ['type' => 'string', 'description' => 'Ekstreyi gönderen otelin adı'],
        'currency' => ['type' => 'string', 'description' => 'Para birimi: USD, EUR veya TRY'],
        'groups' => ['type' => 'array', 'description' => 'Her rezervasyon / voucher bloğu ayrı grup', 'items' => ['type' => 'object', 'properties' => (object)[
            'rez_no' => ['type' => 'string', 'description' => 'Otelin rezervasyon numarası (REZ. NO)'],
            'voucher' => ['type' => 'string', 'description' => 'Voucher numarası olduğu gibi, ör. LUX5582 veya 04228'],
            'lines' => ['type' => 'array', 'items' => $line],
            'total' => ['type' => 'number', 'description' => 'Bloğun TOPLAM satırındaki tutar'],
        ], 'required' => ['voucher', 'lines']]],
        'grand_total' => ['type' => 'number', 'description' => 'İlk GENEL TOPLAM (önceki bakiye eklenmeden)'],
        'previous_balance' => ['type' => 'number', 'description' => 'ÖNCEKİ PROFORMADAN KALAN vb. önceki bakiye; yoksa 0'],
        'paid' => ['type' => 'number', 'description' => 'ÖDEME / ELDEN ödenen tutar; yoksa 0'],
        'amount_due' => ['type' => 'number', 'description' => 'En alttaki ödenecek son toplam'],
        'notes' => ['type' => 'string', 'description' => 'Okunamayan ya da emin olunamayan yerler'],
    ], 'required' => ['groups']];
    $tool = ['name' => 'ekstre', 'description' => 'Otel ödeme ekstresinin tablosu', 'inputSchema' => $schema];
    $prompt = "Bu, bir otelin acentemize gönderdiği ödeme ekstresi (konaklama dökümü). Tablodaki her satırı eksiksiz ve rakamları aynen oku; tarihleri YYYY-AA-GG yaz (ekstrede GG.AA.YYYY). Her REZ/VOUCHER bloğu bir grup. \"1 DBL FREE\" gibi satırlar o grubun free satırıdır (free=true, adet eksi). Hesap düzeltme yapma, ne yazıyorsa onu aktar. Ondalık ayırıcı virgül olabilir (2.415,00 = 2415). Sonucu ekstre aracıyla ver.";
    try {
        $resp = $client->messages->create(
            model: $model,
            maxTokens: 8000,
            tools: [$tool],
            toolChoice: ['type' => 'tool', 'name' => 'ekstre'],
            messages: [['role' => 'user', 'content' => [$file, ['type' => 'text', 'text' => $prompt]]]],
        );
    } catch (Throwable $e) {
        $why = asst_error_reason($e);
        throw new ApiError('Ekstre okunamadı. ' . (can('users') ? "[Ayrıntı: $why]" : 'Daha net bir fotoğrafla tekrar deneyin.'), 502);
    }
    $data = null;
    foreach ($resp->content as $blk) {
        $a = json_decode(json_encode($blk), true);
        if (($a['type'] ?? '') === 'tool_use') { $data = $a['input'] ?? null; break; }
    }
    if (!is_array($data) || empty($data['groups'])) throw new ApiError('Ekstrede tablo bulunamadı. Fotoğrafın tamamı görünüyor mu?');
    return ekstre_normalize($data);
}

function ekstre_type(string $t): array {
    $u = strtoupper(preg_replace('/\s+/', '', $t));
    $chd = str_contains($u, 'CHD') || str_contains($u, 'CHILD');
    $u = preg_replace('/\+?CHD|\+?CHILD|FREE|^\d+/', '', $u);
    $map = ['SNG' => 'SNGL', 'SGL' => 'SNGL', 'SNGL' => 'SNGL', 'SINGLE' => 'SNGL', 'DBL' => 'DBL', 'DOUBLE' => 'DBL', 'DB' => 'DBL', 'TWN' => 'TWIN', 'TWIN' => 'TWIN',
        'TRP' => 'TRPL', 'TRPL' => 'TRPL', 'TPL' => 'TRPL', 'TRIPLE' => 'TRPL', 'QUAD' => 'QUAD', 'QDR' => 'QUAD', 'QUA' => 'QUAD'];
    return [$map[$u] ?? $u, $chd];
}

/** Ekrandan düzeltilmiş ya da API'den gelen ekstreyi tek biçime getirir. */
function ekstre_normalize(array $d): array {
    $n = 'ekstre_num';
    $date = function ($v) {
        $v = trim((string)$v);
        if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})$/', $v, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        return clean_date($v) ?: null;
    };
    $out = ['hotel' => mb_substr(trim((string)($d['hotel'] ?? '')), 0, 160), 'currency' => strtoupper(trim((string)($d['currency'] ?? ''))) ?: null, 'groups' => [],
        'grand_total' => $n($d['grand_total'] ?? null), 'previous_balance' => $n($d['previous_balance'] ?? null) ?? 0.0, 'paid' => $n($d['paid'] ?? null) ?? 0.0,
        'amount_due' => $n($d['amount_due'] ?? null), 'notes' => mb_substr(trim((string)($d['notes'] ?? '')), 0, 1000)];
    if ($out['currency'] === '$') $out['currency'] = 'USD';
    if ($out['currency'] === '€') $out['currency'] = 'EUR';
    foreach (array_slice((array)($d['groups'] ?? []), 0, 200) as $g) {
        $lines = [];
        foreach (array_slice((array)($g['lines'] ?? []), 0, 50) as $l) {
            $qty = $n($l['qty'] ?? null) ?? 0.0;
            $free = !empty($l['free']) || $qty < 0;
            $lines[] = ['check_in' => $date($l['check_in'] ?? ''), 'check_out' => $date($l['check_out'] ?? ''), 'nights' => $n($l['nights'] ?? null),
                'room_type' => strtoupper(trim((string)($l['room_type'] ?? ''))), 'qty' => $qty, 'rate' => $n($l['rate'] ?? null), 'total' => $n($l['total'] ?? null), 'free' => $free];
        }
        $out['groups'][] = ['rez_no' => mb_substr(trim((string)($g['rez_no'] ?? '')), 0, 60), 'voucher' => mb_substr(trim((string)($g['voucher'] ?? '')), 0, 60), 'lines' => $lines, 'total' => $n($g['total'] ?? null)];
    }
    return $out;
}

function ekstre_num($v): ?float {
    if ($v === null || $v === '') return null;
    if (is_int($v) || is_float($v)) return (float)$v;
    $v = str_replace([' ', "\u{00A0}"], '', (string)$v);
    if (preg_match('/^-?[\d.]*,\d{1,2}$/', $v)) $v = str_replace(['.', ','], ['', '.'], $v); // 2.415,00
    else $v = str_replace(',', '', $v);                                                       // 2,415.00
    return is_numeric($v) ? (float)$v : null;
}

function ekstre_code(string $s): string { return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $s)); }

/** Ekstreyi paneldeki gruplarla karşılaştırır. Her grup için hatalar (ödemeyi durdurur) ve notlar döner. */
function ekstre_kontrol(array $st, ?int $hotelId): array {
    need('cari');
    $st = ekstre_normalize($st);
    $eq = fn($a, $b, $tol = 0.01) => $a !== null && $b !== null && abs((float)$a - (float)$b) <= $tol;
    $fmt = fn($v) => $v === null ? '—' : fmt_num((float)$v);
    $res = ['groups' => [], 'errors' => 0, 'sum' => 0.0, 'panel_sum' => 0.0, 'notes' => [], 'missing' => []];
    $seenIds = [];
    $cur = $st['currency'];
    $all = q_all('SELECT id, code, hotel_id, hotel_conf_code, check_in, check_out, status FROM bookings WHERE deleted = 0 ORDER BY id DESC');
    foreach ($st['groups'] as $gi => $g) {
        $err = []; $info = [];
        // Ekstrenin kendi hesabı
        $sum = 0.0;
        foreach ($g['lines'] as $li => $l) {
            $nights = $l['nights'];
            if ($l['check_in'] && $l['check_out']) {
                $nb = nights_between($l['check_in'], $l['check_out']);
                if ($nights !== null && (int)$nights !== $nb) $err[] = "Satır " . ($li + 1) . ": {$nights} gece yazılmış, tarihlere göre $nb gece.";
                $nights = $nights ?? $nb;
            }
            if ($l['rate'] !== null && $nights !== null && $l['total'] !== null) {
                $calc = $l['qty'] * $nights * $l['rate'];
                if (!$eq($calc, $l['total'], 0.5)) $err[] = "Satır " . ($li + 1) . ": {$fmt($l['qty'])} oda × {$fmt($nights)} gece × {$fmt($l['rate'])} = {$fmt($calc)}, ekstrede {$fmt($l['total'])} yazıyor.";
            }
            $sum += (float)($l['total'] ?? 0);
        }
        if ($g['total'] !== null && !$eq($sum, $g['total'], 0.5)) $err[] = "Satırların toplamı {$fmt($sum)}, ekstredeki TOPLAM {$fmt($g['total'])}.";
        $gTotal = $g['total'] ?? $sum;
        $res['sum'] += $gTotal;

        // Paneldeki grup: önce voucher (LUX kodu), sonra otelin rezervasyon no'su
        $code = ekstre_code($g['voucher']); $rz = ekstre_code($g['rez_no']);
        $b = null; $by = '';
        if ($code !== '') foreach ($all as $r) if (ekstre_code((string)$r['code']) === $code) { $b = $r; $by = 'voucher'; break; }
        if (!$b && $rz !== '') foreach ($all as $r) if (ekstre_code((string)$r['hotel_conf_code']) === $rz) { $b = $r; $by = 'rez'; break; }
        $row = ['i' => $gi, 'voucher' => $g['voucher'], 'rez_no' => $g['rez_no'], 'total' => $gTotal, 'booking' => null, 'panel_total' => null, 'errors' => [], 'info' => []];
        if (!$b) {
            $err[] = "Panelde {$g['voucher']}" . ($g['rez_no'] ? " / otel rez. {$g['rez_no']}" : '') . ' numaralı grup bulunamadı.';
        } else {
            if (isset($seenIds[$b['id']])) $err[] = 'Bu grup ekstrede iki kez geçiyor.';
            $seenIds[$b['id']] = true;
            $full = booking_get((int)$b['id']);
            $calc = calc_booking($full);
            $row['booking'] = ['id' => (int)$b['id'], 'code' => $b['code'], 'status' => $b['status'], 'check_in' => $b['check_in'], 'check_out' => $b['check_out'],
                'hotel' => q_val('SELECT name FROM hotels WHERE id = ?', [$b['hotel_id']]), 'agency' => $full['agency_id'] ? q_val('SELECT name FROM agencies WHERE id = ?', [$full['agency_id']]) : null,
                'buy_cur' => $full['buy_cur'], 'rooms' => $full['rooms'], 'free_in' => array_values(array_filter($full['free'], fn($f) => $f['direction'] === 'in'))];
            $row['panel_total'] = $calc['buy_net'];
            if ($by === 'rez') $info[] = 'Voucher no ile değil, otelin rezervasyon numarasıyla eşleşti.';
            if ($hotelId && (int)$b['hotel_id'] !== $hotelId) $err[] = "Bu grup panelde başka otelde: {$row['booking']['hotel']}.";
            if ($b['status'] === 'iptal') $err[] = 'Grup panelde İPTAL.';
            elseif (in_array($b['status'], ['teklif', 'opsiyon'], true)) $err[] = 'Grup panelde henüz konfirme değil (' . (STATUSES[$b['status']] ?? $b['status']) . ').';
            if ($cur && $full['buy_cur'] && $cur !== $full['buy_cur']) $err[] = "Ekstre $cur, paneldeki alış {$full['buy_cur']}.";
            // Tarihler
            $dates = [];
            foreach ($g['lines'] as $l) if ($l['check_in'] || $l['check_out']) $dates[$l['check_in'] . '|' . $l['check_out']] = $l;
            foreach ($dates as $l) {
                if ($l['check_in'] !== $b['check_in'] || $l['check_out'] !== $b['check_out'])
                    $err[] = 'Tarih farkı: ekstrede ' . trd($l['check_in']) . ' – ' . trd($l['check_out']) . ', panelde ' . trd($b['check_in']) . ' – ' . trd($b['check_out']) . '.';
            }
            // Oda adedi ve fiyat (oda tipine göre)
            $stQty = []; $stRate = []; $stFree = []; $chd = [];
            foreach ($g['lines'] as $l) {
                [$t, $isChd] = ekstre_type($l['room_type']);
                if ($isChd) $chd[$t] = true;
                if ($l['free']) { $stFree[$t] = ($stFree[$t] ?? 0) + abs($l['qty']); continue; }
                $stQty[$t] = ($stQty[$t] ?? 0) + $l['qty'];
                if ($l['rate'] !== null) $stRate[$t][] = $l['rate'];
            }
            $pQty = []; $pRate = [];
            foreach ($full['rooms'] as $r) { $pQty[$r['room_type']] = ($pQty[$r['room_type']] ?? 0) + (int)$r['qty']; $pRate[$r['room_type']] = num($r['buy_price']); }
            $pFree = [];
            foreach ($row['booking']['free_in'] as $f) $pFree[$f['room_type']] = ($pFree[$f['room_type']] ?? 0) + (int)$f['qty'];
            foreach (array_unique(array_merge(array_keys($stQty), array_keys($pQty))) as $t) {
                $a = $stQty[$t] ?? 0; $p = $pQty[$t] ?? 0;
                if (!$eq($a, $p)) $err[] = "$t oda: ekstrede {$fmt($a)}, panelde {$fmt($p)}.";
                foreach (array_unique($stRate[$t] ?? []) as $rate) {
                    if (!isset($pRate[$t]) || $pRate[$t] === null) { if ($p) $err[] = "$t için panelde alış fiyatı girilmemiş (ekstrede {$fmt($rate)})."; continue; }
                    if (!$eq($rate, $pRate[$t])) $err[] = "$t fiyatı: ekstrede {$fmt($rate)}, paneldeki alış {$fmt($pRate[$t])}.";
                }
            }
            foreach (array_unique(array_merge(array_keys($stFree), array_keys($pFree))) as $t) {
                $a = $stFree[$t] ?? 0; $p = $pFree[$t] ?? 0;
                if (!$eq($a, $p)) $err[] = "Free $t: ekstrede {$fmt($a)}, panelde {$fmt($p)}.";
            }
            foreach (array_keys($chd) as $t) $info[] = "$t odalarda çocuk (CHD) var; panelde çocuk ayrıca tutulmuyor, kontrol edin.";
            if ($calc['buy_net'] !== null && !$eq($gTotal, $calc['buy_net'], 0.5)) $err[] = "Toplam: ekstrede {$fmt($gTotal)}, panelde alış {$fmt($calc['buy_net'])}.";
            if (array_filter($full['services'], fn($s) => (float)($s['buy'] ?? 0) != 0)) $info[] = 'Grupta ek hizmet alışı var; ekstreye dahil değilse ayrıca ödenir.';
            $res['panel_sum'] += (float)$calc['buy_net'];
        }
        $row['errors'] = $err; $row['info'] = $info;
        $res['errors'] += count($err);
        $res['groups'][] = $row;
    }
    // Genel toplamlar
    if ($st['grand_total'] !== null && !$eq($res['sum'], $st['grand_total'], 0.5)) { $res['notes'][] = ['err', "Blok toplamlarının toplamı {$fmt($res['sum'])}, ekstredeki GENEL TOPLAM {$fmt($st['grand_total'])}."]; $res['errors']++; }
    $due = ($st['grand_total'] ?? $res['sum']) + $st['previous_balance'] - $st['paid'];
    if ($st['amount_due'] !== null && !$eq($due, $st['amount_due'], 0.5)) { $res['notes'][] = ['err', "Genel toplam + önceki bakiye − ödenen = {$fmt($due)}, ekstrede {$fmt($st['amount_due'])}."]; $res['errors']++; }
    if ($st['previous_balance'] != 0) $res['notes'][] = ['info', "Önceki proformadan kalan {$fmt($st['previous_balance'])}: panelde otel ödemeleri tutulmadığı için bunu kendi kaydınızdan kontrol edin."];
    if ($st['notes'] !== '') $res['notes'][] = ['info', 'Okuma notu: ' . $st['notes']];
    // Bu otelde ekstre dönemine giren ama ekstrede olmayan gruplar
    if ($hotelId) {
        $ins = array_filter(array_merge(...array_map(fn($g) => array_column($g['lines'], 'check_in'), $st['groups'] ?: [['lines' => []]])));
        $outs = array_filter(array_merge(...array_map(fn($g) => array_column($g['lines'], 'check_out'), $st['groups'] ?: [['lines' => []]])));
        if ($ins && $outs) {
            $rows = q_all("SELECT id, code, check_in, check_out, status FROM bookings WHERE deleted = 0 AND hotel_id = ? AND status IN ('konfirme','konaklamada','kapandi') AND check_in >= ? AND check_out <= ? ORDER BY check_in", [$hotelId, min($ins), max($outs)]);
            foreach ($rows as $r) if (!isset($seenIds[$r['id']])) $res['missing'][] = $r;
        }
    }
    $res['statement'] = $st;
    $res['due'] = $due;
    $res['ok'] = $res['errors'] === 0 && $res['groups'];
    return $res;
}

function trd(?string $s): string { return $s ? substr($s, 8, 2) . '.' . substr($s, 5, 2) . '.' . substr($s, 0, 4) : '?'; }

/** Kontrol edilen ekstreyi kaydeder; "ödendi" olarak işaretlenebilir. */
function ekstre_kaydet(array $in): array {
    need('cari');
    $hotelId = (int)($in['hotel_id'] ?? 0) ?: null;
    $r = ekstre_kontrol((array)($in['statement'] ?? []), $hotelId);
    $status = ($in['status'] ?? '') === 'odendi' ? 'odendi' : 'kontrol';
    if ($status === 'odendi' && !$r['ok'] && trim((string)($in['note'] ?? '')) === '') throw new ApiError('Ekstrede fark var. Yine de ödendi diyecekseniz nedenini not alanına yazın.');
    $st = $r['statement'];
    $row = ['hotel_id' => $hotelId, 'hotel_name' => $hotelId ? q_val('SELECT name FROM hotels WHERE id = ?', [$hotelId]) : ($st['hotel'] ?: null), 'currency' => $st['currency'],
        'total' => round($r['sum'], 2), 'due' => round($r['due'], 2), 'errors' => $r['errors'], 'status' => $status, 'paid_at' => $status === 'odendi' ? (clean_date($in['paid_at'] ?? '') ?: date('Y-m-d')) : null,
        'note' => mb_substr(trim((string)($in['note'] ?? '')), 0, 255) ?: null, 'data' => json_encode($st, JSON_UNESCAPED_UNICODE)];
    $id = (int)($in['id'] ?? 0);
    if ($id) {
        $u = current_user();
        q('UPDATE hotel_statements SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($row))) . ', updated_by = ?, updated_at = ?, version = version + 1 WHERE id = ?', [...array_values($row), $u['id'], now(), $id]);
    } else {
        $id = (int)insert_row('hotel_statements', $row + stamp_new());
    }
    $cur = $st['currency'] ?: '';
    audit('odeme', $id, $status === 'odendi' ? 'odendi' : 'kaydet', ($row['hotel_name'] ?: 'Otel') . ' ekstresi ' . ($status === 'odendi' ? 'ödendi' : 'kontrol edildi') . ': ' . fmt_num($row['due']) . " $cur" . ($r['errors'] ? " ({$r['errors']} fark)" : ''), null, ['ekstre' => $id, 'not' => $row['note']]);
    unset($row['data']);
    return ['id' => $id] + $row;
}

function ekstre_liste(): array {
    need('cari');
    return q_all("SELECT s.id, s.hotel_id, s.hotel_name, s.currency, s.total, s.due, s.errors, s.status, s.paid_at, s.note, s.created_at, s.updated_at FROM hotel_statements s WHERE s.deleted = 0 ORDER BY s.id DESC LIMIT 200");
}

function ekstre_getir(int $id): array {
    need('cari');
    $r = q_one('SELECT * FROM hotel_statements WHERE id = ? AND deleted = 0', [$id]);
    if (!$r) throw new ApiError('Ekstre bulunamadı.', 404);
    $r['statement'] = json_decode((string)$r['data'], true) ?: [];
    unset($r['data']);
    return $r;
}
