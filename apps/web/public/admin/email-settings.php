<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/notifications.php';
header('X-Robots-Tag: noindex, nofollow');

if (time() - (int)($_SESSION['admin_seen'] ?? 0) >= 3600) redirect('/admin/');
$_SESSION['admin_seen'] = time();
$status = '';
try {
    $db = database();
    $settings = notificationSettings($db);
    if (post()) {
        $action = field('action', 20);
        if ($action === 'disable') {
            $db->exec('DELETE FROM notification_settings WHERE id=1');
            $settings = [];
            $status = 'E-posta bildirimleri kapatıldı.';
        } elseif ($action === 'test' && $settings) {
            if (limited($db, 'mail-test', 4)) {
                $status = 'Test sınırına ulaşıldı. Birkaç dakika sonra tekrar deneyin.';
            } else {
                sendNotification($settings, 'Lux Locations · Bildirim testi',
                    "E-posta bildirimi testi. Yeni talepler için bu adrese bildirim gönderilecektir.");
                $status = 'Test mesajı e-posta sunucusuna teslim edildi. Gelen kutunuzu kontrol edin.';
            }
        } elseif ($action === 'save') {
            $host = strtolower(field('host', 253));
            $port = (int)field('port', 3);
            $recipient = field('recipient', 254);
            $sender = field('sender', 254);
            $username = field('username', 254);
            $password = field('password', 1024);
            if ($password === '' && $host === ($settings['host'] ?? '') && $username === ($settings['username'] ?? '')) {
                $password = $settings['password'];
            }
            if (!filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || !str_contains($host, '.') ||
                filter_var($host, FILTER_VALIDATE_IP) || !in_array($port, [465,587], true) || !$username || !$password ||
                !filter_var($recipient, FILTER_VALIDATE_EMAIL) || !filter_var($sender, FILTER_VALIDATE_EMAIL)) {
                $status = 'Alıcı, gönderici ve SMTP bilgilerini kontrol edin.';
            } else {
                $candidate = compact('recipient','sender','host','port','username','password');
                if (limited($db, 'mail-test', 4)) {
                    $status = 'Test sınırına ulaşıldı. Birkaç dakika sonra tekrar deneyin.';
                } else {
                    sendNotification($candidate, 'Lux Locations · Bildirim testi',
                        "Bu test mesajını aldıysanız yeni rezervasyon bildirimleri için gönderim bağlantısı çalışıyor.");
                    $db->prepare('INSERT OR REPLACE INTO notification_settings VALUES (1,?,?,?,?,?,?)')->execute(array_values($candidate));
                    $settings = $candidate;
                    $status = 'Bildirimler açıldı. Test mesajı e-posta sunucusuna teslim edildi.';
                }
            }
        }
    }
} catch (Throwable $exception) {
    error_log('Lux email settings operation failed: ' . get_class($exception));
    $status = 'SMTP bağlantısı doğrulanamadı. Gönderici, sunucu, port ve erişim bilgilerini kontrol edin.';
}
$settings ??= [];
startPage('E-posta bildirimleri','YÖNETİM PANELİ');
?>
<p><a href="/admin/">Gelen taleplere dön</a></p>
<p>Yeni bir talep kaydedildiğinde hizmet türü, talep numarası ve panel bağlantısı gönderilir.</p>
<p>Bildirimler <strong><?= $settings ? 'açık' : 'kapalı' ?></strong>.</p>
<?php if ($status): ?><p role="status"><?= escape($status) ?></p><?php endif; ?>
<form method="post" class="stack">
<?php csrf(); ?>
<input type="hidden" name="action" value="save">
<label>Bildirim alıcısı<input name="recipient" type="email" required maxlength="254" value="<?= escape($settings['recipient'] ?? 'reservation@luxlocationstours.com') ?>"></label>
<label>Yetkili gönderici adresi<input name="sender" type="email" required maxlength="254" value="<?= escape($settings['sender'] ?? '') ?>"></label>
<label>SMTP sunucusu<input name="host" required maxlength="253" value="<?= escape($settings['host'] ?? '') ?>"></label>
<label>SMTP portu<select name="port"><option value="587">587 · STARTTLS</option><option value="465" <?= ($settings['port'] ?? 0) == 465 ? 'selected' : '' ?>>465 · TLS</option></select></label>
<label>SMTP kullanıcı adı<input name="username" required maxlength="254" autocomplete="off" value="<?= escape($settings['username'] ?? '') ?>"></label>
<label>SMTP parolası veya sağlayıcının SMTP anahtarı<input name="password" type="password" maxlength="1024" autocomplete="new-password" <?= $settings ? '' : 'required' ?>></label>
<button>Test gönder ve etkinleştir</button>
</form>
<?php if ($settings): ?>
<form method="post"><?php csrf(); ?><button name="action" value="test">Test e-postası gönder</button> <button name="action" value="disable">Bildirimleri kapat</button></form>
<?php endif; ?>
</main></body></html>

