<?php
// Otel paneli: tek sayfa. Veriler api.php üzerinden gelir.
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
$v = @filemtime(__DIR__ . '/assets/app.js') . '-' . @filemtime(__DIR__ . '/assets/app.css');
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Otel Paneli · Lux Locations</title>
<link rel="stylesheet" href="assets/app.css?v=<?= htmlspecialchars($v) ?>">
</head>
<body>
<div id="app"><div class="boot">Yükleniyor…</div></div>
<div id="toasts" aria-live="polite"></div>
<script src="assets/guide.js?v=<?= htmlspecialchars($v) ?>"></script>
<script src="assets/app.js?v=<?= htmlspecialchars($v) ?>"></script>
</body>
</html>
