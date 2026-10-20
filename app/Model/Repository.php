<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Config;
use App\Core\Database;
use App\Lib\Git;
use InvalidArgumentException;
use RuntimeException;

/**
 * 仓库模型（元数据 + bare 仓库目录管理）
 */
final class Repository
{
    private const SELECT_WITH_OWNER = 'r.*, u.username AS owner_name, u.display_name AS owner_display_name
        FROM repositories r JOIN users u ON u.id = r.owner_id';

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT ' . self::SELECT_WITH_OWNER . ' WHERE r.id = ?', [$id]);
    }

    public static function findByPath(string $owner, string $name): ?array
    {
        return Database::fetch(
            'SELECT ' . self::SELECT_WITH_OWNER . ' WHERE u.username = ? AND r.name = ?',
            [$owner, $name]
        );
    }

    /** 仓库磁盘路径（bare） */
    public static function path(array $repo): string
    {
        return rtrim((string)Config::get('git.repos_root'), '/') . '/' . (string)$repo['owner_name'] . '/' . (string)$repo['name'] . '.git';
    }

    /** 相对路径（owner/name.git），用于 HTTP 协议 PATH_INFO */
    public static function relPath(array $repo): string
    {
        return (string)$repo['owner_name'] . '/' . (string)$repo['name'] . '.git';
    }

    public static function nameTaken(int $ownerId, string $name): bool
    {
        return Database::count('repositories', 'owner_id = ? AND name = ?', [$ownerId, $name]) > 0;
    }

    // ------------------------------------------------------------ 创建 / 删除

    /**
     * @throws InvalidArgumentException
     */
    public static function create(int $ownerId, string $name, string $description, bool $isPrivate, bool $initReadme, string $gitignore = '', string $license = ''): int
    {
        $name = trim($name);
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/', $name) !== 1 || str_contains($name, '..')) {
            throw new InvalidArgumentException('仓库名只能包含字母、数字、点、下划线和连字符，且不能以点开头');
        }
        if (self::nameTaken($ownerId, $name)) {
            throw new InvalidArgumentException('仓库名已存在');
        }

        $id = Database::insert('repositories', [
            'owner_id'       => $ownerId,
            'name'           => $name,
            'description'    => mb_substr($description, 0, 500),
            'is_private'     => $isPrivate ? 1 : 0,
            'default_branch' => 'main',
            'has_issues'     => 1,
        ]);

        $repo = self::find($id);
        if ($repo === null) {
            throw new RuntimeException('仓库创建后读取失败');
        }
        Git::initBare(self::path($repo));

        if ($initReadme) {
            $owner = User::find($ownerId);
            $files = [
                'README.md' => "# $name\n\n" . ($description !== '' ? $description . "\n\n" : '') . "由 Gitweb 创建。\n",
            ];
            if ($gitignore !== '') {
                $files['.gitignore'] = $gitignore;
            }
            if ($license !== '') {
                $files['LICENSE'] = $license;
            }
            Git::commitFiles(self::path($repo), $files, 'Initial commit', 'main', [
                'name'  => $owner['display_name'] !== '' ? $owner['display_name'] : $owner['username'],
                'email' => $owner['email'],
            ]);
        }

        self::refreshStats($id);
        return $id;
    }

    public static function delete(int $id): void
    {
        $repo = self::find($id);
        if ($repo === null) {
            return;
        }
        $path = self::path($repo);
        $root = realpath((string)Config::get('git.repos_root'));
        $real = realpath($path);
        if ($root !== false && $real !== false && str_starts_with($real, rtrim($root, '/') . '/')) {
            self::removeDir($real);
        }
        Database::delete('repositories', 'id = ?', [$id]);
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }

    public static function update(int $id, array $data): void
    {
        $allowed = ['description', 'homepage', 'is_private', 'default_branch', 'has_issues'];
        $fields = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[$field] = $data[$field];
            }
        }
        if ($fields !== []) {
            Database::update('repositories', $fields, 'id = ?', [$id]);
        }
    }

    public static function rename(int $id, string $newName): void
    {
        $newName = trim($newName);
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/', $newName) !== 1 || str_contains($newName, '..')) {
            throw new InvalidArgumentException('仓库名不合法');
        }
        $repo = self::find($id);
        if ($repo === null) {
            throw new InvalidArgumentException('仓库不存在');
        }
        if ($repo['name'] === $newName) {
            return;
        }
        if (self::nameTaken((int)$repo['owner_id'], $newName)) {
            throw new InvalidArgumentException('仓库名已存在');
        }
        $oldPath = self::path($repo);
        $newRepo = $repo;
        $newRepo['name'] = $newName;
        $newPath = self::path($newRepo);
        if (!@rename($oldPath, $newPath)) {
            throw new RuntimeException('仓库目录移动失败');
        }
        Database::update('repositories', ['name' => $newName], 'id = ?', [$id]);
    }

    public static function transfer(int $id, string $newOwnerUsername): void
    {
        $repo = self::find($id);
        if ($repo === null) {
            throw new InvalidArgumentException('仓库不存在');
        }
        $newOwner = User::findByUsername($newOwnerUsername);
        if ($newOwner === null) {
            throw new InvalidArgumentException('目标用户不存在');
        }
        if ((int)$newOwner['id'] === (int)$repo['owner_id']) {
            return;
        }
        if (self::nameTaken((int)$newOwner['id'], (string)$repo['name'])) {
            throw new InvalidArgumentException('目标用户已存在同名仓库');
        }
        $oldPath = self::path($repo);
        $newRepo = $repo;
        $newRepo['owner_name'] = $newOwnerUsername;
        $newPath = self::path($newRepo);
        $newOwnerDir = dirname($newPath);
        if (!is_dir($newOwnerDir)) {
            @mkdir($newOwnerDir, 0775, true);
        }
        if (!@rename($oldPath, $newPath)) {
            throw new RuntimeException('仓库目录移动失败');
        }
        Database::update('repositories', ['owner_id' => (int)$newOwner['id']], 'id = ?', [$id]);
    }

    // ------------------------------------------------------------ 权限

    public static function permission(?int $userId, array $repo): ?string
    {
        if ($userId === null) {
            return (int)$repo['is_private'] === 0 ? 'read' : null;
        }
        if ((int)$repo['owner_id'] === $userId) {
            return 'owner';
        }
        $row = Database::fetch('SELECT permission FROM collaborators WHERE repo_id = ? AND user_id = ?', [(int)$repo['id'], $userId]);
        if ($row !== null) {
            return (string)$row['permission'];
        }
        return (int)$repo['is_private'] === 0 ? 'read' : null;
    }

    public static function canRead(?int $userId, array $repo): bool
    {
        return self::permission($userId, $repo) !== null;
    }

    public static function canWrite(?int $userId, array $repo): bool
    {
        $permission = self::permission($userId, $repo);
        return $permission !== null && in_array($permission, ['owner', 'admin', 'write'], true);
    }

    // ------------------------------------------------------------ 协作者

    /** @return array<int, array<string, mixed>> */
    public static function collaborators(int $repoId): array
    {
        return Database::fetchAll(
            'SELECT c.permission, c.id AS collab_id, u.id, u.username, u.display_name
             FROM collaborators c JOIN users u ON u.id = c.user_id
             WHERE c.repo_id = ? ORDER BY c.id',
            [$repoId]
        );
    }

    public static function addCollaborator(int $repoId, string $username, string $permission): void
    {
        $user = User::findByUsername($username);
        if ($user === null) {
            throw new InvalidArgumentException('用户不存在');
        }
        if (!in_array($permission, ['read', 'write', 'admin'], true)) {
            throw new InvalidArgumentException('权限值无效');
        }
        $exists = Database::fetch('SELECT id FROM collaborators WHERE repo_id = ? AND user_id = ?', [$repoId, (int)$user['id']]);
        if ($exists !== null) {
            Database::update('collaborators', ['permission' => $permission], 'id = ?', [(int)$exists['id']]);
            return;
        }
        Database::insert('collaborators', ['repo_id' => $repoId, 'user_id' => (int)$user['id'], 'permission' => $permission]);
    }

    public static function removeCollaborator(int $repoId, int $userId): void
    {
        Database::delete('collaborators', 'repo_id = ? AND user_id = ?', [$repoId, $userId]);
    }

    // ------------------------------------------------------------ Star / Fork

    public static function isStarred(int $repoId, ?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }
        return Database::count('stars', 'repo_id = ? AND user_id = ?', [$repoId, $userId]) > 0;
    }

    public static function star(int $repoId, int $userId): void
    {
        if (self::isStarred($repoId, $userId)) {
            return;
        }
        Database::insert('stars', ['repo_id' => $repoId, 'user_id' => $userId]);
        self::syncStarCount($repoId);
    }

    public static function unstar(int $repoId, int $userId): void
    {
        Database::delete('stars', 'repo_id = ? AND user_id = ?', [$repoId, $userId]);
        self::syncStarCount($repoId);
    }

    private static function syncStarCount(int $repoId): void
    {
        $count = Database::count('stars', 'repo_id = ?', [$repoId]);
        Database::update('repositories', ['stars_count' => $count], 'id = ?', [$repoId]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function starredBy(int $userId, int $page, int $per): array
    {
        return Database::fetchAll(
            'SELECT ' . self::SELECT_WITH_OWNER . ' JOIN stars s ON s.repo_id = r.id
             WHERE s.user_id = ? AND r.is_private = 0
             ORDER BY s.created_at DESC LIMIT ' . (int)$per . ' OFFSET ' . (int)(max(1, $page) - 1) * (int)$per,
            [$userId]
        );
    }

    public static function starredCount(int $userId): int
    {
        return Database::count('stars', 'user_id = ?', [$userId]);
    }

    public static function fork(int $sourceId, int $ownerId, string $name): int
    {
        $source = self::find($sourceId);
        if ($source === null) {
            throw new InvalidArgumentException('源仓库不存在');
        }
        $name = trim($name);
        if ($name === '') {
            $name = (string)$source['name'];
        }
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/', $name) !== 1 || str_contains($name, '..')) {
            throw new InvalidArgumentException('仓库名不合法');
        }
        if (self::nameTaken($ownerId, $name)) {
            throw new InvalidArgumentException('仓库名已存在');
        }

        $id = Database::insert('repositories', [
            'owner_id'       => $ownerId,
            'name'           => $name,
            'description'    => 'Forked from ' . $source['owner_name'] . '/' . $source['name'],
            'is_private'     => (int)$source['is_private'] === 1 ? 0 : 0,
            'is_fork'        => 1,
            'parent_id'      => $sourceId,
            'default_branch' => 'main',
            'has_issues'     => 1,
        ]);
        $repo = self::find($id);
        if ($repo === null) {
            throw new RuntimeException('Fork 创建失败');
        }
        Git::cloneBare(self::path($source), self::path($repo));
        $default = Git::defaultBranch(self::path($repo));
        if ($default !== null) {
            Database::update('repositories', ['default_branch' => $default], 'id = ?', [$id]);
        }
        Database::update('repositories', ['forks_count' => (int)$source['forks_count'] + 1], 'id = ?', [$sourceId]);
        self::refreshStats($id);
        return $id;
    }

    // ------------------------------------------------------------ 查询

    /** @return array{items: array<int, array<string, mixed>>, total: int} */
    public static function publicFeed(int $page, int $per, string $q = '', string $sort = 'updated'): array
    {
        $where = 'r.is_private = 0';
        $params = [];
        if ($q !== '') {
            $where .= ' AND (r.name LIKE ? OR r.description LIKE ? OR u.username LIKE ?)';
            $like = '%' . $q . '%';
            $params = [$like, $like, $like];
        }
        $order = match ($sort) {
            'created' => 'r.created_at DESC',
            'stars'   => 'r.stars_count DESC',
            default   => 'COALESCE(r.pushed_at, r.created_at) DESC',
        };
        $total = Database::count('repositories r JOIN users u ON u.id = r.owner_id', $where, $params);
        $items = Database::fetchAll(
            'SELECT ' . self::SELECT_WITH_OWNER . ' WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . (int)$per . ' OFFSET ' . (int)(max(1, $page) - 1) * (int)$per,
            $params
        );
        return ['items' => $items, 'total' => $total];
    }

    /** @return array{items: array<int, array<string, mixed>>, total: int} */
    public static function forUser(int $userId, ?int $viewerId, int $page, int $per, string $sort = 'updated'): array
    {
        $where = 'r.owner_id = ?';
        $params = [$userId];
        if ($viewerId !== $userId) {
            $where .= ' AND r.is_private = 0';
        }
        $order = match ($sort) {
            'created' => 'r.created_at DESC',
            default   => 'COALESCE(r.pushed_at, r.updated_at) DESC',
        };
        $total = Database::count('repositories r JOIN users u ON u.id = r.owner_id', $where, $params);
        $items = Database::fetchAll(
            'SELECT ' . self::SELECT_WITH_OWNER . ' WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . (int)$per . ' OFFSET ' . (int)(max(1, $page) - 1) * (int)$per,
            $params
        );
        return ['items' => $items, 'total' => $total];
    }

    /** @return array{items: array<int, array<string, mixed>>, total: int} */
    public static function all(int $page, int $per, string $q = ''): array
    {
        $where = '1';
        $params = [];
        if ($q !== '') {
            $where = '(r.name LIKE ? OR u.username LIKE ?)';
            $like = '%' . $q . '%';
            $params = [$like, $like];
        }
        $total = Database::count('repositories r JOIN users u ON u.id = r.owner_id', $where, $params);
        $items = Database::fetchAll(
            'SELECT ' . self::SELECT_WITH_OWNER . ' WHERE ' . $where . ' ORDER BY r.id DESC LIMIT ' . (int)$per . ' OFFSET ' . (int)(max(1, $page) - 1) * (int)$per,
            $params
        );
        return ['items' => $items, 'total' => $total];
    }

    public static function countAll(): int
    {
        return Database::count('repositories');
    }

    public static function countPublic(): int
    {
        return Database::count('repositories', 'is_private = 0');
    }

    // ------------------------------------------------------------ 统计

    /** 刷新仓库统计（体积、推送时间、issue 数） */
    public static function refreshStats(int $id): void
    {
        $repo = self::find($id);
        if ($repo === null) {
            return;
        }
        $path = self::path($repo);
        $data = ['size_bytes' => 0, 'pushed_at' => null];
        if (is_dir($path) && !Git::isEmpty($path)) {
            $stats = Git::repoStats($path);
            $data['size_bytes'] = $stats['size_bytes'];
            $branch = (string)$repo['default_branch'];
            $last = Git::lastCommitForPath($path, $branch);
            $data['pushed_at'] = $last !== null ? $last['date'] : date('Y-m-d H:i:s');
        }
        $data['issues_count'] = Database::count('issues', 'repo_id = ? AND state = \'open\'', [$id]);
        Database::update('repositories', $data, 'id = ?', [$id]);
    }

    public static function findByDiskPath(string $path): ?array
    {
        $root = realpath((string)Config::get('git.repos_root'));
        $real = realpath($path);
        if ($root === false || $real === false || !str_starts_with($real, rtrim($root, '/') . '/')) {
            return null;
        }
        $rel = ltrim(substr($real, strlen(rtrim($root, '/'))), '/');
        $rel = preg_replace('/\.git$/', '', $rel) ?? '';
        $parts = explode('/', $rel);
        if (count($parts) !== 2) {
            return null;
        }
        return self::findByPath($parts[0], $parts[1]);
    }
}
