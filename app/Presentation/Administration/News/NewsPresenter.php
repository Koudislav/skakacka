<?php

declare(strict_types=1);

namespace App\Presentation\Administration\News;

use App\Forms\BootstrapFormFactory;
use App\Repository\NewsRepository;
use Nette\Forms\Form;

final class NewsPresenter extends \App\Presentation\Administration\BaseAdministrationPresenter {

	/** @var NewsRepositoy @inject */
	public NewsRepository $newsRepository;

	public function renderDefault(int $newsId = 0): void {
		$this->template->currentNewsId = $newsId;
		$this->template->items = $this->newsRepository->findAll();
		$this->template->newsName = $newsId ? $this->newsRepository->getById($newsId)?->title : null;
	}

	public function createComponentNewsForm() {
		$form = BootstrapFormFactory::create('oneLine');
		$newsId = (int) $this->getParameter('newsId');

		$form->addText('title', 'Nadpis:')
			->setRequired();

		$form->addText('slug', 'Slug:')
			->addRule($form::Pattern, 'Neplatný slug', '^[a-z0-9\-]+$');

		$form->addTextArea('excerpt', 'Perex:');

		$form->addText('cover_image', 'Obrázek:');

		$form->addTextArea('content', 'Obsah:')
			->setHtmlAttribute('class', 'tiny-editor');

		$form->addText('published_at', 'Publikováno:')
			->setHtmlType('datetime-local');

		$form->addCheckbox('is_published', 'Publikováno');

		$form->addSubmit('submit', 'Uložit');

		if ($newsId) {
			$data = $this->newsRepository->getById($newsId);
			if ($data) {
				$form->setDefaults([
					'title' => $data->title,
					'slug' => $data->slug,
					'excerpt' => $data->excerpt,
					'content' => $data->content,
					'cover_image' => $data->cover_image,
					'is_published' => (bool)$data->is_published,
					'published_at' => $data->published_at?->format('Y-m-d\TH:i'),
				]);
			}
		}

		$form->onSuccess[] = [$this, 'submitted'];
		return $form;
	}

	public function submitted(Form $form, \stdClass $values): void {
		$newsId = (int) $this->getParameter('newsId');

		if ($newsId) {
			$this->newsRepository->update($newsId, $values, $this->getUser()->getId());
			$this->flashMessage('Upraveno', 'success');
		} else {
			$id = $this->newsRepository->create($values, $this->getUser()->getId());
			$this->flashMessage('Vytvořeno', 'success');
			$this->redirect('this', ['newsId' => $id]);
		}

		$this->redirect('this');
	}

	public function handleDelete(int $newsId): void {
		$this->newsRepository->delete($newsId, $this->getUser()->getId());
		$this->flashMessage('Smazáno', 'success');
		$this->redirect('default');
	}

}
