<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\Validator;
use App\Lib\DiffParser;
use App\Lib\Git;
use App\Model\PullRequest;
use App\Model\Repository;
use InvalidArgumentException;
use RuntimeException;

/**
 * Pull Request 控制器（发起 / 审查 / 合并）
 */
final class PullRequestController extends Controller
{
    // ------------------------------------------------------------ 公共辅助

    private function loadRepo(string $owner, string $name): array
    {
        $repo = Repository::findByPath($owner, $name);
        if ($repo === null) {
            $this->notFound('仓库不存在');
        }
        if (!Repository::canRead(Auth::id(), $repo)) {
            abort(403, '无权访问该私有仓库');
        }
        return $repo;
    }

    private function requireWrite(array $repo): void
    {
        if (!Repository::canWrite(Auth::id(), $repo)) {
            abort(403, '没有写入权限');
        }
    }

    /** 当前用户对该仓库的 fork 列表（用于跨仓库 PR） */
    private function userForks(int $repoId, ?int $userId): array
    {
        if ($userId === null) {
            return [];
        }
        return Database::fetchAll(
            'SELECT r.*, u.username AS owner_name FROM repositories r
             JOIN users u ON u.id = r.owner_id
             WHERE r.parent_id = ? AND r.owner_id = ?',
            [$repoId, $userId]
        );
    }

    /** 计算 PR 的 diff（跨仓库时临时抓取 head 到 base 仓再 diff） */
    private function diffForPr(array $pr): array
    {
        $baseRepo = Repository::find((int)$pr['repo_id']);
        if ($baseRepo === null) {
            return ['files' => [], 'additions' => 0, 'deletions' => 0];
        }
        $baseDir = Repository::path($baseRepo);
        $headRepo = PullRequest::headRepo($pr);
        if ($headRepo === null) {
            return ['files' => [], 'additions' => 0, 'deletions' => 0];
        }
        $headDir = Repository::path($headRepo);
        $tmpRef = null;
        try {
            $headRef = (string)$pr['head_ref'];
            if ($headDir !== $baseDir) {
                $tmpRef = 'refs/gw-diff-' . ((int)$pr['id']);
                $fetched = Git::fetchRef($baseDir, $headDir, $headRef, $tmpRef);
                if ($fetched === null) {
                    return ['files' => [], 'additions' => 0, 'deletions' => 0];
                }
                $headRef = $tmpRef;
            }
            $diff = DiffParser::parse(Git::diffBetween($baseDir, (string)$pr['base_ref'], $headRef));
        } finally {
            if ($tmpRef !== null) {
                Git::run(['-C', $baseDir, 'update-ref', '-d', $tmpRef]);
            }
        }
        return $diff;
    }

    // ------------------------------------------------------------ 列表

    public function index(string $owner, string $name): void
    {
        $repo = $this->loadRepo($owner, $name);
        $state = (string)$this->req->get('state', 'open');
        if (!in_array($state, ['open', 'closed', 'all'], true)) {
            $state = 'open';
        }
        $q = trim((string)$this->req->get('q', ''));
        $page = max(1, (int)$this->req->get('page', 1));
        $per = 25;

        // forRepo 仅支持 open/closed；all 时分别取
        if ($state === 'all') {
            $items = array_merge(
                PullRequest::forRepo((int)$repo['id'], 'open', $per),
                PullRequest::forRepo((int)$repo['id'], 'closed', $per)
            );
            usort($items, static fn (array $a, array $b): int => strcmp((string)$b['created_at'], (string)$a['created_at']));
            $items = array_slice($items, ($page - 1) * $per, $per);
            $total = PullRequest::openCount((int)$repo['id']) + PullRequest::countByState((int)$repo['id'], 'closed');
        } else {
            $items = PullRequest::forRepo((int)$repo['id'], $state, $per);
            $total = PullRequest::countByState((int)$repo['id'], $state);
        }

        $this->view('repo/pulls/index', [
            'title'       => 'Pull requests · ' . $repo['name'],
            'repo'        => $repo,
            'tab'         => 'pulls',
            'permission'  => Repository::permission(Auth::id(), $repo),
            'pulls'       => $items,
            'total'       => $total,
            'state'       => $state,
            'q'           => $q,
            'page'        => $page,
            'per'         => $per,
            'openCount'   => PullRequest::openCount((int)$repo['id']),
            'closedCount' => PullRequest::countByState((int)$repo['id'], 'closed'),
        ]);
    }

    // ------------------------------------------------------------ 新建

    public function new(string $owner, string $name): void
    {
        $user = $this->requireLogin();
        $repo = $this->loadRepo($owner, $name);
        // 同仓发起 PR 需要写入权限；跨仓（fork）发起只需对 fork 有读权限（下方校验）
        $baseDir = Repository::path($repo);
        $branches = Git::branches($baseDir);
        $branchNames = array_column($branches, 'name');
        $default = (string)$repo['default_branch'];
        if ($default === '' || !in_array($default, $branchNames, true)) {
            $default = $branchNames[0] ?? 'main';
        }

        $forks = $this->userForks((int)$repo['id'], (int)$user['id']);
        // 默认 head：第一个非默认分支，否则默认分支
        $headDefault = $default;
        foreach ($branchNames as $b) {
            if ($b !== $default) {
                $headDefault = $b;
                break;
            }
        }

        $this->view('repo/pulls/new', [
            'title'       => '新建 Pull request · ' . $repo['name'],
            'repo'        => $repo,
            'tab'         => 'pulls',
            'branches'    => $branchNames,
            'defaultRef'  => $default,
            'headDefault' => $headDefault,
            'forks'       => $forks,
            'permission'  => Repository::permission(Auth::id(), $repo),
        ]);
    }

    public function store(string $owner, string $name): void
    {
        $user = $this->requireLogin();
        Csrf::verify();
        $repo = Repository::findByPath($owner, $name);
        if ($repo === null) {
            $this->notFound('仓库不存在');
        }

        $baseRef = trim((string)$this->req->post('base_ref', ''));
        $headRef = trim((string)$this->req->post('head_ref', ''));
        $headRepoId = $this->req->post('head_repo_id');
        $headRepoId = is_numeric($headRepoId) ? (int)$headRepoId : null;
        $title = trim((string)$this->req->post('title', ''));
        $body = (string)$this->req->post('body', '');

        // 跨仓（fork）PR：head_repo 必须是当前用户对该仓库的 fork
        $useFork = false;
        if ($headRepoId !== null && $headRepoId !== (int)$repo['id']) {
            $fork = null;
            foreach ($this->userForks((int)$repo['id'], (int)$user['id']) as $f) {
                if ((int)$f['id'] === $headRepoId) {
                    $fork = $f;
                    break;
                }
            }
            if ($fork === null) {
                Session::flash('error', '你只能基于自己的 fork 发起 PR');
                $this->redirect(repo_url($repo, '/pulls/new'));
                return;
            }
            $useFork = true;
        }

        // 权限：同仓需写入；跨仓（fork）只需读 fork
        if (!$useFork && !Repository::canWrite((int)$user['id'], $repo)) {
            abort(403, '没有写入权限，无法在该仓库发起 PR');
        }

        $headRepo = $useFork ? Repository::find($headRepoId) : $repo;
        if ($headRepo === null) {
            Session::flash('error', 'head 仓库不存在');
            $this->redirect(repo_url($repo, '/pulls/new'));
            return;
        }
        $baseDir = Repository::path($repo);
        $headDir = Repository::path($headRepo);

        if (!in_array($baseRef, Git::branches($baseDir) ? array_column(Git::branches($baseDir), 'name') : [], true)
            && Git::resolveRef($baseDir, $baseRef) === null) {
            Session::flash('error', 'base 分支不存在');
            $this->redirect(repo_url($repo, '/pulls/new'));
            return;
        }
        if (Git::resolveRef($headDir, $headRef) === null) {
            Session::flash('error', 'head 分支不存在');
            $this->redirect(repo_url($repo, '/pulls/new'));
            return;
        }

        // 已存在相同 base/head 的开放 PR 则提示
        $dup = Database::fetch(
            'SELECT id, number FROM pull_requests WHERE repo_id = ? AND base_ref = ? AND head_ref = ? AND state = ?',
            [(int)$repo['id'], $baseRef, $headRef, 'open']
        );
        if ($dup !== null) {
            Session::flash('info', '已存在相同分支的开放 PR #' . $dup['number']);
            $this->redirect(repo_url($repo, '/pulls/' . $dup['number']));
            return;
        }

        // 计算 ahead/behind
        $baseSha = Git::resolveRef($baseDir, $baseRef);
        $headSha = Git::resolveRef($headDir, $headRef);
        $ahead = (int)trim(Git::run(['-C', $headDir, 'rev-list', '--count', $baseSha . '..' . $headSha])['out'] ?? '0');
        if ($ahead === 0) {
            Session::flash('error', 'base 分支已经包含 head 分支的全部提交，没有可合并的内容');
            $this->redirect(repo_url($repo, '/pulls/new'));
            return;
        }

        if ($title === '') {
            // 自动生成标题：head 分支最新提交主题
            $last = Git::log($headDir, $headRef, '', 1, 0);
            $title = $last[0]['short'] ?? ('Merge ' . $headRef . ' into ' . $baseRef);
        }

        $number = PullRequest::nextNumber((int)$repo['id']);
        $prId = PullRequest::create([
            'repo_id'      => (int)$repo['id'],
            'number'       => $number,
            'title'        => $title,
            'body'         => $body,
            'author_id'    => (int)$user['id'],
            'base_ref'     => $baseRef,
            'head_ref'     => $headRef,
            'head_repo_id' => $useFork ? $headRepoId : null,
        ]);
        Session::flash('success', "Pull request #$number 已创建");
        $this->redirect(repo_url($repo, '/pulls/' . $number));
    }

    // ------------------------------------------------------------ 详情

    public function show(string $owner, string $name, string $number): void
    {
        $repo = $this->loadRepo($owner, $name);
        $pr = PullRequest::findByNumber((int)$repo['id'], (int)$number);
        if ($pr === null) {
            $this->notFound('Pull request 不存在');
        }

        $diverge = PullRequest::diverge($pr);
        $headRepo = PullRequest::headRepo($pr);
        $baseRepo = Repository::find((int)$pr['repo_id']);

        $commits = [];
        $diff = ['files' => [], 'additions' => 0, 'deletions' => 0];
        if ($diverge['missing'] === false && $diverge['ahead'] > 0) {
            $headDir = $headRepo !== null ? Repository::path($headRepo) : Repository::path($baseRepo);
            $tmpRef = null;
            try {
                $headRef = (string)$pr['head_ref'];
                if ($headRepo !== null && $headRepo !== $baseRepo) {
                    $tmpRef = 'refs/gw-log-' . ((int)$pr['id']);
                    $fetched = Git::fetchRef(Repository::path($baseRepo), $headDir, $headRef, $tmpRef);
                    if ($fetched !== null) {
                        $headRef = $tmpRef;
                        $headDir = Repository::path($baseRepo);
                    }
                }
                if ($tmpRef === null || $fetched !== null) {
                    $commits = Git::logBetween($headDir, (string)$pr['base_ref'], $headRef, 200);
                    $diff = $this->diffForPr($pr);
                }
            } finally {
                if ($tmpRef !== null) {
                    Git::run(['-C', Repository::path($baseRepo), 'update-ref', '-d', $tmpRef]);
                }
            }
        }

        $comments = PullRequest::comments((int)$pr['id']);
        $reviews = PullRequest::reviews((int)$pr['id']);
        $latestReviews = PullRequest::latestReviewsByUser((int)$pr['id']);
        $mergedBy = !empty($pr['merged_by']) ? \App\Model\User::find((int)$pr['merged_by']) : null;
        $closedBy = !empty($pr['closed_by']) ? \App\Model\User::find((int)$pr['closed_by']) : null;

        $this->view('repo/pulls/show', [
            'title'         => "#{$pr['number']} {$pr['title']} · " . $repo['name'],
            'repo'          => $repo,
            'tab'           => 'pulls',
            'pr'            => $pr,
            'diverge'       => $diverge,
            'headRepo'      => $headRepo,
            'baseRepo'      => $baseRepo,
            'commits'       => $commits,
            'diff'          => $diff,
            'comments'      => $comments,
            'reviews'       => $reviews,
            'latestReviews' => $latestReviews,
            'mergedBy'      => $mergedBy,
            'closedBy'      => $closedBy,
            'permission'    => Repository::permission(Auth::id(), $repo),
            'canManage'     => Repository::canWrite(Auth::id(), $repo),
            'isAuthor'      => (int)$pr['author_id'] === Auth::id(),
        ]);
    }

    // ------------------------------------------------------------ 讨论 / 审查

    public function comment(string $owner, string $name, string $number): void
    {
        $user = $this->requireLogin();
        $repo = $this->loadRepo($owner, $name);
        $pr = PullRequest::findByNumber((int)$repo['id'], (int)$number);
        if ($pr === null) {
            $this->notFound('Pull request 不存在');
        }
        Csrf::verify();
        try {
            PullRequest::addComment((int)$pr['id'], (int)$user['id'], (string)$this->req->post('body', ''));
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect(repo_url($repo, '/pulls/' . $number));
    }

    public function review(string $owner, string $name, string $number): void
    {
        $user = $this->requireLogin();
        $repo = $this->loadRepo($owner, $name);
        $pr = PullRequest::findByNumber((int)$repo['id'], (int)$number);
        if ($pr === null) {
            $this->notFound('Pull request 不存在');
        }
        if ((string)$pr['state'] !== 'open') {
            Session::flash('error', '只有开放的 PR 可以审查');
            $this->redirect(repo_url($repo, '/pulls/' . $number));
            return;
        }
        Csrf::verify();
        $state = (string)$this->req->post('state', 'commented');
        try {
            PullRequest::addReview((int)$pr['id'], (int)$user['id'], $state, (string)$this->req->post('body', ''));
            Session::flash('success', $state === 'approved' ? '已通过审查' : ($state === 'changes_requested' ? '已请求修改' : '已发表评论'));
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect(repo_url($repo, '/pulls/' . $number));
    }

    // ------------------------------------------------------------ 合并 / 关闭

    public function merge(string $owner, string $name, string $number): void
    {
        $user = $this->requireLogin();
        $repo = $this->loadRepo($owner, $name);
        $this->requireWrite($repo);
        $pr = PullRequest::findByNumber((int)$repo['id'], (int)$number);
        if ($pr === null) {
            $this->notFound('Pull request 不存在');
        }
        Csrf::verify();
        $strategy = (string)$this->req->post('strategy', 'merge');
        if (!in_array($strategy, ['merge', 'squash', 'rebase'], true)) {
            $strategy = 'merge';
        }

        $diverge = PullRequest::diverge($pr);
        if ($diverge['missing'] || !$diverge['mergeable']) {
            Session::flash('error', '当前分支无法自动合并（可能已缺失或存在冲突），请先在本地解决');
            $this->redirect(repo_url($repo, '/pulls/' . $number));
            return;
        }
        if ($diverge['ahead'] === 0) {
            Session::flash('error', '没有可合并的提交');
            $this->redirect(repo_url($repo, '/pulls/' . $number));
            return;
        }

        $u = Auth::user();
        $author = [
            'name'  => !empty($u['display_name']) ? $u['display_name'] : $u['username'],
            'email' => $u['email'] ?? ($u['username'] . '@localhost'),
        ];
        try {
            PullRequest::merge((int)$pr['id'], (int)$user['id'], $strategy, $author);
            Session::flash('success', '已合并（' . $strategy . '）');
        } catch (RuntimeException | InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect(repo_url($repo, '/pulls/' . $number));
    }

    public function close(string $owner, string $name, string $number): void
    {
        $user = $this->requireLogin();
        $repo = $this->loadRepo($owner, $name);
        $pr = PullRequest::findByNumber((int)$repo['id'], (int)$number);
        if ($pr === null) {
            $this->notFound('Pull request 不存在');
        }
        if (!Repository::canWrite((int)$user['id'], $repo) && (int)$pr['author_id'] !== (int)$user['id']) {
            abort(403, '没有权限执行此操作');
        }
        Csrf::verify();
        PullRequest::close((int)$pr['id'], (int)$user['id']);
        Session::flash('success', 'Pull request 已关闭');
        $this->redirect(repo_url($repo, '/pulls/' . $number));
    }

    public function reopen(string $owner, string $name, string $number): void
    {
        $user = $this->requireLogin();
        $repo = $this->loadRepo($owner, $name);
        $pr = PullRequest::findByNumber((int)$repo['id'], (int)$number);
        if ($pr === null) {
            $this->notFound('Pull request 不存在');
        }
        if (!Repository::canWrite((int)$user['id'], $repo) && (int)$pr['author_id'] !== (int)$user['id']) {
            abort(403, '没有权限执行此操作');
        }
        Csrf::verify();
        PullRequest::reopen((int)$pr['id']);
        Session::flash('success', 'Pull request 已重新打开');
        $this->redirect(repo_url($repo, '/pulls/' . $number));
    }
}
