<?php

declare(strict_types=1);

namespace App\Forms\Administration\Article;

use App\Forms\BootstrapFormFactory;
use Nette\Application\UI\Form;
use Nette\Forms\Container;

class ArticleFormFactory {

	public const ARTICLE_TYPES = [
		'article' => 'Běžný článek',
		'index' => 'Úvodní stránka',
	];

	public function createArticleForm(array $parentSelectValues, string $previewLink): Form {
		$form = BootstrapFormFactory::create('oneLine');
		$this->addTypeField($form);
		$this->addParentField($form, $parentSelectValues);
		$this->addTitleField($form);
		$this->addTitleShowField($form);
		$this->addSlugField($form);
		$this->addSeoFields($form);
		$this->addContentField($form);
		$this->addPublishField($form);
		$this->addPreviewButton($form, $previewLink);
		$this->addSubmitButton($form);
		return $form;
	}

	public function createLayoutArticleForm(array $parentSelectValues, ?array $placeholders = null,	?array $defaults = null): Form {
		$form = BootstrapFormFactory::create('oneLine');
		$this->addTypeField($form);
		$this->addParentField($form, $parentSelectValues);
		$this->addTitleField($form);
		$this->addSlugField($form);
		$this->addSeoFields($form);

		if (!empty($placeholders)) {
			$this->addTemplateFields($form, $placeholders, $defaults);
		}

		$this->addPublishField($form);
		$this->addSubmitButton($form);
		return $form;
	}

	public function addTypeField(Form $form): void {
		$form->addSelect('type', 'Typ článku:', self::ARTICLE_TYPES)
			->setDefaultValue('article');
	}

	public function addParentField(Form $form, array $parentSelectValues): void {
		$form->addSelect('parent_id', 'Rodič:', $parentSelectValues)
			->setPrompt('— žádný (Domů) —');
	}

	public function addTitleField(Form $form): void {
		$form->addText('title', 'Nadpis:')
			->setRequired('Zadejte nadpis článku.');
	}

	public function addTitleShowField(Form $form): void {
		$form->addCheckbox('show_title', 'Zobrazit nadpis ve stránce')
			->setDefaultValue(false);
	}

	public function addSlugField(Form $form): void {
		$form->addText('slug', 'Slug (jenom malá písmena, čísla, pomlčky):')
			->addRule($form::Pattern, 'Zadejte platný slug (malá písmena, čísla, pomlčky).', '^[a-z0-9\-]+$');
	}

	public function addSeoFields(Form $form): void {
		$form->addText('seo_title', 'SEO titulek:')
			->setHtmlAttribute('placeholder', 'Ponechte prázdné pro použití nadpisu jako SEO titulku.');
		$form->addText('seo_description', 'SEO popis:')
			->setHtmlAttribute('placeholder', 'Ponechte prázdné pro použití SEO description ze sekce NASTAVENÍ.');
		$form->addText('og_image', 'og image:')
			->setHtmlAttribute('placeholder', 'obrázek pro sociální sítě (relativní cesta od kořene webu, např. /upload/obrazek.jpg)');
	}

	public function addContentField(Form $form): void {
		$form->addTextArea('content', 'Obsah článku:')
			->setHtmlAttribute('rows', 10)
			->setHtmlAttribute('class', 'tiny-editor');
	}

	public function addPublishField(Form $form): void {
		$form->addCheckbox('is_published', 'Publikováno')
			->setDefaultValue(false);
	}

	public function addPreviewButton(Form $form, string $previewLink): void {
		$form->addButton('preview', 'Náhled')
			->setHtmlAttribute('class', 'btn btn-sm btn-warning')
			->setHtmlAttribute('data-preview-link', $previewLink)
			->setHtmlAttribute('onclick', 'showPreview(this);');
	}

	public function addSubmitButton(Form $form): void {
		$form->addSubmit('submit', 'Uložit')
			->setHtmlAttribute('class', 'btn btn-sm btn-primary');
	}

	private function addTemplateFields(Form $form, array $placeholders, ?array $defaults): void {
		$container = $form->addContainer('templateData');
		foreach ($placeholders as $key => $config) {
			$type = $config['type'] ?? 'text';
			$label = $config['label'] ?? $key;
			$required = $config['required'] ?? false;

			switch ($type) {
				case 'textarea':
					$input = $container->addTextArea($key, $label);
					break;
				case 'html':
					$input = $this->createHtmlInput($container, $key, $label);
					break;
				case 'image':
					$input = $this->createImageInput($container, $key, $label);
					break;
				case 'repeater':
					$repeaterType = $config['repeater_type'];
					$repeaterContainer = $container->addContainer('repeater_' . $key);
					$existing = $defaults[$key] ?? null;
					if (!is_array($existing)) {
						$existing = [0 => ''];
					} else {
						// pokud existují data, zajistíme, že jsou indexována od 0 a bez mezer
						$existing = array_values($existing);
						if (empty($existing)) {
							$existing = [0 => ''];
						} else {
							$existing[] = ''; // přidáme prázdný řádek pro možnost přidání nového řádku
						}
					}

					// render "řádky" pro každý existující prvek
					foreach ($existing as $i => $item) {
						$row = $repeaterContainer->addContainer((string)$i);
						$numberedLabel = $label . " #" . ($i+1);
						switch ($repeaterType) {
							case 'html':
								$input = $this->createHtmlInput($row, 'value', $numberedLabel);
								break;
							case 'image':
								$input = $this->createImageInput($row, 'value', $numberedLabel);
								break;
							default:
								$input = $this->createTextInput($row, 'value', $numberedLabel);
						}
						$input->setDefaultValue($item['value'] ?? '');

						if ($required && $i === 0) {
							$input->setRequired("Pole '$label' je povinné.");
						}
					}

					// JS/HTML pro přidání nového řádku (frontend musí doplnit)
					$repeaterContainer->addButton('add', 'Přidat další')
						->setHtmlAttribute('class', 'btn btn-sm btn-secondary add-repeater-row')
						->setHtmlAttribute('data-repeater-key', $key);
					break;

				default:
					$input = $this->createTextInput($container, $key, $label);
			}

			if (!in_array($type, ['repeater', 'html']) && $required) {
				$input->setRequired("Pole '$label' je povinné.");
			}

			if ($defaults && isset($defaults[$key]) && $type !== 'repeater') {
				$input->setDefaultValue($defaults[$key]);
			}
		}
	}

	public function createHtmlInput(Container $container, string $key, string $label): \Nette\Forms\Controls\TextArea {
		return $container->addTextArea($key, $label)
			->setHtmlAttribute('class', 'tiny-editor');
	}

	public function createImageInput(Container $container, string $key, string $label): \Nette\Forms\Controls\TextInput {
		return $container->addText($key, $label)
			->setHtmlAttribute('placeholder', 'Relativní cesta od kořene webu, např. /upload/obrazek.jpg')
			->setHtmlAttribute('data-type', 'image');
	}

	public function createTextInput(Container $container, string $key, string $label): \Nette\Forms\Controls\TextInput {
		return $container->addText($key, $label);
	}

}
