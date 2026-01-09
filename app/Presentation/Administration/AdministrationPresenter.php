<?php

declare(strict_types=1);

namespace App\Presentation\Administration;

use App\Forms\LoginFormFactory;
use App\Repository\UserRepository;
use Nette;

final class AdministrationPresenter extends \App\Presentation\BasePresenter {

	/** @var LoginFormFactory @inject */
	public $loginFormFactory;

	/** @var UserRepository @inject */
	public $userRepository;
	
	//components
	protected function createComponentLoginForm() {
		return $this->loginFormFactory->create([$this, 'loginFormSubmitted']);
	}

	//Form manipulation
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
