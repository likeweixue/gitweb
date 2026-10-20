<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Lib\DiffParser;
use App\Lib\Git;
use App\Model\Label;
use App\Model\Release;
use App\Model\Repository;
use App\Model\User;
use InvalidArgumentException;
use RuntimeException;

/**
 * 仓库控制器（代码浏览 / 历史 / 发布 / 设置）
 */
final class RepoController extends Controller
{
    // ------------------------------------------------------------ 公共辅助

    /** 加载仓库并校验读权限 */
    private function loadRepo(string $owner, string $name, string $repoParam = ''): array
    {
        $realName = $repoParam !== '' ? $repoParam : $name;
        $realName = (string)preg_replace('/\.git$/', '', $realName);
        $repo = Repository::findByPath($owner, $realName);
        if ($repo === null) {
            $this->notFound('仓库不存在');
        }
        $viewerId = Auth::id();
        if (!Repository::canRead($viewerId, $repo)) {
            abort(403, '无权访问该私有仓库');
        }
        return $repo;
    }

    /** 要求仓库属主权限 */
    private function requireRepoOwner(array $repo): void
    {
        if (Repository::permission(Auth::id(), $repo) !== 'owner') {
            abort(403, '仅仓库属主可以执行此操作');
        }
    }

    /** @return array<string, mixed> */
    private function pageContext(array $repo): array
    {
        $path = Repository::path($repo);
        $branches = Git::branches($path);
        $branchNames = array_column($branches, 'name');
        $default = (string)$repo['default_branch'];
        if ($default === '' || !in_array($default, $branchNames, true)) {
            $default = $branchNames[0] ?? 'main';
        }
        return [
            'repo'         => $repo,
            'diskPath'     => $path,
            'branches'     => $branches,
            'tags'         => Git::tags($path),
            'defaultRef'   => $default,
            'permission'   => Repository::permission(Auth::id(), $repo),
            'isStarred'    => Repository::isStarred((int)$repo['id'], Auth::id()),
            'cloneUrls'    => clone_urls($repo),
            'empty'        => Git::isEmpty($path),
        ];
    }

    // ------------------------------------------------------------ 创建仓库

    public function newForm(): void
    {
        $this->requireLogin();
        $this->view('repo/new', ['title' => '新建仓库']);
    }

    public function create(): void
    {
        $user = $this->requireLogin();
        Csrf::verify();
        $data = [
            'name'        => trim((string)$this->req->post('name', '')),
            'description' => trim((string)$this->req->post('description', '')),
            'visibility'  => (string)$this->req->post('visibility', 'public'),
            'init'        => (string)$this->req->post('init', '1'),
        ];
        $errors = Validator::make($data, [
            'name'        => 'required|reponame',
            'description' => 'max:500',
            'visibility'  => 'in:public,private',
        ]);
        if ($errors !== []) {
            Session::set('_errors', $errors);
            Session::set('_old', $data);
            $this->redirect(url('/new'));
            return;
        }

        try {
            $id = Repository::create(
                (int)$user['id'],
                $data['name'],
                $data['description'],
                $data['visibility'] === 'private',
                $data['init'] === '1'
            );
            Session::flash('success', '仓库创建成功');
            $this->redirect(repo_url(Repository::find($id) ?? []));
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect(url('/new'));
        }
    }

    // ------------------------------------------------------------ 仓库首页

    public function home(string $owner, string $name): void
    {
        $repo = $this->loadRepo($owner, $name);
        $ctx = $this->pageContext($repo);
        $readme = null;
        $lastCommit = null;
        $entries = [];

        if (!$ctx['empty']) {
            $entries = Git::lsTree($ctx['diskPath'], $ctx['defaultRef']);
            $lastCommit = Git::lastCommitForPath($ctx['diskPath'], $ctx['defaultRef']);
            foreach ($entries as $entry) {
                if ($entry['type'] !== 'blob') {
                    continue;
                }
                $lower = strtolower($entry['name']);
                if ($lower === 'readme.md' || $lower === 'readme.markdown') {
                    $readme = $entry;
                    break;
                }
            }
        }

        $ctx += [
            'title'      => $repo['name'],
            'entries'    => $entries,
            'lastCommit' => $lastCommit,
            'readme'     => $readme,
            'readmeHtml' => $readme !== null ? markdown(Git::catFileBlob($ctx['diskPath'], $readme['hash']) ?? '') : '',
            'tab'        => 'code',
            'forkedFrom' => null,
        ];

        if (!empty($repo['parent_id'])) {
            $parent = Repository::find((int)$repo['parent_id']);
            $ctx['forkedFrom'] = $parent;
        }

        $this->view('repo/home', $ctx);
    }

    // ------------------------------------------------------------ 目录浏览

    public function tree(string $owner, string $name, string $rest): void
    {
        $repo = $this->loadRepo($owner, $name);
        $ctx = $this->pageContext($repo);
        if ($ctx['empty']) {
            $this->redirect(repo_url($repo));
            return;
        }

        $resolved = Git::resolveRefPath($ctx['diskPath'], $rest);
        if ($resolved === null || !Git::isValidTreePath($resolved['path'])) {
            $this->notFound('分支或路径不存在');
        }

        $entries = Git::lsTree($ctx['diskPath'], $resolved['ref'], $resolved['path']);
        if ($entries === []) {
            $type = Git::objectType($ctx['diskPath'], $resolved['ref'] . ':' . $resolved['path']);
            if ($type === 'blob') {
                $this->redirect(repo_url($repo, '/blob/' . implode('/', array_filter([$resolved['ref'], $resolved['path']], static fn ($s) => $s !== ''))));
                return;
            }
            $this->notFound('路径不存在');
        }

        $lastCommit = Git::lastCommitForPath($ctx['diskPath'], $resolved['ref'], $resolved['path']);
        $this->view('repo/tree', $ctx + [
            'title'      => ($resolved['path'] !== '' ? basename($resolved['path']) : $repo['name']) . ' · ' . $repo['name'],
            'tab'        => 'code',
            'ref'        => $resolved['ref'],
            'path'       => $resolved['path'],
            'entries'    => $entries,
            'lastCommit' => $lastCommit,
            'breadcrumbs' => $this->breadcrumbs($repo, $resolved['ref'], $resolved['path']),
        ]);
    }

    // ------------------------------------------------------------ 文件内容

    public function blob(string $owner, string $name, string $rest): void
    {
        $repo = $this->loadRepo($owner, $name);
        $ctx = $this->pageContext($repo);
        if ($ctx['empty']) {
            $this->redirect(repo_url($repo));
            return;
        }

        $resolved = Git::resolveRefPath($ctx['diskPath'], $rest);
        if ($resolved === null || $resolved['path'] === '' || !Git::isValidTreePath($resolved['path'])) {
            $this->notFound('文件不存在');
        }

        $parentPath = dirname($resolved['path']);
        $parentPath = $parentPath === '.' ? '' : $parentPath;
        $fileName = basename($resolved['path']);
        $parentEntries = Git::lsTree($ctx['diskPath'], $resolved['ref'], $parentPath);
        $entry = null;
        foreach ($parentEntries as $candidate) {
            if ($candidate['name'] === $fileName) {
                $entry = $candidate;
                break;
            }
        }
        if ($entry === null) {
            $this->notFound('文件不存在');
        }
        if ($entry['type'] === 'tree') {
            $this->redirect(repo_url($repo, '/tree/' . implode('/', array_filter([$resolved['ref'], $resolved['path']], static fn ($s) => $s !== ''))));
            return;
        }

        $content = Git::catFileBlob($ctx['diskPath'], $entry['hash']) ?? '';
        $size = $entry['size'] ?? strlen($content);
        $isBinary = is_binary_string($content);
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $isMarkdown = in_array($ext, ['md', 'markdown'], true);
        $isImage = in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'bmp'], true);

        $this->view('repo/blob', $ctx + [
            'title'       => $fileName . ' · ' . $repo['name'],
            'tab'         => 'code',
            'ref'         => $resolved['ref'],
            'path'        => $resolved['path'],
            'fileName'    => $fileName,
            'content'     => $content,
            'size'        => $size,
            'isBinary'    => $isBinary,
            'isMarkdown'  => $isMarkdown && !$isBinary,
            'isImage'     => $isImage && !$isBinary,
            'rendered'    => $isMarkdown && !$isBinary ? markdown($content) : '',
            'lines'       => (!$isBinary && !$isMarkdown && !$isImage) ? $this->highlightLines($content, $resolved['path']) : [],
            'breadcrumbs' => $this->breadcrumbs($repo, $resolved['ref'], $resolved['path'], true),
        ]);
    }

    /** @return array<int, string> */
    private function highlightLines(string $content, string $path): array
    {
        if (strlen($content) > 1024 * 1024) {
            return [];
        }
        $lang = lang_from_path($path);
        $text = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = explode("\n", $text);
        if ($lang === '') {
            return array_map(static fn (string $line): string => e($line), $lines);
        }
        try {
            $highlighted = \App\Lib\Highlighter::highlightLines($text, $lang);
            if (count($highlighted) === count($lines)) {
                return $highlighted;
            }
        } catch (\Throwable) {
            // 高亮失败回退纯文本
        }
        return array_map(static fn (string $line): string => e($line), $lines);
    }

    /** 原始文件输出 */
    public function raw(string $owner, string $name, string $rest): void
    {
        $repo = $this->loadRepo($owner, $name);
        $path = Repository::path($repo);
        if (Git::isEmpty($path)) {
            $this->notFound('仓库为空');
        }
        $resolved = Git::resolveRefPath($path, $rest);
        if ($resolved === null || $resolved['path'] === '' || !Git::isValidTreePath($resolved['path'])) {
            $this->notFound('文件不存在');
        }
        $parentPath = dirname($resolved['path']);
        $parentPath = $parentPath === '.' ? '' : $parentPath;
        $fileName = basename($resolved['path']);
        $entry = null;
        foreach (Git::lsTree($path, $resolved['ref'], $parentPath) as $candidate) {
            if ($candidate['name'] === $fileName) {
                $entry = $candidate;
                break;
            }
        }
        if ($entry === null || $entry['type'] !== 'blob') {
            $this->notFound('文件不存在');
        }
        $content = Git::catFileBlob($path, $entry['hash']) ?? '';

        $mime = match (strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'css' => 'text/css',
            'js', 'mjs' => 'text/javascript',
            'json' => 'application/json',
            'pdf' => 'application/pdf',
            'html', 'htm' => 'text/html',
            'txt', 'md', 'log' => 'text/plain',
            'zip' => 'application/zip',
            default => 'application/octet-stream',
        };

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($content));
        header('Content-Disposition: inline; filename="' . addslashes($fileName) . '"');
        header('X-Content-Type-Options: nosniff');
        if ($mime === 'image/svg+xml') {
            // 防止 SVG 中脚本在直接访问时执行
            header('Content-Security-Policy: sandbox');
        }
        echo $content;
    }

    // ------------------------------------------------------------ 提交历史

    public function commits(string $owner, string $name, string $ref = ''): void
    {
        $repo = $this->loadRepo($owner, $name);
        $ctx = $this->pageContext($repo);
        if ($ctx['empty']) {
            $this->redirect(repo_url($repo));
            return;
        }
        $ref = $ref !== '' ? $ref : $ctx['defaultRef'];
        if (Git::resolveRef($ctx['diskPath'], $ref) === null) {
            $this->notFound('分支不存在');
        }

        $page = max(1, (int)$this->req->get('page', 1));
        $per = 35;
        $commits = Git::log($ctx['diskPath'], $ref, '', $per, ($page - 1) * $per);
        $total = Git::commitCount($ctx['diskPath'], $ref);

        $this->view('repo/commits', $ctx + [
            'title'   => '提交历史 · ' . $repo['name'],
            'tab'     => 'code',
            'ref'     => $ref,
            'commits' => $commits,
            'total'   => $total,
            'page'    => $page,
            'per'     => $per,
        ]);
    }

    public function commit(string $owner, string $name, string $sha): void
    {
        $repo = $this->loadRepo($owner, $name);
        $ctx = $this->pageContext($repo);
        $meta = Git::commitMeta($ctx['diskPath'], $sha);
        if ($meta === null) {
            $this->notFound('提交不存在');
        }
        $diff = DiffParser::parse(Git::commitPatch($ctx['diskPath'], $meta['hash']));

        $this->view('repo/commit', $ctx + [
            'title'   => mb_substr($meta['body'] !== '' ? explode("\n", $meta['body'])[0] : $meta['short'], 0, 60) . ' · ' . $repo['name'],
            'tab'     => 'code',
            'meta'    => $meta,
            'diff'    => $diff,
        ]);
    }

    // ------------------------------------------------------------ 分支 / 标签 / 对比

    public function branches(string $owner, string $name): void
    {
        $repo = $this->loadRepo($owner, $name);
        $this->view('repo/branches', $this->pageContext($repo) + [
            'title' => '分支 · ' . $repo['name'],
            'tab'   => 'code',
        ]);
    }

    public function tags(string $owner, string $name): void
    {
        $repo = $this->loadRepo($owner, $name);
        $this->view('repo/tags', $this->pageContext($repo) + [
            'title' => '标签 · ' . $repo['name'],
            'tab'   => 'code',
        ]);
    }

    public function compare(string $owner, string $name): void
    {
        $repo = $this->loadRepo($owner, $name);
        $ctx = $this->pageContext($repo);
        $base = (string)$this->req->get('base', $ctx['defaultRef']);
        $head = (string)$this->req->get('head', $ctx['defaultRef']);
        $error = '';
        $submitted = $this->req->get('base') !== null || $this->req->get('head') !== null;

        if ($submitted) {
            if (Git::resolveRef($ctx['diskPath'], $base) === null) {
                $error = 'base 引用不存在';
            } elseif (Git::resolveRef($ctx['diskPath'], $head) === null) {
                $error = 'head 引用不存在';
            }
        }

        $commits = [];
        $diff = ['files' => [], 'additions' => 0, 'deletions' => 0];
        if ($error === '' && $submitted) {
            $commits = Git::logBetween($ctx['diskPath'], $base, $head, 100);
            $diff = DiffParser::parse(Git::diffBetween($ctx['diskPath'], $base, $head));
        }

        $this->view('repo/compare', $ctx + [
            'title'     => '对比 · ' . $repo['name'],
            'tab'       => 'code',
            'base'      => $base,
            'head'      => $head,
            'error'     => $error,
            'submitted' => $submitted,
            'commits'   => $commits,
            'diff'      => $diff,
            'refs'      => array_merge(array_column($ctx['branches'], 'name'), array_column($ctx['tags'], 'name')),
        ]);
    }

    // ------------------------------------------------------------ Releases / 归档

    public function releases(string $owner, string $name): void
    {
        $repo = $this->loadRepo($owner, $name);
        $ctx = $this->pageContext($repo);
        $releases = Release::forRepo((int)$repo['id']);
        $released = array_column($releases, 'tag_name');
        $unreleasedTags = array_values(array_filter($ctx['tags'], static fn (array $tag): bool => !in_array($tag['name'], $released, true)));

        $this->view('repo/releases', $ctx + [
            'title'          => 'Releases · ' . $repo['name'],
            'tab'            => 'releases',
            'releases'       => $releases,
            'unreleasedTags' => $unreleasedTags,
            'latest'         => Release::latest((int)$repo['id']),
        ]);
    }

    public function createRelease(string $owner, string $name): void
    {
        $repo = $this->loadRepo($owner, $name);
        $user = $this->requireLogin();
        if (!Repository::canWrite((int)$user['id'], $repo)) {
            abort(403, '没有写入权限');
        }
        Csrf::verify();
        $data = [
            'tag_name'    => trim((string)$this->req->post('tag_name', '')),
            'name'        => trim((string)$this->req->post('name', '')),
            'body'        => (string)$this->req->post('body', ''),
            'prerelease'  => (string)$this->req->post('prerelease', ''),
        ];
        try {
            Release::create(
                (int)$repo['id'],
                $data['tag_name'],
                $data['name'],
                $data['body'],
                (int)$user['id'],
                $data['prerelease'] === '1'
            );
            Session::flash('success', 'Release 已创建');
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect(repo_url($repo, '/releases'));
    }

    public function deleteRelease(string $owner, string $name, string $id): void
    {
        $repo = $this->loadRepo($owner, $name);
        $user = $this->requireLogin();
        if (!Repository::canWrite((int)$user['id'], $repo)) {
            abort(403, '没有写入权限');
        }
        Csrf::verify();
        Release::delete((int)$id, (int)$repo['id']);
        Session::flash('success', 'Release 已删除');
        $this->redirect(repo_url($repo, '/releases'));
    }

    public function archive(string $owner, string $name): void
    {
        $repo = $this->loadRepo($owner, $name);
        $path = Repository::path($repo);
        $ref = (string)$this->req->get('ref', (string)$repo['default_branch']);
        $format = (string)$this->req->get('format', 'tar.gz');
        if (!in_array($format, ['tar.gz', 'zip'], true)) {
            $format = 'tar.gz';
        }
        $sha = Git::resolveRef($path, $ref);
        if ($sha === null) {
            $this->notFound('引用不存在');
        }
        try {
            $data = Git::archive($path, $ref, $format);
        } catch (RuntimeException $e) {
            abort(500, $e->getMessage());
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . ($format === 'zip' ? 'application/zip' : 'application/gzip'));
        header('Content-Disposition: attachment; filename="' . $repo['name'] . '-' . str_replace('/', '-', $ref) . '.' . $format . '"');
        header('Content-Length: ' . strlen($data));
        echo $data;
    }

    // ------------------------------------------------------------ Star / Fork

    public function star(string $owner, string $name): void
    {
        $user = $this->requireLogin();
        Csrf::verify();
        $repo = Repository::findByPath($owner, $name);
        if ($repo === null) {
            $this->notFound('仓库不存在');
        }
        if (Repository::isStarred((int)$repo['id'], (int)$user['id'])) {
            Repository::unstar((int)$repo['id'], (int)$user['id']);
        } else {
            Repository::star((int)$repo['id'], (int)$user['id']);
        }
        $this->back();
    }

    public function fork(string $owner, string $name): void
    {
        $user = $this->requireLogin();
        Csrf::verify();
        $repo = Repository::findByPath($owner, $name);
        if ($repo === null) {
            $this->notFound('仓库不存在');
        }
        if (!Repository::canRead((int)$user['id'], $repo)) {
            abort(403, '无权访问该仓库');
        }
        $newName = trim((string)$this->req->post('name', ''));
        try {
            $id = Repository::fork((int)$repo['id'], (int)$user['id'], $newName);
            Session::flash('success', '已 Fork 到你的账号');
            $this->redirect(repo_url(Repository::find($id) ?? []));
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect(repo_url($repo));
        }
    }

    // ------------------------------------------------------------ 仓库设置

    public function settingsGeneral(string $owner, string $name): void
    {
        $this->requireLogin();
        $repo = $this->loadRepo($owner, $name);
        $this->requireRepoOwner($repo);
        $this->view('repo/settings/general', $this->pageContext($repo) + [
            'title' => '仓库设置 · ' . $repo['name'],
            'tab'   => 'settings',
        ]);
    }

    public function updateGeneral(string $owner, string $name): void
    {
        $user = $this->requireLogin();
        Csrf::verify();
        $repo = Repository::findByPath($owner, $name);
        if ($repo === null) {
            $this->notFound('仓库不存在');
        }
        $this->requireRepoOwner($repo);

        $data = [
            'description'  => trim((string)$this->req->post('description', '')),
            'homepage'     => trim((string)$this->req->post('homepage', '')),
            'visibility'   => (string)$this->req->post('visibility', 'public'),
            'default_branch' => (string)$this->req->post('default_branch', ''),
            'has_issues'   => (string)$this->req->post('has_issues', '1'),
        ];
        $errors = Validator::make($data, [
            'description' => 'max:500',
            'homepage'    => 'url',
            'visibility'  => 'in:public,private',
            'has_issues'  => 'in:0,1',
        ]);
        if ($errors !== []) {
            Session::set('_errors', $errors);
            $this->redirect(repo_url($repo, '/settings'));
            return;
        }

        $fields = [
            'description'    => $data['description'],
            'homepage'       => $data['homepage'],
            'is_private'     => $data['visibility'] === 'private' ? 1 : 0,
            'has_issues'     => (int)$data['has_issues'],
        ];
        if ($data['default_branch'] !== '' && in_array($data['default_branch'], array_column(Git::branches(Repository::path($repo)), 'name'), true)) {
            $fields['default_branch'] = $data['default_branch'];
        }
        Repository::update((int)$repo['id'], $fields);
        Session::flash('success', '设置已保存');
        $this->redirect(repo_url($repo, '/settings'));
    }

    public function renameRepo(string $owner, string $name): void
    {
        $user = $this->requireLogin();
        Csrf::verify();
        $repo = Repository::findByPath($owner, $name);
        if ($repo === null) {
            $this->notFound('仓库不存在');
        }
        $this->requireRepoOwner($repo);
        try {
            Repository::rename((int)$repo['id'], (string)$this->req->post('new_name', ''));
            Session::flash('success', '仓库已重命名');
            $this->redirect(repo_url(Repository::find((int)$repo['id']) ?? [], '/settings'));
        } catch (InvalidArgumentException|RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect(repo_url($repo, '/settings'));
        }
    }

    public function transferRepo(string $owner, string $name): void
    {
        $user = $this->requireLogin();
        Csrf::verify();
        $repo = Repository::findByPath($owner, $name);
        if ($repo === null) {
            $this->notFound('仓库不存在');
        }
        $this->requireRepoOwner($repo);
        try {
            Repository::transfer((int)$repo['id'], trim((string)$this->req->post('new_owner', '')));
            Session::flash('success', '仓库已转让');
            $this->redirect(url('/'));
        } catch (InvalidArgumentException|RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect(repo_url($repo, '/settings'));
        }
    }

    public function deleteRepo(string $owner, string $name): void
    {
        $user = $this->requireLogin();
        Csrf::verify();
        $repo = Repository::findByPath($owner, $name);
        if ($repo === null) {
            $this->notFound('仓库不存在');
        }
        $this->requireRepoOwner($repo);
        if ((string)$this->req->post('confirm', '') !== (string)$repo['owner_name'] . '/' . $repo['name']) {
            Session::flash('error', '确认文本不匹配，删除已取消');
            $this->redirect(repo_url($repo, '/settings'));
            return;
        }
        Repository::delete((int)$repo['id']);
        Session::flash('success', '仓库已删除');
        $this->redirect(url('/'));
    }

    // ------------------------------------------------------------ 协作者

    public function settingsCollaborators(string $owner, string $name): void
    {
        $this->requireLogin();
        $repo = $this->loadRepo($owner, $name);
        if (!in_array(Repository::permission(Auth::id(), $repo), ['owner', 'admin'], true)) {
            abort(403, '仅属主或管理员协作者可以管理协作者');
        }
        $this->view('repo/settings/collaborators', $this->pageContext($repo) + [
            'title'         => '协作者 · ' . $repo['name'],
            'tab'           => 'settings',
            'collaborators' => Repository::collaborators((int)$repo['id']),
        ]);
    }

    public function addCollaborator(string $owner, string $name): void
    {
        $user = $this->requireLogin();
        Csrf::verify();
        $repo = Repository::findByPath($owner, $name);
        if ($repo === null) {
            $this->notFound('仓库不存在');
        }
        if (!in_array(Repository::permission((int)$user['id'], $repo), ['owner', 'admin'], true)) {
            abort(403, '仅属主或管理员协作者可以管理协作者');
        }
        try {
            Repository::addCollaborator(
                (int)$repo['id'],
                trim((string)$this->req->post('username', '')),
                (string)$this->req->post('permission', 'read')
            );
            Session::flash('success', '协作者已添加');
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect(repo_url($repo, '/settings/collaborators'));
    }

    public function removeCollaborator(string $owner, string $name): void
    {
        $user = $this->requireLogin();
        Csrf::verify();
        $repo = Repository::findByPath($owner, $name);
        if ($repo === null) {
            $this->notFound('仓库不存在');
        }
        if (!in_array(Repository::permission((int)$user['id'], $repo), ['owner', 'admin'], true)) {
            abort(403, '仅属主或管理员协作者可以管理协作者');
        }
        Repository::removeCollaborator((int)$repo['id'], (int)$this->req->post('user_id', 0));
        Session::flash('success', '协作者已移除');
        $this->redirect(repo_url($repo, '/settings/collaborators'));
    }

    // ------------------------------------------------------------ 面包屑

    /** @return array<int, array{name: string, url: string}> */
    private function breadcrumbs(array $repo, string $ref, string $path, bool $isFile = false): array
    {
        $items = [['name' => $repo['name'], 'url' => repo_url($repo, '/tree/' . rawurlencode($ref))]];
        if ($path === '') {
            return $items;
        }
        $segments = explode('/', $path);
        $accumulated = '';
        $last = count($segments) - 1;
        foreach ($segments as $i => $segment) {
            $accumulated .= ($accumulated === '' ? '' : '/') . $segment;
            $url = repo_url($repo, '/' . ($i === $last && $isFile ? 'blob' : 'tree') . '/' . rawurlencode($ref) . '/' . implode('/', array_map('rawurlencode', explode('/', $accumulated))));
            $items[] = ['name' => $segment, 'url' => $i === $last ? '' : $url];
        }
        return $items;
    }
}
