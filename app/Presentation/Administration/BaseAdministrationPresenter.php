<?php

declare(strict_types=1);

namespace App\Presentation\Administration;

use App\Repository\AppVersionsRepository;
use App\Repository\ArticleRepository;
use App\Repository\TemplateRepository;
use Nette\Utils\FileSystem;

abstract class BaseAdministrationPresenter extends \App\Presentation\BasePresenter {

	/** @var AppVersionsRepository @inject */
	public AppVersionsRepository $appVersionsRepository;

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
			'action' => 'Users:default',
			'icon' => 'bi bi-people-fill',
			'title' => 'Uživatelé',
			// 'accessRoles' => ['admin', 'superadmin'],
			'onlyForLoggedIn' => true,
		],
		[
			'action' => 'Menus:default',
			'icon' => 'bi bi-list',
			'title' => 'Menu',
			'onlyForLoggedIn' => true,
			'params' => [
				'menuKey' => '0',
				'newMenu' => '1',
			],
		],
		[
			'action' => 'News:default',
			'icon' => 'bi bi-calendar2-week',
			'title' => 'Novinky',
			'onlyForLoggedIn' => true,
		],
		[
			'action' => 'Articles:default',
			'icon' => 'bi bi-file-earmark-text',
			'title' => 'Články',
			'onlyForLoggedIn' => true,
		],
		[
			'action' => 'ArticleTemplates:default',
			'icon' => 'bi bi-file-earmark-code',
			'title' => 'Šablony článků',
			'onlyForLoggedIn' => true,
		],
		[
			'action' => 'Galleries:default',
			'icon' => 'bi bi-images',
			'title' => 'Galerie',
			'onlyForLoggedIn' => true,
		],
		[
			'action' => 'UploadManager:default',
			'icon' => 'bi bi-upload',
			'title' => 'Upload',
			'onlyForLoggedIn' => true,
		],
		[
			'action' => 'Configuration:default',
			'icon' => 'bi bi-sliders',
			'title' => 'Nastavení',
			'onlyForLoggedIn' => true,
		],
	];

	public function startup() {
		parent::startup();
		if (!$this->getUser()->isLoggedIn() && !in_array($this->getName(), ['Administration:Dashboard', 'Administration:Auth'])) {
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
		$this->getAppVersion();
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

	public function handleLogout(): void {
		$this->user->logout(true);
		$this->redirect('Dashboard:default');
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

	public function getAppVersion(): void {
		$this->template->appVersion = $this->cache->load('app_version', function (&$dependencies) {
			$dependencies[$this->cache::EXPIRE] = '1 hour';
			return $this->appVersionsRepository->getCurrentVersion();
		});
	}

}
