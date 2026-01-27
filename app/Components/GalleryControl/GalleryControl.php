<?php

declare(strict_types=1);

namespace App\Components;

use Nette\Application\UI\Control;

class GalleryControl extends Control {
	private array $photos = [];

	public function __construct(array $photos)
	{
		$this->photos = array_slice($photos, 0, 5); // max 5 fotek
	}

	public function render(): void
	{
		$this->template->photos = $this->photos;
		$this->template->setFile(__DIR__ . '/trapezoid.latte');
		$this->template->render();
	}

}
