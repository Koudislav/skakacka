<?php

declare(strict_types=1);

namespace App\Presentation\Administration\Versions;

use App\Repository\UserRepository;
use Nette\Utils\Paginator;

final class VersionsPresenter extends \App\Presentation\Administration\BaseAdministrationPresenter {

	/** @var UserRepository @inject */
	public UserRepository $userRepository;

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
		$this->template->subscribeStatus = (bool) $this->getUser()->getIdentity()->notify_versions;
	}

	public function handleChangeSubscribeState(bool $state): void {
		$this->userRepository->update($this->getUser()->getId(), ['notify_versions' => !$state]);
		$this->getUser()->getIdentity()->notify_versions = (int) !$state;
	}

}
