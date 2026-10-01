-- 为 records 表增加检查日期字段，并为历史数据补齐（可重复执行）
-- 执行示例：
-- docker compose exec -T db mysql -uroot -proot hygiene_audit < backend/database/migrate_add_check_date.sql

SET NAMES utf8mb4;
USE hygiene_audit;

SET @db = DATABASE();

-- 若不存在则添加 check_date（仅日期部分）
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

-- 对于缺少 check_date 的历史记录，优先使用 created_at 的日期，否则使用当前日期
UPDATE `records`
SET `check_date` = COALESCE(DATE(`created_at`), CURDATE())
WHERE `check_date` IS NULL;

