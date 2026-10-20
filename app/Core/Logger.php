<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 简易文件日志
 */
final class Logger
{
    public static function write(string $message, string $channel = 'app'): void
    {
        $dir = GW_STORAGE . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents($dir . '/' . $channel . '-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function error(string $message): void
    {
        self::write($message, 'error');
    }

    public static function info(string $message): void
    {
        self::write($message, 'app');
    }
}
