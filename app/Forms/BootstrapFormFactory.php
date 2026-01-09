<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;
use Nette\Forms\Rendering\DefaultFormRenderer;

final class BootstrapFormFactory {
	public const TYPE_INLINE = 'inLine';
	public const TYPE_ONE_LINE = 'oneLine';
	public const TYPE_BASIC = 'basic';

	public static function create(string $type = 'basic', ?string $containerClass = null, ?bool $noLabel = true): Form {
		$form = new Form();

		$form->addProtection('Vypršela platnost formuláře, odešlete jej prosím znovu.');

		switch ($type) {
			case self::TYPE_INLINE:
				self::rendererInLine($form, $containerClass, $noLabel);
				break;
			case self::TYPE_ONE_LINE:
				self::rendererOneLine($form, $containerClass);
				break;
			case self::TYPE_BASIC:
				self::rendererBasic($form, $containerClass);
				break;
			default:
				throw new \InvalidArgumentException("Neplatný typ formuláře: $type");
		}

		$form->getElementPrototype()->addClass('needs-validation');

		// --- Auto-assign bootstrap classes ---
		$form->onRender[] = function (Form $form): void {
			foreach ($form->getControls() as $control) {
				$type = $control->getOption('type');

				switch ($type) {
					case 'button':
					case 'submit':
						break;

					case 'text':
					case 'textarea':
					case 'password':
					case 'email':
					case 'number':
					case 'date':
						$control->getControlPrototype()->addClass('form-control');
						break;

					case 'checkbox':
						$control->getControlPrototype()->addClass('form-check-input');
						$control->getLabelPrototype()->addClass('form-check-label');
						$control->getSeparatorPrototype()->setName('div')->addClass('form-check');
						break;

					case 'radio':
						$control->getSeparatorPrototype()->setName('div')->addClass('form-check');
						$control->getControlPrototype()->addClass('form-check-input');
						$control->getLabelPrototype()->addClass('form-check-label');
						break;
					case 'select':
					case 'multiselect':
						$control->getControlPrototype()->addClass('form-select');
						break;
				}

				// Vylepšení validace (přidání invalid class)
				if ($control->hasErrors()) {
					$control->getControlPrototype()->addClass('is-invalid');
				}
			}
		};

		return $form;
	}

	public static function rendererBasic(Form $form, ?string $containerClass = null) {
		/** @var DefaultFormRenderer $renderer */
		$renderer = $form->getRenderer();

		$renderer->wrappers['form']['container'] = $containerClass ? 'div class="' . $containerClass . '"' : null;
		$renderer->wrappers['error']['container'] = 'div class="alert alert-danger"';
		$renderer->wrappers['error']['item'] = 'p';

		$renderer->wrappers['controls']['container'] = 'div class="row g-3"';
		$renderer->wrappers['pair']['container'] = 'div class="col-md-6 mb-2"';
		$renderer->wrappers['pair']['.error'] = 'has-error';

		$renderer->wrappers['control']['container'] = 'div class="form-group"';
		$renderer->wrappers['control']['description'] = 'div class="form-text text-muted"';
		$renderer->wrappers['control']['errorcontainer'] = 'div class="invalid-feedback d-block"';
		$renderer->wrappers['label']['container'] = 'div class="form-label fw-semibold mb-1"';
	}

	public static function rendererOneLine(Form $form, ?string $containerClass = null) {
		/** @var DefaultFormRenderer $renderer */
		$renderer = $form->getRenderer();

		$renderer->wrappers['form']['container'] = $containerClass ? 'div class="' . $containerClass . '"' : null;
		$renderer->wrappers['error']['container'] = 'div class="alert alert-danger"';
		$renderer->wrappers['error']['item'] = 'p';

		$renderer->wrappers['controls']['container'] = 'div class="row g-1"';
		$renderer->wrappers['pair']['container'] = 'div class="col-12 mb-1"';
		$renderer->wrappers['pair']['.error'] = 'has-error';

		$renderer->wrappers['control']['container'] = 'div class="form-group"';
		$renderer->wrappers['control']['description'] = 'div class="form-text text-muted"';
		$renderer->wrappers['control']['errorcontainer'] = 'div class="invalid-feedback d-block"';
		$renderer->wrappers['label']['container'] = 'div class="form-label fw-semibold mb-1"';
	}

	public static function rendererInLine(Form $form, ?string $containerClass = null, ?bool $noLabel = true) {
		/** @var DefaultFormRenderer $renderer */
		$renderer = $form->getRenderer();

		$outerClass = $containerClass ? $containerClass : 'mb-1';
		$renderer->wrappers['form']['container'] = 'div class="' . $outerClass . '"';

		// --- Samotný <form> ---
		$renderer->wrappers['form']['errors'] = true; // vypisuje chyby nahoře
		$renderer->wrappers['error']['container'] = 'div class="alert alert-danger w-100 mb-2"';
		$renderer->wrappers['error']['item'] = 'p class="mb-0"';

		// --- Obal pro všechny prvky ---
		$renderer->wrappers['controls']['container'] = 'div class="row row-cols-lg-auto g-2 align-items-center"';
		// $renderer->wrappers['groups']['container'] = 'div class="row row-cols-lg-auto g-2 align-items-center"';

		// --- Každý pár (label + control) ---
		$renderer->wrappers['pair']['container'] = 'div class="col' . ($noLabel ? ' no-label' : '') . '"';
		$renderer->wrappers['pair']['.error'] = 'has-error';

		// --- Label (v tomto případě nechceme zobrazovat) ---
		$renderer->wrappers['label']['container'] = null;

		// --- Input (control) ---
		$renderer->wrappers['control']['container'] = null;
		$renderer->wrappers['control']['description'] = 'div class="form-text text-muted"';
		$renderer->wrappers['control']['errorcontainer'] = 'div class="invalid-feedback d-block"';

		// --- Automatické třídy po renderování ---
		$form->getElementPrototype()
			// ->addClass('')
			->setAttribute('novalidate', true);

		$form->onRender[] = function (Form $form) use ($noLabel): void {
			foreach ($form->getControls() as $control) {
				$control->setOption('renderLabel', false);
				$type = $control->getOption('type');
				switch ($type) {
					case 'radio':
						$control->getSeparatorPrototype()
							->setName('div')
							->addClass('form-check')
							->addClass('form-check-inline');
						break;
					case 'text':
					case 'email':
					case 'number':
					case 'date':
						$control->getControlPrototype()->addClass('form-control-sm' . ($noLabel ? ' input-no-label' : ''));
						break;

					case 'select':
					case 'multiselect':
						$control->getControlPrototype()->addClass('form-select-sm' . ($noLabel ? ' input-no-label' : ''));
						break;

					case 'submit':
					case 'button':
						$control->getControlPrototype()->addClass('btn-sm');
						break;

					default:
						$control->getControlPrototype()->addClass('form-control-sm');
				}
			}
		};
	}

}
