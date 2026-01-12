<?php

declare(strict_types=1);

namespace App\Presentation\Administration;

use App\Forms\BootstrapFormFactory;
use App\Forms\LoginFormFactory;
use App\Repository\UserRepository;
use Nette;
use Nette\Application\UI\Form;

final class AdministrationPresenter extends \App\Presentation\BasePresenter {

	/** @var LoginFormFactory @inject */
	public $loginFormFactory;

	/** @var UserRepository @inject */
	public $userRepository;

	public function actionUsers(int $userId = 0): void {
		bdump($userId, 'userId');
		if (!$this->user->isInRole('superadmin')) {
			$this->flashMessage('Nemáte oprávnění pro přístup na tuto stránku.', 'danger');
			$this->redirect('Administration:default');
		}
	}

	public function renderUsers(int $userId = 0): void {
		$this->template->currentUserId = $userId;
		$users = $this->userRepository->findAll();
		$this->template->users = $users;
		if ($userId !== 0) {
			$this->template->selectedUser = $users->get($userId);
		}
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

		if ($userId === 0) {
			$password->setRequired('Zadejte heslo uživatele.');
			$passwordConfirm->setRequired('Zadejte heslo uživatele pro kontrolu.');
		}

		$passwordConfirm->addRule(
			$form::Equal,
			'Hesla se neshodují.',
			$password
		);

		$form->addSelect('role', 'Vyberte roli:', [
			'admin' => 'Administrátor',
			'superadmin' => 'Superadministrátor'
			])
			->setDefaultValue('admin')
			->setRequired('Vyberte roli.');

		$form->addCheckbox('is_active', 'Aktivní')
			->setDefaultValue(true);

		$form->addSubmit('submit', 'Uložit');

		$form->onSuccess[] = [$this, 'userFormSubmitted'];

		return $form;
	}

	//Form manipulation
	public function userFormSubmitted(Form $form, $values): void {
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

}
