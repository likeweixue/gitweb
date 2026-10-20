<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Database;
use InvalidArgumentException;

/**
 * Release（基于 git tag + 备注）
 */
final class Release
{
    /** @return array<int, array<string, mixed>> */
    public static function forRepo(int $repoId): array
    {
        return Database::fetchAll(
            'SELECT r.*, u.username AS author_name FROM releases r
             JOIN users u ON u.id = r.author_id
             WHERE r.repo_id = ? AND r.is_draft = 0
             ORDER BY r.created_at DESC',
            [$repoId]
        );
    }

    public static function findByTag(int $repoId, string $tag): ?array
    {
        return Database::fetch('SELECT * FROM releases WHERE repo_id = ? AND tag_name = ?', [$repoId, $tag]);
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM releases WHERE id = ?', [$id]);
    }

    public static function latest(int $repoId): ?array
    {
        return Database::fetch(
            'SELECT r.*, u.username AS author_name FROM releases r
             JOIN users u ON u.id = r.author_id
             WHERE r.repo_id = ? AND r.is_draft = 0 AND r.is_prerelease = 0
             ORDER BY r.created_at DESC LIMIT 1',
            [$repoId]
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function create(int $repoId, string $tagName, string $name, string $body, int $authorId, bool $prerelease = false): int
    {
        $tagName = trim($tagName);
        if ($tagName === '' || mb_strlen($tagName) > 100) {
            throw new InvalidArgumentException('标签名不合法');
        }
        if (self::findByTag($repoId, $tagName) !== null) {
            throw new InvalidArgumentException('该标签已有 Release');
        }
        return Database::insert('releases', [
            'repo_id'        => $repoId,
            'tag_name'       => $tagName,
            'name'           => mb_substr($name !== '' ? $name : $tagName, 0, 150),
            'body'           => $body,
            'author_id'      => $authorId,
            'is_prerelease'  => $prerelease ? 1 : 0,
        ]);
    }

    public static function delete(int $id, int $repoId): void
    {
        Database::delete('releases', 'id = ? AND repo_id = ?', [$id, $repoId]);
    }
}
