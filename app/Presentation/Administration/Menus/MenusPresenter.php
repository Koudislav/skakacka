<?php

declare(strict_types=1);

namespace App\Presentation\Administration\Menus;

use App\Forms\BootstrapFormFactory;
use App\Repository\GalleryRepository;
use Nette\Forms\Form;

final class MenusPresenter extends \App\Presentation\Administration\BaseAdministrationPresenter {

	/** @var GalleryRepository @inject */
	public $galleryRepository;

	public const MENU_LINK_TYPES = [
		'article' => 'Odkaz na článek',
		'index' => 'Hlavní stránka',
		'gallery' => 'Galerie',
		'parent' => 'Nadřazená položka (bez odkazu)',
	];

	public function renderDefault(?string $menuKey, ?string $newMenu, ?int $menuId): void {
		$menuKey = $this->getParameter('menuKey');
		$this->template->currentMenuKey = $menuKey;
		$this->template->currentMenuId = $menuId;
		if ($menuKey === '0') {
			$this->template->menus = $this->menuRepository->findKeys();
		} else {
			$this->template->menus = $this->menuRepository->findByKeyStructured($menuKey);
		}
	}

	public function createComponentMenuForm() {
		$form = BootstrapFormFactory::create('oneLine');
		$menuKey = (string) $this->getParameter('menuKey');
		$menuId = (int) $this->getParameter('menuId');
		$menuKeyInput = $form->addText('menu_key', 'Název menu:');

		if ($menuKey !== '0') {
			$menuKeyInput->setDisabled()->setOmitted(false)->setDefaultValue($menuKey);
		} else {
			$menuKeyInput->setRequired('Zadejte název menu.');
		}

		$parents = $this->menuRepository->getRootItemsForSelect($menuKey);

		$form->addSelect('parent_id', 'Nadřazená položka:', $parents)
			->setPrompt('— hlavní položka —');

		$form->addText('label', 'Popisek položky:')
			->setRequired('Zadejte popisek položky menu.');

		$linkType = $form->addSelect('linkType', 'Typ odkazu:', self::MENU_LINK_TYPES)->setDefaultValue('article');

		$linkType->addCondition($form::Equal, 'article')
			->toggle('#linkedArticleSlug-pair-container');
		$linkType->addCondition($form::Equal, 'gallery')
			->toggle('#galleryId-pair-container');

		$linkedArticleSlug = $form->addSelect('linkedArticleSlug', 'Propojit s článkem:', $this->articleRepository->getArticleListForSelect())
			->setPrompt('Žádný článek');

		$linkedArticleSlug->setOption('container-id', 'linkedArticleSlug-pair-container');
		$linkedArticleSlug->addConditionOn($linkType, $form::Equal, 'article')
			->setRequired('Vyberte článek, na který má položka menu odkazovat.');

		$galleryId = $form->addSelect('galleryId', 'Propojit s galerií:', ['default' => 'Výpis všech galerií'] + $this->galleryRepository->getGalleryListForSelect(true))
			->setPrompt('Vyberte galerii');

		$galleryId->setOption('container-id', 'galleryId-pair-container');
		$galleryId->addConditionOn($linkType, $form::Equal, 'gallery')
			->setRequired('Vyberte galerii, na kterou má položka menu odkazovat.');

		$form->addCheckbox('is_active', 'Aktivní')
			->setDefaultValue(true);

		if ($menuKey !== '0' && !empty($menuId)) {
			$menuItem = $this->menuRepository->getById($menuId, $menuKey);
			$form->setDefaults([
				'label' => $menuItem['db']->label,
				'is_active' => $menuItem['db']->is_active == 1,
				'linkType' => $menuItem['processed']['linkType'],
				'linkedArticleSlug' => $menuItem['processed']['linkedArticleSlug'],
				'parent_id' => $menuItem['db']->parent_id,
			]);
		}

		$form->addSubmit('submit', 'Uložit')
			->setHtmlAttribute('class', 'btn btn-primary');

		$form->onSuccess[] = [$this, 'menuFormSubmitted'];

		return $form;
	}

	public function menuFormSubmitted(Form $form, \stdClass $values): void {
		$menuKey = (string) $this->getParameter('menuKey');
		$newMenu = (string) $this->getParameter('newMenu');
		$menuId = (int) $this->getParameter('menuId');

		if ($menuKey !== '0' && $newMenu !== '1') {
			//edit
			$this->menuRepository->updateMenuItem($values, $menuId);
			$this->cache->clean([$this->cache::Tags => self::MENU_CACHE_KEY]);
			$this->flashMessage('Položka menu byla úspěšně upravena.', 'success');
			$this->redirect('this');
		} else {
			//novy
			$menuId = $this->menuRepository->createMenuItem($values);
			$this->cache->clean([$this->cache::Tags => self::MENU_CACHE_KEY]);
			$this->flashMessage('Položka menu byla úspěšně vytvořena.', 'success');
			$this->redirect('Menus:default', ['menuKey' => $values->menu_key, 'menuId' => $menuId]);
		}
	}

	public function handleReorderMenu(): void {
		if (!$this->isAjax()) {
			$this->error('Invalid request');
		}

		$data = json_decode($this->getHttpRequest()->getRawBody(), true);

		if (!isset($data['order']) || !is_array($data['order'])) {
			$this->sendJson(['status' => 'error']);
			return;
		}

		$this->menuRepository->updatePositions($data['order']);
		$this->cache->clean([$this->cache::Tags => self::MENU_CACHE_KEY]);

		$this->sendJson(['status' => 'ok']);
	}

	public function handleDelete(int $id): void {
		$this->menuRepository->softDelete($id, $this->getUser()->getId());
		$this->cache->clean([$this->cache::Tags => self::MENU_CACHE_KEY]);
		$this->flashMessage('Položka menu byla smazána.', 'success');
		if ($this->isAjax()) {
			$this->redrawControl('menu');
		} else {
			$this->redirect('this');
		}
	}

}
