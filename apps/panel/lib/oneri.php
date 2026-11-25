<?php
// Yazarken öneriler: geçmişte girilen değerler, hizmetlerin son fiyatı, panel geneli hızlı arama, talepte acente eşleşmesi.
if (!defined('LX_PANEL')) { http_response_code(403); exit; }

/** Daha önce girilmiş değerler (en çok kullanılandan aza). */
function oneri_gecmis(string $alan, int $hotelId = 0): array {
    $sql = [
        'contact' => ['SELECT contact AS v, COUNT(*) AS n FROM hotel_rates WHERE contact IS NOT NULL AND contact <> \'\'' . ($hotelId ? ' AND hotel_id = ?' : '') . ' GROUP BY contact ORDER BY n DESC, contact LIMIT 40', $hotelId ? [$hotelId] : []],
        'contact_name' => ['SELECT contact_name AS v, COUNT(*) AS n FROM booking_extra WHERE contact_name IS NOT NULL AND contact_name <> \'\' GROUP BY contact_name ORDER BY n DESC, contact_name LIMIT 300', []],
        'room_category' => ['SELECT room_category AS v, COUNT(*) AS n FROM booking_extra WHERE room_category IS NOT NULL AND room_category <> \'\' GROUP BY room_category ORDER BY n DESC LIMIT 60', []],
        'nationality' => ['SELECT nationality AS v, COUNT(*) AS n FROM booking_extra WHERE nationality IS NOT NULL AND nationality <> \'\' GROUP BY nationality ORDER BY n DESC LIMIT 60', []],
        'owner' => ['SELECT owner AS v, COUNT(*) AS n FROM agencies WHERE owner IS NOT NULL AND owner <> \'\' GROUP BY owner ORDER BY n DESC LIMIT 40', []],
    ][$alan] ?? null;
    if (!$sql) throw new ApiError('Bilinmeyen alan.');
    return array_column(q_all($sql[0], $sql[1]), 'v');
}

/** Her hizmet türü için en son girilen alış/satış (aynı tür + aynı açıklama önce). */
function oneri_hizmetler(): array {
    $rows = q_all('SELECT s.kind, s.description, s.buy, s.sell, s.currency, b.check_in FROM booking_services s JOIN bookings b ON b.id = s.booking_id
        WHERE b.deleted = 0 ORDER BY b.check_in DESC, s.id DESC LIMIT 2000');
    $out = [];
    foreach ($rows as $r) {
        $k = mb_strtolower(trim($r['kind'])) . '|' . mb_strtolower(trim((string)$r['description']));
        if (isset($out[$k])) continue;
        $out[$k] = ['kind' => $r['kind'], 'description' => (string)$r['description'], 'buy' => $r['buy'] !== null ? (float)$r['buy'] : null, 'sell' => $r['sell'] !== null ? (float)$r['sell'] : null, 'currency' => $r['currency'], 'date' => $r['check_in']];
    }
    return array_values($out);
}

/** Üst menüdeki hızlı arama: otel, acente, grup (kod, misafir, sorumlu), talep. */
function hizli_ara(string $q): array {
    $q = mb_substr(trim($q), 0, 60);
    if (mb_strlen($q) < 2) return [];
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $out = [];
    foreach (q_all("SELECT id, name, region, stars FROM hotels WHERE name LIKE ? ESCAPE '\\' ORDER BY active DESC, name LIMIT 6", [$like]) as $h)
        $out[] = ['t' => 'Otel', 'label' => $h['name'], 'sub' => $h['region'] ?: '', 'href' => '#/otel/' . $h['id']];
    foreach (q_all("SELECT id, name, country FROM agencies WHERE name LIKE ? ESCAPE '\\' OR aliases LIKE ? ESCAPE '\\' ORDER BY active DESC, name LIMIT 6", [$like, $like]) as $a)
        $out[] = ['t' => 'Acente', 'label' => $a['name'], 'sub' => $a['country'] ?: '', 'href' => '#/acente/' . $a['id']];
    $bk = q_all("SELECT DISTINCT b.id, b.code, b.check_in, b.status, h.name AS hotel, a.name AS agency, e.contact_name, g.name AS guest
        FROM bookings b JOIN hotels h ON h.id = b.hotel_id LEFT JOIN agencies a ON a.id = b.agency_id
        LEFT JOIN booking_extra e ON e.booking_id = b.id LEFT JOIN booking_guests g ON g.booking_id = b.id AND g.name LIKE ? ESCAPE '\\'
        WHERE b.deleted = 0 AND (b.code LIKE ? ESCAPE '\\' OR b.hotel_conf_code LIKE ? ESCAPE '\\' OR e.contact_name LIKE ? ESCAPE '\\' OR g.id IS NOT NULL)
        ORDER BY b.check_in DESC LIMIT 8", [$like, $like, $like, $like]);
    foreach ($bk as $b)
        $out[] = ['t' => 'Grup', 'label' => ($b['code'] ?: '#' . $b['id']) . ' · ' . $b['hotel'], 'sub' => implode(' · ', array_filter([$b['agency'], $b['check_in'], $b['guest'] ? 'misafir: ' . $b['guest'] : $b['contact_name']])), 'href' => '#/grup/' . $b['id']];
    if (can('talep') && function_exists('talep_db') && ($db = talep_db())) {
        $st = $db->prepare("SELECT id, name, email, created_at FROM requests WHERE name LIKE ? ESCAPE '\\' OR email LIKE ? ESCAPE '\\' OR phone LIKE ? ESCAPE '\\' ORDER BY created_at DESC LIMIT 5");
        $st->execute([$like, $like, $like]);
        foreach ($st->fetchAll() as $r)
            $out[] = ['t' => 'Talep', 'label' => $r['name'], 'sub' => implode(' · ', array_filter([$r['email'], date('d.m.Y', (int)$r['created_at'])])), 'href' => '#/talepler?q=' . rawurlencode($r['id'])];
    }
    return $out;
}

/** Talep sahibine uyan acente: e-posta acentenin iletişim bilgisinde ya da alan adı eşleşiyorsa, veya ad benziyorsa. */
function talep_acente_eslestir(string $name, string $email): ?array {
    $email = mb_strtolower(trim($email));
    $domain = str_contains($email, '@') ? substr($email, strrpos($email, '@') + 1) : '';
    $free = ['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com', 'icloud.com', 'live.com', 'yandex.com', 'mail.ru', 'yahoo.ro', 'abv.bg', 'gmx.de', 'web.de', 'hotmail.ro'];
    $nn = norm_name($name);
    foreach (q_all('SELECT id, name, country, aliases, contact FROM agencies WHERE active = 1') as $a) {
        $c = mb_strtolower((string)$a['contact']);
        if ($email && str_contains($c, $email)) return $a + ['why' => 'e-posta'];
        if ($domain && !in_array($domain, $free, true) && str_contains($c, '@' . $domain)) return $a + ['why' => 'e-posta alan adı'];
        $names = array_filter(array_map('norm_name', array_merge([$a['name']], explode(',', (string)$a['aliases']))));
        if ($nn && in_array($nn, $names, true)) return $a + ['why' => 'ad'];
    }
    return null;
}
