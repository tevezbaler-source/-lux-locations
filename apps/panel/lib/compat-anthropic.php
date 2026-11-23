<?php
// Bazı paylaşımlı sunucular (IHS dahil) php_uname() işlevini kapatır (disable_functions).
// Anthropic kütüphanesi bunu Anthropic\Core içinde ad alanı belirtmeden çağırır;
// PHP önce aynı ad alanındaki işlevi aradığı için buradaki yedek devreye girer.
namespace Anthropic\Core;

if (!\function_exists('php_uname') && !\function_exists(__NAMESPACE__ . '\php_uname')) {
    function php_uname(string $mode = 'a'): string
    {
        return match ($mode) {
            's' => \PHP_OS_FAMILY,
            'm' => \PHP_INT_SIZE === 8 ? 'x86_64' : 'i686',
            default => \PHP_OS,
        };
    }
}
