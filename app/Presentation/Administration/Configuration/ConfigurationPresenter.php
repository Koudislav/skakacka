<?php

declare(strict_types=1);

namespace App\Presentation\Administration\Configuration;

use App\Forms\BootstrapFormFactory;
use App\Repository\ConfigurationRepository;
use App\Services\BootstrapHelper;
use Nette\Forms\Form;
use Nette\Utils\FileSystem;
use Nette\Utils\Html;

final class ConfigurationPresenter extends \App\Presentation\Administration\BaseAdministrationPresenter {

	/** @var ConfigurationRepository @inject */
	public $configurationRepository;

	public const CONF_ENUM_TRANSLATIONS = [
		'template_menu_position' => BootstrapHelper::BOOTSTRAP_POSITION_ENUM,
		'template_color_scheme' => [
			'light' => 'Světlá',
			'dark' => 'Tmavá',
		],
	];

	public const CONF_ENUM_METHODS = [
		'template_bg_page' => 'enumColors',
		'template_bg_navbar' => 'enumColors',
		'template_bg_content' => 'enumColors',
		'template_p_content' => 'enumPadding',
	];

	public function actionDefault(?string $category = null): void {
		$categories = $this->configurationRepository->getCategories();
		$this->template->categories = $categories;

		if ($category === null) {
			$category = array_key_first($categories);
			$this->redirect('this', ['category' => $category]);
		}

		$this->template->currentCategory = $category;
		$this->template->items = $this->configurationRepository->getByCategory($category);
	}

	public function createComponentConfigurationForm(): Form {
		$form = BootstrapFormFactory::create('oneLine');

		$category = $this->getParameter('category');
		$items = $this->configurationRepository->getByCategory($category);

		foreach ($items as $item) {

			if ($item->type === 'label') {
				$label = Html::el('div')
					->addHtml(Html::el('hr'))
					->addHtml(
						Html::el('div', ['class' => "h4"])
							->addText($item->description)
					);
				$form->addGroup($label);

				continue;
			}

			$canEdit = !$item->access_role || $this->user->isInRole($item->access_role);

			$label = $item->description ?? $item->key;
			$isColor = str_starts_with($item->key, 'hex_pick_');

			if ($isColor) {
				$control = $form->addText($item->key, $label)
					->setDefaultValue($item->value_string)
					->setHtmlType('color')
					->addRule(
						Form::Pattern,
						'Zadejte platný HEX kód barvy',
						'^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$'
					);
			} else {
				$control = match ($item->type) {
					'bool' => $form->addCheckbox($item->key, $label)
						->setDefaultValue((bool) $item->value_bool),

					'int' => $form->addInteger($item->key, $label)
						->setDefaultValue($item->value_int),

					'float' => $form->addText($item->key, $label)
						->setDefaultValue($item->value_float),

					'enum' => $form->addSelect(
						$item->key,
						$this->configEnumLabel($label, $item),
						$this->configEnumOptions($item)
					)->setDefaultValue($item->value_string)
						->setPrompt('— Vyberte —'),

					default => $form->addText($item->key, $label)
						->setDefaultValue($item->value_string),
				};
			}
			if ($item->type === 'enum') {
				$this->colorizeSelect($control, $item);
			}
			if (!$canEdit) {
				$value = $control->getValue();
				$control->setDisabled()->setOmitted(false);
				$control->setDefaultValue($value)
					->setHtmlAttribute('title', 'Nemáte oprávnění upravovat toto nastavení')
					->setHtmlAttribute('data-bs-toggle', 'tooltip');
			} 
		}

		$form->addSubmit('save', 'Uložit')
			->setHtmlAttribute('class', 'btn btn-primary');

		$form->onSuccess[] = [$this, 'configurationFormSubmitted'];
		return $form;
	}

	private function colorizeSelect($select, $item): void {
		if (isset(self::CONF_ENUM_METHODS[$item->key]) && self::CONF_ENUM_METHODS[$item->key] === 'enumColors') {
			if (!empty($item->value_string)) {
				$select->setHtmlAttribute('data-bg-color', 'bg-' . $item->value_string)
					->setHtmlAttribute('class', 'bg-' . $item->value_string);
			} else {
				$select->setHtmlAttribute('data-bg-color', '');
			}
		}
	}

	//Form manipulation
	public function configurationFormSubmitted(Form $form, \stdClass $values): void {
		$category = $this->getParameter('category');
		$items = $this->configurationRepository->getByCategory($category);
		$itemsByKey = [];
		foreach ($items as $item) {
			$itemsByKey[$item->key] = $item;
		}

		$less = false;
		foreach ($values as $key => $value) {
			if ($key === 'save') {
				continue; // button
			}
			if (!isset($itemsByKey[$key])) {
				continue; // neznámý parametr
			}

			$item = $itemsByKey[$key];
			if ($item->access_role && !$this->user->isInRole($item->access_role)) {
				continue; // nemá oprávnění
			}


			if (str_starts_with($key, 'hex_pick_')) {
				$less = true;
				if ($value === '' || $value === null) {
					$value = null;
				} elseif (!preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value)) {
					$form->addError('Neplatná barva u ' . $key);
					continue;
				}
			} elseif (str_starts_with($key, 'template_')) {
				$less = true;
			}
			$this->configurationRepository->updateValue(
				$key,
				$value,
				$this->user->getId()
			);
		}

		$this->cache->remove(\App\Config\Config::CACHE_KEY);
		if ($less) {
			FileSystem::delete(self::TEMP_DIR . '/less/config.less');
			FileSystem::delete(self::WWW_DIR . '/assets/css/styles.css');
		}

		$this->flashMessage('Nastavení bylo uloženo.', 'success');
		$this->redirect('this');
	}

	public function configEnumLabel(string $label, $item) {
		if ($item->enum_options === 'bgColor') {
			return Html::el('div')
				->addHtml(Html::el('span')->setText($label))
				->addHtml(Html::el('span')->class('bg-' . $item->value_string)->setHtml('&nbsp;&nbsp;&nbsp;')->setStyle('display:inline-block;margin-left:10px;'));
		}
		return $label;
	}

	public function configEnumOptions($item): array {
		if (array_key_exists($item->key, self::CONF_ENUM_METHODS)) {
			$fn = self::CONF_ENUM_METHODS[$item->key];
			return $this->$fn($item);
		}
		return array_combine(
			explode(',', $item->enum_options),
			self::CONF_ENUM_TRANSLATIONS[$item->key] ?? explode(',', $item->enum_options)
		);
	}

	public function enumColors($item) {
		return BootstrapHelper::getEnum($item->enum_options) ?? [];
	}

	public function enumSpacing() {
		return BootstrapHelper::getSpacingOptions() ?? [];
	}

	public function enumPadding() {
		return BootstrapHelper::getSpacingOptions('padding') ?? [];
	}

	public function enumMargin() {
		return BootstrapHelper::getSpacingOptions('margin') ?? [];
	}

}
