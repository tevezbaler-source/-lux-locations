<?php
// Gece yedeği: php tools/backup.php  (cPanel "Cron Jobs" ile her gece çalıştırın)
// data/backups/ altına sıkıştırılmış JSON yazar, 60 günden eskileri siler.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('LX_PANEL', 1);
date_default_timezone_set('Europe/Istanbul');
require dirname(__DIR__) . '/lib/db.php';
require dirname(__DIR__) . '/lib/backup.php';
$dir = dirname(__DIR__) . '/data/backups';
if (!is_dir($dir)) mkdir($dir, 0775, true);
$file = $dir . '/yedek-' . date('Y-m-d-His') . '.json.gz';
file_put_contents($file, gzencode(backup_json(db_main()), 9));
foreach (glob($dir . '/yedek-*.json.gz') as $f) if (filemtime($f) < time() - 60 * 86400) unlink($f);
echo "Yedek yazıldı: $file\n";
