-- =============================================================
-- Gitweb 数据库结构（MySQL 8.0+，InnoDB / utf8mb4）
-- 导入：mysql -u root -p gitweb < schema.mysql.sql
-- =============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------- 用户 ----------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(39) NOT NULL COMMENT '登录名（URL 用）',
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(80) NOT NULL DEFAULT '',
  `bio` VARCHAR(500) NOT NULL DEFAULT '',
  `location` VARCHAR(80) NOT NULL DEFAULT '',
  `website` VARCHAR(190) NOT NULL DEFAULT '',
  `role` VARCHAR(10) NOT NULL DEFAULT 'user' COMMENT 'user|admin',
  `status` VARCHAR(10) NOT NULL DEFAULT 'active' COMMENT 'active|banned',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`username`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户';

-- ---------- 记住我令牌 ----------
CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_remember_token` (`token_hash`),
  KEY `idx_remember_user` (`user_id`),
  CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------- 登录尝试（防暴力破解） ----------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip` VARCHAR(45) NOT NULL,
  `username` VARCHAR(39) NOT NULL DEFAULT '',
  `successful` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_ip_time` (`ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------- SSH 公钥 ----------
CREATE TABLE IF NOT EXISTS `ssh_keys` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(120) NOT NULL DEFAULT '',
  `fingerprint` VARCHAR(100) NOT NULL,
  `public_key` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ssh_fingerprint` (`fingerprint`),
  KEY `idx_ssh_user` (`user_id`),
  CONSTRAINT `fk_ssh_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------- 个人访问令牌（HTTPS git 认证） ----------
CREATE TABLE IF NOT EXISTS `access_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(120) NOT NULL DEFAULT '',
  `token_hash` CHAR(64) NOT NULL,
  `scopes` VARCHAR(100) NOT NULL DEFAULT 'repo',
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `last_used_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token_hash` (`token_hash`),
  KEY `idx_token_user` (`user_id`),
  CONSTRAINT `fk_token_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------- 仓库 ----------
CREATE TABLE IF NOT EXISTS `repositories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(500) NOT NULL DEFAULT '',
  `homepage` VARCHAR(190) NOT NULL DEFAULT '',
  `is_private` TINYINT(1) NOT NULL DEFAULT 0,
  `is_fork` TINYINT(1) NOT NULL DEFAULT 0,
  `parent_id` INT UNSIGNED DEFAULT NULL COMMENT 'Fork 来源仓库',
  `default_branch` VARCHAR(100) NOT NULL DEFAULT 'main',
  `has_issues` TINYINT(1) NOT NULL DEFAULT 1,
  `size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `stars_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `forks_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `issues_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `pushed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_repo_owner_name` (`owner_id`, `name`),
  KEY `idx_repo_pushed` (`pushed_at`),
  KEY `idx_repo_private_created` (`is_private`, `created_at`),
  CONSTRAINT `fk_repo_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_repo_parent` FOREIGN KEY (`parent_id`) REFERENCES `repositories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Git 仓库';

-- ---------- 仓库协作者 ----------
CREATE TABLE IF NOT EXISTS `collaborators` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `repo_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `permission` VARCHAR(10) NOT NULL DEFAULT 'read' COMMENT 'read|write|admin',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_collab_repo_user` (`repo_id`, `user_id`),
  KEY `idx_collab_user` (`user_id`),
  CONSTRAINT `fk_collab_repo` FOREIGN KEY (`repo_id`) REFERENCES `repositories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_collab_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------- 组织（beta2 预留） ----------
CREATE TABLE IF NOT EXISTS `organizations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(39) NOT NULL,
  `display_name` VARCHAR(80) NOT NULL DEFAULT '',
  `description` VARCHAR(500) NOT NULL DEFAULT '',
  `owner_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_org_name` (`name`),
  CONSTRAINT `fk_org_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `org_members` (
  `org_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `role` VARCHAR(10) NOT NULL DEFAULT 'member' COMMENT 'owner|admin|member',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`org_id`, `user_id`),
  KEY `idx_orgmember_user` (`user_id`),
  CONSTRAINT `fk_orgmember_org` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_orgmember_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------- Star ----------
CREATE TABLE IF NOT EXISTS `stars` (
  `user_id` INT UNSIGNED NOT NULL,
  `repo_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `repo_id`),
  KEY `idx_stars_repo` (`repo_id`),
  CONSTRAINT `fk_stars_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stars_repo` FOREIGN KEY (`repo_id`) REFERENCES `repositories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------- 关注 ----------
CREATE TABLE IF NOT EXISTS `follows` (
  `follower_id` INT UNSIGNED NOT NULL,
  `following_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`follower_id`, `following_id`),
  KEY `idx_follows_following` (`following_id`),
  CONSTRAINT `fk_follows_follower` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_follows_following` FOREIGN KEY (`following_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------- Issue 标签 ----------
CREATE TABLE IF NOT EXISTS `labels` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `repo_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(60) NOT NULL,
  `color` VARCHAR(7) NOT NULL DEFAULT '#0969da',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_label_repo_name` (`repo_id`, `name`),
  CONSTRAINT `fk_label_repo` FOREIGN KEY (`repo_id`) REFERENCES `repositories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------- Issues ----------
CREATE TABLE IF NOT EXISTS `issues` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `repo_id` INT UNSIGNED NOT NULL,
  `number` INT UNSIGNED NOT NULL COMMENT '仓库内序号',
  `title` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `state` VARCHAR(10) NOT NULL DEFAULT 'open' COMMENT 'open|closed',
  `author_id` INT UNSIGNED NOT NULL,
  `assignee_id` INT UNSIGNED DEFAULT NULL,
  `is_pull` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '预留：Pull Request',
  `closed_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `closed_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_issue_repo_number` (`repo_id`, `number`),
  KEY `idx_issue_state` (`repo_id`, `state`, `created_at`),
  CONSTRAINT `fk_issue_repo` FOREIGN KEY (`repo_id`) REFERENCES `repositories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_issue_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `issue_labels` (
  `issue_id` INT UNSIGNED NOT NULL,
  `label_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`issue_id`, `label_id`),
  KEY `idx_issuelabel_label` (`label_id`),
  CONSTRAINT `fk_issuelabel_issue` FOREIGN KEY (`issue_id`) REFERENCES `issues` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_issuelabel_label` FOREIGN KEY (`label_id`) REFERENCES `labels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------- Issue 评论 ----------
CREATE TABLE IF NOT EXISTS `comments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `issue_id` INT UNSIGNED NOT NULL,
  `author_id` INT UNSIGNED NOT NULL,
  `body` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_comment_issue` (`issue_id`, `created_at`),
  CONSTRAINT `fk_comment_issue` FOREIGN KEY (`issue_id`) REFERENCES `issues` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comment_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------- Releases ----------
CREATE TABLE IF NOT EXISTS `releases` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `repo_id` INT UNSIGNED NOT NULL,
  `tag_name` VARCHAR(100) NOT NULL,
  `name` VARCHAR(150) NOT NULL DEFAULT '',
  `body` TEXT NOT NULL,
  `author_id` INT UNSIGNED NOT NULL,
  `is_draft` TINYINT(1) NOT NULL DEFAULT 0,
  `is_prerelease` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_release_repo_tag` (`repo_id`, `tag_name`),
  CONSTRAINT `fk_release_repo` FOREIGN KEY (`repo_id`) REFERENCES `repositories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_release_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------- Webhooks（beta2 预留） ----------
CREATE TABLE IF NOT EXISTS `webhooks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `repo_id` INT UNSIGNED NOT NULL,
  `url` VARCHAR(500) NOT NULL,
  `secret` VARCHAR(100) NOT NULL DEFAULT '',
  `events` VARCHAR(255) NOT NULL DEFAULT 'push',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_webhook_repo` (`repo_id`),
  CONSTRAINT `fk_webhook_repo` FOREIGN KEY (`repo_id`) REFERENCES `repositories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------- 站点设置（KV） ----------
CREATE TABLE IF NOT EXISTS `settings` (
  `name` VARCHAR(60) NOT NULL,
  `value` TEXT NOT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------- 默认站点设置 ----------
INSERT INTO `settings` (`name`, `value`) VALUES
  ('site_name', 'Gitweb'),
  ('site_description', '属于你自己的代码托管平台'),
  ('allow_register', '1'),
  ('allow_anonymous', '1')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

SET FOREIGN_KEY_CHECKS = 1;
