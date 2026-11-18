<?php
// Unutulan şifreyi sıfırlar. Yalnızca komut satırından çalışır:
//   php tools/sifre-sifirla.php
// Kullanıcıları listeler, kullanıcı adını ve yeni şifreyi sorar. İşlem kaydına yazılır.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('LX_PANEL', 1);
date_default_timezone_set('Europe/Istanbul');
require dirname(__DIR__) . '/lib/db.php';
require dirname(__DIR__) . '/lib/auth.php';

$pdo = db_main();
$users = q_all('SELECT id, username, name, role, active FROM users ORDER BY id', [], $pdo);
if (!$users) { echo "Henüz kullanıcı yok. Paneli tarayıcıda açıp ilk hesabı oluşturun.\n"; exit(1); }

echo "Paneldeki kullanıcılar:\n";
foreach ($users as $u) echo "  - {$u['username']}  ({$u['name']}, " . (ROLES[$u['role']] ?? $u['role']) . ($u['active'] ? '' : ', pasif') . ")\n";
echo "\n";

$ask = function (string $q): string { echo $q; return trim((string)fgets(STDIN)); };
$username = mb_strtolower($ask('Şifresi sıfırlanacak kullanıcı adı: '));
$u = null;
foreach ($users as $x) if ($x['username'] === $username) $u = $x;
if (!$u) { echo "Bu kullanıcı adı yok.\n"; exit(1); }

$p1 = $ask('Yeni şifre (en az 8 karakter): ');
if ($e = validate_password($p1)) { echo "$e\n"; exit(1); }
$p2 = $ask('Yeni şifre (tekrar): ');
if ($p1 !== $p2) { echo "Şifreler aynı değil.\n"; exit(1); }

q('UPDATE users SET pass_hash = ?, active = 1 WHERE id = ?', [password_hash($p1, PASSWORD_DEFAULT), $u['id']], $pdo);
q('DELETE FROM login_attempts WHERE username = ?', [$username], $pdo);
audit('user', (int)$u['id'], 'sifre', "Şifre komut satırından sıfırlandı: {$u['username']}", null, null, $pdo, ['id' => null, 'name' => 'Sistem (şifre sıfırlama)']);
echo "\nTamam. {$u['username']} kullanıcısı yeni şifreyle giriş yapabilir.\n";
