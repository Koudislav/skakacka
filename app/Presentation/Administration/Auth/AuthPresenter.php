<?php

declare(strict_types=1);

namespace App\Presentation\Administration\Auth;

use App\Repository\UserRepository;
use App\Services\Security\HashSigner;

final class AuthPresenter extends \App\Presentation\Administration\BaseAdministrationPresenter {

	/** @var UserRepository @inject */
	public UserRepository $userRepository;

	/** @var HashSigner @inject */
	public HashSigner $hashSigner;

	public function actionVerifyEmail(string $token): void {
		if (strlen($token) !== 64) {
			$this->flashMessage('Neplatný ověřovací odkaz.', 'danger');
			$this->redirect('Dashboard:default');
		}
		$user = $this->userRepository->getByVerificationToken($token);

		if (!$user) {
			$this->flashMessage('Ověřovací odkaz je neplatný, byl již použit, nebo byl vygenerován novější. Pokud máte stále problém s přihlášením, kontaktujte podporu.', 'danger');
			$this->redirect('Dashboard:default');
		}

		if (empty($user->email_verification_expires_at) || $user->email_verification_expires_at < new \DateTime()) {
			$this->flashMessage('Odkaz expiroval.', 'danger');
			$this->redirect('Dashboard:default');
		}

		$this->userRepository->markEmailAsVerified($user->id);

		$this->flashMessage('Email byl úspěšně ověřen.', 'success');
		$this->redirect('Dashboard:default');
	}

	public function actionUnsubscribe(string $type, string $token, int $uid): void {
		$notValidMess = 'Odkaz je neplatný.';
		if (!$this->hashSigner->verify((string) $uid, $token)) {
			$this->flashMessage($notValidMess, 'danger');
			$this->redirect('Dashboard:default');
		}
		$user = $this->userRepository->getById($uid);

		if (!$user) {
			$this->flashMessage($notValidMess, 'danger');
			$this->redirect('Dashboard:default');
		}

		if ($type === 'versions') {
			if ($user->notify_versions) {
				$this->userRepository->update($user->id, ['notify_versions' => 0]);
				$this->flashMessage('Úspěšně jste se odhlásili z odběru aktualizací.', 'success');
			} else {
				$this->flashMessage('Již jste odhlášeni z odběru aktualizací.', 'info');
			}
		} else {
			$this->flashMessage($notValidMess, 'danger');
		}

		$this->redirect('Dashboard:default');
	}

}
