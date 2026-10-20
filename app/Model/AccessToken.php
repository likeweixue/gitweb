<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Database;

/**
 * 个人访问令牌（HTTPS git 认证，格式 gw_ + 32 位 hex）
 */
final class AccessToken
{
    public static function generate(): string
    {
        return 'gw_' . bin2hex(random_bytes(16));
    }

    /** @return array{id: int, token: string} */
    public static function create(int $userId, string $name): array
    {
        $token = self::generate();
        $id = Database::insert('access_tokens', [
            'user_id'    => $userId,
            'name'       => mb_substr($name, 0, 120),
            'token_hash' => hash('sha256', $token),
            'scopes'     => 'repo',
        ]);
        return ['id' => $id, 'token' => $token];
    }

    /** 校验令牌，成功返回用户信息 */
    public static function verify(string $token): ?array
    {
        if (preg_match('/^gw_[a-f0-9]{32}$/', $token) !== 1) {
            return null;
        }
        $row = Database::fetch(
            'SELECT u.*, t.id AS token_id FROM access_tokens t
             JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ? AND u.status = \'active\'',
            [hash('sha256', $token)]
        );
        if ($row === null) {
            return null;
        }
        if (!empty($row['expires_at']) && strtotime((string)$row['expires_at']) < time()) {
            return null;
        }
        self::touch((int)$row['token_id']);
        return $row;
    }

    public static function allForUser(int $userId): array
    {
        return Database::fetchAll(
            'SELECT id, name, scopes, last_used_at, created_at FROM access_tokens WHERE user_id = ? ORDER BY id DESC',
            [$userId]
        );
    }

    public static function delete(int $id, int $userId): void
    {
        Database::delete('access_tokens', 'id = ? AND user_id = ?', [$id, $userId]);
    }

    public static function touch(int $id): void
    {
        Database::update('access_tokens', ['last_used_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
    }
}
