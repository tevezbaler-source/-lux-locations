<?php
// E-posta gönderimi (SMTP, STARTTLS + AUTH LOGIN). Microsoft 365: smtp.office365.com:587.
// Ayarlar yöneticinin panelden girdiği data/eposta.php dosyasında; şifre tarayıcıya hiç geri gönderilmez.
if (!defined('LX_PANEL')) { http_response_code(403); exit; }

function mail_settings_file(): string { return dirname(__DIR__) . '/data/eposta.php'; }

/** config.php 'smtp' => [...] varsa önceliklidir; yoksa panelden girilen ayar. */
function mail_settings(): array {
    $def = ['host' => 'smtp.office365.com', 'port' => 587, 'user' => '', 'pass' => '', 'from_name' => 'LUX LOCATIONS', 'cc' => ''];
    $c = lx_config()['smtp'] ?? null;
    if (is_array($c) && !empty($c['user'])) return $c + $def;
    $f = mail_settings_file();
    $s = is_file($f) ? (include $f) : [];
    return (is_array($s) ? $s : []) + $def;
}

/** Tarayıcıya giden hâli: şifre yok, yalnız girilmiş mi. */
function mail_settings_public(): array {
    $s = mail_settings();
    return ['host' => $s['host'], 'port' => (int)$s['port'], 'user' => $s['user'], 'from_name' => $s['from_name'], 'cc' => $s['cc'],
        'has_pass' => $s['pass'] !== '', 'ready' => $s['user'] !== '' && $s['pass'] !== ''];
}

function mail_settings_save(array $in): array {
    need('users');
    $old = mail_settings();
    $user = trim((string)($in['user'] ?? ''));
    if ($user !== '' && !filter_var($user, FILTER_VALIDATE_EMAIL)) throw new ApiError('Gönderen adresi geçerli bir e-posta olmalı.');
    $host = trim((string)($in['host'] ?? '')) ?: 'smtp.office365.com';
    if (!preg_match('/^[a-z0-9.\-]{3,120}$/i', $host)) throw new ApiError('Sunucu adı hatalı.');
    $port = (int)($in['port'] ?? 587) ?: 587;
    $cc = mail_addr_list((string)($in['cc'] ?? ''), 'Kopya (CC)');
    $pass = (string)($in['pass'] ?? '');
    $s = ['host' => $host, 'port' => $port, 'user' => $user, 'pass' => $pass !== '' ? $pass : ($user === $old['user'] ? $old['pass'] : ''),
        'from_name' => clean_str($in['from_name'] ?? 'LUX LOCATIONS', 80) ?: 'LUX LOCATIONS', 'cc' => implode(', ', $cc), 'at' => now()];
    $f = mail_settings_file();
    if (!is_dir(dirname($f))) @mkdir(dirname($f), 0775, true);
    file_put_contents($f, "<?php\n// Panelden girilen e-posta (SMTP) ayarı. Paylaşmayın.\nreturn " . var_export($s, true) . ";\n", LOCK_EX);
    @chmod($f, 0600);
    audit('ayar', null, 'eposta', "E-posta ayarı kaydedildi: $user" . ($pass !== '' ? ' (şifre girildi)' : ''), null, null, db_main());
    return mail_settings_public();
}

/** "a@x.com, b@y.com; c@z.com" → doğrulanmış liste. */
function mail_addr_list(string $s, string $label = 'Adres'): array {
    $out = [];
    foreach (preg_split('/[\s,;]+/', $s, -1, PREG_SPLIT_NO_EMPTY) as $a) {
        if (!filter_var($a, FILTER_VALIDATE_EMAIL)) throw new ApiError("$label geçersiz: $a");
        $out[strtolower($a)] = $a;
    }
    return array_values($out);
}

function mail_hdr(string $s): string { return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s; }

/**
 * SMTP ile gönderir. Hata olursa sunucunun cevabıyla ApiError atar.
 * $to, $cc: adres listeleri. $html ve $text gövdeleri.
 */
function mail_send(array $to, array $cc, string $subject, string $html, string $text, ?string $replyTo = null): void {
    $s = mail_settings();
    if ($s['user'] === '' || $s['pass'] === '') throw new ApiError('E-posta ayarı yapılmamış. Kullanıcılar sayfasındaki "E-posta gönderimi" bölümünden gönderen adresi ve şifresini girin.');
    if (!$to) throw new ApiError('Alıcı adresi yok.');
    $from = $s['user'];

    $errno = 0; $errstr = '';
    $fp = @stream_socket_client("tcp://{$s['host']}:{$s['port']}", $errno, $errstr, 15);
    if (!$fp) throw new ApiError("E-posta sunucusuna bağlanılamadı ({$s['host']}:{$s['port']}): $errstr");
    stream_set_timeout($fp, 30);
    $read = function () use ($fp): array {
        $lines = '';
        while (($l = fgets($fp, 2048)) !== false) { $lines .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; }
        return [(int)substr($lines, 0, 3), trim($lines)];
    };
    $cmd = function (string $c, array $ok, string $show = '') use ($fp, $read): string {
        fwrite($fp, $c . "\r\n");
        [$code, $msg] = $read();
        if (!in_array($code, $ok, true)) {
            fclose($fp);
            throw new ApiError('E-posta gönderilemedi (' . ($show ?: strtok($c, ' ')) . "): $msg");
        }
        return $msg;
    };
    [$code, $msg] = $read();
    if ($code !== 220) { fclose($fp); throw new ApiError("E-posta sunucusu cevap vermedi: $msg"); }
    $helo = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['SERVER_NAME'] ?? 'localhost') ?: 'localhost';
    $cmd("EHLO $helo", [250]);
    $local = in_array($s['host'], ['127.0.0.1', 'localhost'], true); // yalnız yerel deneme sunucusu için TLS'siz
    if (!$local) {
        $cmd('STARTTLS', [220]);
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0))) {
            fclose($fp); throw new ApiError('E-posta sunucusuyla güvenli bağlantı kurulamadı (TLS).');
        }
        $cmd("EHLO $helo", [250]);
    }
    $cmd('AUTH LOGIN', [334]);
    $cmd(base64_encode($s['user']), [334], 'kullanıcı');
    $cmd(base64_encode($s['pass']), [235], 'şifre');
    $cmd("MAIL FROM:<$from>", [250]);
    foreach (array_merge($to, $cc) as $r) $cmd("RCPT TO:<$r>", [250, 251], "alıcı $r");
    $cmd('DATA', [354]);

    $b = 'lx' . bin2hex(random_bytes(8));
    $head = [
        'Date: ' . date('r'),
        'From: ' . mail_hdr($s['from_name']) . " <$from>",
        'To: ' . implode(', ', $to),
    ];
    if ($cc) $head[] = 'Cc: ' . implode(', ', $cc);
    if ($replyTo) $head[] = "Reply-To: $replyTo";
    $head[] = 'Subject: ' . mail_hdr($subject);
    $head[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (explode('@', $from)[1] ?? 'localhost') . '>';
    $head[] = 'MIME-Version: 1.0';
    $head[] = "Content-Type: multipart/alternative; boundary=\"$b\"";
    $body = implode("\r\n", $head) . "\r\n\r\n"
        . "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text)) . "\r\n"
        . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "\r\n"
        . "--$b--\r\n";
    $body = preg_replace('/^\./m', '..', $body); // nokta ile başlayan satırlar
    $cmd($body . "\r\n.", [250], 'gönderim');
    @fwrite($fp, "QUIT\r\n");
    fclose($fp);
}
