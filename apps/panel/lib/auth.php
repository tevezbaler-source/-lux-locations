<?php
// Oturum, kullanıcılar, roller, CSRF ve işlem kaydı.
if (!defined('LX_PANEL')) { http_response_code(403); exit; }

const ROLES = [
    'yonetici'    => 'Yönetici',
    'operasyon'   => 'Operasyon',
    'rezervasyon' => 'Rezervasyon',
    'muhasebe'    => 'Muhasebe',
];

// Rol => yapabildikleri
const PERMS = [
    'yonetici'    => ['booking', 'hotel', 'agency', 'rate', 'users', 'audit', 'unlock', 'backup', 'talep', 'report'],
    'operasyon'   => ['booking', 'hotel', 'agency', 'rate', 'talep', 'report'],
    'rezervasyon' => ['booking', 'hotel', 'agency', 'talep'],
    'muhasebe'    => ['rate', 'agency', 'report'],
];

function lx_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('LXPANEL');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
    $max = (int)(lx_config()['session_minutes'] ?? 600) * 60;
    if (!empty($_SESSION['uid']) && (time() - ($_SESSION['seen'] ?? 0)) > $max) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['seen'] = time();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function client_ip(): string { return substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 64); }

function current_user(): ?array {
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $u = q_one('SELECT id, username, name, role, active FROM users WHERE id = ?', [$_SESSION['uid']], db_main());
        if (!$u || !(int)$u['active']) { $u = null; unset($_SESSION['uid']); }
    }
    return $u;
}

function can(string $perm): bool {
    $u = current_user();
    return $u && in_array($perm, PERMS[$u['role']] ?? [], true);
}

function users_exist(): bool { return (int)q_val('SELECT COUNT(*) FROM users', [], db_main()) > 0; }

function login_blocked(string $username): bool {
    $since = date('Y-m-d H:i:s', time() - 15 * 60);
    $n = (int)q_val('SELECT COUNT(*) FROM login_attempts WHERE (ip = ? OR username = ?) AND at >= ?', [client_ip(), $username, $since], db_main());
    return $n >= 8;
}

function do_login(string $username, string $password): array {
    $username = trim(mb_strtolower($username));
    if (login_blocked($username)) return ['ok' => false, 'error' => 'Çok fazla hatalı deneme. 15 dakika sonra tekrar deneyin.'];
    $u = q_one('SELECT * FROM users WHERE username = ?', [$username], db_main());
    if (!$u || !(int)$u['active'] || !password_verify($password, $u['pass_hash'])) {
        q('INSERT INTO login_attempts (ip, username, at) VALUES (?,?,?)', [client_ip(), $username, now()], db_main());
        return ['ok' => false, 'error' => 'Kullanıcı adı veya şifre hatalı.'];
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['egitim'] = false;
    q('UPDATE users SET last_login = ? WHERE id = ?', [now(), $u['id']], db_main());
    q('DELETE FROM login_attempts WHERE username = ?', [$username], db_main());
    audit('user', (int)$u['id'], 'giris', 'Panele giriş yaptı', null, null, db_main(), $u);
    return ['ok' => true];
}

function validate_password(string $p): ?string {
    if (mb_strlen($p) < 8) return 'Şifre en az 8 karakter olmalı.';
    return null;
}

/**
 * İşlem kaydı. $pdo verilmezse o anki çalışma veritabanına (gerçek veya eğitim) yazar.
 */
function audit(string $entity, ?int $id, string $action, string $summary, $old = null, $new = null, ?PDO $pdo = null, ?array $user = null): void {
    $user = $user ?: current_user();
    q('INSERT INTO audit_log (at, user_id, user_name, ip, entity, entity_id, action, summary, old_json, new_json) VALUES (?,?,?,?,?,?,?,?,?,?)', [
        now(), $user['id'] ?? null, $user['name'] ?? null, client_ip(), $entity, $id, $action, mb_substr($summary . (!empty($GLOBALS['LX_VIA_ASSISTANT']) ? ' (sesli asistan)' : ''), 0, 255),
        $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE),
        $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE),
    ], $pdo ?: db());
}
