<?php

namespace App\Presentation\Article;

use App\Components\Calendar\CalendarControl;
use App\Components\ContactFormControlFactory;
use App\Repository\ArticleRepository;
use App\Repository\CalendarRepository;
use App\Repository\GalleryRepository;
use App\Repository\NewsRepository;
use App\Repository\TemplateRepository;
use App\Service\LayoutTemplatesService;
use App\Service\ReCaptchaService;
use App\Service\SpecialCodesParser;
use Tracy\Debugger;

final class ArticlePresenter extends \App\Presentation\BasePresenter {

	/** @var ArticleRepository @inject */
	public $articleRepository;

	/** @var CalendarRepository @inject */
	public $calendarRepository;

	/** @var GalleryRepository @inject */
	public $galleryRepository;

	/** @var NewsRepository @inject */
	public NewsRepository $newsRepository;

	/** @var TemplateRepository @inject */
	public TemplateRepository $templateRepository;

	/** @var ReCaptchaService @inject */
	public $reCaptchaService;

	/** @var ContactFormControlFactory @inject */
	public $contactFormControlFactory;

	private const WWW_DIR = __DIR__ . '/../../../www';

	public function actionDefault(string $path): void {
		$article = $this->articleRepository->getByPath($path);;

		if (!$article || !$article->is_published) {
			$this->error('Článek nenalezen'); // 404
		}

		if ($article->template_id) {
			$content = $this->makeTemplateContent($article);
		} else {
			$content = $article->content;
		}

		$parser = new SpecialCodesParser($this);
		$articleContent = $parser->parse($content);

		$this->template->articleContent = $articleContent;
		$this->template->article = $article;
		$this->overwriteSeo($article);
		$this->template->breadcrumbs = $this->buildTemplateBreadcrumbs($article->path, $article->id);
	}

	public function actionPreview(): void {
		$data = $this->getHttpRequest()->getPost();
		$article = (object) [
			'title' => $data['title'] ?? '',
			'content' => $data['content'] ?? '',
			'show_title' => !empty($data['show_title']),
			'seo_title' => $data['seo_title'] ?? '',
			'seo_description' => $data['seo_description'] ?? '',
			'og_image' => $data['og_image'] ?? '',
			'slug' => '#preview',
		];

		$parser = new SpecialCodesParser($this);
		$this->template->articleContent = $parser->parse($article->content);
		$this->template->article = $article;

		$this->overWriteSeo($article);
		$this->setView('default');
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

	protected function overWriteSeo($article): void {
		if (!empty($article->og_image)){
			if (str_starts_with($article->og_image, 'http://') || str_starts_with($article->og_image, 'https://')) {
				$this->seo->ogImage = $article->og_image; // externí URL
			} elseif (realpath(self::WWW_DIR . $article->og_image)) {
				$baseUrl = rtrim($this->getHttpRequest()->getUrl()->getBaseUrl(), '/');
				$this->seo->ogImage = $baseUrl . $article->og_image; // relativní cesta k www
			}
		}

		$this->seo->title = !empty($article->seo_title) ? $article->seo_title : $article->title;
		$this->seo->ogTitle = !empty($article->seo_title) ? $article->seo_title : $article->title;

		if (!empty($article->seo_description)) {
			$this->seo->description = $article->seo_description;
			$this->seo->ogDescription = $article->seo_description;
		}

		if ($this->getAction() !== 'preview') {
			$this->seo->breadcrumbs = $this->buildSeoBreadcrumbs($article->path, $article->id);
		}
	}

	private function buildTemplateBreadcrumbs(string $path, int $articleId): array {
		$breadcrumbs = [];
		if (!$this->config['ui_breadcrumbs_articles'])
			return $breadcrumbs;

		if ($this->config['ui_breadcrumbs_home']) {
			$breadcrumbs[$this->config['ui_breadcrumbs_home_text'] ?: 'Home'] = $this->link('//Home:default');
		}
		$breadcrumbs += $this->buildBreadcrumbs($path, $articleId);
		if (!$this->config['ui_breadcrumbs_show_current']) {
			array_pop($breadcrumbs);
		}

		if (count($breadcrumbs) < (int) $this->config['ui_breadcrumbs_show_min_items']) {
			return [];
		}
		return $breadcrumbs;
	}

	private function buildSeoBreadcrumbs(string $path, int $articleId): array {
		$breadcrumbs = [$this->config['ui_breadcrumbs_home_text'] ?: 'Home' => $this->link('//Home:default')];
		$breadcrumbs += $this->buildBreadcrumbs($path, $articleId);
		return $breadcrumbs;
	}

	private function buildBreadcrumbs(string $path, int $articleId): array {
		return $this->cache->load('breadcrumbs_' . $path, function (&$dependencies) use ($path, $articleId) {
			$dependencies[$this->cache::Expire] = '1 day';
			$dependencies[$this->cache::Tags] = ['article_' . $articleId, 'articleBreadcrumbs'];
			$segments = explode('/', $path);

			$breadcrumbs = [];
			$currentPath = '';
			foreach ($segments as $segment) {
				$currentPath .= ($currentPath ? '/' : '') . $segment;

				$article = $this->articleRepository->getByPath($currentPath);
				if (!$article) {
					continue; // bezpečnost
				}
				$breadcrumbs[$article->title] = $this->link('//Article:default', [
					'path' => $currentPath
				]);
			}
			return $breadcrumbs;
		});
	}

	public function makeTemplateContent($article) {
		try {
			$template = $this->templateRepository->getTemplateById((int)$article->template_id);
			if ($template) {
				$placeholders = json_decode($template->placeholders_json, true) ?: [];
				$data = $this->templateRepository->resolveDataDefaults($placeholders, $article->template_data_json);
				$content = LayoutTemplatesService::renderLayout($template->content, $placeholders, $data);
			} else {
				throw new \Exception("Template with ID {$article->template_id} not found.");
			}
		} catch (\Exception $e) {
			Debugger::log("Chyba při zpracování šablony pro článek ID {$article->id}: " . $e->getMessage(), 'error');
			try {
				$template = $this->templateRepository->getTemplateHistory((int)$article->template_id, (int)$article->template_version);
				if ($template) {
					$placeholders = json_decode($template->placeholders_json, true) ?: [];
					$data = $this->templateRepository->resolveDataDefaults($placeholders, $article->template_data_json);
					$content = LayoutTemplatesService::renderLayout($template->content, $placeholders, $data);
				} else {
					throw new \Exception("Historical template with ID {$article->template_id} and version {$article->template_version} not found.");
				}
			} catch (\Exception $e) {
				Debugger::log("Chyba při zpracování historické šablony pro článek ID {$article->id}: " . $e->getMessage(), 'error');
				try {
					$content = LayoutTemplatesService::renderLayout($article->content, $placeholders, $data);
				} catch (\Exception $e) {
					Debugger::log("Chyba při zpracování původního obsahu jako šablony pro článek ID {$article->id}: " . $e->getMessage(), 'error');
					$content = $article->content; // fallback na původní obsah
				}
			}
		}
		return $content;
	}

}
