INSERT INTO `articles`
(
	`title`,
	`slug`,
	`content`,
	`type`,
	`is_system`,
	`system_description`,
	`is_published`,
	`created_at`
)
VALUES
(
	'@asset-footer',
	'asset-footer',
	'',
	'article',
	1,
	'<ul>
		<li>Obsah tohoto př&iacute;spěvku se vždy zobraz&iacute; na každ&eacute; str&aacute;nce jako patička</li>
		<li>nen&iacute; potřeba (nen&iacute; ani ž&aacute;douc&iacute;), aby byl zveřejněn.</li>
	</ul>',
	0,
	NOW()
)
ON DUPLICATE KEY UPDATE
	`is_system` = 1,
	`system_description` = '<ul>
		<li>Obsah tohoto př&iacute;spěvku se vždy zobraz&iacute; na každ&eacute; str&aacute;nce jako patička</li>
		<li>nen&iacute; potřeba (nen&iacute; ani ž&aacute;douc&iacute;), aby byl zveřejněn.</li>
	</ul>';

INSERT INTO `articles`
(
	`title`,
	`slug`,
	`content`,
	`is_system`,
	`system_description`
)
VALUES
(
	'@asset-logo',
	'asset-logo',
	'',
	1,
	'<ul>
		<li>Obsah tohoto př&iacute;spěvku se zobraz&iacute; jako logo, pokud nen&iacute; v nastaven&iacute; nastaven&eacute; obr&aacute;zkov&eacute; logo nebo obr&aacute;zek nebyl nalezen.</li>
		<li>nen&iacute; potřeba (nen&iacute; ani ž&aacute;douc&iacute;), aby byl zveřejněn.</li>
		<li>barva pozad&iacute; editoru nemus&iacute; odpov&iacute;dat barvě pozad&iacute; prvku ve str&aacute;nce - z&aacute;lež&iacute; na jeho um&iacute;stěn&iacute;</li>
	</ul>'
)
ON DUPLICATE KEY UPDATE
	`is_system` = 1,
	`content` = '<div class=\"d-flex align-items-center\">
		<div class=\"d-flex align-items-center justify-content-center rounded-circle text-white flex-shrink-0\" style=\"width: 50px; height: 50px; font-weight: bold; font-size: 1.2rem; background: linear-gradient(135deg, rgba(235,106,220,1) 0%, rgba(63,80,191,1) 50%, rgba(79,194,232,1) 100%); box-shadow: 0 0 0 2px rgb(108 110 239), 0 4px 12px rgba(63, 80, 191, 0.4);\">JK</div>
		<div class=\"ms-2 d-flex flex-column\"><small class=\"text-muted\" style=\"line-height: 1;\"><small>Jiř&iacute; Kodys</small></small> <span class=\"fs-4 fw-bold text-center\">CMS</span></div>
	</div>',
	`system_description` = '<ul>
		<li>Obsah tohoto př&iacute;spěvku se zobraz&iacute; jako logo, pokud nen&iacute; v nastaven&iacute; nastaven&eacute; obr&aacute;zkov&eacute; logo nebo obr&aacute;zek nebyl nalezen.</li>
		<li>nen&iacute; potřeba (nen&iacute; ani ž&aacute;douc&iacute;), aby byl zveřejněn.</li>
		<li>barva pozad&iacute; editoru nemus&iacute; odpov&iacute;dat barvě pozad&iacute; prvku ve str&aacute;nce - z&aacute;lež&iacute; na jeho um&iacute;stěn&iacute;</li>
	</ul>';
