<?php

declare(strict_types=1);

namespace App\Presentation\Administration;

use App\Forms\BootstrapFormFactory;
use App\Forms\LoginFormFactory;
use App\Repository\ArticleRepository;
use App\Repository\GalleryRepository;
use App\Repository\UserRepository;
use App\Service\ImageService;
use Nette;
use Nette\Application\UI\Form;
use Nette\Caching\Cache;
use Nette\Caching\Storage;

final class AdministrationPresenter extends \App\Presentation\BasePresenter {

	/** @var LoginFormFactory @inject */
	public $loginFormFactory;
	
	/** @var ArticleRepository @inject */
	public $articleRepository;

	/** @var GalleryRepository @inject */
	public $galleryRepository;

	/** @var UserRepository @inject */
	public $userRepository;

	/** @var Storage @inject */
	public Storage $cacheStorage;

	/** @var \App\Service\ImageService @inject */
	public ImageService $imageService;

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
	];

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
			$this->template->menus = $this->menuRepository->findByKey($menuKey);
		}
	}

	public function actionGalleries(int $galleryId = 0): void {
		if (!$this->user->isLoggedIn()) {
			$this->flashMessage('Nemáte oprávnění.', 'danger');
			$this->redirect('Administration:default');
		}
	}

	public function renderGalleries(int $galleryId = 0): void {
		$this->template->currentGalleryId = $galleryId;
		$galleries = $this->galleryRepository->findAllGalleries();
		$this->template->galleries = $galleries;
	}

	//components
	protected function createComponentLoginForm() {
		return $this->loginFormFactory->create([$this, 'loginFormSubmitted']);
	}

	//Forms
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

		$form->addText('label', 'Popisek položky:')
			->setRequired('Zadejte popisek položky menu.');

		$linkType = $form->addSelect('linkType', 'Typ odkazu:', self::MENU_LINK_TYPES)->setDefaultValue('article');

		$linkType->addCondition($form::Equal, 'article')
			->toggle('#linkedArticleSlug-pair-container');

		$linkedArticleSlug = $form->addSelect('linkedArticleSlug', 'Propojit s článkem:', $this->articleRepository->getArticleListForSelect())
			->setPrompt('Žádný článek');

		$linkedArticleSlug->setOption('container-id', 'linkedArticleSlug-pair-container');
		$linkedArticleSlug->addConditionOn($linkType, $form::Equal, 'article')
			->setRequired('Vyberte článek, na který má položka menu odkazovat.');

		$form->addCheckbox('is_active', 'Aktivní')
			->setDefaultValue(true);

		if ($menuKey !== '0' && !empty($menuId)) {
			$menuItem = $this->menuRepository->getById($menuId, $menuKey);
			$form->setDefaults([
				'label' => $menuItem['db']->label,
				'is_active' => $menuItem['db']->is_active == 1,
				'linkType' => $menuItem['processed']['linkType'],
				'linkedArticleSlug' => $menuItem['processed']['linkedArticleSlug'],
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

	//Form manipulation
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
			$update = $this->articleRepository->updateArticle($articleId, $values, $this->user->getId());
			if (!$update) {
				$this->flashMessage('Nebyly provedeny žádné změny.', 'danger');
			} else {
				$this->flashMessage('Článek byl úspěšně upraven.', 'success');
			}
			$cache->remove(ArticleRepository::ALL_ARTICLE_SLUGS_CACHE_KEY);
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

}
