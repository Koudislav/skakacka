<?php

declare(strict_types=1);

namespace App\Service;

use Nette\Mail\Message;
use Nette\Mail\SmtpMailer;
use App\Config\Config;

class MailService {

	public function __construct(
		private Config $config
	) {}

	public function send(string $subject, string $body, array $to = [], bool $addConfigRecipients = false): void {
		// fallback na default recipients z configu
		if (empty($to)) {
			$to = $this->configRecipients();
		} elseif ($addConfigRecipients) {
			$to = array_merge($to, $this->configRecipients());
		}

		if (empty($to)) {
			throw new \RuntimeException('No recipients configured for email.');
		}

		$mail = $this->newMessage($subject, $body);
		foreach ($to as $recipient) {
			$mail->addTo($recipient);
		}

		$mailer = $this->createMailer();

		$mailer->send($mail);
	}

	public function sendBulk(string $subject, string $message, array $recipients, ?SmtpMailer $mailer = null): void {
		if (!$mailer) {
			$mailer = $this->createMailer();
		}
		$mail = $this->newMessage($subject, $message);
		foreach ($recipients as $recipient) {
			$mail->addTo($recipient);
			$mailer->send($mail);
		}
	}

	public function newMessage(string $subject, string $body): Message {
		$mail = new Message();
		$mail->setFrom($this->config['mail_from'], $this->config['mail_from_name'])
			->setSubject($subject)
			->setHtmlBody($body);
		return $mail;
	}

	public function createMailer(): SmtpMailer {
		return new SmtpMailer(
			$this->config['mail_smtp_host'],
			$this->config['mail_smtp_user'],
			$this->config['mail_smtp_pass'],
			$this->config['mail_smtp_port'],
			$this->config['mail_smtp_secure'] ?? null,
		);
	}

	public function configRecipients(): array {
		$recipients = $this->config['mail_recipients'] ?? '';
		return array_filter(array_map('trim', explode(',', $recipients)));
	}

	public function sendEmailVerificationMail(string $email, string $link) {
		$linkEscaped = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
		$subject = 'Ověření e-mailu | ' . $this->config['mail_from_name'];
		$body = "
			<p>Dobrý den,</p>
			<p>pro aktivaci účtu na webu <strong>{$this->config['base_url']}</strong> klikněte na následující odkaz:</p>
			<p><a href='$linkEscaped'>Ověřit odkaz</a></p>
			<p>Platnost odkazu je 24 hodin.</p>
			<p>Pokud vám nefunguje klikací odkaz, zkopírujte a vložte následující URL do vašeho prohlížeče:</p>
			<p>$linkEscaped</p>
			<p>Pokud jste tento email nevyžádali, můžete jej ignorovat.</p>
		";
		$this->send($subject, $body, [$email]);
	}

}
