<?php

declare(strict_types=1);

namespace App\Service;

use Nette\Application\UI\Presenter;
use Latte\Engine;

class SpecialCodesParser {
	private array $allowedActions = [
		'gallery::trapezoid' => 'trapezoid',
		'gallery::preview' => 'renderGalleryBasicPreview',
		'form::contact' => 'renderContactForm',
		'calendar::basic' => 'renderCalendarBasic',
	];

	public function __construct(
		private Presenter $presenter
		) {}

	/**
	 * Nahradí text komponentami
	 */
	public function parse(string $text): string {
		return preg_replace_callback('/\[\[@([a-z0-9:_]+)((?:\|[a-z0-9_]+=[^|\]]*)*)\]\]/i', function ($matches) {
			bdump($matches);
			$action = $matches[1];
			$paramsString = $matches[2]; // |id=6|big=Zážitkový|small=Učení
			$params = [];

			if ($paramsString) {
				// odstraní první "|"
				$paramsString = ltrim($paramsString, '|');
			
				// rozdělí na jednotlivé páry
				$pairs = explode('|', $paramsString);
			
				foreach ($pairs as $pair) {
					[$key, $value] = explode('=', $pair, 2) + [null, null];
					if ($key !== null) {
						$params[$key] = html_entity_decode($value); // dekóduj entity
					}
				}
			}

			if (!array_key_exists($action, $this->allowedActions)) {
				return $matches[0]; // ponech původní text
			}

			bdump($params);
			// Načti fotky z DB (repository/service)
			switch ($this->allowedActions[$action] ?? null) {
				case 'trapezoid':
					return $this->renderTrapezoidGallery($params);
				case 'renderGalleryBasicPreview':
				case 'renderContactForm':
				case 'renderCalendarBasic':
					$fn = $this->allowedActions[$action];
					return $this->$fn($params);
				default:
					return $matches[0]; // ponech původní text
			}
		}, $text);
	}

	public function renderTrapezoidGallery(array $params): string {
		$id = (int)$params['id'];
		$count = $params['count'] ?? 5;
		$photos = $this->presenter->galleryRepository->findPicturesByGalleryId($id);

		$latte = new Engine();

		// Vytvoř komponentu a renderuj do stringu
		$html = $latte->renderToString(
			__DIR__ . '/../Components/GalleryControl/trapezoid.latte',
			[
				'photos' => array_slice($photos, 0, (int)$count),
				'galleryId' => $id,
				'allPhotos' => $photos,
				'top' => $params['top'] ?? null,
				'second' => $params['second'] ?? null,
				'toLeft' => $params['toLeft'] ?? null,
				'height' => $params['height'] ?? null,
			]
		);

		return $html;
	}

	public function renderGalleryBasicPreview($params) {
		$id = (int)$params['id'];
		$count = $params['count'] ?? 5;
		$photos = $this->presenter->galleryRepository->findPicturesByGalleryId($id);

		$latte = new Engine();

		// Vytvoř komponentu a renderuj do stringu
		$html = $latte->renderToString(
			__DIR__ . '/../Components/GalleryControl/basic.latte',
			[
				'photos' => array_slice($photos, 0, (int)$count),
				'galleryId' => $id,
				'allPhotos' => $photos,
				'height' => $params['height'] ?? null,
			]
		);

		return $html;
	}

	public function renderContactForm(array $params): string {
		return $this->presenter->renderContactFormSnippet();
	}

	public function renderCalendarBasic(array $params): string {
		return $this->presenter->renderCalendarSnippet();
	}

}
