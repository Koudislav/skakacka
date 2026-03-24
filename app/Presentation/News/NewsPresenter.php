<?php

namespace App\Presentation\News;

use App\Repository\GalleryRepository;
use App\Repository\NewsRepository;
use App\Service\SpecialCodesParser;

final class NewsPresenter extends \App\Presentation\BasePresenter {

	/** @var GalleryRepository @inject */
	public $galleryRepository;

	/** @var NewsRepository @inject */
	public NewsRepository $newsRepository;

	public function actionDefault(string $slug): void {
		// Najdi novinku podle slug
		$news = $this->newsRepository->getBySlug($slug);

		if (!$news || !$news->is_published) {
			$this->error('Novinka nenalezena'); // 404
		}

		// Parsuj obsah pro shortcody, pokud nějaké novinka obsahuje
		$parser = new SpecialCodesParser($this);
		$newsContent = $parser->parse($news->content);

		// Předání do šablony
		$this->template->news = $news;
		$this->template->newsContent = $newsContent;

		// SEO
		$this->overwriteSeo($news);
	}

	protected function overwriteSeo($news): void {
		$this->seo->title = $news->seo_title ?: $news->title;
		$this->seo->ogTitle = $news->seo_title ?: $news->title;
		$this->seo->description = $news->seo_description ?: $this->seo->description;
		if (!empty($news->og_image)) {
			$this->seo->ogImage = $news->og_image;
		}
	}

}
