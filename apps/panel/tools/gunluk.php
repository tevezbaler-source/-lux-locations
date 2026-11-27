<?php
// Günlük yedek ve opsiyon hatırlatması (isteğe bağlı zamanlanmış görev için): php tools/gunluk.php [--zorla]
// Panel bunu zaten her gün ilk kullanımda kendisi çalıştırır; aynı gün ikinci kez çalışmaz (--zorla hariç).
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('LX_PANEL', 1);
date_default_timezone_set('Europe/Istanbul');
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?? '' ?: dirname(__DIR__, 2);
foreach (['db', 'auth', 'calc', 'data', 'mail', 'talepler', 'gunluk'] as $f) require dirname(__DIR__) . "/lib/$f.php";
$r = gunluk_calistir(in_array('--zorla', $argv, true));
echo $r === null ? "Bugün zaten çalıştı (ya da saat 07:00'den önce).\n" : json_encode($r, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
