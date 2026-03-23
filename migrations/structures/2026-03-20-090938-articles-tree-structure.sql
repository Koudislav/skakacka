-- 1) přidání sloupců
ALTER TABLE articles
ADD parent_id INT(10) UNSIGNED NULL AFTER id,
ADD path VARCHAR(500) NULL AFTER slug;

-- 2) naplnění path (zatím flat struktura)
UPDATE articles
SET path = slug;

-- 3) drop UNIQUE slug
ALTER TABLE articles
DROP INDEX slug;

-- 4) nový UNIQUE (parent_id, slug)
ALTER TABLE articles
ADD UNIQUE KEY uniq_parent_slug (parent_id, slug);

-- 5) UNIQUE na path
ALTER TABLE articles
ADD UNIQUE KEY uniq_path (path);

-- 6) FK na parent
ALTER TABLE articles
ADD CONSTRAINT fk_articles_parent
FOREIGN KEY (parent_id) REFERENCES articles(id)
ON DELETE SET NULL;

-- 7) index pro parent
CREATE INDEX idx_articles_parent_id ON articles(parent_id);

ALTER TABLE articles_history
ADD parent_id INT(10) UNSIGNED NULL AFTER article_id,
ADD path VARCHAR(500) NOT NULL AFTER slug;

DROP TRIGGER IF EXISTS articles_bu;

DELIMITER ;;

CREATE TRIGGER articles_bu BEFORE UPDATE ON articles FOR EACH ROW
INSERT INTO articles_history (
	article_id,
	parent_id,
	title,
	show_title,
	slug,
	path,
	content,
	type,
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
);;

DELIMITER ;

ALTER TABLE `menus`
ADD COLUMN `path` VARCHAR(255) DEFAULT NULL AFTER `action`;

UPDATE `menus`
SET 
	`path` = JSON_UNQUOTE(JSON_EXTRACT(`params`, '$.slug')),
	`target_id` = JSON_EXTRACT(`params`, '$.id')
WHERE `params` IS NOT NULL;

ALTER TABLE `menus`
DROP COLUMN `params`;

CREATE INDEX idx_menus_path ON `menus` (`path`);