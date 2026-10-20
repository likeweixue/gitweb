<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Database;

/**
 * SSH 公钥模型
 */
final class SshKey
{
    private const ALLOWED_TYPES = [
        'ssh-ed25519',
        'ssh-rsa',
        'ecdsa-sha2-nistp256',
        'ecdsa-sha2-nistp384',
        'ecdsa-sha2-nistp521',
        'ssh-dss',
        'sk-ssh-ed25519@openssh.com',
        'sk-ecdsa-sha2-nistp256@openssh.com',
    ];

    /** 规范化公钥文本（压缩空白） */
    public static function normalize(string $publicKey): string
    {
        $parts = preg_split('/\s+/', trim($publicKey)) ?: [];
        if (count($parts) < 2) {
            return '';
        }
        return $parts[0] . ' ' . $parts[1] . (isset($parts[2]) ? ' ' . $parts[2] : '');
    }

    /** 计算 SHA256 指纹（与 ssh-keygen -l 默认输出一致） */
    public static function fingerprintOf(string $publicKey): ?string
    {
        $parts = preg_split('/\s+/', trim($publicKey)) ?: [];
        if (count($parts) < 2) {
            return null;
        }
        if (!in_array($parts[0], self::ALLOWED_TYPES, true)) {
            return null;
        }
        $blob = base64_decode($parts[1], true);
        if ($blob === false || $blob === '' || strlen($blob) < 8) {
            return null;
        }
        // 校验 blob 内嵌类型与声明一致
        $len = unpack('N', substr($blob, 0, 4))[1] ?? 0;
        if ($len < 1 || $len > 64 || substr($blob, 4, $len) !== $parts[0]) {
            return null;
        }
        $digest = hash('sha256', $blob, true);
        return 'SHA256:' . rtrim(str_replace(['+', '/'], ['-', '_'], base64_encode($digest)), '=');
    }

    public static function allForUser(int $userId): array
    {
        return Database::fetchAll('SELECT * FROM ssh_keys WHERE user_id = ? ORDER BY id DESC', [$userId]);
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM ssh_keys WHERE id = ?', [$id]);
    }

    public static function create(int $userId, string $title, string $publicKey): int
    {
        $fingerprint = self::fingerprintOf($publicKey);
        if ($fingerprint === null) {
            throw new \InvalidArgumentException('SSH 公钥格式不正确（支持 ssh-ed25519 / ssh-rsa / ecdsa-*）');
        }
        if (Database::count('ssh_keys', 'fingerprint = ?', [$fingerprint]) > 0) {
            throw new \InvalidArgumentException('该公钥已被添加（可能属于其他账号）');
        }
        return Database::insert('ssh_keys', [
            'user_id'     => $userId,
            'title'       => mb_substr($title, 0, 120),
            'fingerprint' => $fingerprint,
            'public_key'  => self::normalize($publicKey),
        ]);
    }

    public static function delete(int $id, int $userId): void
    {
        Database::delete('ssh_keys', 'id = ? AND user_id = ?', [$id, $userId]);
    }

    public static function touch(int $id): void
    {
        Database::update('ssh_keys', ['last_used_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
    }
}
