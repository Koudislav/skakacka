<?php

declare(strict_types=1);

namespace App\Presentation\Administration\Articles;

use App\Forms\BootstrapFormFactory;
use Nette\Forms\Form;

final class ArticlesPresenter extends \App\Presentation\Administration\BaseAdministrationPresenter {

	public const ARTICLE_TYPES = [
		'article' => 'Běžný článek',
		'news' => 'Novinka',
		'index' => 'Úvodní stránka',
	];

	public function renderDefault(int $articleId = 0): void {
		$this->template->currentArticleId = $articleId;
		$data = $this->articleRepository->findAll();

		$indexes = [];
		$articles = [];

		foreach ($data as $key => $article) {
			if ($article->type === 'index') {
				$indexes[$key] = $article;
			} else {
				$articles[$key] = $article;
			}
		}

		$systemArticle = (bool) ($data[$articleId]->is_system ?? false);
		$this->template->systemArticle = $systemArticle;
		if ($systemArticle) {
			$this->template->systemArticleDescription = $data[$articleId]->system_description;
		}

		$this->template->articleName = $data[$articleId]->title ?? null;
		$this->template->menus = $indexes + $articles;

		$this->collapses($articleId);
		$this->history($articleId);
	}

	public function createComponentArticleForm() {
		$form = BootstrapFormFactory::create('oneLine');
		$articleId = (int) $this->getParameter('articleId');

		$form->addSelect('type', 'Typ článku:', self::ARTICLE_TYPES)
			->setDefaultValue('article');

		$form->addText('title', 'Nadpis:')
			->setRequired('Zadejte nadpis článku.');

		$form->addCheckbox('show_title', 'Nadpis ve stránce')
			->setDefaultValue(false);

		$form->addText('slug', 'Slug (jenom malá písmena, čísla, pomlčky):')
			->addRule($form::Pattern, 'Zadejte platný slug (malá písmena, čísla, pomlčky).', '^[a-z0-9\-]+$');

		$form->addText('seo_title', 'SEO titulek:')
			->setHtmlAttribute('placeholder', 'Ponechte prázdné pro použití nadpisu jako SEO titulku.');
		
		$form->addText('seo_description', 'SEO popis:')
			->setHtmlAttribute('placeholder', 'Ponechte prázdné pro použití SEO description ze sekce NASTAVENÍ.');

		$form->addText('og_image', 'og image:')
			->setHtmlAttribute('placeholder', 'obrázek pro sociální sítě (relativní cesta od kořene webu, např. /upload/obrazek.jpg)');

		$form->addTextArea('content', 'Obsah článku:')
			->setHtmlAttribute('rows', 10)
			->setHtmlAttribute('class', 'tiny-editor');

		$form->addCheckbox('is_published', 'Publikováno')
			->setDefaultValue(false);

		$form->addButton('preview', 'Náhled')
			->setHtmlAttribute('class', 'btn btn-sm btn-warning')
			->setHtmlAttribute('data-preview-link', $this->link('//:Article:preview'))
			->setHtmlAttribute('onclick', 'showPreview(this);');

		$form->addSubmit('submit', 'Uložit')
			->setHtmlAttribute('class', 'btn btn-sm btn-primary');

		if ($articleId !== 0) {
			$articleData = $this->articleRepository->getArticleById($articleId);
			if ($articleData->is_system) {
				$this->disableFieldsForSystem($form);
			}

			$form->setDefaults([
				'type' => $articleData->type,
				'title' => $articleData->title,
				'content' => $articleData->content,
				'slug' => $articleData->slug,
				'show_title' => $articleData->show_title == 1,
				'is_published' => $articleData->is_published == 1,
				'seo_title' => $articleData->seo_title,
				'seo_description' => $articleData->seo_description,
				'og_image' => $articleData->og_image,
			]);
		}

		$form->onSuccess[] = [$this, 'articleFormSubmitted'];
		return $form;
	}

	private function disableFieldsForSystem(Form $form): void {
		foreach (['type','title','slug','show_title','is_published'] as $field) {
			/** @var \Nette\Forms\Controls\BaseControl $control */
			$control = $form[$field];
			$control->setDisabled();
		}
	}

	public function articleFormSubmitted(Form $form, $values): void {
		$articleId = (int) $this->getParameter('articleId');

		if ($articleId !== 0) {
			//edit
			$update = $this->articleRepository->updateArticle($articleId, $values, $this->getUser()->getId());
			if (!$update) {
				$this->flashMessage('Nebyly provedeny žádné změny.', 'danger');
			} else {
				$this->flashMessage('Článek byl úspěšně upraven.', 'success');
			}
			$this->cache->remove($this->articleRepository::ALL_ARTICLE_SLUGS_CACHE_KEY);
			$this->cache->clean([$this->cache::Tags => ['articleAssets']]);
			$this->redirect('this');
		} else {
			//novy
			$create = $this->articleRepository->createArticle($values, $this->user->getId());
			foreach ($create['messages'] as $message) {
				foreach ($message as $type => $msg) {
					$this->flashMessage($msg, $type);
				}
			}
			$this->cache->remove($this->articleRepository::ALL_ARTICLE_SLUGS_CACHE_KEY);
			$this->redirect('this', ['articleId' => $create['articleId']]);
		}
	}

	public function handleDelete(int $articleId): void {
		$deleted = $this->articleRepository->deleteArticle($articleId, $this->getUser()->getId());
		if ($deleted) {
			$this->flashMessage('Článek byl smazán.', 'success');
		} else {
			$this->flashMessage('Článek se nepodařilo smazat.', 'danger');
		}
		$this->redirect('default');
	}

	public function handleSetCollapseState(string $collapseId, bool $state): void {
		$session = $this->getSession('articleAccordion');
		$section = $session->get('sections') ?? [];
		$section[$collapseId] = $state;
		$session->set('sections', $section);
		$this->sendJson(['state' => 'ok']);
	}

	private function collapses(int $articleId): void {
		$default = $articleId === 0;
		$collapses = ['main', 'seo', 'history'];
		foreach ($collapses as $collapse) {
			$this->template->{$collapse . 'CollapseOpen'} = $this->getCollapseState($collapse . '-collapse', $default);
		}
	}

	private function getCollapseState(string $id, bool $default): bool	{
		$session = $this->getSession('articleAccordion');
		$sections = $session->get('sections') ?? [];
		if (!$default && !empty($sections) && array_key_exists($id, $sections)) {
			return $sections[$id];
		}
		$defaults = ['main-collapse' => true];
		return $defaults[$id] ?? false;
	}

	private function history(int $articleId): void {
		if ($articleId !== 0) {
			$history = $this->articleRepository->getHistoryByArticleId($articleId);
			if (empty($history)) return;

			$history = array_values($history);
			$diffs = [];
			foreach ($history as $i => $h) {
				$prevContent = $history[$i+1]->content ?? '';
				$diffs[$h->id] = $this->articleRepository->generateDiff($prevContent, $h->content);
			}
			$this->template->history = $history;
			$this->template->historyDiffs = $diffs;
		}
	}

}
