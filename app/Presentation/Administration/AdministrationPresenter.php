<?php

declare(strict_types=1);

namespace App\Presentation\Administration;

use App\Forms\BootstrapFormFactory;
use App\Forms\LoginFormFactory;
use App\Repository\ArticleRepository;
use App\Repository\UserRepository;
use Nette;
use Nette\Application\UI\Form;

final class AdministrationPresenter extends \App\Presentation\BasePresenter {

	/** @var LoginFormFactory @inject */
	public $loginFormFactory;
	
	/** @var ArticleRepository @inject */
	public $articleRepository;

	/** @var UserRepository @inject */
	public $userRepository;

	public const MENU = [
		[
			'action' => 'Administration:users',
			'icon' => 'bi bi-people-fill',
			'title' => 'Správa uživatelů',
			// 'accessRoles' => ['admin', 'superadmin'],
			'onlyForLoggedIn' => true,
		],
		[
			'action' => 'Administration:articles',
			'icon' => 'bi bi-file-earmark-text',
			'title' => 'Články',
			'onlyForLoggedIn' => true,
		]
	];

	public const ARTICLE_TYPES = [
		'article' => 'Běžný článek',
		'news' => 'Novinka',
	];

	public const ROLES = [
		'admin' => 'Administrátor',
		'superadmin' => 'Superadministrátor'
	];

	public function beforeRender() {
		$this->template->menu = $this->processMenu();
	}

	public function actionUsers(int $userId = 0): void {
		if (!$this->user->isLoggedIn()) {
			$this->flashMessage('Nemáte oprávnění pro přístup na tuto stránku.', 'danger');
			$this->redirect('Administration:default');
		}
		if (!$this->user->isInRole('superadmin') && $this->user->getId() !== $userId && $userId !== 0) {
			$this->flashMessage('Nemáte oprávnění upravovat jiného uživatele.', 'danger');
			$this->redirect('Administration:Users');
		}
		if (!$this->user->isInRole('superadmin') && $userId === 0) {
			$this->redirect('Administration:Users', ['userId' => $this->user->getId()]);
		}
	}

	public function renderUsers(int $userId = 0): void {
		$this->template->currentUserId = $userId;
		$users = $this->userRepository->findAll();
		$this->template->users = $users;
	}

	public function actionArticles(int $articleId = 0): void {
		if (!$this->user->isLoggedIn()) {
			$this->flashMessage('Pro přístup na tuto stránku se musíte přihlásit.', 'danger');
			$this->redirect('Administration:default');
		}
	}

	public function renderArticles(int $articleId = 0): void {
		$this->template->currentArticleId = $articleId;
		$articles = $this->articleRepository->findAll();
		$this->template->articles = $articles;
		bdump($articles);

	}
	
	//components
	protected function createComponentLoginForm() {
		return $this->loginFormFactory->create([$this, 'loginFormSubmitted']);
	}

	//Forms
	public function createComponentUserForm() {
		$form = BootstrapFormFactory::create('oneLine');
		$userId = (int) $this->getParameter('userId');
		$loggedUserId = (int) $this->getUser()->getId();
		$isSelfEdit = $userId !== 0 && $userId === $loggedUserId;
		$isSuperAdmin = $this->getUser()->isInRole('superadmin');

		$form->addText('email', 'E-mail:')
			->setRequired('Zadejte e-mail uživatele.')
			->addRule($form::Email, 'Zadejte platnou e-mailovou adresu.');

		if ($isSelfEdit) {
			$form->addPassword('oldPassword', 'Současné heslo:')
				->setRequired('Pro změnu hesla zadejte současné heslo.');
		}

		$password = $form->addPassword('password', 'Zadejte nové heslo:');
		$passwordConfirm = $form->addPassword('passwordConfirm', 'Zadejte nové heslo pro kontrolu:');

		$passwordConfirm->addRule(
			$form::Equal,
			'Hesla se neshodují.',
			$password
		);

		$role = $form->addSelect('role', 'Vyberte roli:', self::ROLES)
			->setDefaultValue('admin')
			->setRequired('Vyberte roli.');

		if (!$isSuperAdmin) {
			$role->setDisabled();
		}

		$active = $form->addCheckbox('is_active', 'Aktivní')
			->setDefaultValue(true);

		if ($isSelfEdit) {
			$active->setDisabled();
		}

		$form->addSubmit('submit', 'Uložit');

		if ($userId !== 0) {
			$userData = $this->userRepository->getUserById($userId);
			$form->setDefaults([
				'email' => $userData->email,
				'role' => $userData->role,
				'is_active' => $userData->is_active == 1,
			]);
		}

		if ($userId === 0) {
			$password->setRequired('Zadejte heslo uživatele.');
			$passwordConfirm->setRequired('Zadejte heslo uživatele pro kontrolu.');
			if (!$this->user->isInRole('superadmin')) {
				$this->disableForm($form);
			}
		}

		$form->onSuccess[] = [$this, 'userFormSubmitted'];

		return $form;
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

		$form->addTextArea('content', 'Obsah:')
			->setRequired('Zadejte obsah článku.')
			->setHtmlAttribute('rows', 10);

		$form->addCheckbox('is_published', 'Publikováno')
			->setDefaultValue(false);

		$form->addSubmit('submit', 'Uložit');

		if ($articleId !== 0) {
			$articleData = $this->articleRepository->getArticleById($articleId);
			$form->setDefaults([
				'type' => $articleData->type,
				'title' => $articleData->title,
				'content' => $articleData->content,
				'slug' => $articleData->slug,
				'show_title' => $articleData->show_title == 1,
				'is_published' => $articleData->is_published == 1,
			]);
		}

		$form->onSuccess[] = [$this, 'articleFormSubmitted'];

		return $form;
	}

	//Form manipulation
	public function userFormSubmitted(Form $form, $values): void {
		if (!$this->user->isLoggedIn()) {
			$form->addError('Pro tuto akci musíte být přihlášeni.');
			return;
		}
		$userId = (int) $this->getParameter('userId');
		$loggedUserId = (int) $this->getUser()->getId();
		$isSelfEdit = $userId !== 0 && $userId === $loggedUserId;
		$isSuperAdmin = $this->getUser()->isInRole('superadmin');

		// EDITACE
		if ($userId !== 0) {

			// změna hesla
			if ($values->password !== '') {

				// editace sebe sama → kontrola starého hesla
				if ($isSelfEdit) {
					if (!$this->userRepository->verifyPasswordById($userId, $values->oldPassword)) {
						$form->addError('Zadané současné heslo není správné.');
						return;
					}
				}
				// editace cizího uživatele → jen superadmin
				elseif (!$isSuperAdmin) {
					$form->addError('Nemáte oprávnění měnit heslo jinému uživateli.');
					return;
				}

				$this->userRepository->setPassword($userId, $values->password);
			}

			// další update (email, role, aktivní)
			$this->userRepository->updateUser($userId, $values);
			$this->flashMessage('Uživatel byl úspěšně upraven.', 'success');
		}

		// NOVÝ UŽIVATEL
		else {
			$this->userRepository->createUser($values);
			$this->flashMessage('Uživatel byl úspěšně vytvořen.', 'success');
		}

		$this->redirect('this');
	}

	public function articleFormSubmitted(Form $form, $values): void {
		$articleId = (int) $this->getParameter('articleId');

		if ($articleId !== 0) {
			//edit
			$update = $this->articleRepository->updateArticle($articleId, $values, $this->user->getId());
			if (!$update) {
				$this->flashMessage('Nebyly provedeny žádné změny.', 'danger');
			} else {
				$this->flashMessage('Článek byl úspěšně upraven.', 'success');
			}
		} else {
			//novy
			$create = $this->articleRepository->createArticle($values, $this->user->getId());
			foreach ($create['messages'] as $message) {
				foreach ($message as $type => $msg) {
					$this->flashMessage($msg, $type);
				}
			}
			$this->redirect('Administration:articles', ['articleId' => $create['articleId']]);
		}

		$this->redirect('this');
	}

	public function loginFormSubmitted($form, $values) {
		try {
			$this->getUser()->login($values->email, $values->password);
			$this->flashMessage('Přihlášení bylo úspěšné.', 'success');
			\Tracy\Debugger::log('User login success - ' . $values->email, 'user');
		} catch (Nette\Security\AuthenticationException $e) {
			$form->addError('Nepodařilo se přihlásit: ' . $e->getMessage());
			\Tracy\Debugger::log('User login failed - ' . $values->email . " - {$e->getMessage()}", 'user');
			return;
		}
		$this->redirect('this');
	}

	public function handleLogout(): void {
		$this->user->logout(true);
		$this->redirect('Administration:default');
	}

	//helpers
	public function processMenu(): array {
		$menu = self::MENU;
		foreach ($menu as $key => $m) {
			if (isset($m['accessRoles'])) {
				$hasAccess = false;
				foreach ($m['accessRoles'] as $role) {
					if ($this->user->isInRole($role)) {
						$hasAccess = true;
						break;
					}
				}
				if (!$hasAccess) {
					unset($menu[$key]);
					continue;
				}
			}
			if (isset($m['onlyForLoggedIn']) && $m['onlyForLoggedIn'] === true) {
				if (!$this->user->isLoggedIn()) {
					unset($menu[$key]);
					continue;
				}
			}
		}
		return $menu;
	}

}
