<?php

declare(strict_types=1);

namespace App\Presentation\Administration\Articles;

use App\Forms\Administration\Article\ArticleFormFactory;
use Nette\Forms\Form;

final class ArticlesPresenter extends \App\Presentation\Administration\BaseAdministrationPresenter {

	/** @var ArticleFormFactory @inject */
	public ArticleFormFactory $articleFormFactory;

	public function renderDefault(int $articleId = 0): void {
		$this->template->currentArticleId = $articleId;
		$this->basicData($articleId);
		$this->collapses($articleId);
		$this->history($articleId);
	}

	public function renderCreateFromLayout(): void {
		$this->template->currentArticleId = 0;
		$this->basicData(0);
		$this->template->templates = $this->templateRepository->findLayouts();
	}

	public function renderArticleFromLayout(int $articleId = 0, ?int $templateId = null): void {
		$this->template->currentArticleId = $articleId;
		if ($articleId === 0) {
			if ($templateId === null) {
				$this->error('Nebyl vybrán žádný článek ani šablona.', 404);
			}
		} else {
			$article = $this->articleRepository->getArticleById($articleId);
			if (!$article || !$article->template_id) {
				$this->error('Nebyl vybrán žádný článek ani šablona.', 404);
			}
		}
		$this->basicData($articleId);
		$this->collapses($articleId);
	}

	public function createComponentArticleForm() {
		$articleId = (int) $this->getParameter('articleId');

		$form = $this->articleFormFactory->createArticleForm($this->articleRepository->getArticleOptions($articleId), $this->link('//:Article:preview'));

		if ($articleId !== 0) {
			$articleData = $this->articleRepository->getArticleById($articleId);
			if (!$articleData) {
				$this->flashMessage('Článek nenalezen.', 'danger');
				$this->redirect('default');
			} elseif ($articleData->is_system) {
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
				'parent_id' => $articleData->parent_id,
			]);
		}

		$form->onSuccess[] = [$this, 'articleFormSubmitted'];
		return $form;
	}

	public function createComponentLayoutArticleForm() {
		$articleId = (int) $this->getParameter('articleId');
		if ($articleId !== 0) {
			$article = $this->articleRepository->getArticleById($articleId);
			if (!$article || !$article->template_id) {
				$this->error('Article not found', 404);
			}
			$templateId = $article->template_id;
		} else {
			$templateId = (int) $this->getParameter('templateId');
		}
		$template = $this->templateRepository->getById($templateId);
		if (!$template) {
			$this->error('Template not found');
		}
		$placeholders = $template->placeholders_json ? json_decode($template->placeholders_json, true) : [];

		if (!empty($article)) {
			$templateDefaults = $this->templateRepository->resolveDataDefaults($placeholders, $article->template_data_json ?? null);
		}
		$form = $this->articleFormFactory->createLayoutArticleForm($this->articleRepository->getArticleOptions($articleId), $placeholders, $templateDefaults ?? null);

		if (!empty($article)) {
			// $templateDefaults = $this->templateRepository->resolveDataDefaults($placeholders, $article->template_data_json ?? null);
			$form->setDefaults($article);
			$form['templateData']->setDefaults($templateDefaults);
		}
		$form->onSuccess[] = function (Form $form, $values) use ($template, $articleId) {
			$dataJson = $this->articleRepository->createTemplateJson($values->templateData);
			$data =[
				'type' => $values->type,
				'title' => $values->title,
				'slug' => $values->slug,
				'seo_title' => $values->seo_title,
				'seo_description' => $values->seo_description,
				'og_image' => $values->og_image,
				'is_published' => $values->is_published,
				'parent_id' => $values->parent_id,
				'show_title' => 0,

				'content' => $template->content,
				'template_id' => $template->id,
				'template_version' => $template->version,
				'template_data_json' => $dataJson,
			];
			if ($articleId) {
				$this->articleRepository->updateArticleFromArray($articleId, $data, $this->user->getId());
				$this->redirect('this');
			} else {
				$create = $this->articleRepository->createArticleFromArray($data, $this->user->getId());
				$this->redirect('this', ['articleId' => $create['articleId']]);
			}
		};
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
			$this->cache->remove($this->articleRepository::ALL_ARTICLE_PATHS_CACHE_KEY);
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
			$this->cache->remove($this->articleRepository::ALL_ARTICLE_PATHS_CACHE_KEY);
			$this->cache->clean([$this->cache::Tags => ['articleAssets', 'articleBreadcrumbs']]);
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

	private function basicData(int $articleId): void {
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
		$this->template->menus = $this->addMenuLinks($this->articleRepository->getArticleTree());
	}

	private function addMenuLinks(array $tree): array {
		foreach ($tree as &$node) {
			if (!empty($node['article']->template_id)) {
				$node['link'] = $this->link('articleFromLayout', ['articleId' => $node['article']->id]);
			} else {
				$node['link'] = $this->link('default', ['articleId' => $node['article']->id]);
			}
			// children recursion
			if (!empty($node['children'])) {
				$node['children'] = $this->addMenuLinks($node['children']);
			}
		}
		return $tree;
	}

}
