<?php

declare(strict_types=1);

namespace App\Presentation\Administration;

use App\Repository\ArticleRepository;
use App\Repository\TemplateRepository;
use Nette\Utils\FileSystem;

abstract class BaseAdministrationPresenter extends \App\Presentation\BasePresenter {

	/** @var ArticleRepository @inject */
	public $articleRepository;

	/** @var TemplateRepository @inject */
	public $templateRepository;

	public const WWW_DIR = __DIR__ . '/../../../www';
	public const TEMP_DIR = __DIR__ . '/../../../temp';
	public const UPLOAD_DIR = self::WWW_DIR . '/upload';

	public const MENU = [
		[
			'action' => 'Dashboard:default',
			'icon' => 'bi bi-speedometer2',
			'title' => 'Přehled',
			'onlyForLoggedIn' => true,
			 // 'accessRoles' => ['admin', 'superadmin'],
		],
		[
			'action' => 'users:default',
			'icon' => 'bi bi-people-fill',
			'title' => 'Uživatelé',
			// 'accessRoles' => ['admin', 'superadmin'],
			'onlyForLoggedIn' => true,
		],
		[
			'action' => 'menus:default',
			'icon' => 'bi bi-list',
			'title' => 'Menu',
			'onlyForLoggedIn' => true,
			'params' => [
				'menuKey' => '0',
				'newMenu' => '1',
			],
		],
		[
			'action' => 'articles:default',
			'icon' => 'bi bi-file-earmark-text',
			'title' => 'Články',
			'onlyForLoggedIn' => true,
		],
		[
			'action' => 'articleTemplates:default',
			'icon' => 'bi bi-file-earmark-code',
			'title' => 'Šablony článků',
			'onlyForLoggedIn' => true,
		],
		[
			'action' => 'galleries:default',
			'icon' => 'bi bi-images',
			'title' => 'Galerie',
			'onlyForLoggedIn' => true,
		],
		[
			'action' => 'uploadManager:default',
			'icon' => 'bi bi-images',
			'title' => 'Upload',
			'onlyForLoggedIn' => true,
		],
		[
			'action' => 'configuration:default',
			'icon' => 'bi bi-sliders',
			'title' => 'Nastavení',
			'onlyForLoggedIn' => true,
		],
	];

	
	public function startup() {
		parent::startup();
		if (!$this->getUser()->isLoggedIn() && $this->getName() !== 'Administration:Dashboard') {
			if ($this->isAjax()) {
				$this->error('Unauthorized', 403);
			}
			$this->flashMessage('Pro tuto akci nemáte oprávnění.', 'danger');
			$this->redirect('Dashboard:default');
		}
		FileSystem::createDir(self::UPLOAD_DIR);
	}

	public function beforeRender() {
		$this->template->menu = $this->processMenu();
		$this->template->colorScheme = $this->getColorScheme();
		$this->template->favicons = $this->favicons();
	}

	//helpers
	public function processMenu(): array {
		$menu = self::MENU;
		foreach ($menu as $key => $m) {
			$link = $this->link($m['action'], $m['params'] ?? []);
			$menu[$key]['link'] = $link;

			if (isset($m['accessRoles'])) {
				$hasAccess = false;
				foreach ($m['accessRoles'] as $role) {
					if ($this->user->isInRole($role)) {
						$hasAccess = true;
						break;
					}
				}
				if (!$hasAccess) {
					unset($menu[$key]);
					continue;
				}
			}
			if (isset($m['onlyForLoggedIn']) && $m['onlyForLoggedIn'] === true) {
				if (!$this->user->isLoggedIn()) {
					unset($menu[$key]);
					continue;
				}
			}
		}

		return $menu;
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

	public function handleLogout(): void {
		$this->user->logout(true);
		$this->redirect('dashboard:default');
	}

	public function handleLoadTemplates(): void {
		$templates = [];
		foreach ($this->templateRepository->findAll() as $row) {
			$templates[] = [ 'id' => $row->id, 'name' => $row->name, ];
		}
		$this->sendJson($templates);
	}

	// Vrátí obsah konkrétní šablony
	public function handleLoadTemplate(int $templateId): void {
		$template = $this->templateRepository->getTemplateById($templateId);
		if (!$template) {
			$this->sendJson(['error' => 'Šablona nenalezena']);
		}
		$this->sendJson([
			'id' => $template->id,
			'name' => $template->name,
			'content' => $template->content,
		]);
	}

}
