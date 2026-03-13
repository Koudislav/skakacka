<?php

declare(strict_types=1);

namespace App\Presentation\Administration\Users;

use App\Forms\BootstrapFormFactory;
use App\Repository\UserRepository;
use Nette\Forms\Form;

final class UsersPresenter extends \App\Presentation\Administration\BaseAdministrationPresenter {

	/** @var UserRepository @inject */
	public $userRepository;

	public const ROLES = [
		'admin' => 'Administrátor',
		'superadmin' => 'Superadministrátor'
	];

	public function actionDefault(int $userId = 0): void {
		if (!$this->user->isInRole('superadmin') && $this->user->getId() !== $userId && $userId !== 0) {
			$this->flashMessage('Nemáte oprávnění upravovat jiného uživatele.', 'danger');
			$this->redirect('Users:default');
		}
		if (!$this->user->isInRole('superadmin') && $userId === 0) {
			$this->redirect('Users:default', ['userId' => $this->user->getId()]);
		}
	}

	public function renderDefault(int $userId = 0): void {
		$this->template->currentUserId = $userId;
		$users = $this->userRepository->findAll();
		$this->template->users = $users;
	}

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

		$form->addSubmit('submit', 'Uložit')
			->setHtmlAttribute('class', 'btn btn-primary');

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

}
