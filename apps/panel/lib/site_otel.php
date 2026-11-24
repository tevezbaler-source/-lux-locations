<?php
// Sitedeki oteller (lx-content/data/hotels.json, site düzenleyiciden yönetilir) panele otomatik gelir.
// Yön: siteden panele. Panelde aynı isimli otel varsa kopya açılmaz, ona bağlanır.
// Kapak görseli sitede değişirse panel kartında da değişir. Panelde elle girilen yıldız/bölge/adres korunur.
if (!defined('LX_PANEL')) { http_response_code(403); exit; }

function site_hotels_path(): string {
    $c = lx_config()['site_hotels_json'] ?? '';
    if ($c) return $c;
    $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 2);
    return $root . '/lx-content/data/hotels.json';
}

/** Sitedeki oteller, panel alanlarına çevrilmiş hâliyle (slug, name, stars, region, address, cover). */
function site_hotels_list(): array {
    $f = site_hotels_path();
    if (!is_file($f)) return [];
    $list = json_decode((string)file_get_contents($f), true);
    if (!is_array($list)) return [];
    $out = [];
    foreach ($list as $s) {
        if (!is_array($s) || empty($s['slug']) || empty($s['name'])) continue;
        $cover = (string)($s['cover'] ?? '');
        if ($cover !== '' && !preg_match('~^(/|https://)~', $cover)) $cover = '/' . ltrim($cover, '/');
        $cityParts = array_values(array_filter(array_map('trim', explode('·', (string)($s['city']['tr'] ?? '')))));
        $out[] = ['slug' => (string)$s['slug'], 'name' => mb_substr((string)$s['name'], 0, 160), 'stars' => (int)($s['stars'] ?? 0) ?: null,
            'region' => $cityParts ? mb_substr($cityParts[0], 0, 60) : null, 'address' => clean_str($s['address'] ?? null), 'cover' => $cover ?: null];
    }
    return $out;
}

/** "Otel ekle" formundaki öneriler: sitedeki oteller + paneldeki oteller (panelde olanlar id ile). */
function hotel_name_suggestions(): array {
    $panel = q_all('SELECT id, name, stars, region, site_slug, cover FROM hotels ORDER BY name');
    $bySlug = []; $byName = [];
    foreach ($panel as $h) { if ($h['site_slug']) $bySlug[$h['site_slug']] = $h; $byName[norm_name($h['name'])] = $h; }
    $out = []; $used = [];
    foreach (site_hotels_list() as $s) {
        $h = $bySlug[$s['slug']] ?? $byName[norm_name($s['name'])] ?? null;
        if ($h) $used[$h['id']] = true;
        $out[] = $s + ['site' => true, 'panel_id' => $h['id'] ?? null];
    }
    foreach ($panel as $h) if (empty($used[$h['id']])) $out[] = ['name' => $h['name'], 'stars' => $h['stars'], 'region' => $h['region'], 'cover' => $h['cover'], 'site' => false, 'panel_id' => $h['id']];
    usort($out, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    return $out;
}

/** hotels.json değiştiyse (ya da $zorla) eşitler. Yalnız gerçek veritabanında çalışır. */
function site_hotels_sync(bool $zorla = false): ?array {
    $f = site_hotels_path();
    if (!is_file($f)) return null;
    $stamp = dirname(__DIR__) . '/data/site-otel-esitleme.txt';
    $sig = filemtime($f) . ':' . filesize($f);
    if (!$zorla && is_file($stamp) && trim((string)@file_get_contents($stamp)) === $sig) return null;
    $list = json_decode((string)file_get_contents($f), true);
    if (!is_array($list)) return null;

    $pdo = db_main();
    $panel = q_all('SELECT id, name, stars, region, address, site_slug, cover FROM hotels', [], $pdo);
    $bySlug = []; $byName = [];
    foreach ($panel as $h) {
        if ($h['site_slug']) $bySlug[$h['site_slug']] = $h;
        $byName[norm_name($h['name'])] = $h;
    }
    $added = []; $linked = []; $changed = 0;
    foreach ($list as $s) {
        if (!is_array($s) || empty($s['slug']) || empty($s['name'])) continue;
        $slug = (string)$s['slug'];
        $cover = (string)($s['cover'] ?? '');
        if ($cover !== '' && !preg_match('~^(/|https://)~', $cover)) $cover = '/' . ltrim($cover, '/');
        $stars = (int)($s['stars'] ?? 0) ?: null;
        $cityParts = array_values(array_filter(array_map('trim', explode('·', (string)($s['city']['tr'] ?? '')))));
        $region = $cityParts ? mb_substr($cityParts[0], 0, 60) : null; // semt: Sultanahmet, Laleli, Topkapı…
        $address = clean_str($s['address'] ?? null);
        $h = $bySlug[$slug] ?? null;
        if (!$h) {
            $h = $byName[norm_name($s['name'])] ?? null;
            if ($h && $h['site_slug'] && $h['site_slug'] !== $slug) $h = null; // başka site oteline bağlı
        }
        if ($h) {
            $upd = [];
            if ($h['site_slug'] !== $slug) { $upd['site_slug'] = $slug; $linked[] = $h['name']; }
            if ((string)$h['cover'] !== $cover) $upd['cover'] = $cover ?: null;
            if (!$h['stars'] && $stars) $upd['stars'] = $stars;
            if (!$h['region'] && $region) $upd['region'] = $region;
            if (!$h['address'] && $address) $upd['address'] = $address;
            if ($upd) {
                $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($upd)));
                q("UPDATE hotels SET $set WHERE id = ?", array_merge(array_values($upd), [$h['id']]), $pdo);
                $changed++;
            }
        } else {
            $u = current_user();
            q('INSERT INTO hotels (name, stars, region, address, site_slug, cover, active, version, created_by, created_at, updated_by, updated_at) VALUES (?,?,?,?,?,?,1,1,?,?,?,?)',
                [mb_substr((string)$s['name'], 0, 160), $stars, $region, $address, $slug, $cover ?: null, $u['id'] ?? null, now(), $u['id'] ?? null, now()], $pdo);
            $added[] = $s['name'];
        }
    }
    file_put_contents($stamp, $sig);
    if ($added || $linked) {
        audit('otel', null, 'site', 'Siteden oteller: ' . ($added ? count($added) . ' eklendi (' . implode(', ', $added) . ')' : '') . ($added && $linked ? '; ' : '') . ($linked ? count($linked) . ' mevcut otele bağlandı' : ''), null, null, $pdo);
    }
    return ['added' => $added, 'linked' => $linked, 'changed' => $changed];
}
