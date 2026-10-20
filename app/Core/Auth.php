<?php
declare(strict_types=1);

namespace App\Core;

use App\Model\User;

/**
 * 认证（会话 + 记住我）
 */
final class Auth
{
    private static ?array $cachedUser = null;
    private static bool $resolved = false;

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user === null ? null : (int)$user['id'];
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        return $user !== null && $user['role'] === 'admin';
    }

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$cachedUser;
        }
        self::$resolved = true;

        $id = Session::get('user_id');
        if ($id !== null) {
            $user = User::find((int)$id);
            if ($user !== null) {
                return self::$cachedUser = $user;
            }
            Session::forget('user_id');
        }

        $user = self::userFromRemember();
        return self::$cachedUser = $user;
    }

    public static function attempt(string $login, string $password, bool $remember = false): bool
    {
        $user = User::findByLogin($login);
        if ($user === null || !password_verify($password, (string)$user['password_hash'])) {
            return false;
        }
        if ($user['status'] === 'banned') {
            return false;
        }
        self::login($user, $remember);
        return true;
    }

    public static function login(array $user, bool $remember = false): void
    {
        Session::regenerate();
        Session::set('user_id', (int)$user['id']);
        self::$resolved = false;
        self::$cachedUser = $user;

        if ($remember && PHP_SAPI !== 'cli') {
            $days = (int)Config::get('security.remember_days', 30);
            $token = bin2hex(random_bytes(32));
            Database::insert('remember_tokens', [
                'user_id'     => (int)$user['id'],
                'token_hash'  => hash('sha256', $token),
                'expires_at'  => date('Y-m-d H:i:s', time() + $days * 86400),
            ]);
            setcookie('gitweb_remember', $token, [
                'expires'  => time() + $days * 86400,
                'path'     => (string)(Config::get('app.base_path', '') ?: '/'),
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => (new Request())->isHttps(),
            ]);
        }
    }

    public static function logout(): void
    {
        if (PHP_SAPI !== 'cli') {
            $cookie = $_COOKIE['gitweb_remember'] ?? '';
            if (is_string($cookie) && $cookie !== '') {
                Database::delete('remember_tokens', 'token_hash = ?', [hash('sha256', $cookie)]);
                setcookie('gitweb_remember', '', [
                    'expires'  => time() - 3600,
                    'path'     => (string)(Config::get('app.base_path', '') ?: '/'),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
        }
        Session::destroy();
        self::$resolved = false;
        self::$cachedUser = null;
    }

    private static function userFromRemember(): ?array
    {
        if (PHP_SAPI === 'cli') {
            return null;
        }
        $cookie = $_COOKIE['gitweb_remember'] ?? '';
        if (!is_string($cookie) || !preg_match('/^[a-f0-9]{64}$/', $cookie)) {
            return null;
        }
        $row = Database::fetch(
            'SELECT u.* FROM remember_tokens rt JOIN users u ON u.id = rt.user_id WHERE rt.token_hash = ? AND rt.expires_at > ?',
            [hash('sha256', $cookie), date('Y-m-d H:i:s')]
        );
        if ($row === null) {
            return null;
        }
        // 滚动续期
        Database::update('remember_tokens', ['expires_at' => date('Y-m-d H:i:s', time() + (int)Config::get('security.remember_days', 30) * 86400)], 'token_hash = ?', [hash('sha256', $cookie)]);
        return $row;
    }

    // ---------- 登录限制 ----------

    public static function tooManyAttempts(string $ip): bool
    {
        $max = (int)Config::get('security.login_max_attempts', 5);
        $minutes = (int)Config::get('security.login_lock_minutes', 15);
        $count = Database::count(
            'login_attempts',
            'ip = ? AND successful = 0 AND created_at > ?',
            [$ip, date('Y-m-d H:i:s', time() - $minutes * 60)]
        );
        return $count >= $max;
    }

    public static function recordAttempt(string $ip, string $username, bool $success): void
    {
        Database::insert('login_attempts', [
            'ip'         => $ip,
            'username'   => mb_substr($username, 0, 39),
            'successful' => $success ? 1 : 0,
        ]);
        if (random_int(1, 20) === 1) {
            Database::delete('login_attempts', 'created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
        }
    }
}
