<?php

declare(strict_types=1);

namespace App\Presentation\Administration\Dashboard;

use App\Forms\LoginFormFactory;
use Nette;

final class DashboardPresenter extends \App\Presentation\Administration\BaseAdministrationPresenter {

	/** @var LoginFormFactory @inject */
	public $loginFormFactory;

	public function renderDefault() {
		if ($this->getUser()->isLoggedIn()) {
			$this->template->dashboardHeader = 'Přehled';
		} else {
			$this->template->dashboardHeader = 'Přihlášení';
		}
	}

	protected function createComponentLoginForm() {
		return $this->loginFormFactory->create([$this, 'loginFormSubmitted']);
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

}
