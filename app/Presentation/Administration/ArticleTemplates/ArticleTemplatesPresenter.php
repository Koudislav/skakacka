<?php

declare(strict_types=1);

namespace App\Presentation\Administration\ArticleTemplates;

use App\Forms\BootstrapFormFactory;
use Nette\Forms\Form;

final class ArticleTemplatesPresenter extends \App\Presentation\Administration\BaseAdministrationPresenter {

	public function renderDefault(int $templateId = 0): void {
		$this->template->currentTemplateId = $templateId;
		$this->template->templateType = $templateData->type ?? null;
		$data = $this->templateRepository->findAll();
		$templates = [];
		foreach ($data as $key => $article) {
			$templates[$key] = $article;
		}
		$this->template->templateName = $data[$templateId]->name ?? null;
		$this->template->menus = $templates;
	}

	public function createComponentTemplateForm() {
		$form = BootstrapFormFactory::create('oneLine');
		$templateId = (int) $this->getParameter('templateId');

		$form->addGroup();
		$form->addText('name', 'Název šablony:')
			->setRequired('Zadejte název šablony.');
		$form->addText('description', 'Popis šablony:');
		$form->addSelect('type', 'Typ šablony:', $this->templateRepository::TEMPLATES_TYPES)->setRequired();
		$form->addTextArea('content', 'Obsah:')
			->setHtmlAttribute('rows', 10)
			->setHtmlAttribute('class', 'tiny-editor');

		if ($templateId !== 0) {
			$templateData = $this->templateRepository->getTemplateById($templateId);
			$form->setDefaults($templateData->toArray());

			if ($templateData->type === 'layout' && !empty($templateData->placeholders_json)) {
				$placeholders = json_decode($templateData->placeholders_json, true);

				if (is_array($placeholders)) {
					foreach ($placeholders as $name => $config) {
						$this->templateFormVariableInput($form, $name, $config);
					}
				}
			}
		}

		$form->addGroup();
		$form->addSubmit('submit', 'Uložit')
			->setHtmlAttribute('class', 'btn btn-primary');
		$form->onSuccess[] = [$this, 'templateFormSubmitted'];

		return $form;
	}

	public function templateFormVariableInput(Form $form, string $name, array $config): void {
		$form->addGroup('Nastavení proměnně {{' . $name . '}}')
			->setOption('container', 'div class="border p-3 mb-3"');
		$container = $form->addContainer('placeholders_' . $name);
		$container->addText('label', "Label:")
			->setDefaultValue($config['label'] ?? ucfirst($name))
			->setRequired('Zadejte label pro proměnnou {{' . $name . '}}');
		$variableType = $container->addSelect('type', "Typ:", $this->templateRepository::TEMPLATES_VARIABLE_TYPES)
			->setDefaultValue($config['type'] ?? 'text');
		$repeaterTypeId = 'repeater-type-' . $name;
		$container->addSelect('repeater_type', "Typ položek (pro opakovatelnou skupinu):", $this->templateRepository::TEMPLATES_REPEATER_TYPES)
			->setDefaultValue($config['repeater_type'] ?? 'text')
			->setOption('container-id', $repeaterTypeId);
		$variableType->addCondition(Form::Equal, 'repeater')
			->toggle($repeaterTypeId);
		$container->addCheckbox('required', 'Povinné')
			->setDefaultValue($config['required'] ?? true);
	}

	public function templateFormSubmitted(Form $form, $values): void {
		$templateId = (int) $this->getParameter('templateId');

		if ($templateId !== 0) {
			//edit
			$update = $this->templateRepository->updateTemplate($templateId, $values, $this->getUser()->getId());
			if (!$update) {
				$this->flashMessage('Nebyly provedeny žádné změny.', 'danger');
			} else {
				$this->flashMessage('Šablona byla úspěšně upravena.', 'success');
			}
			$this->redirect('this');
		} else {
			//novy
			$create = $this->templateRepository->createTemplate($values, $this->user->getId());
			foreach ($create['messages'] as $message) {
				foreach ($message as $type => $msg) {
					$this->flashMessage($msg, $type);
				}
			}
			$this->redirect('this', ['templateId' => $create['templateId']]);
		}
	}

}
