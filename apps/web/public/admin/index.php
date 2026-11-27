<?php
// Eski "Gelen talepler" ekranı kapatıldı: talepler artık otel panelinde (Talepler bölümü).
// Veriler aynı veritabanında duruyor (../lux-private/requests.sqlite); eski bağlantılar ve bildirim mailleri buraya yönlenir.
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
$q = isset($_GET['q']) && is_string($_GET['q']) && preg_match('/^[a-f0-9]{24}$/D', $_GET['q']) ? '?q=' . $_GET['q'] : '';
header('Location: /panel/#/talepler' . $q, true, 302);
exit;
