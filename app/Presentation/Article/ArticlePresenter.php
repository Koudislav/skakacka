<?php

namespace App\Presentation\Article;

use App\Repository\ArticleRepository;
use App\Repository\GalleryRepository;
use App\Service\ReCaptchaService;
use App\Service\SpecialCodesParser;

class ArticlePresenter extends \App\Presentation\BasePresenter {

	/** @var ArticleRepository @inject */
	public $articleRepository;

	/** @var GalleryRepository @inject */
	public $galleryRepository;

	/** @var ReCaptchaService @inject */
	public $reCaptchaService;

	public function actionDefault(string $slug): void {
		$article = $this->articleRepository->getBySlug($slug);

		if (!$article) {
			$this->error('Článek nenalezen'); // 404
		}

		$parser = new SpecialCodesParser($this);
		$articleContent = $parser->parse($article->content);

		$this->template->articleContent = $articleContent;
		$this->template->article = $article;

		$this->seo->breadcrumbs = [
			$this->homeString => $this->link('//Home:default'),
			$article->title => $this->link('//Article:default', ['slug' => $article->slug]),
		];
	}

	public function renderContactFormSnippet(): string {
		$control = $this['contactForm']; // komponenta

		ob_start();
		$control->render();
		return ob_get_clean();
	}

	protected function createComponentContactForm(): \App\Components\ContactFormControl {
		return new \App\Components\ContactFormControl($this->reCaptchaService, $this->getHttpRequest(), $this->config);
	}

}
