<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Repository\AppVersionsRepository;
use App\Repository\UserRepository;
use App\Service\MailService;
use Tracy\Debugger;

final class SystemMailerJob {

	public function __construct(
		private AppVersionsRepository $appVersionRepository,
		private UserRepository $userRepository,
		private MailService $mailService,
	) {}

	public function newVersionMail(): void {
		Debugger::log('Spouštím SystemMailerJob->newVersionMail', 'scheduler');
		// najdi nové záznamy, kde email_send = 0
		$newUpdate = $this->appVersionRepository->getOldestForMail();

		if (!$newUpdate) {
			return;
		}

		$subject = "[Nová aktualizace] {$newUpdate->title}";
		$body = "<h2>Dobrý den,</h2>
			</h3>rádi bychom Vás informovali o nové aktualizaci naší aplikace!<br>
			verze {$newUpdate->version} vydaná dne " . $newUpdate->created_at->format('j. n. Y - H:i') . "</h3>";
		$body .= nl2br($newUpdate->message);

		$users = $this->userRepository->findAll();
		$mailer = $this->mailService->createMailer();
		foreach ($users as $user) {

			$message = $body . $this->unsubscribeVersions($user->email);
			$this->mailService->sendBulk($subject, $message, [$user->email], $mailer);

			// označíme update jako odeslaný
			$newUpdate->update(['email_send' => 1]);
		}
	}

	private function unsubscribeVersions(string $email): string {
		$unsubscribeLink = "https://yourapp.com/unsubscribe?email=" . urlencode($email);
		return "<p style='font-size: 0.8em; color: #888;'>Pokud si nepřejete dostávat tyto aktualizace, <b>nemůžete</b> se odhlásit zde (zatím).</p>";
	}

}
