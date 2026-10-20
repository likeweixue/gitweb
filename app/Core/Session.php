<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 会话管理
 */
final class Session
{
    private static bool $started = false;

    public static function start(array $config = []): void
    {
        if (self::$started || PHP_SAPI === 'cli') {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        session_name((string)($config['name'] ?? 'gitweb_session'));
        $basePath = (string)Config::get('app.base_path', '');
        session_set_cookie_params([
            'lifetime' => (int)($config['lifetime'] ?? 7200),
            'path'     => $basePath !== '' ? $basePath : '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (new Request())->isHttps(),
        ]);
        session_start();
        self::$started = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return array<int, array{type: string, message: string}> */
    public static function takeFlashes(): array
    {
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flashes;
    }

    public static function regenerate(): void
    {
        if (self::$started) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        if (!self::$started) {
            return;
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
        self::$started = false;
    }
}
