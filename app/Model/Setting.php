<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Database;

/**
 * 站点设置（KV 存储，带请求级缓存）
 */
final class Setting
{
    /** @var array<string, string> */
    private static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }
        $row = Database::fetch('SELECT value FROM settings WHERE name = ?', [$key]);
        if ($row === null) {
            return $default;
        }
        return self::$cache[$key] = (string)$row['value'];
    }

    public static function set(string $key, string $value): void
    {
        $exists = Database::count('settings', 'name = ?', [$key]) > 0;
        if ($exists) {
            Database::update('settings', ['value' => $value], 'name = ?', [$key]);
        } else {
            Database::insert('settings', ['name' => $key, 'value' => $value]);
        }
        self::$cache[$key] = $value;
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        $out = [];
        foreach (Database::fetchAll('SELECT name, value FROM settings') as $row) {
            $out[(string)$row['name']] = (string)$row['value'];
            self::$cache[(string)$row['name']] = (string)$row['value'];
        }
        return $out;
    }
}
