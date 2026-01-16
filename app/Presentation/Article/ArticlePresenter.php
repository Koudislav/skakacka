<?php

namespace App\Presentation\Article;

use App\Repository\ArticleRepository;

class ArticlePresenter extends \App\Presentation\BasePresenter {

	/** @var ArticleRepository @inject */
	public $articleRepository;

	public function actionDefault(string $slug): void {
		$article = $this->articleRepository->getBySlug($slug);

		if (!$article) {
			$this->error('Článek nenalezen'); // 404
		}

		$this->template->article = $article;
	}

}
