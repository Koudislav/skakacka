<?php

declare(strict_types=1);

namespace App\Presentation\Administration\Dashboard;

use App\Forms\LoginFormFactory;
use App\Service\DiskQuotaService;
use Nette;
use Nette\Caching\Cache;
use Nette\Caching\Storage;

final class DashboardPresenter extends \App\Presentation\Administration\BaseAdministrationPresenter {

	/** @var LoginFormFactory @inject */
	public $loginFormFactory;

	/** @var Storage @inject */
	public Storage $cacheStorage;

	/** @var DiskQuotaService @inject */
	public DiskQuotaService $diskQuota;

	public Cache $cache;

	public function startup() {
		parent::startup();
		$this->cache = new Cache($this->cacheStorage);
	}

	public function renderDefault() {
		if ($this->getUser()->isLoggedIn()) {
			$this->template->dashboardHeader = 'Přehled';
		} else {
			$this->template->dashboardHeader = 'Přihlášení';
		}
		if ($this->getUser()->isLoggedIn()) {
			$this->checkConsistency();
		}
		$this->diskQuota();
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

}
