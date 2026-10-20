#!/usr/bin/env php
<?php
/**
 * SSH 权限校验（由 bin/gitweb-shell 调用）
 *
 * 用法：php bin/ssh-check.php <用户名> <owner/repo> <read|write>
 * 退出码：0 = 允许，1 = 拒绝（任何异常一律拒绝）
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/app/bootstrap.php';

use App\Model\Repository;
use App\Model\User;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$username = (string)($argv[1] ?? '');
$repoPath = (string)($argv[2] ?? '');
$mode = (string)($argv[3] ?? 'read');

if ($username === '' || $repoPath === '') {
    exit(1);
}

$parts = explode('/', $repoPath);
if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
    exit(1);
}

try {
    $user = User::findByUsername($username);
    if ($user === null || (string)$user['status'] !== 'active') {
        exit(1);
    }
    $repo = Repository::findByPath($parts[0], $parts[1]);
    if ($repo === null) {
        exit(1);
    }
    $allowed = $mode === 'write'
        ? Repository::canWrite((int)$user['id'], $repo)
        : Repository::canRead((int)$user['id'], $repo);
    exit($allowed ? 0 : 1);
} catch (\Throwable) {
    exit(1);
}
