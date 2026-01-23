<?php

namespace App\Presentation\Gallery;

use App\Repository\GalleryRepository;

class GalleryPresenter extends \App\Presentation\BasePresenter {

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

		$pictures = $this->galleryRepository->findPicturesByGalleryId($id);

		$this->template->gallery = $gallery;
		$this->template->pictures = $pictures;
	}

}
