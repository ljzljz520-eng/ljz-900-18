-- 为已有数据库添加登录相关字段（可重复执行，缺啥补啥）
-- 执行: docker compose exec -T db mysql -uroot -proot hygiene_audit < backend/database/migrate_add_auth.sql
SET NAMES utf8mb4;
USE hygiene_audit;

-- 仅当列不存在时添加（MySQL 8.0）
SET @db = DATABASE();

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'username') = 0,
  'ALTER TABLE `users` ADD COLUMN `username` varchar(64) DEFAULT NULL AFTER `name`',
  'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_hash') = 0,
  'ALTER TABLE `users` ADD COLUMN `password_hash` varchar(255) DEFAULT NULL AFTER `username`',
  'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'auth_token') = 0,
  'ALTER TABLE `users` ADD COLUMN `auth_token` varchar(128) DEFAULT NULL AFTER `role`',
  'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'auth_token_expires') = 0,
  'ALTER TABLE `users` ADD COLUMN `auth_token_expires` datetime DEFAULT NULL AFTER `auth_token`',
  'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 若不存在则添加 username 唯一索引
SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND INDEX_NAME = 'username') = 0,
  'ALTER TABLE `users` ADD UNIQUE KEY `username` (`username`)',
  'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `users` SET `username` = 'admin' WHERE `role` = 'admin' AND (`username` IS NULL OR `username` = '') LIMIT 1;
