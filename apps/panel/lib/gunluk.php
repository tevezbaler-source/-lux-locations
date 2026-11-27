<?php
// Günlük işler: gece yedeği ve opsiyon hatırlatması.
// Sunucuda zamanlanmış görev gerekmez: panel her gün sabah 07:00'den sonraki ilk kullanımda bunları bir kez çalıştırır.
// İsteğe bağlı olarak Plesk'te "php tools/gunluk.php" zamanlanmış görevi de kurulabilir; aynı gün ikinci kez çalışmaz.
if (!defined('LX_PANEL')) { http_response_code(403); exit; }

const GUNLUK_SAKLA_GUN = 30;

function gunluk_dir(): string { return dirname(__DIR__) . '/data/yedek'; }

/** Bugün daha yapılmadıysa günlük işleri çalıştırır. Hata olursa paneli durdurmaz. */
function gunluk_calistir(bool $zorla = false): ?array {
    if (!$zorla && (int)date('G') < 7) return null;
    $dir = gunluk_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) return null;
    $stamp = $dir . '/son-calisma.txt';
    $today = date('Y-m-d');
    if (!$zorla && is_file($stamp) && trim((string)@file_get_contents($stamp)) === $today) return null;
    $lock = @fopen($dir . '/.kilit', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return null;
    try {
        if (!$zorla && is_file($stamp) && trim((string)@file_get_contents($stamp)) === $today) return null;
        file_put_contents($stamp, $today);
        $r = ['yedek' => null, 'hatirlatma' => null];
        try { $r['yedek'] = gunluk_yedek(); } catch (Throwable $e) { $r['yedek'] = 'Hata: ' . $e->getMessage(); error_log('panel gunluk yedek: ' . $e->getMessage()); }
        try { $r['hatirlatma'] = gunluk_opsiyon_mail(); } catch (Throwable $e) { $r['hatirlatma'] = 'Hata: ' . $e->getMessage(); error_log('panel gunluk opsiyon: ' . $e->getMessage()); }
        return $r;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Panel veritabanının ve site taleplerinin sıkıştırılmış kopyası; son 30 gün saklanır. */
function gunluk_yedek(): array {
    $dir = gunluk_dir();
    $day = date('Y-m-d');
    $made = [];
    $c = lx_config();
    $gz = function (string $src, string $dst) {
        $data = file_get_contents($src);
        if ($data === false) throw new RuntimeException('okunamadı: ' . basename($src));
        file_put_contents($dst, gzencode($data, 6), LOCK_EX);
        @chmod($dst, 0600);
    };
    if (($c['driver'] ?? 'sqlite') === 'sqlite') {
        $tmp = $dir . "/panel-$day.sqlite";
        @unlink($tmp);
        try { db_main()->exec('VACUUM INTO ' . db_main()->quote($tmp)); } catch (PDOException $e) { copy($c['sqlite_path'], $tmp); }
        $gz($tmp, "$tmp.gz");
        @unlink($tmp);
        $made[] = basename("$tmp.gz");
    } else {
        require_once __DIR__ . '/backup.php';
        file_put_contents($dir . "/panel-$day.json.gz", gzencode(backup_json(db_main()), 6), LOCK_EX);
        $made[] = "panel-$day.json.gz";
    }
    if (function_exists('talep_db') && ($tdb = talep_db())) {
        $tmp = $dir . "/talepler-$day.sqlite";
        @unlink($tmp);
        try { $tdb->exec('VACUUM INTO ' . $tdb->quote($tmp)); } catch (PDOException $e) { copy(talep_db_path(), $tmp); }
        $gz($tmp, "$tmp.gz");
        @unlink($tmp);
        $made[] = basename("$tmp.gz");
    }
    // Eski yedekleri sil
    $limit = time() - GUNLUK_SAKLA_GUN * 86400;
    foreach (glob($dir . '/*.gz') ?: [] as $f) if (filemtime($f) < $limit) @unlink($f);
    return $made;
}

/** Opsiyonu bugün ya da yarın biten (veya geçmiş) teklif/opsiyon grupları. */
function opsiyon_yaklasan(?PDO $pdo = null): array {
    $until = date('Y-m-d', strtotime('+1 day'));
    return q_all("SELECT b.id, b.code, b.status, b.option_until, b.check_in, b.check_out, b.rooms_option, b.rooms_reserved, h.name AS hotel, a.name AS agency
        FROM bookings b JOIN hotels h ON h.id = b.hotel_id LEFT JOIN agencies a ON a.id = b.agency_id
        WHERE b.deleted = 0 AND b.status IN ('teklif', 'opsiyon') AND b.option_until IS NOT NULL AND b.option_until <> '' AND b.option_until <= ?
        ORDER BY b.option_until, b.check_in", [$until], $pdo);
}

/** Yaklaşan opsiyonları gönderen adrese (reservation@) ve CC'ye bir kez mailler. */
function gunluk_opsiyon_mail(): string {
    $list = opsiyon_yaklasan(db_main());
    if (!$list) return 'yaklaşan opsiyon yok';
    $s = mail_settings();
    if ($s['user'] === '' || $s['pass'] === '') return 'e-posta ayarı yok';
    $base = rtrim((string)(lx_config()['public_url'] ?? 'https://luxlocationstours.com/panel'), '/');
    $today = date('Y-m-d');
    $rows = ''; $text = '';
    foreach ($list as $b) {
        $when = $b['option_until'] < $today ? 'GEÇTİ' : ($b['option_until'] === $today ? 'BUGÜN' : 'yarın');
        $line = "{$b['hotel']} · " . ($b['agency'] ?: '—') . ' · ' . ($b['code'] ?: '#' . $b['id']) . " · giriş {$b['check_in']} · opsiyon {$b['option_until']} ($when)";
        $text .= "- $line\n  $base/#/grup/{$b['id']}\n";
        $rows .= '<tr><td style="padding:4px 8px;border-bottom:1px solid #dfe4e9">' . htmlspecialchars($b['hotel']) . '</td><td style="padding:4px 8px;border-bottom:1px solid #dfe4e9">' . htmlspecialchars($b['agency'] ?: '—')
            . '</td><td style="padding:4px 8px;border-bottom:1px solid #dfe4e9"><a href="' . htmlspecialchars("$base/#/grup/{$b['id']}") . '">' . htmlspecialchars($b['code'] ?: '#' . $b['id']) . '</a></td><td style="padding:4px 8px;border-bottom:1px solid #dfe4e9">' . htmlspecialchars($b['check_in'])
            . '</td><td style="padding:4px 8px;border-bottom:1px solid #dfe4e9;font-weight:bold;color:' . ($when === 'yarın' ? '#a86a00' : '#c0392b') . '">' . htmlspecialchars($b['option_until'] . " ($when)") . '</td></tr>';
    }
    $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#1d2730"><p>Opsiyonu bugün/yarın biten ya da geçmiş gruplar (' . count($list) . '):</p>'
        . '<table style="border-collapse:collapse;font-size:13px"><tr style="background:#f4f6f8"><th align="left" style="padding:4px 8px">Otel</th><th align="left" style="padding:4px 8px">Acente</th><th align="left" style="padding:4px 8px">Grup</th><th align="left" style="padding:4px 8px">Giriş</th><th align="left" style="padding:4px 8px">Opsiyon</th></tr>'
        . $rows . '</table><p style="color:#66727d;font-size:12px">Otel Paneli günlük hatırlatması. Opsiyonu uzatın, konfirme edin ya da iptal edin.</p></div>';
    $cc = $s['cc'] ? preg_split('/[\s,;]+/', $s['cc'], -1, PREG_SPLIT_NO_EMPTY) : [];
    mail_send([$s['user']], $cc, 'Opsiyon hatırlatma · ' . count($list) . ' grup', $html, "Opsiyonu bugün/yarın biten ya da geçmiş gruplar:\n\n" . $text);
    return count($list) . ' grup maillendi';
}
