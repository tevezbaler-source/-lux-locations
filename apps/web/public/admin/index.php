<?php
// Eski ayrı "Gelen talepler" paneli kapatıldı: talepler artık tek panelde (Site düzenleme → Talepler).
// Veriler aynı veritabanında duruyor (../lux-private/requests.sqlite); eski bağlantılar buraya yönlenir.
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Location: /admin/site/?page=talepler', true, 302);
exit;
