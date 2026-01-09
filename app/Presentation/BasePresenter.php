<?php

declare(strict_types=1);

namespace App\Presentation;

use Nette;
use App\Model\LessCompiler;

class BasePresenter extends Nette\Application\UI\Presenter {

	/** @var LessCompiler @inject */
	public $lessCompiler;

	public function startUp() {
		parent::startup();
		$cssFile = $this->lessCompiler->getCss('styles.less', true);
		$this->template->cssFile = $cssFile['final'];
	}

}
