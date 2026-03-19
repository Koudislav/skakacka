<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Config\Config;
use App\Repository\AppVersionsRepository;
use App\Repository\UserRepository;
use App\Service\MailService;
use App\Services\Security\HashSigner;
use Tracy\Debugger;

final class SystemMailerJob {

	public function __construct(
		private AppVersionsRepository $appVersionRepository,
		private UserRepository $userRepository,
		private MailService $mailService,
		private Config $config,
		private HashSigner $hashSigner,
	) {}

	public function newVersionMail(): void {
		Debugger::log('Spouštím SystemMailerJob->newVersionMail', 'scheduler');
		// najdi nové záznamy, kde email_send = 0
		$newUpdate = $this->appVersionRepository->getOldestForMail();

		if (!$newUpdate) {
			Debugger::log('Žádný update pro emailing.', 'scheduler');
			return;
		}

		$users = $this->userRepository->findAll()
			->where('is_active', 1)
			->where('notify_versions', 1)
			->fetchAll();

		if (empty($users)) {
			Debugger::log('Žádný uživatel přihlášen k odběru aktualizací.', 'scheduler');
			$newUpdate->update(['email_send' => 1]);
			return;
		}

		$subject = "[Nová aktualizace] {$newUpdate->title}";
		$body = "<h2>Dobrý den,</h2>
			</h3>rádi bychom Vás informovali o nové aktualizaci naší aplikace!<br>
			verze {$newUpdate->version} vydaná dne " . $newUpdate->created_at->format('j. n. Y - H:i') . "</h3>";
		$body .= nl2br($newUpdate->message);

		$mailer = $this->mailService->createMailer();
		foreach ($users as $user) {
			if ($user->created_at > $newUpdate->created_at) {
				Debugger::log("Uživatel {$user->email} se zaregistroval po vydání aktualizace, přeskočím odesílání emailu.", 'scheduler');
				continue;
			}
			$message = $body . $this->unsubscribeVersions($user->id, $user->email);
			Debugger::log("Odesílám email o nové aktualizaci verze {$newUpdate->version} uživateli {$user->email}", 'scheduler');
			$this->mailService->sendBulk($subject, $message, [$user->email], $mailer);
		}
		$newUpdate->update(['email_send' => 1]);
	}

	private function unsubscribeVersions(int $userId, string $email): string {
		$token = $this->hashSigner->sign((string) $userId);
		$unsubscribeLink = $this->config['base_url'] . "administration/auth/unsubscribe?type=versions&token={$token}&uid={$userId}";
		return "<p style='font-size: 0.8em; color: #888;'>Pokud si nepřejete dostávat tyto aktualizace, můžete se <a href='" . htmlspecialchars($unsubscribeLink) . "'>odhlásit zde</a>.</p>";
	}

}
