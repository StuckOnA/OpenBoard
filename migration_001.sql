-- Upgrade the prototype database in place. BACK UP FIRST.
-- Run with the openboard database selected (phpMyAdmin: openboard -> SQL tab).
-- Only for the ORIGINAL prototype database (posts table without user_id). Run once.
-- After this, import database.sql to add the remaining tables, then run migration_002.sql.

CREATE TABLE users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(20)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Placeholder author for old prototype posts. '!' is not a valid password hash,
-- so nobody can ever log in as 'legacy'.
INSERT INTO users (username, password_hash) VALUES ('legacy', '!');

-- Add the author column as NULL first, fill it for existing rows, then make it required.
ALTER TABLE posts ADD COLUMN user_id INT UNSIGNED NULL AFTER id;
UPDATE posts SET user_id = (SELECT id FROM users WHERE username = 'legacy');

ALTER TABLE posts
  MODIFY user_id INT UNSIGNED NOT NULL,
  ADD KEY idx_posts_user (user_id),
  ADD KEY idx_posts_created (created_at),
  ADD CONSTRAINT fk_posts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE;
