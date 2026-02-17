<?php

declare(strict_types=1);

namespace App\Service;

use Nette\Mail\Message;
use Nette\Mail\SmtpMailer;
use App\Model\Config;

class MailService {

	public function __construct(
		private Config $config
	) {}

	public function send(string $subject, string $body, array $to = [], bool $addConfigRecipients = false): void {
		// fallback na default recipients z configu
		if (empty($to)) {
			$to = $this->configRecipients();
			bdump($to, 'Recipients from config');
		} elseif ($addConfigRecipients) {
			$to = array_merge($to, $this->configRecipients());
			bdump($to, 'Recipients merged with config');
		}

		if (empty($to)) {
			throw new \RuntimeException('No recipients configured for email.');
		}

		$mail = new Message();
		$mail->setFrom($this->config['mail_from'], $this->config['mail_from_name'])
			->setSubject($subject)
			->setHtmlBody($body);

		foreach ($to as $recipient) {
			$mail->addTo($recipient);
		}

		$mailer = new SmtpMailer(
			$this->config['mail_smtp_host'],
			$this->config['mail_smtp_user'],
			$this->config['mail_smtp_pass'],
			$this->config['mail_smtp_port'],
			$this->config['mail_smtp_secure'] ?? null,
		);

		$mailer->send($mail);
	}

	public function configRecipients(): array {
		$recipients = $this->config['mail_recipients'] ?? '';
		bdump($recipients, 'Raw recipients from config');		
		return array_filter(array_map('trim', explode(',', $recipients)));
	}

	// public function configRecipients(): array {
	// 	$config = $this->config['mail_recipients'] ?? '';
	// 	bdump($config, 'Raw recipients from config');
	// 	$exploded = explode(',', $config);

	// 	$recipients = [];
	// 	foreach ($exploded as $recipient) {
	// 		if (str_contains($recipient, '@') && str_contains($recipient, '.')) {
	// 			$recipients[] = trim($recipient);
	// 		}
	// 	}
	// 	return $recipients;
	// }

}
