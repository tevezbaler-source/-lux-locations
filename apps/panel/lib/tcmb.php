<?php
// Merkez Bankası (TCMB) döviz kurları: https://www.tcmb.gov.tr/kurlar/today.xml
// TCMB kurları iş günlerinde bir kez, 15:30 civarı yayımlar. Panel en fazla 30 dakikada bir sorar (data/tcmb.json önbelleği).
if (!defined('LX_PANEL')) { http_response_code(403); exit; }

const TCMB_URL = 'https://www.tcmb.gov.tr/kurlar/today.xml';
const TCMB_TTL = 1800;

function tcmb_cache_file(): string { return dirname(__DIR__) . '/data/tcmb.json'; }

/** today.xml içeriğini çözer: ['date' => 'Y-m-d', 'USD' => [buy, sell, bn_buy, bn_sell], 'EUR' => ...] */
function tcmb_parse(string $xml): ?array {
    $prev = libxml_use_internal_errors(true);
    $x = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
    libxml_use_internal_errors($prev);
    if (!$x) return null;
    $d = DateTime::createFromFormat('d.m.Y', (string)$x['Tarih']);
    if (!$d) return null;
    $out = ['date' => $d->format('Y-m-d'), 'bulletin' => (string)$x['Bulten_No']];
    $num = fn($v) => ($v = trim((string)$v)) === '' ? null : (float)$v;
    foreach ($x->Currency as $c) {
        $code = (string)$c['Kod'];
        if (!in_array($code, ['USD', 'EUR', 'GBP'], true)) continue;
        $unit = max(1, (int)$c->Unit);
        $out[$code] = [
            'buy' => ($v = $num($c->ForexBuying)) === null ? null : round($v / $unit, 4),
            'sell' => ($v = $num($c->ForexSelling)) === null ? null : round($v / $unit, 4),
            'bn_buy' => ($v = $num($c->BanknoteBuying)) === null ? null : round($v / $unit, 4),
            'bn_sell' => ($v = $num($c->BanknoteSelling)) === null ? null : round($v / $unit, 4),
        ];
    }
    return isset($out['USD']['sell'], $out['EUR']['sell']) ? $out : null;
}

function tcmb_fetch_raw(): ?string {
    if (function_exists('curl_init')) {
        $url = lx_config()['tcmb_url'] ?? TCMB_URL;
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_USERAGENT => 'Mozilla/5.0 (otel-paneli)']);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code === 200) ? $body : null;
    }
    $ctx = stream_context_create(['http' => ['timeout' => 8, 'header' => "User-Agent: Mozilla/5.0 (otel-paneli)\r\n"]]);
    $body = @file_get_contents(lx_config()['tcmb_url'] ?? TCMB_URL, false, $ctx);
    return $body === false ? null : $body;
}

/**
 * Güncel TCMB kurları (önbellekli). TCMB'ye ulaşılamazsa son başarılı sonuç 'stale' => true ile döner.
 * Hiç sonuç yoksa ['error' => ...].
 */
function tcmb_rates(bool $force = false): array {
    $file = tcmb_cache_file();
    $cache = is_file($file) ? (json_decode((string)file_get_contents($file), true) ?: null) : null;
    if ($force && $cache && (time() - ($cache['checked'] ?? 0)) < 60) $force = false; // en fazla dakikada bir
    if (!$force && $cache && (time() - ($cache['checked'] ?? 0)) < TCMB_TTL) return $cache['rates'] ?? ['error' => $cache['error'] ?? 'Kur alınamadı.'];

    $raw = tcmb_fetch_raw();
    $rates = $raw ? tcmb_parse($raw) : null;
    if ($rates) {
        $rates['fetched_at'] = date('Y-m-d H:i');
        $cache = ['checked' => time(), 'rates' => $rates];
    } else {
        // Ulaşılamadı: eski sonucu koru, 5 dakika sonra tekrar dene
        $cache = ['checked' => time() - TCMB_TTL + 300, 'rates' => ($cache['rates'] ?? null), 'error' => 'Merkez Bankası sitesine ulaşılamadı.'];
        if ($cache['rates']) $cache['rates']['stale'] = true;
    }
    if (!is_dir(dirname($file))) @mkdir(dirname($file), 0775, true);
    @file_put_contents($file, json_encode($cache), LOCK_EX);
    return $cache['rates'] ?? ['error' => $cache['error']];
}

/**
 * Gerçek veritabanında TCMB'nin yayımladığı gün için kur kaydı yoksa döviz satış kurunu otomatik kaydeder.
 * Ofisin elle girdiği kur her zaman önceliklidir: o gün için elle kur girilmişse hiçbir şey yapılmaz,
 * sonradan girilen elle kur da otomatik kaydın önüne geçer (aynı günde son girilen kullanılır).
 */
function tcmb_auto_record(PDO $pdo, ?array $rates = null): ?array {
    if (!(lx_config()['tcmb_auto'] ?? true)) return null;
    $rates = $rates ?? tcmb_rates();
    if (empty($rates['date']) || !empty($rates['stale'])) return null;
    if (q_val('SELECT id FROM fx_rates WHERE rate_date = ? LIMIT 1', [$rates['date']], $pdo)) return null;
    $data = ['rate_date' => $rates['date'], 'eur_try' => $rates['EUR']['sell'], 'usd_try' => $rates['USD']['sell'],
        'note' => 'TCMB döviz satış (otomatik)', 'version' => 1, 'created_at' => now(), 'updated_at' => now()];
    $cols = array_keys($data);
    q('INSERT INTO fx_rates (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($data), $pdo);
    $id = (int)$pdo->lastInsertId();
    audit('kur', $id, 'ekle', "Kur TCMB'den alındı: {$rates['date']} EUR {$data['eur_try']} / USD {$data['usd_try']} TL", null, $data, $pdo,
        ['id' => null, 'name' => 'Merkez Bankası (otomatik)']);
    return $data;
}
