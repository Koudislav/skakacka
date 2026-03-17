<?php

declare(strict_types=1);

namespace App\Presentation\Administration\Versions;

use App\Repository\AppVersionsRepository;
use Nette\Utils\Paginator;

final class VersionsPresenter extends \App\Presentation\Administration\BaseAdministrationPresenter {

	/** @var AppVersionsRepository @inject */
	public AppVersionsRepository $appVersionsRepository;

	private const PER_PAGE = 20;

	public function renderDefault(int $page = 1): void {
		$paginator = new Paginator();
		$paginator->setItemsPerPage(self::PER_PAGE);
		$paginator->setPage($page);

		$allCount = $this->appVersionsRepository->findAll()->count();
		$paginator->setItemCount($allCount);

		$versions = $this->appVersionsRepository->findAll()
			->limit($paginator->getLength(), $paginator->getOffset());

		$this->template->versions = $versions;
		$this->template->paginator = $paginator;
		$this->template->dashboardHeader = 'Historie verzí';
	}

}
