<?php
// Alış / satış / free / kur / kâr hesabı ve uyarılar. Tek hesap yeri burası; ekran canlı önizlemeyi de buradan alır.
if (!defined('LX_PANEL')) { http_response_code(403); exit; }

const ROOM_TYPES = ['SNGL', 'DBL', 'TWIN', 'TRPL', 'QUAD'];
// Kişi başı (PP) fiyattan oda fiyatına çarpan. Grupta "PP32 = SNGL DBL 64, TRPL 96"; QUAD = DBL x 2.
const PP_MULT = ['SNGL' => 2, 'DBL' => 2, 'TWIN' => 2, 'TRPL' => 3, 'QUAD' => 4];
const CURRENCIES = ['EUR', 'USD', 'TRY'];
const STATUSES = [
    'teklif' => 'Teklif', 'opsiyon' => 'Opsiyon', 'konfirme' => 'Konfirme',
    'konaklamada' => 'Konaklamada', 'kapandi' => 'Kapandı', 'iptal' => 'İptal',
];
const LOCKED_STATUSES = ['konfirme', 'konaklamada', 'kapandi'];
// Kontenjandan oda düşen durumlar
const ACTIVE_STATUSES = ['opsiyon', 'konfirme', 'konaklamada', 'kapandi'];

function nights_between(string $in, string $out): int {
    $a = DateTime::createFromFormat('!Y-m-d', $in);
    $b = DateTime::createFromFormat('!Y-m-d', $out);
    if (!$a || !$b) return 0;
    return (int)$a->diff($b)->format('%r%a');
}

function latest_rate(?PDO $pdo = null): ?array {
    return q_one('SELECT rate_date, eur_try, usd_try FROM fx_rates ORDER BY rate_date DESC, id DESC LIMIT 1', [], $pdo);
}

/** Otelin verilen tarihteki sözleşme fiyatı (en son eklenen geçerli dönem). */
function contract_for(int $hotelId, string $date, ?PDO $pdo = null): ?array {
    return q_one('SELECT * FROM hotel_rates WHERE hotel_id = ? AND date_from <= ? AND date_to >= ? ORDER BY id DESC LIMIT 1', [$hotelId, $date, $date], $pdo);
}

function contract_price(array $c, string $type): ?float {
    $map = ['SNGL' => 'sngl', 'DBL' => 'dbl', 'TWIN' => 'twin', 'TRPL' => 'trpl', 'QUAD' => 'quad'];
    $v = $c[$map[$type]] ?? null;
    if (($v === null || $v === '') && $type === 'TWIN') $v = $c['dbl'] ?? null;
    if (($v === null || $v === '') && $type === 'QUAD' && isset($c['dbl']) && $c['dbl'] !== null) $v = (float)$c['dbl'] * 2;
    return ($v === null || $v === '') ? null : (float)$v;
}

/** Erken rezervasyon indirimi bu grup için geçerli mi? $bookDate: grubun açıldığı gün. */
function contract_eb_active(array $c, string $bookDate): bool {
    return !empty($c['eb_until']) && (float)($c['eb_pct'] ?? 0) > 0 && $bookDate <= $c['eb_until'];
}

/** Sözleşmeden beklenen alış (erken rezervasyon indirimi düşülmüş). */
function contract_effective(array $c, string $type, string $bookDate): ?float {
    $p = contract_price($c, $type);
    if ($p === null) return null;
    return contract_eb_active($c, $bookDate) ? round($p * (1 - (float)$c['eb_pct'] / 100), 2) : $p;
}

/** Grubun gecelerinde kontenjan ve stop sale durumu (diğer aktif gruplar + bu grup). */
function allotment_check(int $hotelId, string $in, string $out, ?int $selfId, int $myRooms, ?PDO $pdo = null): array {
    $al = q_all('SELECT date_from, date_to, rooms, stop_sale FROM hotel_allotments WHERE hotel_id = ? AND date_from < ? AND date_to >= ?', [$hotelId, $out, $in], $pdo);
    if (!$al) return ['has' => false, 'over' => [], 'stop' => [], 'max_res' => 0, 'max_al' => null];
    $st = implode(',', array_map(fn($x) => "'$x'", ACTIVE_STATUSES));
    $others = q_all("SELECT b.check_in, b.check_out, b.rooms_reserved, (SELECT SUM(qty) FROM booking_rooms r WHERE r.booking_id = b.id) AS q FROM bookings b
        WHERE b.hotel_id = ? AND b.deleted = 0 AND b.status IN ($st) AND b.id <> ? AND b.check_in < ? AND b.check_out > ?", [$hotelId, $selfId ?: 0, $out, $in], $pdo);
    $over = []; $stop = []; $maxRes = 0; $maxAl = null;
    for ($d = $in, $i = 0; $d < $out && $i < 400; $d = date('Y-m-d', strtotime("$d +1 day")), $i++) {
        $cap = null; $closed = false;
        foreach ($al as $a) {
            if ($a['date_from'] > $d || $a['date_to'] < $d) continue;
            if ((int)$a['stop_sale']) $closed = true; else $cap = ($cap ?? 0) + (int)$a['rooms'];
        }
        $res = $myRooms;
        foreach ($others as $o) if ($o['check_in'] <= $d && $o['check_out'] > $d) $res += max((int)$o['q'], (int)$o['rooms_reserved']);
        if ($closed) $stop[] = $d;
        if ($cap !== null) { $maxAl = max($maxAl ?? 0, $cap); if ($res > $cap) $over[] = [$d, $res, $cap]; }
        $maxRes = max($maxRes, $res);
    }
    return ['has' => true, 'over' => $over, 'stop' => $stop, 'max_res' => $maxRes, 'max_al' => $maxAl];
}

function num($v): ?float {
    if ($v === null || $v === '') return null;
    if (is_string($v)) $v = str_replace(',', '.', trim($v));
    return is_numeric($v) ? (float)$v : null;
}

/**
 * $b: check_in, check_out, hotel_id, buy_cur, sell_cur, sell_mode ('room'|'pp'), eur_try, usd_try,
 *     rooms: [{room_type, qty, buy_price, sell_price}], free: [{direction:'in'|'out', room_type, qty}],
 *     services: [{kind, buy, sell, currency}], buy_note, status
 */
function calc_booking(array $b, ?PDO $pdo = null): array {
    $w = [];
    $warn = function (string $level, string $code, string $msg) use (&$w) { $w[] = ['level' => $level, 'code' => $code, 'msg' => $msg]; };

    $nights = (!empty($b['check_in']) && !empty($b['check_out'])) ? nights_between($b['check_in'], $b['check_out']) : 0;
    if ($nights <= 0) $warn('error', 'tarih', 'Çıkış tarihi girişten sonra olmalı.');

    $buyCur = $b['buy_cur'] ?? '';
    $sellCur = $b['sell_cur'] ?? '';
    if (!in_array($buyCur, CURRENCIES, true)) $warn('error', 'doviz', 'Alış döviz cinsi seçilmedi.');
    if (!in_array($sellCur, CURRENCIES, true)) $warn('error', 'doviz', 'Satış döviz cinsi seçilmedi.');
    if ($buyCur && $sellCur && $buyCur !== $sellCur) $warn('info', 'doviz_farkli', "Dikkat: alış $buyCur, satış $sellCur.");

    $pp = ($b['sell_mode'] ?? 'room') === 'pp';
    $buyPer = []; $sellPer = []; $rooms = 0; $lines = [];
    $buyTotal = 0.0; $sellTotal = 0.0; $missingBuy = false; $missingSell = false;
    foreach (($b['rooms'] ?? []) as $r) {
        $type = $r['room_type'] ?? '';
        $qty = (int)($r['qty'] ?? 0);
        if (!in_array($type, ROOM_TYPES, true) || $qty <= 0) continue;
        $buy = num($r['buy_price'] ?? null);
        $sellIn = num($r['sell_price'] ?? null);
        $sell = $sellIn === null ? null : ($pp ? $sellIn * PP_MULT[$type] : $sellIn);
        if ($buy === null) $missingBuy = true;
        if ($sell === null) $missingSell = true;
        $buyPer[$type] = $buy; $sellPer[$type] = $sell;
        $rooms += $qty;
        $lb = $buy === null ? 0 : $qty * $nights * $buy;
        $ls = $sell === null ? 0 : $qty * $nights * $sell;
        $buyTotal += $lb; $sellTotal += $ls;
        $lines[] = ['room_type' => $type, 'qty' => $qty, 'buy_night' => $buy, 'sell_night' => $sell, 'buy_total' => $lb, 'sell_total' => $ls];
    }
    if ($rooms === 0) $warn('error', 'oda', 'En az bir oda satırı girin.');
    if ($missingBuy) $warn('error', 'alis_eksik', 'Alış fiyatı eksik oda tipi var.');
    if ($missingSell) $warn('error', 'satis_eksik', 'Satış fiyatı eksik oda tipi var.');

    // Free odalar
    $freeIn = 0.0; $freeOut = 0.0; $freeInRooms = 0; $freeOutRooms = 0;
    foreach (($b['free'] ?? []) as $f) {
        $type = $f['room_type'] ?? ''; $qty = (int)($f['qty'] ?? 0);
        if (!in_array($type, ROOM_TYPES, true) || $qty <= 0) continue;
        if (($f['direction'] ?? '') === 'in') {
            $freeInRooms += $qty;
            if (!array_key_exists($type, $buyPer)) { $warn('warn', 'free_tip', "Otelden alınan free $type, ama grupta $type oda yok."); continue; }
            $freeIn += $qty * $nights * (float)$buyPer[$type];
        } else {
            $freeOutRooms += $qty;
            if (!array_key_exists($type, $sellPer)) { $warn('warn', 'free_tip', "Acenteye verilen free $type, ama grupta $type oda yok."); continue; }
            $freeOut += $qty * $nights * (float)$sellPer[$type];
        }
    }
    // Free odalar oda tipindeki adetten fazla olamaz (fazla girilirse alış/satış eksiye düşer, kâr şişer)
    $qtyBy = [];
    foreach (($b['rooms'] ?? []) as $r) if (in_array($r['room_type'] ?? '', ROOM_TYPES, true)) $qtyBy[$r['room_type']] = ($qtyBy[$r['room_type']] ?? 0) + max(0, (int)($r['qty'] ?? 0));
    $freeBy = ['in' => [], 'out' => []];
    foreach (($b['free'] ?? []) as $f) { $t = $f['room_type'] ?? ''; $dir = ($f['direction'] ?? '') === 'in' ? 'in' : 'out'; $freeBy[$dir][$t] = ($freeBy[$dir][$t] ?? 0) + max(0, (int)($f['qty'] ?? 0)); }
    foreach ($freeBy as $dir => $types) foreach ($types as $t => $n) {
        if (isset($qtyBy[$t]) && $n > $qtyBy[$t]) $warn('error', 'free_fazla', ($dir === 'in' ? 'Otelden alınan' : 'Acenteye verilen') . " free $t ($n) grubun $t oda sayısından ({$qtyBy[$t]}) fazla.");
    }
    $buyNet = $buyTotal - $freeIn;
    $sellNet = $sellTotal - $freeOut;

    // Ek hizmetler
    $srv = ['EUR' => ['buy' => 0, 'sell' => 0], 'USD' => ['buy' => 0, 'sell' => 0], 'TRY' => ['buy' => 0, 'sell' => 0]];
    foreach (($b['services'] ?? []) as $s) {
        $cur = $s['currency'] ?? '';
        if (!isset($srv[$cur])) continue;
        $srv[$cur]['buy'] += (float)(num($s['buy'] ?? null) ?? 0);
        $srv[$cur]['sell'] += (float)(num($s['sell'] ?? null) ?? 0);
    }

    // Kur
    $eur = num($b['eur_try'] ?? null); $usd = num($b['usd_try'] ?? null); $rateSource = 'grup';
    if (!$eur || !$usd) {
        $lr = latest_rate($pdo);
        if ($lr) { $eur = $eur ?: (float)$lr['eur_try']; $usd = $usd ?: (float)$lr['usd_try']; $rateSource = 'son kur ' . $lr['rate_date']; }
    }
    $toTry = function (float $amt, string $cur) use ($eur, $usd): ?float {
        if ($cur === 'TRY') return $amt;
        if ($cur === 'EUR') return $eur ? $amt * $eur : null;
        if ($cur === 'USD') return $usd ? $amt * $usd : null;
        return null;
    };
    $buyTry = $toTry($buyNet, $buyCur); $sellTry = $toTry($sellNet, $sellCur);
    foreach ($srv as $cur => $v) {
        if ($v['buy'] == 0 && $v['sell'] == 0) continue;
        $tb = $toTry($v['buy'], $cur); $ts = $toTry($v['sell'], $cur);
        $buyTry = ($buyTry === null || $tb === null) ? null : $buyTry + $tb;
        $sellTry = ($sellTry === null || $ts === null) ? null : $sellTry + $ts;
    }
    if ($missingBuy || $missingSell || $rooms === 0 || $nights <= 0) { $buyTry = $missingBuy ? null : $buyTry; $sellTry = $missingSell ? null : $sellTry; }
    $profitTry = ($buyTry === null || $sellTry === null || $missingBuy || $missingSell) ? null : $sellTry - $buyTry;
    $needsRate = ($buyCur !== $sellCur) || array_filter($srv, fn($v, $c) => ($v['buy'] || $v['sell']) && $c !== $sellCur, ARRAY_FILTER_USE_BOTH);
    if ((!$eur || !$usd) && $needsRate) $warn('warn', 'kur', 'Kur girilmemiş; kâr hesaplanamıyor. Kurlar sayfasından günün kurunu girin.');
    $toEur = fn(?float $try) => ($try === null || !$eur) ? null : $try / $eur;

    $buyEur = $toEur($buyTry); $sellEur = $toEur($sellTry); $profitEur = $toEur($profitTry);
    if ($profitEur !== null && $rooms > 0 && $profitEur < 0) {
        $warn('error', 'zarar', 'Satış alışın altında: bu grup zarar ediyor.');
    }

    // Sözleşme karşılaştırması
    $contract = null;
    $selfId = !empty($b['id']) ? (int)$b['id'] : null;
    $bookDate = $selfId ? (substr((string)q_val('SELECT created_at FROM bookings WHERE id = ?', [$selfId], $pdo), 0, 10) ?: date('Y-m-d')) : date('Y-m-d');
    if (!empty($b['hotel_id']) && !empty($b['check_in'])) {
        $contract = contract_for((int)$b['hotel_id'], $b['check_in'], $pdo);
        if (!$contract) {
            $warn('info', 'sozlesme_yok', 'Bu tarih için otelin sözleşme fiyatı girilmemiş; alış karşılaştırılamıyor.');
        } else {
            $diff = [];
            if ($contract['currency'] !== $buyCur) {
                $diff[] = "döviz sözleşmede {$contract['currency']}, girilen $buyCur";
            } else {
                $eb = contract_eb_active($contract, $bookDate);
                foreach ($buyPer as $type => $price) {
                    $cp = contract_effective($contract, $type, $bookDate);
                    if ($cp !== null && $price !== null && abs($cp - $price) > 0.009) $diff[] = "$type sözleşmede " . fmt_num($cp) . ($eb ? ' (erken rez. %' . fmt_num((float)$contract['eb_pct']) . ' düşülmüş)' : '') . ", girilen " . fmt_num($price);
                }
            }
            if ($eb ?? false) $warn('info', 'erken_rez', 'Erken rezervasyon indirimi geçerli: %' . fmt_num((float)$contract['eb_pct']) . ' (' . date('d.m.Y', strtotime($contract['eb_until'])) . ' tarihine kadar açılan gruplar).');
            $minN = (int)($contract['min_nights'] ?? 0);
            if ($minN > 0 && $nights > 0 && $nights < $minN) $warn('warn', 'min_gece', "Sözleşmede en az $minN gece konaklama var; bu grup $nights gece.");
            if ($nights > 1) {
                $last = date('Y-m-d', strtotime($b['check_out'] . ' -1 day'));
                $c2 = contract_for((int)$b['hotel_id'], $last, $pdo);
                if (!$c2) $warn('warn', 'sozlesme_bitiyor', 'Sözleşme dönemi ' . date('d.m.Y', strtotime($contract['date_to'])) . ' tarihinde bitiyor; sonraki geceler için sözleşme fiyatı yok.');
                elseif ((int)$c2['id'] !== (int)$contract['id']) $warn('warn', 'iki_donem', 'Konaklama iki sözleşme dönemine yayılıyor: ' . date('d.m.Y', strtotime($c2['date_from'])) . ' gecesinden itibaren fiyat farklı olabilir (DBL ' . fmt_num((float)($c2['dbl'] ?? 0)) . " {$c2['currency']}). Alışı buna göre girin.");
            }
            if (!empty($contract['chd_policy'])) $warn('info', 'cocuk', 'Çocuk politikası: ' . $contract['chd_policy']);
            if ($diff) {
                $hasNote = trim((string)($b['buy_note'] ?? '')) !== '';
                $warn($hasNote ? 'warn' : 'error', 'sozlesme_farki', 'Alış sözleşmeden farklı: ' . implode('; ', $diff) . ($hasNote ? ' (neden yazılmış)' : '. "Alış notu" alanına nedenini yazın.'));
            }
            $every = (int)($contract['free_every'] ?? 0);
            if ($every > 0 && $rooms > 0) {
                $expected = intdiv($rooms, $every);
                if ($expected !== $freeInRooms) $warn('warn', 'free_kural', "Sözleşmeye göre $rooms odada $expected {$contract['free_type']} free alınmalı; girilen $freeInRooms.");
            }
        }
    }
    // Kontenjan ve stop sale
    if (!empty($b['hotel_id']) && $nights > 0 && ($b['status'] ?? '') !== 'iptal') {
        $mine = max($rooms, (int)($b['rooms_reserved'] ?? 0));
        $ac = allotment_check((int)$b['hotel_id'], $b['check_in'], $b['check_out'], $selfId, $mine, $pdo);
        $dm = fn($d) => date('d.m', strtotime($d));
        if ($ac['stop']) $warn('warn', 'stop_sale', 'Otel bu gecelerde satışı kapattı (stop sale): ' . implode(', ', array_map($dm, array_slice($ac['stop'], 0, 6))) . (count($ac['stop']) > 6 ? '…' : '') . '. Otelden ayrıca onay alın.');
        if ($ac['over']) {
            $worst = array_reduce($ac['over'], fn($w, $x) => !$w || ($x[1] - $x[2]) > ($w[1] - $w[2]) ? $x : $w);
            $msg = 'Kontenjan aşılıyor: ' . count($ac['over']) . ' gece (en çok ' . $dm($worst[0]) . " gecesi {$worst[1]} oda / kontenjan {$worst[2]}).";
            if (in_array($b['status'] ?? '', ACTIVE_STATUSES, true)) $warn('warn', 'kontenjan', $msg . ' Otelden ek oda isteyin.');
            else $warn('info', 'kontenjan', 'Opsiyona alınırsa ' . mb_strtolower(mb_substr($msg, 0, 1)) . mb_substr($msg, 1));
        }
    }
    if (!empty($b['rooms_reserved']) && (int)$b['rooms_reserved'] < $rooms) {
        $warn('warn', 'oda_fazla', "Oda satırlarında $rooms oda var, ayrılan oda {$b['rooms_reserved']}.");
    }

    return [
        'nights' => $nights, 'rooms' => $rooms, 'lines' => $lines,
        'buy_gross' => round($buyTotal, 2), 'free_in' => round($freeIn, 2), 'buy_net' => round($buyNet, 2), 'buy_cur' => $buyCur,
        'sell_gross' => round($sellTotal, 2), 'free_out' => round($freeOut, 2), 'sell_net' => round($sellNet, 2), 'sell_cur' => $sellCur,
        'free_in_rooms' => $freeInRooms, 'free_out_rooms' => $freeOutRooms,
        'services' => $srv,
        'eur_try' => $eur, 'usd_try' => $usd, 'rate_source' => $rateSource,
        'buy_eur' => $buyEur === null ? null : round($buyEur, 2), 'sell_eur' => $sellEur === null ? null : round($sellEur, 2),
        'profit_eur' => $profitEur === null ? null : round($profitEur, 2),
        'profit_usd' => ($profitTry === null || !$usd) ? null : round($profitTry / $usd, 2), 'profit_try' => $profitTry === null ? null : round($profitTry, 2),
        'contract' => $contract,
        'warnings' => $w,
        'has_error' => (bool)array_filter($w, fn($x) => $x['level'] === 'error'),
    ];
}

function fmt_num(float $n): string {
    return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
}
