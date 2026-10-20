<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Database;

/**
 * Issue 标签
 */
final class Label
{
    /** @return array<int, string> 预设颜色 */
    public static function defaultColors(): array
    {
        return ['#0969da', '#1a7f37', '#cf222e', '#9a6700', '#8250df', '#0550ae', '#57606a', '#d4a72c'];
    }

    /** @return array<int, array<string, mixed>> */
    public static function forRepo(int $repoId): array
    {
        return Database::fetchAll('SELECT * FROM labels WHERE repo_id = ? ORDER BY name', [$repoId]);
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM labels WHERE id = ?', [$id]);
    }

    public static function create(int $repoId, string $name, string $color): int
    {
        $name = mb_substr(trim($name), 0, 60);
        if ($name === '') {
            throw new \InvalidArgumentException('标签名不能为空');
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#0969da';
        }
        if (Database::count('labels', 'repo_id = ? AND name = ?', [$repoId, $name]) > 0) {
            throw new \InvalidArgumentException('标签已存在');
        }
        return Database::insert('labels', ['repo_id' => $repoId, 'name' => $name, 'color' => $color]);
    }

    public static function delete(int $id, int $repoId): void
    {
        Database::delete('labels', 'id = ? AND repo_id = ?', [$id, $repoId]);
    }
}
