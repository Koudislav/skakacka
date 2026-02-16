<?php

declare(strict_types=1);

namespace App\Presentation\Home;

use App\Repository\ArticleRepository;
use App\Repository\GalleryRepository;
use App\Service\SpecialCodesParser;

final class HomePresenter extends \App\Presentation\BasePresenter {

	/** @var ArticleRepository @inject */
	public $articleRepository;

	/** @var GalleryRepository @inject */
	public $galleryRepository;

	public function actionDefault(): void {
		$indexArticles = $this->articleRepository->getIndexes();
		foreach ($indexArticles as $article) {
			$parser = new SpecialCodesParser($this);
			$articleContent = $parser->parse($article->content);

			$this->template->setFile(__DIR__ . '/../Article/default.latte');
			$this->template->articleContent = $articleContent;
			$this->template->article = $article;
			break;
		}
		$this->seo->schemaType = 'WebSite';
		$this->template->seo = $this->seo;
		$this->seo->breadcrumbs = [$this->homeString => $this->link('//Home:default')];
	}

}
