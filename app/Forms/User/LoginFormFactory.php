<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;

class LoginFormFactory {

	public function create(callable $onSuccess): Form {
		$form = BootstrapFormFactory::create();
		$form->addText('email', 'Zadejte email:')
			->setRequired('Zadejte email.')
			->addRule($form::Email, 'Zadejte platnou e-mailovou adresu.');

		$form->addPassword('password', 'Heslo:')
			->setRequired('Zadejte heslo.');

		$form->addSubmit('send', 'Přihlásit se')
			->setHtmlAttribute('class', 'btn btn-primary');

		$form->onSuccess[] = $onSuccess;
		return $form;
	}

}
