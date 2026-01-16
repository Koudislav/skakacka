<?php

declare(strict_types=1);

namespace App\Presentation;

use Nette;
use App\Model\Config;
use App\Model\LessCompiler;
use App\Repository\MenuRepository;
use Nette\Application\UI\Form;

class BasePresenter extends Nette\Application\UI\Presenter {

	/** @var Config @inject */
	public $config;

	/** @var LessCompiler @inject */
	public $lessCompiler;

	/** @var MenuRepository @inject */
	public $menuRepository;

	public function startUp() {
		parent::startup();
		$cssFile = $this->lessCompiler->getCss('styles.less', true);
		$this->template->cssFile = $cssFile['final'];
		$this->template->config = $this->config;
	}

	public function beforeRender() {
		parent::beforeRender();
		$this->template->navbarMenu = $this->processNavbarMenu();
	}

	public function disableForm(Form $form): void{
		foreach ($form->getControls() as $control) {
			$control->setDisabled();
		}
	}

	private function processNavbarMenu(): array {
		$menu = [];
		$menuItems = $this->menuRepository->findByKey('main_horizontal', true);
		foreach ($menuItems as $item) {
			$menu[] = [
				'label' => $item->label,
				'link' => $this->link($item->presenter . ':' . $item->action, $item->params ? json_decode($item->params, true) : []),
			];
		}

		return $menu;
	}

}
