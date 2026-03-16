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
			$this->diskQuota();
			$this->checkConsistency();
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
