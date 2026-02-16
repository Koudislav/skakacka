<?php

declare(strict_types=1);

use App\Repository\ArticleRepository;
use Nette\Caching\Cache;
use Nette\Caching\Storages\FileStorage;

require __DIR__ . '/../vendor/autoload.php';

setlocale(LC_ALL, 'cs_CZ.UTF-8');
date_default_timezone_set('Europe/Prague');

$bootstrap = new App\Bootstrap;
$container = $bootstrap->bootWebApplication();

$storage = new FileStorage(__DIR__ . '/../temp/cache');
$cache = new Cache($storage);
$articleRepository = $container->getByType(ArticleRepository::class);

if ($cache->load(ArticleRepository::ALL_ARTICLE_SLUGS_CACHE_KEY) === null) {
	$cache->save(ArticleRepository::ALL_ARTICLE_SLUGS_CACHE_KEY, $articleRepository->getAllSlugs(true), [Cache::Expire => '10 minutes']);
}

$application = $container->getByType(Nette\Application\Application::class);
$application->run();
