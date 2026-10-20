-- =============================================================
-- Gitweb 数据库结构（SQLite —— 仅用于本地开发 / 沙箱测试）
-- 生产环境请使用 schema.mysql.sql（MySQL 8）
-- =============================================================

CREATE TABLE IF NOT EXISTS `users` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `username` VARCHAR(39) NOT NULL UNIQUE,
  `email` VARCHAR(190) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(80) NOT NULL DEFAULT '',
  `bio` VARCHAR(500) NOT NULL DEFAULT '',
  `location` VARCHAR(80) NOT NULL DEFAULT '',
  `website` VARCHAR(190) NOT NULL DEFAULT '',
  `role` VARCHAR(10) NOT NULL DEFAULT 'user',
  `status` VARCHAR(10) NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS `idx_users_created_at` ON `users` (`created_at`);

CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `user_id` INTEGER NOT NULL REFERENCES `users`(`id`) ON DELETE CASCADE,
  `token_hash` CHAR(64) NOT NULL UNIQUE,
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS `idx_remember_user` ON `remember_tokens` (`user_id`);

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `ip` VARCHAR(45) NOT NULL,
  `username` VARCHAR(39) NOT NULL DEFAULT '',
  `successful` INTEGER NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS `idx_attempts_ip_time` ON `login_attempts` (`ip`, `created_at`);

CREATE TABLE IF NOT EXISTS `ssh_keys` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `user_id` INTEGER NOT NULL REFERENCES `users`(`id`) ON DELETE CASCADE,
  `title` VARCHAR(120) NOT NULL DEFAULT '',
  `fingerprint` VARCHAR(100) NOT NULL UNIQUE,
  `public_key` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` TIMESTAMP NULL DEFAULT NULL
);
CREATE INDEX IF NOT EXISTS `idx_ssh_user` ON `ssh_keys` (`user_id`);

CREATE TABLE IF NOT EXISTS `access_tokens` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `user_id` INTEGER NOT NULL REFERENCES `users`(`id`) ON DELETE CASCADE,
  `name` VARCHAR(120) NOT NULL DEFAULT '',
  `token_hash` CHAR(64) NOT NULL UNIQUE,
  `scopes` VARCHAR(100) NOT NULL DEFAULT 'repo',
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `last_used_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS `idx_token_user` ON `access_tokens` (`user_id`);

CREATE TABLE IF NOT EXISTS `repositories` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `owner_id` INTEGER NOT NULL REFERENCES `users`(`id`) ON DELETE CASCADE,
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(500) NOT NULL DEFAULT '',
  `homepage` VARCHAR(190) NOT NULL DEFAULT '',
  `is_private` INTEGER NOT NULL DEFAULT 0,
  `is_fork` INTEGER NOT NULL DEFAULT 0,
  `parent_id` INTEGER DEFAULT NULL REFERENCES `repositories`(`id`) ON DELETE SET NULL,
  `default_branch` VARCHAR(100) NOT NULL DEFAULT 'main',
  `has_issues` INTEGER NOT NULL DEFAULT 1,
  `size_bytes` INTEGER NOT NULL DEFAULT 0,
  `stars_count` INTEGER NOT NULL DEFAULT 0,
  `forks_count` INTEGER NOT NULL DEFAULT 0,
  `issues_count` INTEGER NOT NULL DEFAULT 0,
  `pushed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (`owner_id`, `name`)
);
CREATE INDEX IF NOT EXISTS `idx_repo_pushed` ON `repositories` (`pushed_at`);
CREATE INDEX IF NOT EXISTS `idx_repo_private_created` ON `repositories` (`is_private`, `created_at`);

CREATE TABLE IF NOT EXISTS `collaborators` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `repo_id` INTEGER NOT NULL REFERENCES `repositories`(`id`) ON DELETE CASCADE,
  `user_id` INTEGER NOT NULL REFERENCES `users`(`id`) ON DELETE CASCADE,
  `permission` VARCHAR(10) NOT NULL DEFAULT 'read',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (`repo_id`, `user_id`)
);
CREATE INDEX IF NOT EXISTS `idx_collab_user` ON `collaborators` (`user_id`);

CREATE TABLE IF NOT EXISTS `organizations` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `name` VARCHAR(39) NOT NULL UNIQUE,
  `display_name` VARCHAR(80) NOT NULL DEFAULT '',
  `description` VARCHAR(500) NOT NULL DEFAULT '',
  `owner_id` INTEGER NOT NULL REFERENCES `users`(`id`) ON DELETE CASCADE,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `org_members` (
  `org_id` INTEGER NOT NULL REFERENCES `organizations`(`id`) ON DELETE CASCADE,
  `user_id` INTEGER NOT NULL REFERENCES `users`(`id`) ON DELETE CASCADE,
  `role` VARCHAR(10) NOT NULL DEFAULT 'member',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`org_id`, `user_id`)
);
CREATE INDEX IF NOT EXISTS `idx_orgmember_user` ON `org_members` (`user_id`);

CREATE TABLE IF NOT EXISTS `stars` (
  `user_id` INTEGER NOT NULL REFERENCES `users`(`id`) ON DELETE CASCADE,
  `repo_id` INTEGER NOT NULL REFERENCES `repositories`(`id`) ON DELETE CASCADE,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `repo_id`)
);
CREATE INDEX IF NOT EXISTS `idx_stars_repo` ON `stars` (`repo_id`);

CREATE TABLE IF NOT EXISTS `follows` (
  `follower_id` INTEGER NOT NULL REFERENCES `users`(`id`) ON DELETE CASCADE,
  `following_id` INTEGER NOT NULL REFERENCES `users`(`id`) ON DELETE CASCADE,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`follower_id`, `following_id`)
);
CREATE INDEX IF NOT EXISTS `idx_follows_following` ON `follows` (`following_id`);

CREATE TABLE IF NOT EXISTS `labels` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `repo_id` INTEGER NOT NULL REFERENCES `repositories`(`id`) ON DELETE CASCADE,
  `name` VARCHAR(60) NOT NULL,
  `color` VARCHAR(7) NOT NULL DEFAULT '#0969da',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (`repo_id`, `name`)
);

CREATE TABLE IF NOT EXISTS `issues` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `repo_id` INTEGER NOT NULL REFERENCES `repositories`(`id`) ON DELETE CASCADE,
  `number` INTEGER NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `state` VARCHAR(10) NOT NULL DEFAULT 'open',
  `author_id` INTEGER NOT NULL REFERENCES `users`(`id`) ON DELETE CASCADE,
  `assignee_id` INTEGER DEFAULT NULL,
  `is_pull` INTEGER NOT NULL DEFAULT 0,
  `closed_by` INTEGER DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at` TIMESTAMP NULL DEFAULT NULL,
  UNIQUE (`repo_id`, `number`)
);
CREATE INDEX IF NOT EXISTS `idx_issue_state` ON `issues` (`repo_id`, `state`, `created_at`);

CREATE TABLE IF NOT EXISTS `issue_labels` (
  `issue_id` INTEGER NOT NULL REFERENCES `issues`(`id`) ON DELETE CASCADE,
  `label_id` INTEGER NOT NULL REFERENCES `labels`(`id`) ON DELETE CASCADE,
  PRIMARY KEY (`issue_id`, `label_id`)
);
CREATE INDEX IF NOT EXISTS `idx_issuelabel_label` ON `issue_labels` (`label_id`);

CREATE TABLE IF NOT EXISTS `comments` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `issue_id` INTEGER NOT NULL REFERENCES `issues`(`id`) ON DELETE CASCADE,
  `author_id` INTEGER NOT NULL REFERENCES `users`(`id`) ON DELETE CASCADE,
  `body` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS `idx_comment_issue` ON `comments` (`issue_id`, `created_at`);

CREATE TABLE IF NOT EXISTS `releases` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `repo_id` INTEGER NOT NULL REFERENCES `repositories`(`id`) ON DELETE CASCADE,
  `tag_name` VARCHAR(100) NOT NULL,
  `name` VARCHAR(150) NOT NULL DEFAULT '',
  `body` TEXT NOT NULL,
  `author_id` INTEGER NOT NULL REFERENCES `users`(`id`) ON DELETE CASCADE,
  `is_draft` INTEGER NOT NULL DEFAULT 0,
  `is_prerelease` INTEGER NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (`repo_id`, `tag_name`)
);

CREATE TABLE IF NOT EXISTS `webhooks` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `repo_id` INTEGER NOT NULL REFERENCES `repositories`(`id`) ON DELETE CASCADE,
  `url` VARCHAR(500) NOT NULL,
  `secret` VARCHAR(100) NOT NULL DEFAULT '',
  `events` VARCHAR(255) NOT NULL DEFAULT 'push',
  `active` INTEGER NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS `idx_webhook_repo` ON `webhooks` (`repo_id`);

-- ---------- Pull Requests ----------
CREATE TABLE IF NOT EXISTS `pull_requests` (
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
);
CREATE INDEX IF NOT EXISTS `idx_pr_repo_state` ON `pull_requests` (`repo_id`, `state`, `created_at`);

CREATE TABLE IF NOT EXISTS `pull_request_comments` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `pr_id` INTEGER NOT NULL REFERENCES `pull_requests`(`id`) ON DELETE CASCADE,
  `author_id` INTEGER NOT NULL REFERENCES `users`(`id`) ON DELETE CASCADE,
  `body` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS `idx_prcomment_pr` ON `pull_request_comments` (`pr_id`, `created_at`);

CREATE TABLE IF NOT EXISTS `pull_request_reviews` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `pr_id` INTEGER NOT NULL REFERENCES `pull_requests`(`id`) ON DELETE CASCADE,
  `author_id` INTEGER NOT NULL REFERENCES `users`(`id`) ON DELETE CASCADE,
  `state` VARCHAR(15) NOT NULL DEFAULT 'commented',
  `body` TEXT NOT NULL DEFAULT '',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS `idx_prreview_pr` ON `pull_request_reviews` (`pr_id`, `created_at`);

CREATE TABLE IF NOT EXISTS `settings` (
  `name` VARCHAR(60) PRIMARY KEY,
  `value` TEXT NOT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO `settings` (`name`, `value`) VALUES
  ('site_name', 'Gitweb'),
  ('site_description', '属于你自己的代码托管平台'),
  ('allow_register', '1'),
  ('allow_anonymous', '1');
