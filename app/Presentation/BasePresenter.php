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
		// $this->template->actualLink = $this->link('this');
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
		$menuItems = $this->menuRepository->findByKeyStructured('main_horizontal', true);
		foreach ($menuItems as $item) {
			if (empty($item['item']->presenter)) {
				$children = [];
				$hasActiveChild = false;
				foreach ($item['children'] as $child) {
					$childItem = $this->processNavbarMenuItem(['item' => $child]);
					if ($childItem['isActive']) {
						$hasActiveChild = true;
					}
					$children[] = $childItem;
				}
				$menu[] = [
					'label' => $item['item']->label,
					'isParent' => true,
					'children' => $children,
					'isActive' => $hasActiveChild,
				];

			} else {
				$menu[] = $this->processNavbarMenuItem($item);
			}
		}

		return $menu;
	}

	public function processNavbarMenuItem(array $item): array {
		$currentPresenter = $this->getName();
		$currentParams = $this->getParameters();
		$itemParams = $item['item']->params ? json_decode($item['item']->params, true) : [];

		$isActive = false;
		if ($currentPresenter === $item['item']->presenter) {
			if ($currentPresenter === 'Article' && !empty($currentParams['slug'])) {
				if ($currentParams['slug'] ?? null === ($itemParams['slug'] ?? null)) {
					if (isset($itemParams['slug']) && $currentParams['slug'] === $itemParams['slug']) {
						$isActive = true;
					}
				}
			}
			if ($currentPresenter === 'Gallery') {
				$isActive = true;
			}
		}
		return [
			'label' => $item['item']->label,
			'link' => $this->link($item['item']->presenter . ':' . $item['item']->action, $itemParams),
			'isParent' => false,
			'isActive' => $isActive,
		];
	}

}
