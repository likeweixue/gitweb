#!/usr/bin/env php
<?php
/**
 * 仓库统计刷新（post-receive 钩子 / cron 调用）
 *
 * 用法：
 *   php bin/cron-stats.php --repo=/path/to/repo.git   单个仓库（钩子调用）
 *   php bin/cron-stats.php --all                      全部仓库（cron 调用）
 *
 * 建议 crontab 每 10 分钟执行一次（全量刷新兜底）：
 *   0,10,20,30,40,50 * * * * php /www/sites/gitweb.example.com/bin/cron-stats.php --all >/dev/null 2>&1
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/app/bootstrap.php';

use App\Model\Repository;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$repoPath = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--repo=')) {
        $repoPath = substr($arg, strlen('--repo='));
    }
}

try {
    if ($repoPath !== null && $repoPath !== '') {
        $repo = Repository::findByDiskPath($repoPath);
        if ($repo !== null) {
            Repository::refreshStats((int)$repo['id']);
        }
        exit(0);
    }

    $page = 1;
    do {
        $batch = Repository::all($page, 50, '')['items'];
        foreach ($batch as $repo) {
            Repository::refreshStats((int)$repo['id']);
        }
        $page++;
    } while (count($batch) === 50);
} catch (\Throwable $e) {
    App\Core\Logger::error('cron-stats 失败: ' . $e->getMessage());
    exit(1);
}
exit(0);
