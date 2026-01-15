<?php

declare(strict_types=1);

namespace App\Core;

use Nette;
use Nette\Application\Routers\RouteList;
use Nette\Caching\Cache;
use Nette\Caching\Storages\FileStorage;

final class RouterFactory {

	use Nette\StaticClass;

	public static function createRouter(): RouteList {
		$router = new RouteList;

		$storage = new FileStorage(__DIR__ . '/../../temp/cache');
		$cache = new Cache($storage);
		$slugs = $cache->load(\App\Repository\ArticleRepository::ALL_ARTICLE_SLUGS_CACHE_KEY);

		if ($slugs) {
			$escapedSlugs = array_map(fn($s) => preg_quote($s, '#'), $slugs);
			$pattern = implode('|', $escapedSlugs);
		
			$router->addRoute('<slug ' . $pattern . '>', [
				'presenter' => 'Article',
				'action' => 'default'
			]);
		}

		$router->addRoute('<presenter>/<action>[/<id>]', 'Home:default');

		return $router;
	}
}
