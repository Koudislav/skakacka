<?php

declare(strict_types=1);

namespace App\Components;

use App\Forms\BootstrapFormFactory;
use App\Service\MailService;
use App\Service\ReCaptchaService;
use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Http\Request;

class ContactFormControl extends Control {

	public function __construct(
		private ReCaptchaService $reCaptcha,
		private Request $httpRequest,
		private MailService $mailService,
	) {}

	public function createComponentForm(): Form {
		$form = BootstrapFormFactory::create('inLine');

		$form->addGroup('Kontaktujte nás a my se vám ozveme zpět.');
		$form->addText('name', 'Jméno:')
			->setRequired('Zadejte jméno')
			->setHtmlAttribute('placeholder', 'Vaše jméno');

		$form->addEmail('email', 'Email:')
			->setRequired('Zadejte email')
			->setHtmlAttribute('placeholder', 'Váš email');

		$form->addText('phone', 'Telefon:')
			->setHtmlAttribute('placeholder', 'Váš telefon');

		$form->addGroup();

		$form->addTextArea('message', '')
			->setRequired('Napište zprávu')
			->setHtmlAttribute('placeholder', 'Vaše zpráva');

		$form->addSubmit('send', 'Odeslat')
			->setHtmlAttribute('class', 'btn btn-primary');

		// skryté pole pro reCAPTCHA token
		$form->addHidden('recaptchaToken');
		$form->getElementPrototype()->setAttribute('data-recaptcha', 'true');

		$form->onSuccess[] = [$this, 'process'];

		return $form;
	}

	public function process(Form $form, \stdClass $values): void {

		bdump($values);
		if (!$this->reCaptcha->verify(
			$values->recaptchaToken,
			$this->httpRequest->getRemoteAddress()
		)) {
			$form->addError('Ověření proti spamu selhalo.');
			return;
		}

		$this->mailService->send(
			'Nová zpráva z kontaktního formuláře',
			"Jméno: {$values->name}<br>Email: {$values->email}<br>Telefon: {$values->phone}<br>Zpráva:<br>{$values->message}"
		);

		$this->presenter->flashMessage('Zpráva byla odeslána.');
		$this->presenter->redirect('this');
	}

	public function render(): void {
		$this->template->setFile(__DIR__ . '/contactForm.latte');
		$this->template->render();
	}

}
