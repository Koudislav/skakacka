<?php

declare(strict_types=1);

namespace App\Presentation\Administration\Dashboard;

use App\Forms\LoginFormFactory;
use App\Repository\AppVersionsRepository;
use App\Repository\UserRepository;
use App\Service\DiskQuotaService;
use App\Service\MailService;
use Nette;

final class DashboardPresenter extends \App\Presentation\Administration\BaseAdministrationPresenter {

	/** @var LoginFormFactory @inject */
	public LoginFormFactory $loginFormFactory;

	/** @var AppVersionsRepository @inject */
	public AppVersionsRepository $appVersionsRepository;

	/** @var UserRepository @inject */
	public UserRepository $userRepository;

	/** @var DiskQuotaService @inject */
	public DiskQuotaService $diskQuota;

	/** @var MailService @inject */
	public MailService $mailService;

	public function renderDefault() {
		if ($this->getUser()->isLoggedIn()) {
			$this->template->dashboardHeader = 'Přehled';
			$this->diskQuota();
			$this->checkConsistency();
			$this->versions();
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
			\Tracy\Debugger::log('User login success - ' . $values->email, 'user');
		} catch (Nette\Security\AuthenticationException $e) {
			if ($e->getCode() === UserRepository::ERROR_EMAIL_NOT_VERIFIED) {
				$user = $this->userRepository->getByEmail($values->email);
				if ($user) {
					if (empty($user->email_verification_expires_at) || $user->email_verification_expires_at < new \DateTime() || empty($user->email_verification_token)) {
						$this->flashMessage('Email není ověřen. Ověřovací odkaz expiroval, odesílám nový. Zkontrolujte svou emailovou schránku pro ověřovací odkaz.', 'warning');
						$token = $this->userRepository->generateEmailVerification($user->id);
						$mailLink = $this->link('//Auth:verifyEmail', ['token' => $token]);
						$this->mailService->sendEmailVerificationMail($user->email, $mailLink);
					} else {
						$this->flashMessage('Email není ověřen. Zkontrolujte svou emailovou schránku pro ověřovací odkaz.', 'warning');
					}
				}
				return;
			}
			$form->addError('Nepodařilo se přihlásit: ' . $e->getMessage());
			\Tracy\Debugger::log('User login failed - ' . $values->email . " - {$e->getMessage()}", 'user');
			return;
		}
		$this->redirect('this');
	}

	private function diskQuota(): void {
		$usageFormated = $this->diskQuota->getUsageFormatted();
		$limitFormated = $this->diskQuota->getLimitFormatted();
		if ($this->diskQuota->isQuotaExceeded()) {
			$this->flashMessage('Disk je plný, funkce jsou omezeny', 'danger');
		} elseif ($this->diskQuota->isQuotaCritical()) {
			$this->flashMessage("Disk je téměř plný: $usageFormated z limitu $limitFormated", 'danger');
		} elseif ($this->diskQuota->isQuotaWarning()) {
			$this->flashMessage("Disk je využit z více než 75 %: $usageFormated z limitu $limitFormated", 'warning');
		}

		$this->template->appDiskUsage = $usageFormated;
		$this->template->appDiskLimit = $limitFormated;
		$this->template->appDiskPercent = $this->diskQuota->getUsagePercent();
	}

	public function versions(): void {
		$this->template->appVersions = $this->appVersionsRepository->findFew(4);
	}

	public function checkConsistency(): void {
		$indexArticles = $this->articleRepository->getIndexes();
		if (!$indexArticles) {
			$unpublishedIndexArticles = $this->articleRepository->getIndexes(false);
			if (!$unpublishedIndexArticles) {
				$this->flashMessage('Varování: Není nastaven žádný článek jako úvodní stránka. V administraci vytvořte článek a nastavte ho jako "Úvodní stránka" + publikováno.', 'danger');
			} else {
				$this->flashMessage('Varování: Žádný článek typu \'Úvodní stránka\' není zveřejněn, jeden musí být publikovaný', 'danger');
			}
		}
		if (count($indexArticles) > 1) {
			$this->flashMessage('Varování: Je nastaveno více než jeden článek jako úvodní stránka. V administraci upravte články a nastavte pouze jeden z nich jako "Úvodní stránka" + publikováno.', 'danger');
		}
	}

}
