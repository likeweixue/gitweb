<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Database;

/**
 * Issue 模型
 */
final class Issue
{
    private const SELECT = 'i.*, u.username AS author_name, u.display_name AS author_display
        FROM issues i JOIN users u ON u.id = i.author_id';

    /** @return array{items: array<int, array<string, mixed>>, total: int} */
    public static function forRepo(int $repoId, string $state = 'open', int $page = 1, int $per = 25, string $q = ''): array
    {
        $where = 'i.repo_id = ?';
        $params = [$repoId];
        if ($state === 'open' || $state === 'closed') {
            $where .= ' AND i.state = ?';
            $params[] = $state;
        }
        if ($q !== '') {
            $where .= ' AND i.title LIKE ?';
            $params[] = '%' . $q . '%';
        }
        $total = Database::count('issues i JOIN users u ON u.id = i.author_id', $where, $params);
        $items = Database::fetchAll(
            'SELECT ' . self::SELECT . ' WHERE ' . $where . ' ORDER BY i.created_at DESC LIMIT ' . (int)$per . ' OFFSET ' . (int)(max(1, $page) - 1) * (int)$per,
            $params
        );
        return ['items' => $items, 'total' => $total];
    }

    public static function countForRepo(int $repoId, string $state = 'open'): int
    {
        return Database::count('issues', 'repo_id = ? AND state = ?', [$repoId, $state]);
    }

    public static function find(int $repoId, int $number): ?array
    {
        return Database::fetch('SELECT ' . self::SELECT . ' WHERE i.repo_id = ? AND i.number = ?', [$repoId, $number]);
    }

    public static function findById(int $id): ?array
    {
        return Database::fetch('SELECT ' . self::SELECT . ' WHERE i.id = ?', [$id]);
    }

    public static function create(int $repoId, int $authorId, string $title, string $body, array $labelIds = []): int
    {
        $number = self::nextIssueNumber($repoId);
        $id = Database::insert('issues', [
            'repo_id'   => $repoId,
            'number'    => $number,
            'title'     => mb_substr($title, 0, 255),
            'body'      => $body,
            'state'     => 'open',
            'author_id' => $authorId,
        ]);
        self::setLabels($id, $labelIds);
        Database::update('repositories', ['issues_count' => self::countForRepo($repoId, 'open')], 'id = ?', [$repoId]);
        return $number;
    }

    public static function nextIssueNumber(int $repoId): int
    {
        $row = Database::fetch('SELECT COALESCE(MAX(number), 0) + 1 AS next FROM issues WHERE repo_id = ?', [$repoId]);
        return (int)($row['next'] ?? 1);
    }

    public static function setState(int $id, string $state, ?int $closedBy = null): void
    {
        $issue = self::findById($id);
        if ($issue === null) {
            return;
        }
        Database::update('issues', [
            'state'     => $state,
            'closed_by' => $state === 'closed' ? $closedBy : null,
            'closed_at' => $state === 'closed' ? date('Y-m-d H:i:s') : null,
        ], 'id = ?', [$id]);
        Database::update(
            'repositories',
            ['issues_count' => self::countForRepo((int)$issue['repo_id'], 'open')],
            'id = ?',
            [(int)$issue['repo_id']]
        );
    }

    public static function setLabels(int $issueId, array $labelIds): void
    {
        Database::delete('issue_labels', 'issue_id = ?', [$issueId]);
        foreach ($labelIds as $labelId) {
            Database::insert('issue_labels', ['issue_id' => $issueId, 'label_id' => (int)$labelId]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public static function labelsFor(int $issueId): array
    {
        return Database::fetchAll(
            'SELECT l.* FROM issue_labels il JOIN labels l ON l.id = il.label_id WHERE il.issue_id = ? ORDER BY l.name',
            [$issueId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function comments(int $issueId): array
    {
        return Database::fetchAll(
            'SELECT c.*, u.username, u.display_name FROM comments c
             JOIN users u ON u.id = c.author_id
             WHERE c.issue_id = ? ORDER BY c.created_at ASC',
            [$issueId]
        );
    }

    public static function addComment(int $issueId, int $authorId, string $body): void
    {
        Database::insert('comments', [
            'issue_id'  => $issueId,
            'author_id' => $authorId,
            'body'      => $body,
        ]);
    }

    public static function commentCount(int $issueId): int
    {
        return Database::count('comments', 'issue_id = ?', [$issueId]);
    }

    /** 列表页批量取评论数 */
    public static function commentCounts(array $issueIds): array
    {
        if ($issueIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($issueIds), '?'));
        $rows = Database::fetchAll(
            'SELECT issue_id, COUNT(*) AS c FROM comments WHERE issue_id IN (' . $placeholders . ') GROUP BY issue_id',
            $issueIds
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(int)$row['issue_id']] = (int)$row['c'];
        }
        return $out;
    }
}
