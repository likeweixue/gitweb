<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Database;

/**
 * 用户模型
 */
final class User
{
    /** 保留用户名（不可注册） */
    public static function reservedUsernames(): array
    {
        return [
            'login', 'logout', 'register', 'settings', 'admin', 'explore', 'new',
            'api', 'help', 'about', 'git', 'assets', 'favicon.ico', 'robots.txt',
        ];
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function findByUsername(string $username): ?array
    {
        return Database::fetch('SELECT * FROM users WHERE username = ?', [$username]);
    }

    /** 支持用户名或邮箱登录 */
    public static function findByLogin(string $login): ?array
    {
        return Database::fetch('SELECT * FROM users WHERE username = ? OR email = ?', [$login, $login]);
    }

    public static function usernameExists(string $username): bool
    {
        return Database::count('users', 'username = ?', [$username]) > 0;
    }

    public static function emailExists(string $email): bool
    {
        return Database::count('users', 'email = ?', [$email]) > 0;
    }

    public static function create(string $username, string $email, string $password, string $displayName = ''): int
    {
        return Database::insert('users', [
            'username'      => $username,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'display_name'  => $displayName !== '' ? $displayName : $username,
            'role'          => 'user',
            'status'        => 'active',
        ]);
    }

    public static function updateProfile(int $id, array $fields): void
    {
        $data = [];
        foreach (['display_name', 'bio', 'location', 'website'] as $field) {
            if (array_key_exists($field, $fields)) {
                $data[$field] = mb_substr((string)$fields[$field], 0, $field === 'bio' ? 500 : 190);
            }
        }
        if ($data !== []) {
            Database::update('users', $data, 'id = ?', [$id]);
        }
    }

    public static function updatePassword(int $id, string $password): void
    {
        Database::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$id]);
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::update('users', ['status' => $status], 'id = ?', [$id]);
    }

    public static function setRole(int $id, string $role): void
    {
        Database::update('users', ['role' => $role], 'id = ?', [$id]);
    }

    public static function countAll(): int
    {
        return Database::count('users');
    }

    /** @return array{items: array<int, array<string, mixed>>, total: int} */
    public static function paginate(int $page, int $per, string $q = ''): array
    {
        $where = '1';
        $params = [];
        if ($q !== '') {
            $where = '(username LIKE ? OR email LIKE ? OR display_name LIKE ?)';
            $like = '%' . $q . '%';
            $params = [$like, $like, $like];
        }
        $total = Database::count('users', $where, $params);
        $items = Database::fetchAll(
            'SELECT * FROM users WHERE ' . $where . ' ORDER BY id DESC LIMIT ' . (int)$per . ' OFFSET ' . (int)(max(1, $page) - 1) * (int)$per,
            $params
        );
        return ['items' => $items, 'total' => $total];
    }

    /** @return array<int, array<string, mixed>> */
    public static function search(string $q, int $limit = 20): array
    {
        if ($q === '') {
            return [];
        }
        return Database::fetchAll(
            'SELECT id, username, display_name, bio FROM users WHERE username LIKE ? ORDER BY username LIMIT ' . (int)$limit,
            ['%' . $q . '%']
        );
    }

    public static function publicRepoCount(int $id): int
    {
        return Database::count('repositories', 'owner_id = ? AND is_private = 0', [$id]);
    }
}
