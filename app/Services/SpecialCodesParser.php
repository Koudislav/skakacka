<?php

declare(strict_types=1);

namespace App\Service;

use Nette\Application\UI\Presenter;
use Latte\Engine;

class SpecialCodesParser {
	private array $allowedActions = [
		'gallery::trapezoid' => 'trapezoid',
	];

	public function __construct(
		private Presenter $presenter
		) {}

	/**
	 * Nahradí text komponentami
	 */
	public function parse(string $text): string {
		return preg_replace_callback('/\[\[@([a-z0-9:_]+)\|id=(\d+)\]\]/i', function ($matches) {
			$action = $matches[1];
			$id = (int)$matches[2];

			if (!array_key_exists($action, $this->allowedActions)) {
				return $matches[0]; // ponech původní text
			}

			// Načti fotky z DB (repository/service)
			switch ($this->allowedActions[$action]) {
				case 'trapezoid':
					return $this->renderTrapezoidGallery($id);
				default:
					return $matches[0]; // ponech původní text
			}
		}, $text);
	}

	public function renderTrapezoidGallery(int $id): string {

		$photos = $this->presenter->galleryRepository->findPicturesByGalleryId($id);

		$latte = new Engine();

		// Vytvoř komponentu a renderuj do stringu
		$html = $latte->renderToString(
			__DIR__ . '/../Components/GalleryControl/trapezoid.latte',
			[
				'photos' => array_slice($photos, 0, 5),
				'galleryId' => $id,
				'allPhotos' => $photos,
			]
		);
		
		return $html;
	}

}
