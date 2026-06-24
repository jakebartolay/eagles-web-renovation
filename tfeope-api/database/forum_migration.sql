-- Forum database migration for TFOE-PE
-- Adds forum categories, threads, posts, reactions, and per-user thread views.
-- Existing tables `users` and `user_info` are referenced but not recreated.

START TRANSACTION;

-- Board/section list for grouping forum discussions.
CREATE TABLE IF NOT EXISTS forum_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  description VARCHAR(255) NULL,
  icon VARCHAR(80) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_private TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_forum_categories_slug (slug),
  KEY idx_forum_categories_sort_order (sort_order),
  KEY idx_forum_categories_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Forum boards or sections such as General Discussion and Chapter Updates.';

-- Discussion topics started by portal users.
CREATE TABLE IF NOT EXISTS forum_threads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  is_pinned TINYINT(1) NOT NULL DEFAULT 0,
  is_locked TINYINT(1) NOT NULL DEFAULT 0,
  views INT NOT NULL DEFAULT 0,
  reply_count INT NOT NULL DEFAULT 0,
  last_reply_at DATETIME NULL,
  last_reply_user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_forum_threads_category_slug (category_id, slug),
  KEY idx_forum_threads_category_id (category_id),
  KEY idx_forum_threads_user_id (user_id),
  KEY idx_forum_threads_last_reply_user_id (last_reply_user_id),
  KEY idx_forum_threads_created_at (created_at),
  KEY idx_forum_threads_last_reply_at (last_reply_at),
  KEY idx_forum_threads_activity (is_pinned, last_reply_at, created_at),
  CONSTRAINT fk_forum_threads_category
    FOREIGN KEY (category_id) REFERENCES forum_categories (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_forum_threads_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_forum_threads_last_reply_user
    FOREIGN KEY (last_reply_user_id) REFERENCES users (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Forum discussion threads with opening post body and denormalized activity counters.';

-- Replies within a discussion thread. Parent post supports nested or quoted replies.
CREATE TABLE IF NOT EXISTS forum_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  thread_id INT NOT NULL,
  user_id INT NOT NULL,
  body TEXT NOT NULL,
  parent_post_id INT NULL,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_forum_posts_thread_id (thread_id),
  KEY idx_forum_posts_user_id (user_id),
  KEY idx_forum_posts_parent_post_id (parent_post_id),
  KEY idx_forum_posts_created_at (created_at),
  CONSTRAINT fk_forum_posts_thread
    FOREIGN KEY (thread_id) REFERENCES forum_threads (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_forum_posts_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_forum_posts_parent
    FOREIGN KEY (parent_post_id) REFERENCES forum_posts (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Forum replies or posts inside a thread, with soft delete support.';

-- One reaction per user per forum reply/post.
CREATE TABLE IF NOT EXISTS forum_reactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  user_id INT NOT NULL,
  type ENUM('like', 'helpful', 'eagle') NOT NULL DEFAULT 'like',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_forum_reactions_post_user (post_id, user_id),
  KEY idx_forum_reactions_post_id (post_id),
  KEY idx_forum_reactions_user_id (user_id),
  KEY idx_forum_reactions_created_at (created_at),
  CONSTRAINT fk_forum_reactions_post
    FOREIGN KEY (post_id) REFERENCES forum_posts (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_forum_reactions_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Likes and lightweight reactions on forum posts.';

-- Per-user view tracking for unread/read indicators.
CREATE TABLE IF NOT EXISTS forum_thread_views (
  id INT AUTO_INCREMENT PRIMARY KEY,
  thread_id INT NOT NULL,
  user_id INT NOT NULL,
  last_viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_forum_thread_views_thread_user (thread_id, user_id),
  KEY idx_forum_thread_views_thread_id (thread_id),
  KEY idx_forum_thread_views_user_id (user_id),
  KEY idx_forum_thread_views_last_viewed_at (last_viewed_at),
  CONSTRAINT fk_forum_thread_views_thread
    FOREIGN KEY (thread_id) REFERENCES forum_threads (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_forum_thread_views_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tracks the latest thread view per user for unread forum indicators.';

-- Default forum boards.
INSERT IGNORE INTO forum_categories
  (name, slug, description, icon, sort_order, is_private)
VALUES
  ('General Discussion', 'general-discussion', 'Open discussion for members and general organization topics.', 'message-square', 10, 0),
  ('Eagles News', 'eagles-news', 'Official announcements, news, and updates from TFOE-PE.', 'newspaper', 20, 0),
  ('Chapter Updates', 'chapter-updates', 'Regional, chapter, and club-level activity updates.', 'map-pin', 30, 0),
  ('Member Help', 'member-help', 'Questions about membership, IDs, portal access, and support concerns.', 'help-circle', 40, 0),
  ('Off-Topic', 'off-topic', 'Casual conversations outside official forum topics.', 'coffee', 50, 0);

COMMIT;
