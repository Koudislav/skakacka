CREATE TABLE IF NOT EXISTS `system_updates` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`title` VARCHAR(255) NOT NULL,
	`message` TEXT NOT NULL,
	`version` VARCHAR(50) DEFAULT NULL,
	`type` ENUM('info','warning','danger') NOT NULL DEFAULT 'info',
	`created_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS `system_update_seen` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`update_id` INT UNSIGNED NOT NULL,
	`user_id` INT UNSIGNED NOT NULL,
	`seen_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `ux_user_update` (`update_id`,`user_id`),
	KEY `idx_user` (`user_id`),
	KEY `idx_update` (`update_id`),
	CONSTRAINT `fk_update_seen_update` FOREIGN KEY (`update_id`) REFERENCES `system_updates`(`id`) ON DELETE CASCADE,
	CONSTRAINT `fk_update_seen_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;