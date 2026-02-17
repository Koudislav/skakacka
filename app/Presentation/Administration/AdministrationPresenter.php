<?php

declare(strict_types=1);

namespace App\Presentation\Administration;

use App\Forms\BootstrapFormFactory;
use App\Forms\LoginFormFactory;
use App\Repository\ArticleRepository;
use App\Repository\ConfigurationRepository;
use App\Repository\GalleryRepository;
use App\Repository\UserRepository;
use App\Service\ImageService;
use Nette;
use Nette\Application\UI\Form;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Utils\FileSystem;
use Nette\Utils\Finder;
use Nette\Utils\Strings;

final class AdministrationPresenter extends \App\Presentation\BasePresenter {

	/** @var LoginFormFactory @inject */
	public $loginFormFactory;

	/** @var ArticleRepository @inject */
	public $articleRepository;

	/** @var ConfigurationRepository @inject */
	public $configurationRepository;

	/** @var GalleryRepository @inject */
	public $galleryRepository;

	/** @var UserRepository @inject */
	public $userRepository;

	/** @var Storage @inject */
	public Storage $cacheStorage;

	/** @var \App\Service\ImageService @inject */
	public ImageService $imageService;

	// /** @var \App\Repository\CalendarRepository @inject */
	// public \App\Repository\CalendarRepository $calendarRepository;

	// public $calendarYear;
	// public $calendarMonth;

	public const WWW_DIR = __DIR__ . '/../../../www';
	public const UPLOAD_DIR = self::WWW_DIR . '/upload';

	public const MENU = [
		[
			'action' => 'Administration:users',
			'icon' => 'bi bi-people-fill',
			'title' => 'Uživatelé',
			// 'accessRoles' => ['admin', 'superadmin'],
			'onlyForLoggedIn' => true,
		],
		[
			'action' => 'Administration:menus',
			'icon' => 'bi bi-list',
			'title' => 'Menu',
			'onlyForLoggedIn' => true,
			'params' => [
				'menuKey' => '0',
				'newMenu' => '1',
			],
		],
		[
			'action' => 'Administration:articles',
			'icon' => 'bi bi-file-earmark-text',
			'title' => 'Články',
			'onlyForLoggedIn' => true,
		],
		[
			'action' => 'Administration:galleries',
			'icon' => 'bi bi-images',
			'title' => 'Galerie',
			'onlyForLoggedIn' => true,
		],
		[
			'action' => 'Administration:uploadManager',
			'icon' => 'bi bi-images',
			'title' => 'Upload',
			'onlyForLoggedIn' => true,
		],
		[
			'action' => 'Administration:configuration',
			'icon' => 'bi bi-sliders',
			'title' => 'Nastavení',
			'onlyForLoggedIn' => true,
		],
		// [
		// 	'action' => 'Administration:calendarBinary',
		// 	'icon' => 'bi bi-calendar-event',
		// 	'title' => 'Kalendář',
		// 	'onlyForLoggedIn' => true,
		// ],
	];

	public const ARTICLE_TYPES = [
		'article' => 'Běžný článek',
		'news' => 'Novinka',
		'index' => 'Úvodní stránka',
	];

	public const ROLES = [
		'admin' => 'Administrátor',
		'superadmin' => 'Superadministrátor'
	];

	public const MENU_LINK_TYPES = [
		'article' => 'Odkaz na článek',
		'index' => 'Hlavní stránka',
		'gallery' => 'Galerie',
		'parent' => 'Nadřazená položka (bez odkazu)',
	];

	public function startUp() {
		parent::startUp();
		FileSystem::createDir(self::UPLOAD_DIR);
	}

	public function beforeRender() {
		$this->template->menu = $this->processMenu();
		$this->checkConsistency();
	}

	public function actionUsers(int $userId = 0): void {
		if (!$this->user->isLoggedIn()) {
			$this->flashMessage('Nemáte oprávnění pro přístup na tuto stránku.', 'danger');
			$this->redirect('Administration:default');
		}
		if (!$this->user->isInRole('superadmin') && $this->user->getId() !== $userId && $userId !== 0) {
			$this->flashMessage('Nemáte oprávnění upravovat jiného uživatele.', 'danger');
			$this->redirect('Administration:Users');
		}
		if (!$this->user->isInRole('superadmin') && $userId === 0) {
			$this->redirect('Administration:Users', ['userId' => $this->user->getId()]);
		}
	}

	public function renderUsers(int $userId = 0): void {
		$this->template->currentUserId = $userId;
		$users = $this->userRepository->findAll();
		$this->template->users = $users;
	}

	public function actionArticles(int $articleId = 0): void {
		if (!$this->user->isLoggedIn()) {
			$this->flashMessage('Pro přístup na tuto stránku se musíte přihlásit.', 'danger');
			$this->redirect('Administration:default');
		}
	}

	public function renderArticles(int $articleId = 0): void {
		$this->template->currentArticleId = $articleId;
		$data = $this->articleRepository->findAll();

		$indexes = [];
		$articles = [];

		foreach ($data as $key => $article) {
			if ($article->type === 'index') {
				$indexes[$key] = $article;
			} else {
				$articles[$key] = $article;
			}
		}
		$this->template->articleName = $data[$articleId]->title ?? null;
		$this->template->menus = $indexes + $articles;
	}

	public function actionMenus(?string $menuKey, ?string $newMenu, ?int $menuId): void {
		if (!$this->user->isLoggedIn()) {
			$this->flashMessage('Nemáte oprávnění.', 'danger');
			$this->redirect('Administration:default');
		}
	}

	public function renderMenus(?string $menuKey, ?string $newMenu, ?int $menuId): void {
		$menuKey = $this->getParameter('menuKey');
		$this->template->currentMenuKey = $menuKey;
		$this->template->currentMenuId = $menuId;
		if ($menuKey === '0') {
			$this->template->menus = $this->menuRepository->findKeys();
		} else {
			$this->template->menus = $this->menuRepository->findByKeyStructured($menuKey);
		}
	}

	public function actionGalleries(int $galleryId = 0): void {
		if (!$this->getUser()->isLoggedIn()) {
			$this->flashMessage('Nemáte oprávnění.', 'danger');
			$this->redirect('Administration:default');
		}
	}

	public function actionUploadManager(?string $folder = null) {
		if ($folder === '/') {
			$folder = null;
		}
		if (!$this->getUser()->isLoggedIn()) {
			$this->flashMessage('Nemáte oprávnění.', 'danger');
			$this->redirect('Administration:default');
		}
		$wwwDir = realpath(self::WWW_DIR);
		$uploadDir = realpath(self::UPLOAD_DIR) . DIRECTORY_SEPARATOR;
		$currentFolder = realpath($uploadDir . $folder) . DIRECTORY_SEPARATOR;

		if (!str_starts_with($currentFolder, $uploadDir)) {
			$this->flashMessage('Neplatná cesta ke složce.', 'danger');
			$this->redirect('Administration:uploadManager', ['folder' => null]);
		} else {
			$relativePath = str_replace($wwwDir, '', rtrim($currentFolder, DIRECTORY_SEPARATOR));
			$this->template->relativePath = $relativePath;
		}
		$dirs = Finder::findDirectories()
			->in($currentFolder);

		$dirsProcessed = [];
		foreach ($dirs as $dir) {
			$dirsProcessed[] = [
				'relativePath' => str_replace(realpath(self::UPLOAD_DIR), '', rtrim($dir->getRealPath(), DIRECTORY_SEPARATOR)),
				'name' => $dir->getBasename(),
			];
		}

		$files = Finder::findFiles()
			->in($currentFolder);

		$filesProcessed = [];
		foreach ($files as $file) {
			$ext = strtolower($file->getExtension());
			$relative = str_replace(realpath(self::WWW_DIR), '', $file->getRealPath());
		
			$filesProcessed[] = [
				'relativePath' => $relative,
				'publicPath' => $relative, // předpoklad: upload je pod www
				'name' => $file->getBasename(),
				'extension' => $ext,
				'isImage' => in_array($ext, ['jpg','jpeg','png','gif','webp','svg']),
			];
		}

		if ($folder !== null) {
			$parentDir = dirname(rtrim($folder, '/\\'));
			if ($parentDir === '.' || $parentDir === DIRECTORY_SEPARATOR) {
				$parentDir = null;
			}
		}
		$this->template->parentDir = $parentDir ?? null;

		$this->template->dirs = $dirsProcessed;
		$this->template->files = $filesProcessed;
	}

	public function renderGalleries(int $galleryId = 0): void {
		$this->template->currentGalleryId = $galleryId;
		$galleries = $this->galleryRepository->findAllGalleries();
		$this->template->galleries = $galleries;
	}

	public function actionConfiguration(?string $category = null): void {
		if (!$this->user->isLoggedIn()) {
			$this->flashMessage('Nemáte oprávnění.', 'danger');
			$this->redirect('Administration:default');
		}

		$categories = $this->configurationRepository->getCategories();
		$this->template->categories = $categories;

		if ($category === null) {
			$category = array_key_first($categories);
			$this->redirect('this', ['category' => $category]);
		}

		$this->template->currentCategory = $category;
		$this->template->items = $this->configurationRepository->getByCategory($category);
	}

	// public function actionCalendarBinary(?int $year = null, ?int $month = null): void {
	// 	if (!$this->user->isLoggedIn()) {
	// 		$this->flashMessage('Nemáte oprávnění.', 'danger');
	// 		$this->redirect('Administration:default');
	// 	}

	// 	$now = new \DateTimeImmutable();

	// 	$this->calendarYear = $year ?? (int) $now->format('Y');
	// 	$this->calendarMonth = $month ?? (int) $now->format('n');
	// }

	// public function renderCalendarBinary(): void {
	// 	$from = (new \DateTimeImmutable("{$this->calendarYear}-{$this->calendarMonth}-01"))
	// 		->modify('first day of this month')
	// 		->setTime(0, 0);

	// 	$to = $from
	// 		->modify('last day of this month')
	// 		->setTime(23, 59, 59);

	// 	$this->template->year = $this->calendarYear;
	// 	$this->template->month = $this->calendarMonth;

	// 	$this->template->binaryDays = $this->calendarRepository
	// 		->getBinaryData($from, $to);
	// }

	//components
	protected function createComponentLoginForm() {
		return $this->loginFormFactory->create([$this, 'loginFormSubmitted']);
	}

	//Forms

	public function createComponentConfigurationForm(): Form {
		$form = BootstrapFormFactory::create('oneLine');

		$category = $this->getParameter('category');
		$items = $this->configurationRepository->getByCategory($category);

		foreach ($items as $item) {

			// label se jen zobrazí
			if ($item->type === 'label') {
				$form->addGroup($item->value_string);
				continue;
			}

			// kontrola role
			if ($item->access_role && !$this->user->isInRole($item->access_role)) {
				continue;
			}

			$label = $item->description ?? $item->key;

			$control = match ($item->type) {
				'bool' => $form->addCheckbox($item->key, $label)
					->setDefaultValue((bool) $item->value_bool),

				'int' => $form->addInteger($item->key, $label)
					->setDefaultValue($item->value_int),

				'float' => $form->addText($item->key, $label)
					->setDefaultValue($item->value_float),

				default => $form->addText($item->key, $label)
					->setDefaultValue($item->value_string),
			};
			$control->setHtmlAttribute('title', $item->key);
		}

		$form->addSubmit('save', 'Uložit')
			->setHtmlAttribute('class', 'btn btn-primary');

		$form->onSuccess[] = [$this, 'configurationFormSubmitted'];
		return $form;
	}

	public function createComponentUserForm() {
		$form = BootstrapFormFactory::create('oneLine');
		$userId = (int) $this->getParameter('userId');
		$loggedUserId = (int) $this->getUser()->getId();
		$isSelfEdit = $userId !== 0 && $userId === $loggedUserId;
		$isSuperAdmin = $this->getUser()->isInRole('superadmin');

		$form->addText('email', 'E-mail:')
			->setRequired('Zadejte e-mail uživatele.')
			->addRule($form::Email, 'Zadejte platnou e-mailovou adresu.');

		if ($isSelfEdit) {
			$form->addPassword('oldPassword', 'Současné heslo:')
				->setRequired('Pro změnu hesla zadejte současné heslo.');
		}

		$password = $form->addPassword('password', 'Zadejte nové heslo:');
		$passwordConfirm = $form->addPassword('passwordConfirm', 'Zadejte nové heslo pro kontrolu:');

		$passwordConfirm->addRule(
			$form::Equal,
			'Hesla se neshodují.',
			$password
		);

		$role = $form->addSelect('role', 'Vyberte roli:', self::ROLES)
			->setDefaultValue('admin')
			->setRequired('Vyberte roli.');

		if (!$isSuperAdmin) {
			$role->setDisabled();
		}

		$active = $form->addCheckbox('is_active', 'Aktivní')
			->setDefaultValue(true);

		if ($isSelfEdit) {
			$active->setDisabled();
		}

		$form->addSubmit('submit', 'Uložit')
			->setHtmlAttribute('class', 'btn btn-primary');

		if ($userId !== 0) {
			$userData = $this->userRepository->getUserById($userId);
			$form->setDefaults([
				'email' => $userData->email,
				'role' => $userData->role,
				'is_active' => $userData->is_active == 1,
			]);
		}

		if ($userId === 0) {
			$password->setRequired('Zadejte heslo uživatele.');
			$passwordConfirm->setRequired('Zadejte heslo uživatele pro kontrolu.');
			if (!$this->user->isInRole('superadmin')) {
				$this->disableForm($form);
			}
		}

		$form->onSuccess[] = [$this, 'userFormSubmitted'];

		return $form;
	}

	public function createComponentArticleForm() {
		$form = BootstrapFormFactory::create('oneLine');
		$articleId = (int) $this->getParameter('articleId');

		$form->addSelect('type', 'Typ článku:', self::ARTICLE_TYPES)
			->setDefaultValue('article');

		$form->addText('title', 'Nadpis:')
			->setRequired('Zadejte nadpis článku.');

		$form->addCheckbox('show_title', 'Nadpis ve stránce')
			->setDefaultValue(false);

		$form->addText('slug', 'Slug (jenom malá písmena, čísla, pomlčky):')
			->addRule($form::Pattern, 'Zadejte platný slug (malá písmena, čísla, pomlčky).', '^[a-z0-9\-]+$');

		$form->addText('seo_title', 'SEO titulek:')
			->setHtmlAttribute('placeholder', 'Ponechte prázdné pro použití nadpisu jako SEO titulku.');
		
		$form->addText('seo_description', 'SEO popis:')
			->setHtmlAttribute('placeholder', 'Ponechte prázdné pro použití SEO description ze sekce NASTAVENÍ.');

		$form->addTextArea('content', 'Obsah:')
			->setHtmlAttribute('rows', 10)
			->setHtmlAttribute('class', 'tiny-editor');

		$form->addCheckbox('is_published', 'Publikováno')
			->setDefaultValue(false);

		$form->addSubmit('submit', 'Uložit')
			->setHtmlAttribute('class', 'btn btn-primary');

		if ($articleId !== 0) {
			$articleData = $this->articleRepository->getArticleById($articleId);
			$form->setDefaults([
				'type' => $articleData->type,
				'title' => $articleData->title,
				'content' => $articleData->content,
				'slug' => $articleData->slug,
				'show_title' => $articleData->show_title == 1,
				'is_published' => $articleData->is_published == 1,
				'seo_title' => $articleData->seo_title,
				'seo_description' => $articleData->seo_description,
			]);
		}

		$form->onSuccess[] = [$this, 'articleFormSubmitted'];

		return $form;
	}

	public function createComponentMenuForm() {
		$form = BootstrapFormFactory::create('oneLine');
		$menuKey = (string) $this->getParameter('menuKey');
		$menuId = (int) $this->getParameter('menuId');
		$menuKeyInput = $form->addText('menu_key', 'Název menu:');

		if ($menuKey !== '0') {
			$menuKeyInput->setDisabled()->setOmitted(false)->setDefaultValue($menuKey);
		} else {
			$menuKeyInput->setRequired('Zadejte název menu.');
		}

		$parents = $this->menuRepository->getRootItemsForSelect($menuKey);

		$form->addSelect('parent_id', 'Nadřazená položka:', $parents)
			->setPrompt('— hlavní položka —');

		$form->addText('label', 'Popisek položky:')
			->setRequired('Zadejte popisek položky menu.');

		$linkType = $form->addSelect('linkType', 'Typ odkazu:', self::MENU_LINK_TYPES)->setDefaultValue('article');

		$linkType->addCondition($form::Equal, 'article')
			->toggle('#linkedArticleSlug-pair-container');
		$linkType->addCondition($form::Equal, 'gallery')
			->toggle('#galleryId-pair-container');

		$linkedArticleSlug = $form->addSelect('linkedArticleSlug', 'Propojit s článkem:', $this->articleRepository->getArticleListForSelect())
			->setPrompt('Žádný článek');

		$linkedArticleSlug->setOption('container-id', 'linkedArticleSlug-pair-container');
		$linkedArticleSlug->addConditionOn($linkType, $form::Equal, 'article')
			->setRequired('Vyberte článek, na který má položka menu odkazovat.');

		$galleryId = $form->addSelect('galleryId', 'Propojit s galerií:', ['default' => 'Výpis všech galerií'] + $this->galleryRepository->getGalleryListForSelect(true))
			->setPrompt('Vyberte galerii');

		$galleryId->setOption('container-id', 'galleryId-pair-container');
		$galleryId->addConditionOn($linkType, $form::Equal, 'gallery')
			->setRequired('Vyberte galerii, na kterou má položka menu odkazovat.');

		$form->addCheckbox('is_active', 'Aktivní')
			->setDefaultValue(true);

		if ($menuKey !== '0' && !empty($menuId)) {
			$menuItem = $this->menuRepository->getById($menuId, $menuKey);
			$form->setDefaults([
				'label' => $menuItem['db']->label,
				'is_active' => $menuItem['db']->is_active == 1,
				'linkType' => $menuItem['processed']['linkType'],
				'linkedArticleSlug' => $menuItem['processed']['linkedArticleSlug'],
				'parent_id' => $menuItem['db']->parent_id,
			]);
		}

		$form->addSubmit('submit', 'Uložit')
			->setHtmlAttribute('class', 'btn btn-primary');

		$form->onSuccess[] = [$this, 'menuFormSubmitted'];

		return $form;
	}

	public function createComponentGalleryForm() {
		$form = BootstrapFormFactory::create('oneLine');
		$form->addText('title', 'Název galerie:')
			->setRequired('Zadejte název galerie.');
		$form->addTextArea('description', 'Popis galerie:')
			->setHtmlAttribute('class', 'tiny-editor');

		$form->addCheckbox('is_published', 'Publikováno')
			->setDefaultValue(false);
		$form->addSubmit('submit', 'Uložit')
			->setHtmlAttribute('class', 'btn btn-primary');

		if ((int) $this->getParameter('galleryId') !== 0) {
			$galleryData = $this->galleryRepository->getGalleryById((int) $this->getParameter('galleryId'));
			$form->setDefaults([
				'title' => $galleryData->title,
				'description' => $galleryData->description,
				'is_published' => $galleryData->is_published == 1,
			]);
		}

		$form->onSuccess[] = [$this, 'galleryFormSubmitted'];
		return $form;
	}

	public function createComponentUploadPhotosForm(): Form {
		$form = BootstrapFormFactory::create('oneLine');
		$form->addMultiUpload('photos', 'Nahrát fotografie:')
			->setHtmlId('photos-upload-input')
			->setHtmlAttribute('accept', '.jpg,.jpeg,.png,.webp')
			->setRequired('Vyberte alespoň jeden obrázek k nahrání.')
			->addRule(Form::Image, 'Pouze soubory typu JPEG, PNG nebo WebP jsou povoleny.');
		return $form;
	}

	public function createComponentCreateFolderForm(): Form {
		$form = BootstrapFormFactory::create('inLine');
		$form->addHidden('folder')
			->setDefaultValue($this->getParameter('folder') ?? '');
		$form->addText('folderName', 'Název nové složky:')
			->setHtmlAttribute('placeholder', 'Název nové složky')
			->setRequired('Vyplňte název složky.');
		$form->addSubmit('submit', 'Vytvořit novou složku')
			->setHtmlAttribute('class', 'btn btn-primary');
		$form->onSuccess[] = [$this, 'createFolderFormSubmitted'];
		return $form;
	}

	public function createComponentUploadForm(): Form {
		$form = BootstrapFormFactory::create('inLine');
		$form->addHidden('folder')
			->setDefaultValue($this->getParameter('folder') ?? '');
		$form->addGroup();
		$form->addUpload('file', 'Vyberte soubor k nahrání:')
			->setRequired('Vyberte soubor k nahrání.');
		$form->addGroup();
		$form->addSubmit('submit', 'Nahrát soubor')
			->setHtmlAttribute('class', 'btn btn-primary');
		$form->onSuccess[] = [$this, 'uploadFormSubmitted'];
		return $form;
	}

	//Form manipulation
	public function configurationFormSubmitted(Form $form, \stdClass $values): void {
		foreach ($values as $key => $value) {
			if ($key === 'save') {
				continue;
			}
			$this->configurationRepository->updateValue(
				$key,
				$value,
				$this->user->getId()
			);
		}
		$cache = new Cache($this->cacheStorage);
		$cache->remove('app_config');

		$this->flashMessage('Nastavení bylo uloženo.', 'success');
		$this->redirect('this');
	}

	public function userFormSubmitted(Form $form, $values): void {
		if (!$this->user->isLoggedIn()) {
			$form->addError('Pro tuto akci musíte být přihlášeni.');
			return;
		}
		$userId = (int) $this->getParameter('userId');
		$loggedUserId = (int) $this->getUser()->getId();
		$isSelfEdit = $userId !== 0 && $userId === $loggedUserId;
		$isSuperAdmin = $this->getUser()->isInRole('superadmin');

		// EDITACE
		if ($userId !== 0) {

			// změna hesla
			if ($values->password !== '') {

				// editace sebe sama → kontrola starého hesla
				if ($isSelfEdit) {
					if (!$this->userRepository->verifyPasswordById($userId, $values->oldPassword)) {
						$form->addError('Zadané současné heslo není správné.');
						return;
					}
				}
				// editace cizího uživatele → jen superadmin
				elseif (!$isSuperAdmin) {
					$form->addError('Nemáte oprávnění měnit heslo jinému uživateli.');
					return;
				}

				$this->userRepository->setPassword($userId, $values->password);
			}

			// další update (email, role, aktivní)
			$this->userRepository->updateUser($userId, $values);
			$this->flashMessage('Uživatel byl úspěšně upraven.', 'success');
		}

		// NOVÝ UŽIVATEL
		else {
			$this->userRepository->createUser($values);
			$this->flashMessage('Uživatel byl úspěšně vytvořen.', 'success');
		}

		$this->redirect('this');
	}

	public function articleFormSubmitted(Form $form, $values): void {
		$articleId = (int) $this->getParameter('articleId');
		$cache = new Cache($this->cacheStorage);

		if ($articleId !== 0) {
			//edit
			$update = $this->articleRepository->updateArticle($articleId, $values, $this->getUser()->getId());
			if (!$update) {
				$this->flashMessage('Nebyly provedeny žádné změny.', 'danger');
			} else {
				$this->flashMessage('Článek byl úspěšně upraven.', 'success');
			}
			$cache->remove(ArticleRepository::ALL_ARTICLE_SLUGS_CACHE_KEY);
			$cache->clean([Cache::Tags => ['articleAssets']]);
			$this->redirect('this');
		} else {
			//novy
			$create = $this->articleRepository->createArticle($values, $this->user->getId());
			foreach ($create['messages'] as $message) {
				foreach ($message as $type => $msg) {
					$this->flashMessage($msg, $type);
				}
			}
			$cache->remove(ArticleRepository::ALL_ARTICLE_SLUGS_CACHE_KEY);
			$this->redirect('Administration:articles', ['articleId' => $create['articleId']]);
		}
	}

	public function menuFormSubmitted(Form $form, \stdClass $values): void {
		$menuKey = (string) $this->getParameter('menuKey');
		$newMenu = (string) $this->getParameter('newMenu');
		$menuId = (int) $this->getParameter('menuId');

		if ($menuKey !== '0' && $newMenu !== '1') {
			//edit
			$this->menuRepository->updateMenuItem($values, $menuId);
			$this->flashMessage('Položka menu byla úspěšně upravena.', 'success');
			$this->redirect('this');
		} else {
			//novy
			$menuId = $this->menuRepository->createMenuItem($values);
			$this->flashMessage('Položka menu byla úspěšně vytvořena.', 'success');
			$this->redirect('Administration:menus', ['menuKey' => $values->menu_key, 'menuId' => $menuId]);
		}
	}

	public function galleryFormSubmitted(Form $form, \stdClass $values) {
		$galleryId = (int) $this->getParameter('galleryId');

		if ($galleryId !== 0) {
			$update = $this->galleryRepository->updateGallery($galleryId, $values, $this->user->getId());
			if (!$update) {
				$this->flashMessage('Nebyly provedeny žádné změny.', 'danger');
			} else {
				$this->flashMessage('Galerie byla úspěšně upravena.', 'success');
			}
			$this->redirect('this');
		} else {
			$create = $this->galleryRepository->createGallery($values, $this->user->getId());
			$this->flashMessage('Galerie byla úspěšně vytvořena.', 'success');
			$this->redirect('Administration:galleries', ['galleryId' => $create->id]);
		}
	}

	public function actionGalleryImages(int $galleryId): void {
		if (!$this->user->isLoggedIn()) {
			$this->flashMessage('Nemáte oprávnění.', 'danger');
			$this->redirect('Administration:default');
		}
	}

	public function createFolderFormSubmitted(Form $form, \stdClass $values): void {
		if (!$this->getUser()->isLoggedIn()) {
			$this->flashMessage('Nemáte oprávnění.', 'danger');
			$this->redirect('Administration:default');
		}

		$uploadDir = realpath(self::UPLOAD_DIR) . DIRECTORY_SEPARATOR;
		$currentFolder = $values->folder ? realpath($uploadDir . $values->folder) : $uploadDir;

		// Webalize název složky
		$nameWeb = Strings::webalize($values->folderName);

		$newFolder = $currentFolder . DIRECTORY_SEPARATOR . $nameWeb;
		if (!is_dir($newFolder)) {
			FileSystem::createDir($newFolder, 0755);
			$this->flashMessage('Složka vytvořena.', 'success');
		} else {
			$this->flashMessage('Složka již existuje.', 'warning');
		}

		$this->redirect('this', ['folder' => $values->folder]);
	}

	public function uploadFormSubmitted(Form $form, array $values): void {
		$uploadDir = realpath(self::UPLOAD_DIR) . DIRECTORY_SEPARATOR;
		$currentFolder = $values['folder'] ? realpath($uploadDir . $values['folder']) : $uploadDir;

		$file = $values['file'];

		if ($file->isOk()) {
			$name = $file->getName();
			$ext = pathinfo($name, PATHINFO_EXTENSION);
			$baseName = pathinfo($name, PATHINFO_FILENAME);

			// Webalize jen hlavní část názvu, přípona zůstane
			$safeName = Strings::webalize($baseName) . ($ext ? '.' . $ext : '');

			$filePath = $currentFolder . DIRECTORY_SEPARATOR . $safeName;
			$file->move($filePath);

			$this->flashMessage('Soubor nahrán.', 'success');
		} else {
			$this->flashMessage('Chyba při nahrávání souboru.', 'danger');
		}

		$this->redirect('this', ['folder' => $values['folder']]);
	}

	public function renderGalleryImages(int $galleryId): void {
		$this->template->currentGalleryId = $galleryId;
		$galleryData = $this->galleryRepository->getGalleryById($galleryId);

		if (!$galleryData) {
			$this->flashMessage('Zvolená galerie neexistuje.', 'danger');
			$this->redirect('Administration:galleries');
		}
		$this->template->gallery = $galleryData;
		$images = $this->galleryRepository->findPicturesByGalleryId($galleryId);

		if ($images) {
			$coverNotSet = true;
			foreach ($images as $image) {
				$firstId = $firstId ?? $image->id;
				if ($image->is_cover) {
					$coverNotSet = false;
					break;
				}
			}
			if ($coverNotSet) {
				$this->galleryRepository->setGalleryCover($firstId);
				$images = $this->galleryRepository->findPicturesByGalleryId($galleryId);
			}
		}

		$this->template->images = $images;
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

	//handlers
	public function handleLogout(): void {
		$this->user->logout(true);
		$this->redirect('Administration:default');
	}

	public function actionUploadPhoto(): void {	
		$file = $this->getHttpRequest()->getFile('photo');
		$galleryId = (int) $this->getHttpRequest()->getPost('galleryId');

		$gallery = $this->galleryRepository->getGalleryById($galleryId);

		if (!$gallery) {
			$this->sendJson(['status' => 'error']);
			return;
		}

		if (!$file || !$file->isOk() || !$file->isImage()) {
			$this->sendJson(['status' => 'error']);
			return;
		}

		// === TEMP FILE ===
		$tempPath = $file->getTemporaryFile();
		$originalName = $file->getUntrustedName();
		$originalName = mb_substr($originalName, 0, 255);

		// === DB INSERT (new) ===
		$photoId = $this->galleryRepository->insertPhoto([
			'gallery_id' => $galleryId,
			'original_name' => $originalName,
			'processed' => 'processing',
			'created_at' => new \DateTime(),
			'created_by' => (int) $this->user->getId(),
		]);

		try {
			$paths = $this->imageService->processUpload(
				$tempPath,
				$galleryId,
				$originalName
			);

			$this->galleryRepository->updatePhoto($photoId, array_merge($paths, [
				'processed' => 'done',
			]));

			$status = 'ok';
		}
		catch (\Throwable $e) {
			$this->galleryRepository->updatePhoto($photoId, [
				'processed' => 'error',
			]);
			$this->flashMessage('Nastala chyba při zpracování obrázku: ' . $e->getMessage(), 'danger');

			\Tracy\Debugger::log($e, 'image');

			$status = 'error';
		}
		$this->sendJson(['status' => $status]);
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
			$this->flashMessage('Varování: Není nastaven žádný článek jako úvodní stránka. V administraci vytvořte nový článek a nastavte jeho typ na "Úvodní stránka" + publikováno.', 'danger');
		}
		if (count($indexArticles) > 1) {
			$this->flashMessage('Varování: Je nastaveno více než jeden článek jako úvodní stránka. V administraci upravte články a nastavte pouze jeden z nich jako "Úvodní stránka" + publikováno.', 'danger');
		}
	}

	public function handleToggleImageVisibility(?int $imageId): void {
		if (!$imageId) {
			$this->sendJson(['status' => 'error']);
			return;
		}
		$this->galleryRepository->toggleImageVisibility($imageId);
		$this->sendJson(['status' => 'ok']);
	}

	public function handleDeleteImage(?int $imageId): void {
		if (!$imageId) {
			$this->sendJson(['status' => 'error']);
			return;
		}

		$image = $this->galleryRepository->getImageById($imageId);
	
		if (!$image) {
			$this->sendJson(['status' => 'error']);
			return;
		}

		// 1) smaž soubory z filesystemu
		$this->imageService->deleteImageFiles([
			'path_original' => $image->path_original,
			'path_big' => $image->path_big,
			'path_medium' => $image->path_medium,
			'path_small' => $image->path_small,
		]);

		// 2) smaž z DB
		$this->galleryRepository->deleteImage($imageId);

		$this->sendJson(['status' => 'ok']);
	}

	public function handleUpdateImageDescription(?int $imageId, ?string $description): void {
		if (!$imageId || $description === null) {
			$this->sendJson(['status' => 'error']);
			return;
		}
		$this->galleryRepository->updateImageDescription($imageId, $description);
		$this->sendJson(['status' => 'ok']);
	}

	public function handleSetGalleryCover(?int $imageId): void {
		if (!$imageId) {
			$this->sendJson(['status' => 'error']);
			return;
		}
		$this->galleryRepository->setGalleryCover($imageId);
		$this->sendJson(['status' => 'ok']);
	}

	public function handleReorderMenu(): void {
		if (!$this->isAjax()) {
			$this->error('Invalid request');
		}

		$data = json_decode($this->getHttpRequest()->getRawBody(), true);

		if (!isset($data['order']) || !is_array($data['order'])) {
			$this->sendJson(['status' => 'error']);
			return;
		}

		$this->menuRepository->updatePositions($data['order']);

		$this->sendJson(['status' => 'ok']);
	}

	// public function handleToggleDay(string $date): void {
	// 	if (!$this->isAjax()) {
	// 		$this->error('Invalid request');
	// 	}
	
	// 	$dt = new \DateTimeImmutable($date);
	
	// 	$this->calendarRepository->toggleBlockingDay($dt);
	
	// 	if ($this->isAjax()) {
	// 		$this->redrawControl('calendar');
	// 	} else {
	// 		$this->redirect('this');
	// 	}
	// 	$this->sendJson(['status' => 'ok']);
	// }

}
