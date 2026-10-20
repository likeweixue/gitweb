<?php
/**
 * Gitweb 数据库迁移脚本
 * 用途：在已存在的实例上补齐新增表（Pull Request 相关），幂等、双驱动。
 * 用法：php bin/migrate.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Database;

$driver = (string)Config::get('db.driver', 'mysql');
$isMysql = $driver !== 'sqlite';

echo "驱动：$driver\n";

$statements = $isMysql ? [
    "CREATE TABLE IF NOT EXISTS `pull_requests` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `repo_id` INT UNSIGNED NOT NULL,
      `number` INT UNSIGNED NOT NULL,
      `title` VARCHAR(255) NOT NULL,
      `body` TEXT NOT NULL,
      `author_id` INT UNSIGNED NOT NULL,
      `base_ref` VARCHAR(100) NOT NULL DEFAULT 'main',
      `head_ref` VARCHAR(100) NOT NULL DEFAULT '',
      `head_repo_id` INT UNSIGNED DEFAULT NULL,
      `head_sha` VARCHAR(64) NOT NULL DEFAULT '',
      `state` VARCHAR(10) NOT NULL DEFAULT 'open',
      `merge_strategy` VARCHAR(10) DEFAULT NULL,
      `merged_by` INT UNSIGNED DEFAULT NULL,
      `merged_at` TIMESTAMP NULL DEFAULT NULL,
      `closed_by` INT UNSIGNED DEFAULT NULL,
      `closed_at` TIMESTAMP NULL DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uk_pr_repo_number` (`repo_id`, `number`),
      KEY `idx_pr_repo_state` (`repo_id`, `state`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
    "CREATE TABLE IF NOT EXISTS `pull_request_comments` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `pr_id` INT UNSIGNED NOT NULL,
      `author_id` INT UNSIGNED NOT NULL,
      `body` TEXT NOT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_prcomment_pr` (`pr_id`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
    "CREATE TABLE IF NOT EXISTS `pull_request_reviews` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `pr_id` INT UNSIGNED NOT NULL,
      `author_id` INT UNSIGNED NOT NULL,
      `state` VARCHAR(15) NOT NULL DEFAULT 'commented',
      `body` TEXT NOT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_prreview_pr` (`pr_id`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
] : [
    "CREATE TABLE IF NOT EXISTS `pull_requests` (
      `id` INTEGER PRIMARY KEY AUTOINCREMENT,
      `repo_id` INTEGER NOT NULL REFERENCES `repositories`(`id`) ON DELETE CASCADE,
      `number` INTEGER NOT NULL,
      `title` VARCHAR(255) NOT NULL,
      `body` TEXT NOT NULL DEFAULT '',
      `author_id` INTEGER NOT NULL REFERENCES `users`(`id`) ON DELETE CASCADE,
      `base_ref` VARCHAR(100) NOT NULL DEFAULT 'main',
      `head_ref` VARCHAR(100) NOT NULL DEFAULT '',
      `head_repo_id` INTEGER DEFAULT NULL REFERENCES `repositories`(`id`) ON DELETE SET NULL,
      `head_sha` VARCHAR(64) NOT NULL DEFAULT '',
      `state` VARCHAR(10) NOT NULL DEFAULT 'open',
      `merge_strategy` VARCHAR(10) DEFAULT NULL,
      `merged_by` INTEGER DEFAULT NULL REFERENCES `users`(`id`) ON DELETE SET NULL,
      `merged_at` TIMESTAMP NULL DEFAULT NULL,
      `closed_by` INTEGER DEFAULT NULL REFERENCES `users`(`id`) ON DELETE SET NULL,
      `closed_at` TIMESTAMP NULL DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE (`repo_id`, `number`)
    )",
    "CREATE INDEX IF NOT EXISTS `idx_pr_repo_state` ON `pull_requests` (`repo_id`, `state`, `created_at`)",
    "CREATE TABLE IF NOT EXISTS `pull_request_comments` (
      `id` INTEGER PRIMARY KEY AUTOINCREMENT,
      `pr_id` INTEGER NOT NULL REFERENCES `pull_requests`(`id`) ON DELETE CASCADE,
      `author_id` INTEGER NOT NULL REFERENCES `users`(`id`) ON DELETE CASCADE,
      `body` TEXT NOT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE INDEX IF NOT EXISTS `idx_prcomment_pr` ON `pull_request_comments` (`pr_id`, `created_at`)",
    "CREATE TABLE IF NOT EXISTS `pull_request_reviews` (
      `id` INTEGER PRIMARY KEY AUTOINCREMENT,
      `pr_id` INTEGER NOT NULL REFERENCES `pull_requests`(`id`) ON DELETE CASCADE,
      `author_id` INTEGER NOT NULL REFERENCES `users`(`id`) ON DELETE CASCADE,
      `state` VARCHAR(15) NOT NULL DEFAULT 'commented',
      `body` TEXT NOT NULL DEFAULT '',
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE INDEX IF NOT EXISTS `idx_prreview_pr` ON `pull_request_reviews` (`pr_id`, `created_at`)",
];

$ok = 0;
foreach ($statements as $sql) {
    try {
        Database::query($sql);
        $ok++;
    } catch (\Throwable $e) {
        echo "  跳过/失败: " . $e->getMessage() . "\n";
    }
}
echo "已执行 $ok 条迁移语句。\n";
echo "完成。\n";
