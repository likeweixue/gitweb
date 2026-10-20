<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Config;
use App\Core\Database;
use App\Lib\Git;
use InvalidArgumentException;
use RuntimeException;

/**
 * Pull Request 模型
 */
final class PullRequest
{
    /** 下一个 PR 序号 */
    public static function nextNumber(int $repoId): int
    {
        $row = Database::fetch('SELECT MAX(number) AS m FROM pull_requests WHERE repo_id = ?', [$repoId]);
        return (int)($row['m'] ?? 0) + 1;
    }

    /** @return int 新 PR id */
    public static function create(array $data): int
    {
        $id = Database::insert('pull_requests', [
            'repo_id'     => (int)$data['repo_id'],
            'number'      => (int)$data['number'],
            'title'       => (string)$data['title'],
            'body'        => (string)($data['body'] ?? ''),
            'author_id'   => (int)$data['author_id'],
            'base_ref'    => (string)$data['base_ref'],
            'head_ref'    => (string)$data['head_ref'],
            'head_repo_id' => isset($data['head_repo_id']) && $data['head_repo_id'] !== null ? (int)$data['head_repo_id'] : null,
            'head_sha'    => '',
            'state'       => 'open',
        ]);
        return $id;
    }

    public static function find(int $id): ?array
    {
        return Database::fetch(
            'SELECT p.*, u.username AS author_name, u.display_name AS author_display_name
             FROM pull_requests p JOIN users u ON u.id = p.author_id WHERE p.id = ?',
            [$id]
        );
    }

    public static function findByNumber(int $repoId, int $number): ?array
    {
        return Database::fetch(
            'SELECT p.*, u.username AS author_name, u.display_name AS author_display_name
             FROM pull_requests p JOIN users u ON u.id = p.author_id
             WHERE p.repo_id = ? AND p.number = ?',
            [$repoId, $number]
        );
    }

    /** 列出某仓库的 PR（按状态：open / closed，closed 含 merged） */
    public static function forRepo(int $repoId, string $state = 'open', int $limit = 50): array
    {
        $where = 'p.repo_id = ?';
        $params = [$repoId];
        if ($state === 'closed') {
            $where .= " AND p.state IN ('closed', 'merged')";
        } else {
            $where .= " AND p.state = 'open'";
        }
        return Database::fetchAll(
            'SELECT p.*, u.username AS author_name, u.display_name AS author_display_name
             FROM pull_requests p JOIN users u ON u.id = p.author_id
             WHERE ' . $where . ' ORDER BY p.created_at DESC LIMIT ' . (int)$limit,
            $params
        );
    }

    public static function openCount(int $repoId): int
    {
        return Database::count('pull_requests', 'repo_id = ? AND state = ?', [$repoId, 'open']);
    }

    public static function countByState(int $repoId, string $state): int
    {
        if ($state === 'closed') {
            return Database::count('pull_requests', "repo_id = ? AND state IN ('closed','merged')", [$repoId]);
        }
        return Database::count('pull_requests', 'repo_id = ? AND state = ?', [$repoId, $state]);
    }

    /** 讨论评论 */
    public static function comments(int $prId): array
    {
        return Database::fetchAll(
            'SELECT c.*, u.username, u.display_name
             FROM pull_request_comments c JOIN users u ON u.id = c.author_id
             WHERE c.pr_id = ? ORDER BY c.created_at ASC',
            [$prId]
        );
    }

    public static function addComment(int $prId, int $authorId, string $body): void
    {
        if (trim($body) === '') {
            throw new InvalidArgumentException('评论内容不能为空');
        }
        Database::insert('pull_request_comments', [
            'pr_id'      => $prId,
            'author_id'  => $authorId,
            'body'       => trim($body),
        ]);
        self::touch($prId);
    }

    /** 审查（approved / changes_requested / commented） */
    public static function reviews(int $prId): array
    {
        return Database::fetchAll(
            'SELECT r.*, u.username, u.display_name
             FROM pull_request_reviews r JOIN users u ON u.id = r.author_id
             WHERE r.pr_id = ? ORDER BY r.created_at ASC',
            [$prId]
        );
    }

    /** 每个审查者的最新审查状态（用于显示） */
    public static function latestReviewsByUser(int $prId): array
    {
        $all = self::reviews($prId);
        $map = [];
        foreach ($all as $r) {
            $map[(int)$r['author_id']] = $r;
        }
        return $map;
    }

    public static function addReview(int $prId, int $authorId, string $state, string $body): void
    {
        if (!in_array($state, ['approved', 'changes_requested', 'commented'], true)) {
            throw new InvalidArgumentException('无效的审查状态');
        }
        Database::insert('pull_request_reviews', [
            'pr_id'      => $prId,
            'author_id'  => $authorId,
            'state'      => $state,
            'body'       => trim($body),
        ]);
        self::touch($prId);
    }

    /** head 仓库（fork 时为源，否则与 base 同仓） */
    public static function headRepo(array $pr): ?array
    {
        if (!empty($pr['head_repo_id']) && (int)$pr['head_repo_id'] !== (int)$pr['repo_id']) {
            return Repository::find((int)$pr['head_repo_id']);
        }
        return Repository::find((int)$pr['repo_id']);
    }

    /**
     * 合并 PR（调用 Git 在 bare 仓库执行合并，并改写 base 分支）
     * 返回新分支顶端 SHA
     *
     * @param array<string,string> $author
     */
    public static function merge(int $prId, int $userId, string $strategy, array $author): string
    {
        $pr = self::find($prId);
        if ($pr === null) {
            throw new InvalidArgumentException('PR 不存在');
        }
        if ((string)$pr['state'] !== 'open') {
            throw new InvalidArgumentException('只有开放的 PR 可以合并');
        }
        $baseRepo = Repository::find((int)$pr['repo_id']);
        if ($baseRepo === null) {
            throw new RuntimeException('仓库不存在');
        }
        $headRepo = self::headRepo($pr);
        if ($headRepo === null) {
            throw new RuntimeException('源仓库不存在');
        }

        $baseDir = Repository::path($baseRepo);
        $headDir = Repository::path($headRepo);
        $headSha = Git::resolveRef($headDir, (string)$pr['head_ref']);
        if ($headSha === null) {
            throw new RuntimeException('head 分支不存在或已被删除');
        }

        // 跨仓库（fork）时，先将 head 抓取到 base 仓库的临时引用
        $tmpRef = null;
        if ($headDir !== $baseDir) {
            $tmpRef = 'refs/gw-pr-' . $prId;
            $fetched = Git::fetchRef($baseDir, $headDir, (string)$pr['head_ref'], $tmpRef);
            if ($fetched === null) {
                throw new RuntimeException('无法从源仓库抓取 head 分支');
            }
            $headSha = $fetched;
        }

        try {
            $newSha = Git::merge($baseDir, (string)$pr['base_ref'], $headSha, (string)$pr['title'], $author, $strategy);
        } finally {
            if ($tmpRef !== null) {
                Git::run(['-C', $baseDir, 'update-ref', '-d', $tmpRef]);
            }
        }

        Database::update('pull_requests', [
            'state'          => 'merged',
            'merge_strategy' => $strategy,
            'merged_by'      => $userId,
            'merged_at'      => date('Y-m-d H:i:s'),
        ], 'id = ?', [$prId]);

        Repository::refreshStats((int)$pr['repo_id']);
        return $newSha;
    }

    public static function close(int $prId, int $userId): void
    {
        $pr = self::find($prId);
        if ($pr === null || (string)$pr['state'] !== 'open') {
            return;
        }
        Database::update('pull_requests', [
            'state'     => 'closed',
            'closed_by' => $userId,
            'closed_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$prId]);
    }

    public static function reopen(int $prId): void
    {
        $pr = self::find($prId);
        if ($pr === null || (string)$pr['state'] === 'open') {
            return;
        }
        Database::update('pull_requests', [
            'state'     => 'open',
            'closed_by' => null,
            'closed_at' => null,
        ], 'id = ?', [$prId]);
    }

    /** 计算 ahead/behind 与是否可合并（无冲突） */
    public static function diverge(array $pr): array
    {
        $baseRepo = Repository::find((int)$pr['repo_id']);
        $headRepo = self::headRepo($pr);
        if ($baseRepo === null || $headRepo === null) {
            return ['ahead' => 0, 'behind' => 0, 'mergeable' => false, 'missing' => true];
        }
        $baseDir = Repository::path($baseRepo);
        $headDir = $headRepo === $baseRepo ? $baseDir : Repository::path($headRepo);

        $baseSha = Git::resolveRef($baseDir, (string)$pr['base_ref']);
        $headSha = Git::resolveRef($headDir, (string)$pr['head_ref']);
        if ($baseSha === null || $headSha === null) {
            return ['ahead' => 0, 'behind' => 0, 'mergeable' => false, 'missing' => true];
        }

        $ahead = (int)trim(Git::run(['-C', $headDir, 'rev-list', '--count', $baseSha . '..' . $headSha])['out'] ?? '0');
        $behind = (int)trim(Git::run(['-C', $headDir, 'rev-list', '--count', $headSha . '..' . $baseSha])['out'] ?? '0');

        // 跨仓库时需在 base 仓内检测冲突：临时抓取 head
        $tmpRef = null;
        $mergeable = false;
        try {
            $testDir = $baseDir;
            $testHead = $headSha;
            if ($headDir !== $baseDir) {
                $tmpRef = 'refs/gw-check-' . ((int)$pr['id']);
                $fetched = Git::fetchRef($baseDir, $headDir, (string)$pr['head_ref'], $tmpRef);
                if ($fetched === null) {
                    return ['ahead' => $ahead, 'behind' => $behind, 'mergeable' => false, 'missing' => true];
                }
                $testHead = $fetched;
            }
            $mb = Git::mergeBase($testDir, $baseSha, $testHead);
            if ($mb !== null && ($mb === $testHead || $mb === $baseSha)) {
                $mergeable = true; // 可 fast-forward
            } else {
                $r = Git::run(['-C', $testDir, 'merge-tree', '--write-tree', $baseSha, $testHead]);
                $mergeable = $r['code'] === 0;
            }
        } finally {
            if ($tmpRef !== null) {
                Git::run(['-C', $baseDir, 'update-ref', '-d', $tmpRef]);
            }
        }

        return ['ahead' => $ahead, 'behind' => $behind, 'mergeable' => $mergeable, 'missing' => false];
    }

    private static function touch(int $prId): void
    {
        Database::update('pull_requests', ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$prId]);
    }
}
