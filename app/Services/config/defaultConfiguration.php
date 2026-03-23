<?php

namespace App\Config;

final class DefaultConfiguration {

	public const SCHEMA = [

		//BASIC
		'basic' => [
			'category' => 'basic',
			'type' => 'label',
			'description' => 'Základní nastavení důležité pro fungování celého projektu.',
			'sort_order' => 0,
			'default' => 'Základní nastavení',
		],

		'base_url' => [
			'category' => 'basic',
			'type' => 'string',
			'description' => 'Doména webu včetně protokolu s lomenem na konci',
			'sort_order' => 100,
			'default' => '',
		],

		'aplication_size' => [
			'category' => 'basic',
			'type' => 'string',
			'description' => 'Maximalni velikost dat pro nahrani - upload souborů, galerie atd. Dle fakturace.',
			'sort_order' => 150,
			'default' => '500M',
			'access_role' => 'owner',
		],

		//LICENCE
		'licence' => [
			'category' => 'licence',
			'type' => 'label',
			'description' => 'Licence a api klíče pro použití použitých knihoven a nástrojů.',
			'sort_order' => 0,
			'default' => 'Api klíče, licence a analytika',
		],

		'ga4_id' => [
			'category' => 'licence',
			'type' => 'string',
			'description' => 'GA4 měřicí ID',
			'sort_order' => 10,
			'default' => '',
		],

		'ga4_stream_id' => [
			'category' => 'licence',
			'type' => 'string',
			'description' => 'GA4 stream ID',
			'sort_order' => 20,
			'default' => '',
		],

		'recaptcha_public' => [
			'category' => 'licence',
			'type' => 'string',
			'description' => 'reCAPTCHA 3 - veřejný klíc',
			'sort_order' => 100,
			'default' => '',
		],

		'recaptcha_secret' => [
			'category' => 'licence',
			'type' => 'string',
			'description' => 'reCAPTCHA 3 - tajný klíč',
			'sort_order' => 100,
			'default' => '',
		],

		'tinymce_api_key' => [
			'category' => 'licence',
			'type' => 'string',
			'description' => 'API klíč pro TinyMCE editor',
			'sort_order' => 100,
			'default' => 'mppcv3lb67516mbqhwamdkmcgeez4lpeqy9on9qyxxnzrf6u',
		],

		//GRAPHICS
		'graphics' => [
			'category' => 'graphics',
			'type' => 'label',
			'description' => 'Úprava grafickcýh prvků, barevné schéma a obrázky.',
			'sort_order' => 0,
			'default' => 'Grafika',
		],

		'graphics_label_color' => [
			'category' => 'graphics',
			'type' => 'label',
			'description' => 'Barevné schéma',
			'sort_order' => 200,
			'default' => '',
		],

		'logo_path' => [
			'category' => 'graphics',
			'type' => 'string',
			'description' => 'Cesta k logu webu (vlevo nahoře)',
			'sort_order' => 10,
			'default' => '',
		],

		'template_color_scheme' => [
			'category' => 'graphics',
			'type' => 'enum',
			'description' => 'Výchozí barevné schéma',
			'sort_order' => 205,
			'default' => 'light',
			'enum_options' => ['light','dark'],
		],

		'hex_pick_color_secondary' => [
			'category' => 'graphics',
			'type' => 'string',
			'description' => 'Primarni barva - v top menu / nadpisy / tlačítka',
			'sort_order' => 209,
			'default' => '#9c9c9c',
		],

		'hex_pick_color_anchor' => [
			'category' => 'graphics',
			'type' => 'string',
			'description' => 'barva odkazu',
			'sort_order' => 210,
			'default' => '#9c9c9c',
		],

		'template_bg_page' => [
			'category' => 'graphics',
			'type' => 'enum',
			'description' => 'Barva pozadí celé stránky',
			'sort_order' => 211,
			'default' => null,
			'enum_options' => ['bgColor'],
		],

		'template_bg_navbar' => [
			'category' => 'graphics',
			'type' => 'enum',
			'description' => 'Barva pozadí navbaru',
			'sort_order' => 212,
			'default' => null,
			'enum_options' => ['bgColor'],
		],

		'template_bg_content' => [
			'category' => 'graphics',
			'type' => 'enum',
			'description' => 'Barva pozadí obsahové části stránky',
			'sort_order' => 213,
			'default' => null,
			'enum_options' => ['bgColor'],
		],

		'graphics_label_templating' => [
			'category' => 'graphics',
			'type' => 'label',
			'description' => 'Rozložení šablony',
			'sort_order' => 300,
			'default' => '',
		],

		'template_menu_position' => [
			'category' => 'graphics',
			'type' => 'enum',
			'description' => 'Menu v navbaru  zarovnat',
			'sort_order' => 310,
			'default' => 'center',
			'enum_options' => ['start','center','end','between','around'],
		],

		'template_navbar_fluid' => [
			'category' => 'graphics',
			'type' => 'bool',
			'description' => 'Navbar na celou šířku obrazovky namísto výchozího užšího formátu',
			'sort_order' => 319,
			'default' => false,
		],

		'template_container_fluid' => [
			'category' => 'graphics',
			'type' => 'bool',
			'description' => 'Obsah na celou šířku obrazovky namísto výchozího užšího formátu',
			'sort_order' => 320,
			'default' => false,
		],

		'template_p_content' => [
			'category' => 'graphics',
			'type' => 'enum',
			'description' => 'Nastavení okrajů / odsazení obsahu',
			'sort_order' => 330,
			'default' => 'pt-5',
			'enum_options' => ['padding'],
		],

		//MAIL
		'mail' => [
			'category' => 'mail',
			'type' => 'label',
			'description' => 'Nastavení e-mailů',
			'sort_order' => 0,
			'default' => 'E-maily, SMTP, odesílatelé a příjemci',
		],

		'mail_from' => [
			'category' => 'mail',
			'type' => 'string',
			'description' => 'E-mail zobrazující se jako odesílatel pošty',
			'sort_order' => 100,
			'default' => '',
		],

		'mail_from_name' => [
			'category' => 'mail',
			'type' => 'string',
			'description' => 'Jméno zobrazující se jako odesílatel pošty',
			'sort_order' => 100,
			'default' => '',
		],

		'mail_recipients' => [
			'category' => 'mail',
			'type' => 'string',
			'description' => 'Příjemci (validní emailové adresy oddělené čárkou)',
			'sort_order' => 100,
			'default' => '',
		],

		'mail_smtp_host' => [
			'category' => 'mail',
			'type' => 'string',
			'description' => 'SMTP HOST',
			'sort_order' => 100,
			'default' => 'mail.jirikodys.cz',
		],

		'mail_smtp_pass' => [
			'category' => 'mail',
			'type' => 'string',
			'description' => 'SMTP PASSWORD',
			'sort_order' => 100,
			'default' => '',
		],

		'mail_smtp_port' => [
			'category' => 'mail',
			'type' => 'int',
			'description' => 'SMTP PORT',
			'sort_order' => 100,
			'default' => 587,
		],

		'mail_smtp_secure' => [
			'category' => 'mail',
			'type' => 'string',
			'description' => 'SMTP SECURE - \'tls\', \'ssl\', null',
			'sort_order' => 100,
			'default' => 'tls',
		],

		'mail_smtp_user' => [
			'category' => 'mail',
			'type' => 'string',
			'description' => 'SMTP USER',
			'sort_order' => 100,
			'default' => '',
		],

		//SEO
		'seo' => [
			'category' => 'seo',
			'type' => 'label',
			'description' => 'Základní nastavení SEO a jeho defaultních hodnot',
			'sort_order' => 0,
			'default' => 'SEO nastavení',
		],

		'seo_default_title' => [
			'category' => 'seo',
			'type' => 'string',
			'description' => 'Defaultní title',
			'sort_order' => 10,
			'default' => 'Demo',
		],

		'seo_default_title_og' => [
			'category' => 'seo',
			'type' => 'string',
			'description' => 'Defaultní title OG pro sociální sítě - nepovinný',
			'sort_order' => 20,
			'default' => '',
		],

		'seo_default_description' => [
			'category' => 'seo',
			'type' => 'string',
			'description' => 'Defaultní description',
			'sort_order' => 100,
			'default' => 'CMS by Jiří Kodys - rychlý a jednoduchý systém pro správu obsahu webových stránek.',
		],

		'seo_default_description_og' => [
			'category' => 'seo',
			'type' => 'string',
			'description' => 'Defaultní description OG pro sociální sítě - nepovinný',
			'sort_order' => 100,
			'default' => '',
		],

		'seo_default_og_image' => [
			'category' => 'seo',
			'type' => 'string',
			'description' => 'Default OG image - obrázek pro sociální sítě - abolutní cesta - ideálně v poměru 1,91:1, nejlépe 1200x630px',
			'sort_order' => 100,
			'default' => '',
		],

		'seo_title_constant' => [
			'category' => 'seo',
			'type' => 'string',
			'description' => 'Konstantní část title za dynamickou (nemusí být vyplněno)',
			'sort_order' => 100,
			'default' => '| CMS by Jiří Kodys',
		],

		'ui' => [
			'category' => 'ui',
			'type' => 'label',
			'description' => 'Nastavení uživatelského rozhraní a viditelnosti jednotlivých prvků.',
			'sort_order' => 0,
			'default' => 'Uživatelské rozhraní',
		],

		'ui_label_breadcrumbs' => [
			'category' => 'ui',
			'type' => 'label',
			'description' => 'Breadcrumbs (drobečková navigace)',
			'sort_order' => 100,
			'default' => '',
		],

		'ui_breadcrumbs_articles' => [
			'category' => 'ui',
			'type' => 'bool',
			'description' => 'Zobrazovat breadcrumbs v zobrazení článků',
			'sort_order' => 120,
			'default' => false,
		],

		'ui_breadcrumbs_galleries' => [
			'category' => 'ui',
			'type' => 'bool',
			'description' => 'Zobrazovat breadcrumbs v galeriích',
			'sort_order' => 130,
			'default' => false,
		],

		'ui_breadcrumbs_home' => [
			'category' => 'ui',
			'type' => 'bool',
			'description' => 'Zobrazovat v breadcrumbs odkaz na domovskou stránku',
			'sort_order' => 150,
			'default' => true,
		],

		'ui_breadcrumbs_home_text' => [
			'category' => 'ui',
			'type' => 'string',
			'description' => 'Text odkazu na domovskou stránku v breadcrumbs',
			'sort_order' => 155,
			'default' => 'Domů',
		],

		'ui_breadcrumbs_separator' => [
			'category' => 'ui',
			'type' => 'string',
			'description' => 'Znak oddělující položky v breadcrumbs',
			'sort_order' => 160,
			'default' => '/',
		],

		'ui_breadcrumbs_show_min_items' => [
			'category' => 'ui',
			'type' => 'enum',
			'description' => 'Minimální počet viditelných položek potřebný pro zobrazení breadcrumbs',
			'sort_order' => 165,
			'default' => 2,
			'enum_options' => null,
		],

		'ui_breadcrumbs_show_current' => [
			'category' => 'ui',
			'type' => 'bool',
			'description' => 'Zobrazovat v breadcrumbs aktuální stránku jako poslední položku',
			'sort_order' => 170,
			'default' => true,
		],
	];

}
