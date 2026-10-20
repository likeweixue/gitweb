<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Model\Issue;
use App\Model\Label;
use App\Model\Repository;

/**
 * Issue 控制器
 */
final class IssueController extends Controller
{
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

        $feed = Issue::forRepo((int)$repo['id'], $state === 'all' ? '' : $state, $page, $per, $q);
        $commentCounts = Issue::commentCounts(array_column($feed['items'], 'id'));

        $this->view('issue/index', [
            'title'         => 'Issues · ' . $repo['name'],
            'repo'          => $repo,
            'tab'           => 'issues',
            'permission'    => Repository::permission(Auth::id(), $repo),
            'issues'        => $feed['items'],
            'total'         => $feed['total'],
            'state'         => $state,
            'q'             => $q,
            'page'          => $page,
            'per'           => $per,
            'commentCounts' => $commentCounts,
            'openCount'     => Issue::countForRepo((int)$repo['id'], 'open'),
            'closedCount'   => Issue::countForRepo((int)$repo['id'], 'closed'),
        ]);
    }

    public function new(string $owner, string $name): void
    {
        $user = $this->requireLogin();
        $repo = $this->loadRepo($owner, $name);
        $this->view('issue/new', [
            'title'   => '新建 Issue · ' . $repo['name'],
            'repo'    => $repo,
            'tab'     => 'issues',
            'labels'  => Label::forRepo((int)$repo['id']),
        ]);
    }

    public function store(string $owner, string $name): void
    {
        $user = $this->requireLogin();
        $repo = $this->loadRepo($owner, $name);
        Csrf::verify();

        if ((int)$repo['has_issues'] !== 1) {
            Session::flash('error', '该仓库已关闭 Issues');
            $this->redirect(repo_url($repo, '/issues'));
            return;
        }

        $data = [
            'title'  => trim((string)$this->req->post('title', '')),
            'body'   => (string)$this->req->post('body', ''),
            'labels' => (array)$this->req->post('labels', []),
        ];
        $errors = Validator::make($data, ['title' => 'required|max:255']);
        if ($errors !== []) {
            Session::set('_errors', $errors);
            Session::set('_old', $data);
            $this->redirect(repo_url($repo, '/issues/new'));
            return;
        }

        $labelIds = array_map('intval', $data['labels']);
        $number = Issue::create((int)$repo['id'], (int)$user['id'], $data['title'], $data['body'], $labelIds);
        Session::flash('success', "Issue #$number 已创建");
        $this->redirect(repo_url($repo, '/issues/' . $number));
    }

    public function show(string $owner, string $name, string $number): void
    {
        $repo = $this->loadRepo($owner, $name);
        $issue = Issue::find((int)$repo['id'], (int)$number);
        if ($issue === null) {
            $this->notFound('Issue 不存在');
        }
        $labels = Issue::labelsFor((int)$issue['id']);
        $comments = Issue::comments((int)$issue['id']);
        $closer = null;
        if (!empty($issue['closed_by'])) {
            $closer = \App\Model\User::find((int)$issue['closed_by']);
        }

        $this->view('issue/show', [
            'title'      => "#{$issue['number']} {$issue['title']} · " . $repo['name'],
            'repo'       => $repo,
            'tab'        => 'issues',
            'issue'      => $issue,
            'labels'     => $labels,
            'allLabels'  => Label::forRepo((int)$repo['id']),
            'comments'   => $comments,
            'closer'     => $closer,
            'permission' => Repository::permission(Auth::id(), $repo),
            'canManage'  => Repository::canWrite(Auth::id(), $repo),
        ]);
    }

    public function comment(string $owner, string $name, string $number): void
    {
        $user = $this->requireLogin();
        $repo = $this->loadRepo($owner, $name);
        $issue = Issue::find((int)$repo['id'], (int)$number);
        if ($issue === null) {
            $this->notFound('Issue 不存在');
        }
        Csrf::verify();
        $body = trim((string)$this->req->post('body', ''));
        if ($body === '') {
            Session::flash('error', '评论不能为空');
            $this->redirect(repo_url($repo, '/issues/' . $number));
            return;
        }
        Issue::addComment((int)$issue['id'], (int)$user['id'], $body);
        $this->redirect(repo_url($repo, '/issues/' . $number));
    }

    public function setState(string $owner, string $name, string $number): void
    {
        $user = $this->requireLogin();
        $repo = $this->loadRepo($owner, $name);
        $issue = Issue::find((int)$repo['id'], (int)$number);
        if ($issue === null) {
            $this->notFound('Issue 不存在');
        }
        if (!Repository::canWrite((int)$user['id'], $repo) && (int)$issue['author_id'] !== (int)$user['id']) {
            abort(403, '没有权限执行此操作');
        }
        Csrf::verify();
        $state = (string)$this->req->post('state', 'closed') === 'open' ? 'open' : 'closed';
        Issue::setState((int)$issue['id'], $state, (int)$user['id']);
        Session::flash('success', $state === 'closed' ? 'Issue 已关闭' : 'Issue 已重新打开');
        $this->redirect(repo_url($repo, '/issues/' . $number));
    }

    public function setLabels(string $owner, string $name, string $number): void
    {
        $user = $this->requireLogin();
        $repo = $this->loadRepo($owner, $name);
        $issue = Issue::find((int)$repo['id'], (int)$number);
        if ($issue === null) {
            $this->notFound('Issue 不存在');
        }
        if (!Repository::canWrite((int)$user['id'], $repo)) {
            abort(403, '没有写入权限');
        }
        Csrf::verify();
        $labelIds = array_map('intval', (array)$this->req->post('labels', []));
        $valid = array_column(Label::forRepo((int)$repo['id']), 'id');
        $labelIds = array_values(array_intersect($labelIds, $valid));
        Issue::setLabels((int)$issue['id'], $labelIds);
        Session::flash('success', '标签已更新');
        $this->redirect(repo_url($repo, '/issues/' . $number));
    }
}
