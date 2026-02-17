<?php

declare(strict_types=1);

namespace App\Components\ArticleAsset;

use App\Repository\ArticleRepository;
use Nette\Application\UI\Control;
use Nette\Caching\Cache;
use Nette\Caching\Storage;

final class ArticleAssetControl extends Control {

	private Cache $cache;

	public function __construct(
		private ArticleRepository $articleRepository,
		Storage $storage,
	) {
		$this->cache = new Cache($storage);
	}

	public function render(string $title): void {
		$article = $this->cache->load('articleAssets-' . $title, function (&$dependencies) use ($title) {
			$dependencies[Cache::Tags] = ['articleAssets', 'articleAssets-' . $title];
			$dependencies[Cache::Expire] = '12 hours';
			$article = $this->articleRepository
				->findAll()
				->where('title', $title)
				->fetch();
			return $article ? $article->toArray() : [];
		});

		$this->template->article = $article;
		$this->template->setFile(__DIR__ . '/templates/articleAsset.latte');
		$this->template->render();
	}

}
