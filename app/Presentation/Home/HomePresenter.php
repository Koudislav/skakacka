<?php

declare(strict_types=1);

namespace App\Presentation\Home;

use App\Repository\ArticleRepository;
use App\Repository\GalleryRepository;
use App\Repository\NewsRepository;
use App\Service\SpecialCodesParser;

final class HomePresenter extends \App\Presentation\BasePresenter {

	/** @var ArticleRepository @inject */
	public $articleRepository;

	/** @var GalleryRepository @inject */
	public $galleryRepository;

	/** @var NewsRepository @inject */
	public NewsRepository $newsRepository;

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
		$this->seo->breadcrumbs = [$this->config['ui_breadcrumbs_home_text'] ?: 'Home' => $this->link('//Home:default')];
	}

}
