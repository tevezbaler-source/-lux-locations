<?php
// Tüm kayıtların JSON yedeği (şifre hash'leri hariç).
if (!defined('LX_PANEL')) { http_response_code(403); exit; }

function backup_json(PDO $pdo): string {
    $tables = ['users', 'hotels', 'hotel_rates', 'hotel_allotments', 'agencies', 'bookings', 'booking_rooms', 'booking_free', 'booking_services', 'booking_guests', 'booking_extra', 'fx_rates', 'notes', 'payments', 'mail_log', 'talep_log', 'audit_log'];
    $out = ['app' => 'otel-paneli', 'created_at' => date('Y-m-d H:i:s'), 'tables' => []];
    foreach ($tables as $t) {
        $cols = $t === 'users' ? 'id, username, name, role, active, created_at, last_login' : '*';
        $out['tables'][$t] = $pdo->query("SELECT $cols FROM $t ORDER BY " . ($t === 'booking_extra' ? 'booking_id' : 'id'))->fetchAll(PDO::FETCH_ASSOC);
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
