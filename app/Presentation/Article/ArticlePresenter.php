<?php

namespace App\Presentation\Article;

use App\Repository\ArticleRepository;
use App\Repository\GalleryRepository;
use App\Service\SpecialCodesParser;

class ArticlePresenter extends \App\Presentation\BasePresenter {

	/** @var ArticleRepository @inject */
	public $articleRepository;

	/** @var GalleryRepository @inject */
	public $galleryRepository;

	public function actionDefault(string $slug): void {
		$article = $this->articleRepository->getBySlug($slug);

		if (!$article) {
			$this->error('Článek nenalezen'); // 404
		}

		$parser = new SpecialCodesParser($this);
		$articleContent = $parser->parse($article->content);

		$this->template->articleContent = $articleContent;
		$this->template->article = $article;
	}

}
