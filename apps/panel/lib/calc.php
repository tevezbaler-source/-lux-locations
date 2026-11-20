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
    if (!empty($b['hotel_id']) && !empty($b['check_in'])) {
        $contract = contract_for((int)$b['hotel_id'], $b['check_in'], $pdo);
        if (!$contract) {
            $warn('info', 'sozlesme_yok', 'Bu tarih için otelin sözleşme fiyatı girilmemiş; alış karşılaştırılamıyor.');
        } else {
            $diff = [];
            if ($contract['currency'] !== $buyCur) {
                $diff[] = "döviz sözleşmede {$contract['currency']}, girilen $buyCur";
            } else {
                foreach ($buyPer as $type => $price) {
                    $cp = contract_price($contract, $type);
                    if ($cp !== null && $price !== null && abs($cp - $price) > 0.009) $diff[] = "$type sözleşmede " . fmt_num($cp) . ", girilen " . fmt_num($price);
                }
            }
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
