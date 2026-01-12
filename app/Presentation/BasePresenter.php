<?php

declare(strict_types=1);

namespace App\Presentation;

use Nette;
use App\Model\LessCompiler;
use Nette\Application\UI\Form;

class BasePresenter extends Nette\Application\UI\Presenter {

	/** @var LessCompiler @inject */
	public $lessCompiler;

	public function startUp() {
		parent::startup();
		$cssFile = $this->lessCompiler->getCss('styles.less', true);
		$this->template->cssFile = $cssFile['final'];
	}

	public function disableForm(Form $form): void{
		foreach ($form->getControls() as $control) {
			$control->setDisabled();
		}
	}
}
