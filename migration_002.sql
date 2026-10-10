-- Adds the role system. Select the openboard database first.
-- Run ONCE on a database that already has the Part 1 schema (migration_001.sql or Part 1's database.sql).
-- Running it a second time fails safely with "Duplicate column name 'role'" and changes nothing.
-- All existing users, posts, comments, votes and tags are untouched; existing users become 'user'.

ALTER TABLE users
  ADD COLUMN role ENUM('user', 'admin') NOT NULL DEFAULT 'user' AFTER password_hash;

-- Check afterwards:  SELECT username, role FROM users;
