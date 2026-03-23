<?php

namespace App\Presentation\Gallery;

use App\Repository\GalleryRepository;
use Nette\Database\Table\ActiveRow;

final class GalleryPresenter extends \App\Presentation\BasePresenter {

	/** @var GalleryRepository @inject */
	public $galleryRepository;

	public function renderDefault(): void {
		$galleries = $this->galleryRepository->findAllGalleries(true);
		$coverImagesIds = [];
		foreach ($galleries as $id => $gallery) {
			$coverImagesIds[$id] = $gallery->id;
		}
		$coversData = $this->galleryRepository->findCoverPicturesByGalleryIds($coverImagesIds);
		$covers = [];
		foreach ($coversData as $cover) {
			$covers[$cover->gallery_id] = $cover;
		}

		$this->template->galleries = $galleries;
		$this->template->covers = $covers;
		$this->seo->schemaType = 'CollectionPage';
		$this->seo->title = 'Galerie';
		$this->seo->description = 'Přehled všech galerií s fotografiemi na našem webu.';
		$this->seo->breadcrumbs = $this->buildBreadcrumbs();
		$this->template->breadcrumbs = $this->buildTemplateBreadctumbs();
	}

	/**
	 * Zobrazí jednotlivou galerii s obrázky
	 * @param int $id - ID galerie
	 */
	public function renderView(int $id): void {
		$gallery = $this->galleryRepository->getGalleryById($id);

		if (!$gallery || !$gallery->is_published) {
			$this->error('Galerie nebyla nalezena');
		}

		$pictures = $this->galleryRepository->findPicturesByGalleryId($id, true);

		$this->template->gallery = $gallery;
		$this->template->pictures = $pictures;
		$this->seo->title = $gallery->title;
		$this->seo->ogTitle = $gallery->title;
		if ($gallery->description) {
			$description = strip_tags($gallery->description);
			$description = str_replace("\n", ' ', $description);
			$description = trim(mb_substr($description, 0, 200)) . (mb_strlen($description) > 200 ? '...' : '');
			$this->seo->description = $description;
			$this->seo->ogDescription = $description;
		}
		$ogPicture = $this->galleryRepository->findCoverPictureByGalleryId($id);
		if ($ogPicture && is_file(self::WWW_PATH . $ogPicture->path_big)) {
			$this->seo->ogImage = $this->config['base_url'] . $ogPicture->path_big;
		}
		$this->seo->schemaType = 'ImageGallery';
		$this->seo->breadcrumbs = $this->buildBreadcrumbs($gallery);
		$this->template->breadcrumbs = $this->buildTemplateBreadctumbs($gallery);
	}

	private function buildTemplateBreadctumbs(?ActiveRow $gallery = null): array {
		if (!$this->config['ui_breadcrumbs_galleries'])
			return [];

		$breadcrumbs = $this->buildBreadcrumbs($gallery);

		if (!$this->config['ui_breadcrumbs_home'])
			array_shift($breadcrumbs);

		if (!$this->config['ui_breadcrumbs_show_current'])
			array_pop($breadcrumbs);

		if (count($breadcrumbs) < (int) $this->config['ui_breadcrumbs_show_min_items'])
			return [];

		return $breadcrumbs;
	}

	private function buildBreadcrumbs(?ActiveRow $gallery = null): array {
		$breadcrumbs = [
			$this->config['ui_breadcrumbs_home_text'] ?: 'Home' => $this->link('//Home:default'),
			'Galerie' => $this->link('//Gallery:default'),
		];
		if ($gallery) {
			$breadcrumbs[$gallery->title] = $this->link('//Gallery:view', ['id' => $gallery->id]);
		}
		return $breadcrumbs;
	}

}
