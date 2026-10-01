SET NAMES utf8mb4;
USE hygiene_audit;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `username` varchar(64) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `token` varchar(64) NOT NULL,
  `qr_code_url` varchar(255) DEFAULT NULL,
  `role` varchar(16) NOT NULL DEFAULT 'employee',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `auth_token` varchar(128) DEFAULT NULL,
  `auth_token_expires` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `inspection_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `score` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `records` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `item_id` int unsigned NOT NULL,
  `item_name_snapshot` varchar(64) DEFAULT NULL,
  `item_score_snapshot` int DEFAULT NULL,
  `sequence_key` int unsigned NOT NULL,
  `issue_image` varchar(255) NOT NULL,
  `fix_image` varchar(255) DEFAULT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `check_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_sequence` (`user_id`,`sequence_key`),
  KEY `user_id` (`user_id`),
  KEY `item_id` (`item_id`),
  KEY `user_check_date` (`user_id`, `check_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Seed: admin (username=admin, password=admin123 on first login), employees, inspection items, records
INSERT INTO `users` (`name`, `username`, `password_hash`, `token`, `role`, `is_active`) VALUES
('管理员', 'admin', NULL, 'admin-token-001', 'admin', 1),
('张三', NULL, NULL, 'emp-token-001', 'employee', 1),
('李四', NULL, NULL, 'emp-token-002', 'employee', 1);

INSERT INTO `inspection_items` (`name`, `score`) VALUES
('地面清洁', 5),
('桌面整理', 3),
('设备摆放', 2),
('垃圾清理', 5);

INSERT INTO `records` (`user_id`, `item_id`, `item_name_snapshot`, `item_score_snapshot`, `sequence_key`, `issue_image`, `status`, `check_date`) VALUES
(2, 1, '地面清洁', 5, 1, '/uploads/issue_1.jpg', 'pending', CURDATE()),
(2, 2, '桌面整理', 3, 2, '/uploads/issue_2.jpg', 'completed', CURDATE()),
(2, 3, '设备摆放', 2, 3, '/uploads/issue_3.jpg', 'pending', CURDATE());

UPDATE `records` SET `fix_image` = '/uploads/fix_2.jpg' WHERE `id` = 2;
