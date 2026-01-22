<?php

declare(strict_types=1);

namespace App\Presentation\Home;

use App\Repository\ArticleRepository;

final class HomePresenter extends \App\Presentation\BasePresenter {

	/** @var ArticleRepository @inject */
	public $articleRepository;

	public function actionDefault(): void {
		$indexArticles = $this->articleRepository->getIndexes();
		foreach ($indexArticles as $indexArticle) {
			$this->template->article = $indexArticle;
			break;
		}
	}

}
