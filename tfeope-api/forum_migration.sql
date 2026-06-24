-- ================================================================
-- TFOE-PE Forum Migration v2 — more compatible, no FK type issues
-- Run in phpMyAdmin on your TFEOPE database
-- If you ran v1 already, run DROP TABLE first (see bottom)
-- ================================================================

-- Drop old tables if they exist (run this if you ran v1 already)
-- SET FOREIGN_KEY_CHECKS = 0;
-- DROP TABLE IF EXISTS forum_reactions;
-- DROP TABLE IF EXISTS forum_posts;
-- DROP TABLE IF EXISTS forum_threads;
-- DROP TABLE IF EXISTS forum_categories;
-- SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE IF NOT EXISTS `forum_categories` (
  `id`           INT           NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(120)  NOT NULL,
  `slug`         VARCHAR(120)  NOT NULL,
  `description`  VARCHAR(500)  DEFAULT NULL,
  `icon`         VARCHAR(10)   DEFAULT '💬',
  `sort_order`   SMALLINT      NOT NULL DEFAULT 0,
  `is_private`   TINYINT(1)    NOT NULL DEFAULT 0,
  `thread_count` INT           NOT NULL DEFAULT 0,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_forum_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `forum_threads` (
  `id`                 INT      NOT NULL AUTO_INCREMENT,
  `category_id`        INT      NOT NULL,
  `user_id`            INT      NOT NULL,
  `title`              VARCHAR(255) NOT NULL,
  `body`               TEXT    NOT NULL,
  `is_pinned`          TINYINT(1) NOT NULL DEFAULT 0,
  `is_locked`          TINYINT(1) NOT NULL DEFAULT 0,
  `views`              INT     NOT NULL DEFAULT 0,
  `reply_count`        INT     NOT NULL DEFAULT 0,
  `last_reply_at`      DATETIME DEFAULT NULL,
  `last_reply_user_id` INT     DEFAULT NULL,
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ft_category` (`category_id`),
  KEY `idx_ft_user`     (`user_id`),
  KEY `idx_ft_activity` (`last_reply_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `forum_posts` (
  `id`             INT      NOT NULL AUTO_INCREMENT,
  `thread_id`      INT      NOT NULL,
  `user_id`        INT      NOT NULL,
  `body`           TEXT     NOT NULL,
  `parent_post_id` INT      DEFAULT NULL,
  `is_deleted`     TINYINT(1) NOT NULL DEFAULT 0,
  `like_count`     INT      NOT NULL DEFAULT 0,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fp_thread`  (`thread_id`),
  KEY `idx_fp_user`    (`user_id`),
  KEY `idx_fp_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `forum_reactions` (
  `id`         INT      NOT NULL AUTO_INCREMENT,
  `post_id`    INT      NOT NULL,
  `user_id`    INT      NOT NULL,
  `type`       ENUM('like','helpful','eagle') NOT NULL DEFAULT 'like',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_forum_reaction` (`post_id`, `user_id`),
  KEY `idx_fr_post` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add last_seen to users table (for online tracking)
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `last_seen` DATETIME NULL DEFAULT NULL;
CREATE INDEX IF NOT EXISTS `idx_users_last_seen` ON `users` (`last_seen`);

-- Add slug to forum_threads if missing
ALTER TABLE `forum_threads` ADD COLUMN IF NOT EXISTS `slug` VARCHAR(255) NULL DEFAULT NULL AFTER `title`;

-- Seed categories (only if empty)
INSERT INTO `forum_categories` (`name`, `slug`, `description`, `icon`, `sort_order`)
SELECT * FROM (
  SELECT 'General Discussion' AS name, 'general'  AS slug, 'Open discussion for all Eagles members.' AS description,    '🦅' AS icon, 1 AS sort_order UNION ALL
  SELECT 'Eagles News',                'news',      'Official announcements and news from TFOE-PE.',              '📢',             2 UNION ALL
  SELECT 'Chapter Updates',            'chapters',  'Updates and news from local Eagle chapters and clubs.',       '🏛️',            3 UNION ALL
  SELECT 'Member Help',                'help',      'Questions, assistance, and support for members.',             '🤝',             4 UNION ALL
  SELECT 'Off-Topic',                  'off-topic', 'Casual conversations not related to Eagles business.',        '☕',             5
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `forum_categories` LIMIT 1);
