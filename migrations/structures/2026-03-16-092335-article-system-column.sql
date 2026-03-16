ALTER TABLE `articles`
	ADD COLUMN `is_system` TINYINT(1) NOT NULL DEFAULT 0 AFTER `type`,
	ADD COLUMN `system_description` TEXT NULL AFTER `is_system`,
	ADD INDEX `idx_articles_is_system` (`is_system`);

ALTER TABLE `articles_history`
	MODIFY `changed_by` INT(10) UNSIGNED NULL;