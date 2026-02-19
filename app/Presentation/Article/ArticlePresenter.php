<?php

namespace App\Presentation\Article;

use App\Components\Calendar\CalendarControl;
use App\Components\ContactFormControlFactory;
use App\Repository\ArticleRepository;
use App\Repository\CalendarRepository;
use App\Repository\GalleryRepository;
use App\Service\ReCaptchaService;
use App\Service\SpecialCodesParser;

class ArticlePresenter extends \App\Presentation\BasePresenter {

	/** @var ArticleRepository @inject */
	public $articleRepository;

	/** @var CalendarRepository @inject */
	public $calendarRepository;

	/** @var GalleryRepository @inject */
	public $galleryRepository;

	/** @var ReCaptchaService @inject */
	public $reCaptchaService;

	/** @var ContactFormControlFactory @inject */
	public $contactFormControlFactory;

	private const WWW_DIR = __DIR__ . '/../../../www';

	public function actionDefault(string $slug): void {
		$article = $this->articleRepository->getBySlug($slug);

		if (!$article) {
			$this->error('Článek nenalezen'); // 404
		}

		$parser = new SpecialCodesParser($this);
		$articleContent = $parser->parse($article->content);

		$this->template->articleContent = $articleContent;
		$this->template->article = $article;

		if (!empty($article->og_image)){
			if (str_starts_with($article->og_image, 'http://') || str_starts_with($article->og_image, 'https://')) {
				$this->seo->ogImage = $article->og_image; // externí URL
			} elseif (realpath(self::WWW_DIR . $article->og_image)) {
				$baseUrl = rtrim($this->getHttpRequest()->getUrl()->getBaseUrl(), '/');
				$this->seo->ogImage = $baseUrl . $article->og_image; // relativní cesta k www
			}
		}

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
		return $this->contactFormControlFactory->create();
	}

	public function renderCalendarSnippet(): string {
		$control = $this['calendar']; // komponenta

		ob_start();
		$control->render();
		return ob_get_clean();
	}

	protected function createComponentCalendar(): CalendarControl {
		$c = new CalendarControl();

		// přepnutí režimu
		$mode = CalendarControl::MODE_BINARY; // nebo MODE_EVENTS
		$c->setMode($mode);

		$c->onLoadData['dataCallback'] = function (\DateTimeImmutable $from, \DateTimeImmutable $to) use ($mode) {
			return $this->calendarRepository->getCalendarData(
				$from,
				$to,
				$mode,
				null // nebo resource_id
			);
		};
		$c->onLoadData['toggleCallback'] = function (\DateTimeImmutable $date) {
			$this->calendarRepository->toggleBlockingDay($date);
		};

			return $c;
	}

}
