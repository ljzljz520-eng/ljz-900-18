-- 为 records 增加检查项“快照”字段，并为 users 增加启用/禁用字段（可重复执行）
-- 执行示例：
-- docker compose exec -T db mysql -uroot -proot hygiene_audit < backend/database/migrate_add_snapshots.sql

SET NAMES utf8mb4;
USE hygiene_audit;

SET @db = DATABASE();

-- users.is_active
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'users'
       AND COLUMN_NAME = 'is_active') = 0,
    'ALTER TABLE `users` ADD COLUMN `is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `role`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- records.check_date（若你已经执行 migrate_add_check_date.sql，这里会跳过）
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'records'
       AND COLUMN_NAME = 'check_date') = 0,
    'ALTER TABLE `records` ADD COLUMN `check_date` date DEFAULT NULL AFTER `status`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- records.item_name_snapshot
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'records'
       AND COLUMN_NAME = 'item_name_snapshot') = 0,
    'ALTER TABLE `records` ADD COLUMN `item_name_snapshot` varchar(64) DEFAULT NULL AFTER `item_id`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- records.item_score_snapshot
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'records'
       AND COLUMN_NAME = 'item_score_snapshot') = 0,
    'ALTER TABLE `records` ADD COLUMN `item_score_snapshot` int DEFAULT NULL AFTER `item_name_snapshot`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 索引（若不存在）
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*)
     FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'records'
       AND INDEX_NAME = 'user_check_date') = 0,
    'ALTER TABLE `records` ADD KEY `user_check_date` (`user_id`, `check_date`)',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 回填 check_date（与 migrate_add_check_date.sql 一致）
UPDATE `records`
SET `check_date` = COALESCE(`check_date`, DATE(`created_at`), CURDATE());

-- 回填快照：仅对空快照的记录补齐
UPDATE `records` r
JOIN `inspection_items` i ON i.id = r.item_id
SET r.item_name_snapshot = COALESCE(r.item_name_snapshot, i.name),
    r.item_score_snapshot = COALESCE(r.item_score_snapshot, i.score)
WHERE (r.item_name_snapshot IS NULL OR r.item_name_snapshot = '')
   OR r.item_score_snapshot IS NULL;

