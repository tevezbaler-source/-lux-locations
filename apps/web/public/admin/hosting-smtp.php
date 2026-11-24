<?php
declare(strict_types=1);

require_once __DIR__ . '/mail-lib/SMTP.php';

final class LuxHostingSMTP extends PHPMailer\PHPMailer\SMTP
{
    protected function get_lines()
    {
        if (function_exists('stream_select')) return parent::get_lines();
        if (!is_resource($this->smtp_conn)) return '';

        $reply = '';
        $deadline = microtime(true) + min($this->Timeout, $this->Timelimit ?: $this->Timeout);
        while (($remaining = $deadline - microtime(true)) > 0) {
            $seconds = (int)$remaining;
            stream_set_timeout($this->smtp_conn, $seconds, (int)(($remaining - $seconds) * 1000000));
            $line = fgets($this->smtp_conn, self::MAX_REPLY_LENGTH);
            if ($line === false) break;
            $reply .= $line;
            if (($line[3] ?? '') !== '-') break;
        }
        return $reply;
    }
}
