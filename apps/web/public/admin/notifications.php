<?php
declare(strict_types=1);

function notificationSettings(PDO $db): array {
    $db->exec('CREATE TABLE IF NOT EXISTS notification_settings (
        id INTEGER PRIMARY KEY CHECK (id=1), recipient TEXT NOT NULL, sender TEXT NOT NULL,
        host TEXT NOT NULL, port INTEGER NOT NULL, username TEXT NOT NULL, password TEXT NOT NULL
    )');
    return $db->query('SELECT * FROM notification_settings WHERE id=1')->fetch() ?: [];
}

function sendNotification(array $settings, string $subject, string $message): void {
    foreach (['Exception','SMTP','PHPMailer'] as $file) {
        require_once __DIR__ . '/mail-lib/' . $file . '.php';
    }
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    require_once __DIR__ . '/hosting-smtp.php';
    $mail->setSMTPInstance(new LuxHostingSMTP());
    $mail->isSMTP();
    $mail->Host = $settings['host'];
    $mail->Port = (int)$settings['port'];
    $mail->SMTPAuth = true;
    $mail->Username = $settings['username'];
    $mail->Password = $settings['password'];
    $mail->SMTPSecure = $mail->Port === 465 ? 'ssl' : 'tls';
    $mail->Timeout = 5;
    $mail->Timelimit = 5;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($settings['sender'], 'Lux Locations');
    $mail->addAddress($settings['recipient']);
    $mail->Subject = $subject;
    $mail->Body = $message;
    $mail->send();
}

function notifyNewRequest(PDO $db, string $id, string $category): void {
    try {
        $settings = notificationSettings($db);
        if (!$settings) return;
        sendNotification($settings, 'Yeni rezervasyon talebi · ' . CATEGORIES[$category],
            "Lux Locations paneline yeni bir talep geldi.\n\nHizmet: " . CATEGORIES[$category] .
            "\nTalep numarası: " . $id . "\n\nhttps://luxlocationstours.com/admin/?q=" . $id);
    } catch (Throwable $exception) {
        error_log('Lux email notification failed for request ' . $id . ': ' . get_class($exception));
    }
}

