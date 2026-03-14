SET NAMES utf8mb4;

INSERT INTO `articles`
(`title`, `show_title`, `slug`, `content`, `type`, `seo_title`, `seo_description`, `seo_robots`, `og_image`, `is_published`, `published_at`, `created_at`, `updated_at`)
SELECT
'@asset-logo',
0,
'asset-logo',
'<div class=\"mceNonEditable mce-visible-only\" style=\"background: #fff3cd; padding: 8px;\">
<p>⚠ Tato č&aacute;st je generovan&aacute; syst&eacute;mem &ndash; neměňte ji.</p>
<ul>
<li>Obsah tohoto př&iacute;spěvku se zobraz&iacute; jako logo, pokud nen&iacute; v nastaven&iacute; nastaven&eacute; obr&aacute;zkov&eacute; logo nebo obr&aacute;zek nebyl nalezen.</li>
<li>nen&iacute; potřeba (nen&iacute; ani ž&aacute;douc&iacute;), aby byl zveřejněn.</li>
<li>barva pozad&iacute; editoru nemus&iacute; odpov&iacute;dat barvě pozad&iacute; prvku ve str&aacute;nce - z&aacute;lež&iacute; na jeho um&iacute;stěn&iacute;</li>
</ul>
</div>
<div class=\"d-flex align-items-center\">
<div class=\"d-flex align-items-center justify-content-center rounded-circle bg-primary text-white flex-shrink-0\" style=\"width: 50px; height: 50px; font-weight: bold; font-size: 1.2rem;\">JK</div>
<div class=\"ms-2 d-flex flex-column\"><small class=\"text-muted\" style=\"line-height: 1;\"><small>Jiř&iacute; Kodys</small></small> <span class=\"fs-4 fw-bold text-center\">CMS</span></div>
</div>',
'article',
'',
'',
NULL,
'',
0,
NULL,
'2026-03-14 11:47:42',
'2026-03-14 11:52:15'
WHERE NOT EXISTS (
	SELECT 1 FROM `articles` WHERE `slug` = 'asset-logo'
);