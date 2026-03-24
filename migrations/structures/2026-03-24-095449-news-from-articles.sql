DROP TABLE IF EXISTS `news`;
CREATE TABLE `news` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`title` varchar(255) NOT NULL,
	`slug` varchar(255) NOT NULL,
	`content` longtext NOT NULL,

	`excerpt` varchar(500) DEFAULT NULL,
	`cover_image` varchar(255) DEFAULT NULL,

	`seo_title` varchar(255) DEFAULT NULL,
	`seo_description` varchar(255) DEFAULT NULL,
	`seo_robots` varchar(50) DEFAULT NULL,
	`og_image` varchar(255) DEFAULT NULL,

	`is_published` tinyint(1) NOT NULL DEFAULT 1,
	`published_at` datetime DEFAULT NULL,
	`published_to` datetime DEFAULT NULL,

	`created_at` datetime NOT NULL DEFAULT current_timestamp(),
	`updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),

	`created_by` int(10) unsigned DEFAULT NULL,
	`updated_by` int(10) unsigned DEFAULT NULL,
	`deleted_at` datetime DEFAULT NULL,
	`deleted_by` int(10) unsigned DEFAULT NULL,

	PRIMARY KEY (`id`),
	UNIQUE KEY `uniq_slug` (`slug`),

	KEY `idx_published` (`is_published`,`published_at`),

	CONSTRAINT `fk_news_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
	CONSTRAINT `fk_news_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
	CONSTRAINT `fk_news_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

DROP TABLE IF EXISTS `news_history`;
CREATE TABLE `news_history` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`news_id` int(10) unsigned NOT NULL,

	`title` varchar(255) NOT NULL,
	`slug` varchar(255) NOT NULL,
	`content` longtext NOT NULL,

	`excerpt` varchar(500) DEFAULT NULL,
	`cover_image` varchar(255) DEFAULT NULL,

	`seo_title` varchar(255) DEFAULT NULL,
	`seo_description` varchar(255) DEFAULT NULL,
	`seo_robots` varchar(50) DEFAULT NULL,
	`og_image` varchar(255) DEFAULT NULL,

	`is_published` tinyint(1) NOT NULL,
	`published_at` datetime DEFAULT NULL,
	`published_to` datetime DEFAULT NULL,

	`created_at` datetime NOT NULL,
	`updated_at` datetime DEFAULT NULL,

	`created_by` int(10) unsigned DEFAULT NULL,
	`updated_by` int(10) unsigned DEFAULT NULL,

	`changed_by` int(10) unsigned DEFAULT NULL,
	`changed_at` datetime NOT NULL DEFAULT current_timestamp(),

	PRIMARY KEY (`id`),

	KEY `idx_news_id` (`news_id`),

	CONSTRAINT `fk_news_history_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
	CONSTRAINT `fk_news_history_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`),
	CONSTRAINT `fk_news_history_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

DELIMITER ;;

CREATE TRIGGER `news_bu` BEFORE UPDATE ON `news`
FOR EACH ROW
BEGIN
	INSERT INTO `news_history` (
		news_id,
		title,
		slug,
		content,
		excerpt,
		cover_image,
		seo_title,
		seo_description,
		seo_robots,
		og_image,
		is_published,
		published_at,
		published_to,
		created_at,
		updated_at,
		created_by,
		updated_by,
		changed_by,
		changed_at
	)
	VALUES (
		OLD.id,
		OLD.title,
		OLD.slug,
		OLD.content,
		OLD.excerpt,
		OLD.cover_image,
		OLD.seo_title,
		OLD.seo_description,
		OLD.seo_robots,
		OLD.og_image,
		OLD.is_published,
		OLD.published_at,
		OLD.published_to,
		OLD.created_at,
		OLD.updated_at,
		OLD.created_by,
		OLD.updated_by,
		NEW.updated_by,
		NOW()
	);
END;;

DELIMITER ;

INSERT INTO `news` (
	title,
	slug,
	content,
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
	deleted_at,
	deleted_by
)
SELECT
	title,
	slug,
	content,
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
	deleted_at,
	deleted_by
FROM `articles`
WHERE `type` = 'news';

DELETE FROM `articles`
WHERE `type` = 'news';

ALTER TABLE `articles`
MODIFY `type` enum('article','index') NOT NULL DEFAULT 'article';