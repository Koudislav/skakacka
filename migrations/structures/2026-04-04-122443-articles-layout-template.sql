ALTER TABLE `articles`
ADD COLUMN `template_id` INT(10) UNSIGNED NULL AFTER `type`,
ADD COLUMN `template_version` INT(10) UNSIGNED NULL AFTER `template_id`,
ADD COLUMN `template_data_json` LONGTEXT
	CHARACTER SET utf8mb4 COLLATE utf8mb4_bin
	NULL CHECK (json_valid(`template_data_json`)) AFTER `template_version`,
ADD KEY `idx_articles_template_id` (`template_id`),
ADD CONSTRAINT `fk_articles_template`
	FOREIGN KEY (`template_id`) REFERENCES `templates`(`id`)
	ON DELETE SET NULL;

ALTER TABLE `articles_history`
ADD COLUMN `template_id` INT(10) UNSIGNED NULL AFTER `type`,
ADD COLUMN `template_version` INT(10) UNSIGNED NULL AFTER `template_id`,
ADD COLUMN `template_data_json` LONGTEXT
	CHARACTER SET utf8mb4 COLLATE utf8mb4_bin
	NULL CHECK (json_valid(`template_data_json`)) AFTER `template_version`,
ADD KEY `idx_articles_history_template_id` (`template_id`);

DROP TRIGGER IF EXISTS `articles_bu`;
DELIMITER ;;

CREATE TRIGGER `articles_bu`
BEFORE UPDATE ON `articles`
FOR EACH ROW
BEGIN
	INSERT INTO articles_history (
		article_id,
		parent_id,
		title,
		show_title,
		slug,
		path,
		content,
		type,
		template_id,
		template_version,
		template_data_json,
		seo_title,
		seo_description,
		seo_robots,
		og_image,
		is_published,
		published_at,
		created_at,
		updated_at,
		created_by,
		updated_by,
		changed_by,
		changed_at
	)
	VALUES (
		OLD.id,
		OLD.parent_id,
		OLD.title,
		OLD.show_title,
		OLD.slug,
		OLD.path,
		OLD.content,
		OLD.type,
		OLD.template_id,
		OLD.template_version,
		OLD.template_data_json,
		OLD.seo_title,
		OLD.seo_description,
		OLD.seo_robots,
		OLD.og_image,
		OLD.is_published,
		OLD.published_at,
		OLD.created_at,
		OLD.updated_at,
		OLD.created_by,
		OLD.updated_by,
		NEW.updated_by,
		NOW()
	);
END;;

DELIMITER ;