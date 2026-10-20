<?php
declare(strict_types=1);

namespace App\Lib;

use App\Core\Config;
use RuntimeException;

/**
 * Git 命令行封装（proc_open 调用系统 git）
 *
 * 所有仓库均以 bare 形式存放在 git.repos_root 下：{owner}/{name}.git
 */
final class Git
{
    private static string $bin = 'git';

    public static function configure(): void
    {
        self::$bin = (string)Config::get('git.bin', 'git');
    }

    /**
     * 执行 git 命令
     *
     * @param array<string>      $args
     * @return array{code: int, out: string, err: string}
     */
    public static function run(array $args, ?string $input = null, ?string $cwd = null, ?array $env = null): array
    {
        $command = escapeshellarg(self::$bin);
        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg((string)$arg);
        }

        $childEnv = null;
        if ($env !== null) {
            $childEnv = [];
            foreach ($_SERVER as $key => $value) {
                if (is_string($value)) {
                    $childEnv[$key] = $value;
                }
            }
            $childEnv = array_merge($childEnv, $env);
        }

        $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $spec, $pipes, $cwd, $childEnv);
        if (!is_resource($process)) {
            throw new RuntimeException('无法执行 git 命令');
        }

        if ($input !== null && $input !== '') {
            fwrite($pipes[0], $input);
        }
        fclose($pipes[0]);

        $out = (string)stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $err = (string)stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($process);

        return ['code' => $code, 'out' => $out, 'err' => $err];
    }

    // --------------------------------------------------------------- 仓库生命周期

    /** 初始化 bare 仓库并写入 Gitweb 所需配置与钩子 */
    public static function initBare(string $dir): void
    {
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException("无法创建仓库目录: $dir");
            }
        }
        $result = self::run(['init', '--bare', '--quiet', $dir]);
        if ($result['code'] !== 0) {
            throw new RuntimeException('git init 失败: ' . $result['err']);
        }
        self::run(['-C', $dir, 'symbolic-ref', 'HEAD', 'refs/heads/main']);
        self::run(['-C', $dir, 'config', 'http.receivepack', 'true']);
        self::run(['-C', $dir, 'config', 'core.logAllRefUpdates', 'true']);
        self::installHooks($dir);
    }

    /** 安装 post-receive 钩子（推送后更新统计） */
    public static function installHooks(string $dir): void
    {
        $hooksDir = rtrim($dir, '/') . '/hooks';
        if (!is_dir($hooksDir)) {
            return;
        }
        $phpBin = PHP_BINARY;
        $statsScript = GW_APP_ROOT . '/bin/cron-stats.php';
        $script = <<<HOOK
#!/bin/bash
# Gitweb post-receive：推送后更新仓库统计
GITWEB_PHP="\${GITWEB_PHP:-$phpBin}"
exec "\$GITWEB_PHP" "$statsScript" --repo="\$(pwd)" >/dev/null 2>&1
HOOK;
        @file_put_contents($hooksDir . '/post-receive', $script);
        @chmod($hooksDir . '/post-receive', 0755);
    }

    /** bare 克隆（用于 Fork） */
    public static function cloneBare(string $srcDir, string $dstDir): void
    {
        $result = self::run(['clone', '--bare', '--quiet', $srcDir, $dstDir]);
        if ($result['code'] !== 0) {
            throw new RuntimeException('git clone --bare 失败: ' . $result['err']);
        }
        self::run(['-C', $dstDir, 'remote', 'remove', 'origin']);
        self::run(['-C', $dstDir, 'config', 'http.receivepack', 'true']);
        $default = self::defaultBranch($srcDir);
        if ($default !== null) {
            self::run(['-C', $dstDir, 'symbolic-ref', 'HEAD', 'refs/heads/' . $default]);
        }
        self::installHooks($dstDir);
    }

    /** 通过底层命令创建初始提交（files: 路径 => 内容，仅限根目录文件） */
    public static function commitFiles(string $dir, array $files, string $message, string $branch = 'main', array $author = []): string
    {
        if ($files === []) {
            throw new RuntimeException('没有要提交的文件');
        }
        $name = (string)($author['name'] ?? 'Gitweb');
        $email = (string)($author['email'] ?? 'gitweb@localhost');
        $date = date('c');
        $env = [
            'GIT_AUTHOR_NAME'     => $name,
            'GIT_AUTHOR_EMAIL'    => $email,
            'GIT_AUTHOR_DATE'     => $date,
            'GIT_COMMITTER_NAME'  => $name,
            'GIT_COMMITTER_EMAIL' => $email,
            'GIT_COMMITTER_DATE'  => $date,
        ];

        // 临时索引文件路径（不能预先创建：空文件会被 git 判定为损坏的索引）
        $indexFile = sys_get_temp_dir() . '/gitweb-idx-' . bin2hex(random_bytes(8));
        $env['GIT_INDEX_FILE'] = $indexFile;

        try {
            foreach ($files as $path => $content) {
                if (!preg_match('#^[A-Za-z0-9._-]+$#', (string)$path)) {
                    throw new RuntimeException('初始提交仅支持根目录文件: ' . $path);
                }
                $blob = trim(self::run(['-C', $dir, 'hash-object', '-w', '--stdin'], (string)$content)['out']);
                if ($blob === '') {
                    throw new RuntimeException('hash-object 失败: ' . $path);
                }
                $result = self::run(['-C', $dir, 'update-index', '--add', '--cacheinfo', '100644,' . $blob . ',' . $path], null, null, $env);
                if ($result['code'] !== 0) {
                    throw new RuntimeException('update-index 失败: ' . $result['err']);
                }
            }
            $tree = trim(self::run(['-C', $dir, 'write-tree'], null, null, $env)['out']);
            $commit = trim(self::run(['-C', $dir, 'commit-tree', $tree, '-m', $message], null, null, $env)['out']);
            if ($commit === '') {
                throw new RuntimeException('commit-tree 失败');
            }
            self::run(['-C', $dir, 'update-ref', 'refs/heads/' . $branch, $commit]);
            return $commit;
        } finally {
            if (is_file($indexFile)) {
                @unlink($indexFile);
            }
        }
    }

    // --------------------------------------------------------------- 引用与查询

    public static function defaultBranch(string $dir): ?string
    {
        $result = self::run(['-C', $dir, 'symbolic-ref', '--quiet', '--short', 'HEAD']);
        if ($result['code'] !== 0) {
            return null;
        }
        $branch = trim($result['out']);
        return $branch !== '' ? $branch : null;
    }

    /** 解析 ref 为 commit sha，失败返回 null */
    public static function resolveRef(string $dir, string $ref): ?string
    {
        if ($ref === '' || !self::isValidRef($ref)) {
            return null;
        }
        $result = self::run(['-C', $dir, 'rev-parse', '--verify', '--quiet', $ref . '^{commit}']);
        if ($result['code'] !== 0) {
            return null;
        }
        $sha = trim($result['out']);
        return preg_match('/^[0-9a-f]{40,64}$/', $sha) === 1 ? $sha : null;
    }

    /** 从 "ref/path..." 中拆分引用与路径（优先最长引用匹配） */
    public static function resolveRefPath(string $dir, string $rest): ?array
    {
        $segments = explode('/', trim($rest, '/'));
        for ($i = count($segments); $i >= 1; $i--) {
            $ref = implode('/', array_slice($segments, 0, $i));
            $path = implode('/', array_slice($segments, $i));
            $sha = self::resolveRef($dir, $ref);
            if ($sha !== null) {
                return ['ref' => $ref, 'sha' => $sha, 'path' => $path];
            }
        }
        return null;
    }

    /** @return array<int, string> */
    public static function parentsOf(string $dir, string $sha): array
    {
        $result = self::run(['-C', $dir, 'rev-parse', $sha . '^@']);
        if ($result['code'] !== 0) {
            return [];
        }
        $parents = [];
        foreach (preg_split('/\s+/', trim($result['out'])) ?: [] as $line) {
            if (preg_match('/^[0-9a-f]{40,64}$/', $line) === 1) {
                $parents[] = $line;
            }
        }
        return $parents;
    }

    public static function branches(string $dir): array
    {
        $result = self::run(['-C', $dir, 'for-each-ref', '--format=%(refname:short)%x1f%(objectname)%x1f%(committerdate:iso-strict)', 'refs/heads/']);
        if ($result['code'] !== 0) {
            return [];
        }
        $branches = [];
        foreach (explode("\n", $result['out']) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = explode("\x1f", $line);
            if (count($parts) < 3) {
                continue;
            }
            $branches[] = ['name' => $parts[0], 'sha' => $parts[1], 'date' => $parts[2]];
        }
        usort($branches, static fn (array $a, array $b): int => strcmp((string)$b['date'], (string)$a['date']));
        return $branches;
    }

    public static function tags(string $dir): array
    {
        $result = self::run(['-C', $dir, 'for-each-ref', '--format=%(refname:short)%x1f%(objectname)%x1f%(*objectname)%x1f%(creatordate:iso-strict)%x1f%(subject)', 'refs/tags/']);
        if ($result['code'] !== 0) {
            return [];
        }
        $tags = [];
        foreach (explode("\n", $result['out']) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = explode("\x1f", $line);
            if (count($parts) < 5) {
                continue;
            }
            $tags[] = [
                'name'   => $parts[0],
                'sha'    => $parts[1],
                'target' => $parts[2] !== '' ? $parts[2] : $parts[1],
                'date'   => $parts[3],
                'subject' => $parts[4],
            ];
        }
        usort($tags, static fn (array $a, array $b): int => strcmp((string)$b['date'], (string)$a['date']));
        return $tags;
    }

    // --------------------------------------------------------------- 分支与合并（bare 仓库）

    public static function isValidBranchName(string $name): bool
    {
        if ($name === '' || mb_strlen($name) > 250) {
            return false;
        }
        return preg_match('/^[A-Za-z0-9._\/-]+$/', $name) === 1
            && $name[0] !== '-'
            && !str_contains($name, '..')
            && !str_contains($name, '//')
            && !str_ends_with($name, '/')
            && !str_ends_with($name, '.lock')
            && !str_ends_with($name, '.');
    }

    /** 分支是否存在 */
    public static function branchExists(string $dir, string $name): bool
    {
        $result = self::run(['-C', $dir, 'show-ref', '--verify', '--quiet', 'refs/heads/' . $name]);
        return $result['code'] === 0;
    }

    /** 从某引用/SHA 创建分支 */
    public static function createBranch(string $dir, string $name, string $fromRef): void
    {
        if (!self::isValidBranchName($name)) {
            throw new RuntimeException('分支名非法');
        }
        if (self::branchExists($dir, $name)) {
            throw new RuntimeException('分支已存在');
        }
        $fromSha = self::resolveRef($dir, $fromRef);
        if ($fromSha === null) {
            throw new RuntimeException('源引用不存在');
        }
        $result = self::run(['-C', $dir, 'branch', $name, $fromSha]);
        if ($result['code'] !== 0) {
            throw new RuntimeException('创建分支失败: ' . $result['err']);
        }
    }

    /** 删除分支（强制） */
    public static function deleteBranch(string $dir, string $name): void
    {
        if (!self::isValidBranchName($name)) {
            throw new RuntimeException('分支名非法');
        }
        $result = self::run(['-C', $dir, 'branch', '-D', $name]);
        if ($result['code'] !== 0) {
            throw new RuntimeException('删除分支失败: ' . $result['err']);
        }
    }

    /** 更新引用 */
    public static function updateRef(string $dir, string $ref, string $sha): void
    {
        $result = self::run(['-C', $dir, 'update-ref', $ref, $sha]);
        if ($result['code'] !== 0) {
            throw new RuntimeException('更新引用失败: ' . $result['err']);
        }
    }

    /** 判断 $maybeAncestor 是否为 $sha 的祖先（含相等） */
    public static function isAncestor(string $dir, string $maybeAncestor, string $sha): bool
    {
        $a = self::resolveRef($dir, $maybeAncestor);
        $b = self::resolveRef($dir, $sha);
        if ($a === null || $b === null) {
            return false;
        }
        $result = self::run(['-C', $dir, 'merge-base', '--is-ancestor', $a, $b]);
        return $result['code'] === 0;
    }

    public static function mergeBase(string $dir, string $a, string $b): ?string
    {
        $ra = self::resolveRef($dir, $a);
        $rb = self::resolveRef($dir, $b);
        if ($ra === null || $rb === null) {
            return null;
        }
        $result = self::run(['-C', $dir, 'merge-base', $ra, $rb]);
        if ($result['code'] !== 0) {
            return null;
        }
        $sha = trim($result['out']);
        return preg_match('/^[0-9a-f]{40,64}$/', $sha) === 1 ? $sha : null;
    }

    /**
     * 从另一个仓库抓取指定引用到本仓库的临时引用，返回抓取的 SHA
     * 用于合并 Fork 来源的 PR（head 仓不同于 base 仓）
     */
    public static function fetchRef(string $dstDir, string $srcDir, string $srcRef, string $tmpRef): ?string
    {
        $result = self::run(['-C', $dstDir, 'fetch', $srcDir, $srcRef . ':' . $tmpRef]);
        if ($result['code'] !== 0) {
            return null;
        }
        return self::resolveRef($dstDir, $tmpRef);
    }

    /**
     * 在 bare 仓库中合并（无工作树）：将 headSha 合并进 baseRef 分支。
     * 策略：merge（创建合并提交，--no-ff）、squash（压为单个提交）、rebase（无冲突时按树重放）。
     * 返回新分支顶端 SHA；无法自动合并（冲突）时抛出 RuntimeException。
     *
     * @param array<string,string> $author
     */
    public static function merge(string $dir, string $baseRef, string $headSha, string $message, array $author, string $strategy = 'merge'): string
    {
        $baseSha = self::resolveRef($dir, $baseRef);
        if ($baseSha === null) {
            throw new RuntimeException('base 引用不存在');
        }
        if (self::resolveRef($dir, $headSha) === null) {
            throw new RuntimeException('head 提交不存在');
        }
        if (!in_array($strategy, ['merge', 'squash', 'rebase'], true)) {
            throw new RuntimeException('不支持的合并策略');
        }
        if (!self::isValidRef('refs/heads/' . $baseRef) && !self::isValidBranchName($baseRef)) {
            throw new RuntimeException('base 分支名非法');
        }

        $name = (string)($author['name'] ?? 'Gitweb');
        $email = (string)($author['email'] ?? 'gitweb@localhost');
        $date = date('c');
        $env = [
            'GIT_AUTHOR_NAME'     => $name,
            'GIT_AUTHOR_EMAIL'    => $email,
            'GIT_AUTHOR_DATE'     => $date,
            'GIT_COMMITTER_NAME'  => $name,
            'GIT_COMMITTER_EMAIL' => $email,
            'GIT_COMMITTER_DATE'  => $date,
        ];

        if ($strategy === 'rebase') {
            return self::rebaseOnto($dir, $baseSha, $headSha, $baseRef, $env);
        }

        $mb = self::mergeBase($dir, $baseSha, $headSha);
        // 任一方向可 fast-forward：直接移动引用（merge/squash 在无分叉时结果一致）
        if ($mb !== null && ($mb === $headSha || $mb === $baseSha)) {
            self::updateRef($dir, 'refs/heads/' . $baseRef, $headSha);
            return $headSha;
        }

        // 真正的三方合并（无工作树；merge-tree --write-tree 自动计算 merge-base）
        $treeResult = self::run(['-C', $dir, 'merge-tree', '--write-tree', $baseSha, $headSha], null, null, $env);
        if ($treeResult['code'] !== 0) {
            throw new RuntimeException('合并冲突，无法自动合并：' . trim($treeResult['err']));
        }
        $tree = trim($treeResult['out']);
        if (!preg_match('/^[0-9a-f]{40,64}$/', $tree)) {
            throw new RuntimeException('合并失败：未得到树对象');
        }

        if ($strategy === 'squash') {
            $newCommit = self::commitTree($dir, $tree, [$baseSha], $message, $env);
        } else {
            $newCommit = self::commitTree($dir, $tree, [$baseSha, $headSha], $message, $env);
        }
        self::updateRef($dir, 'refs/heads/' . $baseRef, $newCommit);
        return $newCommit;
    }

    /** @param array<string,string> $author */
    public static function commitTree(string $dir, string $tree, array $parents, string $message, array $authorEnv): string
    {
        $args = ['-C', $dir, 'commit-tree', $tree];
        foreach ($parents as $p) {
            $args[] = '-p';
            $args[] = $p;
        }
        $result = self::run($args, $message . "\n", null, $authorEnv);
        if ($result['code'] !== 0) {
            throw new RuntimeException('commit-tree 失败: ' . $result['err']);
        }
        $sha = trim($result['out']);
        if (!preg_match('/^[0-9a-f]{40,64}$/', $sha)) {
            throw new RuntimeException('commit-tree 未返回 SHA');
        }
        return $sha;
    }

    /** rebase：将 base..head 的提交逐个在最新 parent 之上重放（冲突则中止，杜绝静默丢数据） */
    private static function rebaseOnto(string $dir, string $baseSha, string $headSha, string $baseRef, array $env): string
    {
        $commits = self::logBetween($dir, $baseSha, $headSha, 2000);
        if ($commits === []) {
            self::updateRef($dir, 'refs/heads/' . $baseRef, $headSha);
            return $headSha;
        }
        $commits = array_reverse($commits); // 最旧 → 最新
        $parent = $baseSha;
        foreach ($commits as $i => $commit) {
            $hash = $commit['hash'];
            // 检测该提交在 $parent 之上重放是否冲突（merge-tree 自动计算 merge-base）
            $check = self::run(['-C', $dir, 'merge-tree', '--write-tree', $parent, $hash], null, null, $env);
            if ($check['code'] !== 0) {
                throw new RuntimeException('rebase 在第 ' . ($i + 1) . ' 个提交处遇到冲突，无法自动 rebase');
            }
            $tree = trim($check['out']);
            if (!preg_match('/^[0-9a-f]{40,64}$/', $tree)) {
                throw new RuntimeException('rebase 失败：未得到树对象');
            }
            $meta = self::commitMeta($dir, $hash);
            if ($meta === null) {
                continue;
            }
            $msg = $meta['body'] !== '' ? $meta['body'] : $meta['subject'];
            $authorEnv = array_merge($env, [
                'GIT_AUTHOR_NAME'     => $meta['author_name'],
                'GIT_AUTHOR_EMAIL'    => $meta['author_email'],
                'GIT_AUTHOR_DATE'     => $meta['author_date'],
            ]);
            $parent = self::commitTree($dir, $tree, [$parent], $msg, $authorEnv);
        }
        self::updateRef($dir, 'refs/heads/' . $baseRef, $parent);
        return $parent;
    }

    // --------------------------------------------------------------- 目录树 / 文件

    /**
     * 列出 ref:path 下的条目
     *
     * @return array<int, array{mode: string, type: string, hash: string, size: ?int, name: string}>
     */
    public static function lsTree(string $dir, string $ref, string $path = ''): array
    {
        $spec = $path === '' ? $ref : $ref . ':' . $path;
        $result = self::run(['-C', $dir, 'ls-tree', '-l', '-z', $spec]);
        if ($result['code'] !== 0) {
            return [];
        }
        $entries = [];
        foreach (explode("\0", rtrim($result['out'], "\0")) as $record) {
            if ($record === '') {
                continue;
            }
            // 注意：-l 输出的 size 字段带右对齐填充空格，需用 + 匹配
            if (preg_match('/^(\d+) (\w+) ([0-9a-f]{40,64}) +(-|\d+)\t(.*)$/s', $record, $m) !== 1) {
                continue;
            }
            $entries[] = [
                'mode' => $m[1],
                'type' => $m[2],
                'hash' => $m[3],
                'size' => $m[4] === '-' ? null : (int)$m[4],
                'name' => $m[5],
            ];
        }
        usort($entries, static function (array $a, array $b): int {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'tree' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });
        return $entries;
    }

    public static function catFileBlob(string $dir, string $sha): ?string
    {
        $result = self::run(['-C', $dir, 'cat-file', 'blob', $sha]);
        return $result['code'] === 0 ? $result['out'] : null;
    }

    public static function blobSize(string $dir, string $sha): int
    {
        $result = self::run(['-C', $dir, 'cat-file', '-s', $sha]);
        return $result['code'] === 0 ? (int)trim($result['out']) : 0;
    }

    /** 对象类型（blob / tree / commit / tag），失败返回 null */
    public static function objectType(string $dir, string $rev): ?string
    {
        $result = self::run(['-C', $dir, 'cat-file', '-t', $rev]);
        if ($result['code'] !== 0) {
            return null;
        }
        $type = trim($result['out']);
        return in_array($type, ['blob', 'tree', 'commit', 'tag'], true) ? $type : null;
    }

    // --------------------------------------------------------------- 提交历史

    /**
     * 提交历史
     *
     * @return array<int, array<string, mixed>>
     */
    public static function log(string $dir, string $ref, string $path = '', int $limit = 30, int $offset = 0): array
    {
        $args = ['-C', $dir, 'log', '--format=%H%x1f%h%x1f%P%x1f%an%x1f%ae%x1f%aI%x1f%s%x1e'];
        if ($limit > 0) {
            $args[] = '-n';
            $args[] = (string)$limit;
        }
        if ($offset > 0) {
            $args[] = '--skip';
            $args[] = (string)$offset;
        }
        $args[] = $ref;
        if ($path !== '') {
            $args[] = '--';
            $args[] = $path;
        }
        $result = self::run($args);
        if ($result['code'] !== 0) {
            return [];
        }
        return self::parseLog($result['out']);
    }

    /** @return array<int, array<string, mixed>> */
    public static function logBetween(string $dir, string $from, string $to, int $limit = 50): array
    {
        $result = self::run(['-C', $dir, 'log', '--format=%H%x1f%h%x1f%P%x1f%an%x1f%ae%x1f%aI%x1f%s%x1e', '-n', (string)$limit, $from . '..' . $to]);
        if ($result['code'] !== 0) {
            return [];
        }
        return self::parseLog($result['out']);
    }

    /** @return array<int, array<string, mixed>> */
    private static function parseLog(string $out): array
    {
        $commits = [];
        foreach (explode("\x1e", $out) as $record) {
            $record = trim($record, "\n");
            if ($record === '') {
                continue;
            }
            $fields = explode("\x1f", $record);
            if (count($fields) < 7) {
                continue;
            }
            $commits[] = [
                'hash'         => $fields[0],
                'short'        => $fields[1],
                'parents'      => $fields[2] === '' ? [] : explode(' ', $fields[2]),
                'author_name'  => $fields[3],
                'author_email' => $fields[4],
                'date'         => $fields[5],
                'subject'      => $fields[6],
            ];
        }
        return $commits;
    }

    /** 单个提交的完整元信息（含完整消息体） */
    public static function commitMeta(string $dir, string $sha): ?array
    {
        $result = self::run(['-C', $dir, 'show', '-s', '--format=%H%x1f%h%x1f%P%x1f%an%x1f%ae%x1f%aI%x1f%cn%x1f%ce%x1f%cI%x1f%B', $sha]);
        if ($result['code'] !== 0) {
            return null;
        }
        $fields = explode("\x1f", $result['out'], 10);
        if (count($fields) < 10) {
            return null;
        }
        return [
            'hash'           => $fields[0],
            'short'          => $fields[1],
            'parents'        => $fields[2] === '' ? [] : explode(' ', $fields[2]),
            'author_name'    => $fields[3],
            'author_email'   => $fields[4],
            'author_date'    => $fields[5],
            'committer_name' => $fields[6],
            'committer_email' => $fields[7],
            'commit_date'    => $fields[8],
            'body'           => rtrim($fields[9], "\n"),
        ];
    }

    /** 提交的 patch（统一 diff 格式） */
    public static function commitPatch(string $dir, string $sha): string
    {
        $parents = self::parentsOf($dir, $sha);
        $args = ['-C', $dir, 'diff-tree', '--no-commit-id', '-p', '-U3', '--no-color'];
        if (count($parents) > 1) {
            $args[] = '-m';
        } else {
            $args[] = '--root';
        }
        $args[] = $sha;
        $result = self::run($args);
        return $result['code'] === 0 ? $result['out'] : '';
    }

    /** 两个引用之间的差异 */
    public static function diffBetween(string $dir, string $from, string $to, string $path = ''): string
    {
        $args = ['-C', $dir, 'diff', '-U3', '--no-color', $from, $to];
        if ($path !== '') {
            $args[] = '--';
            $args[] = $path;
        }
        $result = self::run($args);
        return $result['code'] === 0 ? $result['out'] : '';
    }

    /** 某路径下最后一次提交 */
    public static function lastCommitForPath(string $dir, string $ref, string $path = ''): ?array
    {
        $args = ['-C', $dir, 'log', '-1', '--format=%H%x1f%h%x1f%an%x1f%ae%x1f%aI%x1f%s', $ref];
        if ($path !== '') {
            $args[] = '--';
            $args[] = $path;
        }
        $result = self::run($args);
        if ($result['code'] !== 0 || trim($result['out']) === '') {
            return null;
        }
        $fields = explode("\x1f", trim($result['out']));
        if (count($fields) < 6) {
            return null;
        }
        return [
            'hash' => $fields[0], 'short' => $fields[1], 'author_name' => $fields[2],
            'author_email' => $fields[3], 'date' => $fields[4], 'subject' => $fields[5],
        ];
    }

    // --------------------------------------------------------------- 统计 / 打包

    /** 仓库体积（字节）与对象数 */
    public static function repoStats(string $dir): array
    {
        $result = self::run(['-C', $dir, 'count-objects', '-v']);
        $size = 0;
        $count = 0;
        if ($result['code'] === 0) {
            foreach (explode("\n", $result['out']) as $line) {
                if (str_starts_with($line, 'size:') || str_starts_with($line, 'size-pack:')) {
                    $size += (int)trim(substr($line, strpos($line, ':') + 1));
                }
                if (str_starts_with($line, 'count:')) {
                    $count = (int)trim(substr($line, strpos($line, ':') + 1));
                }
            }
        }
        return ['size_bytes' => $size * 1024, 'objects' => $count];
    }

    /** 提交总数（指定 ref 或全部） */
    public static function commitCount(string $dir, ?string $ref = null): int
    {
        $args = ['-C', $dir, 'rev-list', '--count'];
        $args[] = $ref !== null && self::isValidRef($ref) ? $ref : '--all';
        $result = self::run($args);
        return $result['code'] === 0 ? (int)trim($result['out']) : 0;
    }

    public static function isEmpty(string $dir): bool
    {
        $result = self::run(['-C', $dir, 'for-each-ref', '--format=%(refname)']);
        return trim($result['out']) === '';
    }

    /** 导出归档（tar.gz / zip），返回二进制字符串 */
    public static function archive(string $dir, string $ref, string $format = 'tar.gz'): string
    {
        $result = self::run(['-C', $dir, 'archive', '--format=' . $format, $ref]);
        if ($result['code'] !== 0) {
            throw new RuntimeException('打包失败: ' . $result['err']);
        }
        return $result['out'];
    }

    // --------------------------------------------------------------- 安全校验

    public static function isValidRef(string $ref): bool
    {
        if ($ref === '' || mb_strlen($ref) > 255) {
            return false;
        }
        if (preg_match('/^[A-Za-z0-9._\/-]+$/', $ref) !== 1) {
            return false;
        }
        return $ref[0] !== '-' && !str_contains($ref, '..');
    }

    public static function isSha(string $sha): bool
    {
        return preg_match('/^[0-9a-f]{40}$|^[0-9a-f]{64}$/', $sha) === 1;
    }

    /** 校验仓库相对路径片段（owner/name） */
    public static function isValidRepoPath(string $owner, string $name): bool
    {
        return preg_match('/^[A-Za-z0-9._-]+$/', $owner) === 1
            && preg_match('/^[A-Za-z0-9._-]+$/', $name) === 1
            && !str_contains($owner, '..') && !str_contains($name, '..');
    }

    /** 校验树内路径（防目录穿越） */
    public static function isValidTreePath(string $path): bool
    {
        if ($path === '') {
            return true;
        }
        if (str_starts_with($path, '/') || str_contains($path, "\0") || str_contains($path, '\\')) {
            return false;
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }
        return true;
    }
}
