<?php

declare(strict_types=1);

namespace App\Presentation;

use Nette;
use App\Components\ArticleAsset\ArticleAssetControlFactory;
use App\Model\Config;
use App\Model\LessCompiler;
use App\Model\Seo\SeoData;
use App\Repository\MenuRepository;
use App\Service\SitemapGenerator;
use Nette\Application\UI\Form;
use Nette\Caching\Cache;
use Nette\Caching\Storage;

class BasePresenter extends Nette\Application\UI\Presenter {

	/** @var ArticleAssetControlFactory @inject */
	public ArticleAssetControlFactory $articleAssetControlFactory;

	/** @var Config @inject */
	public $config;

	/** @var LessCompiler @inject */
	public $lessCompiler;

	/** @var MenuRepository @inject */
	public $menuRepository;

	/** @var SitemapGenerator @inject */
	public $sitemapGenerator;

	/** @var Storage @inject */
	public $storage;

	public SeoData $seo;

	public string $homeString = 'home';

	public const MENU_CACHE_KEY = 'navbar_menu';

	public function startUp() {
		parent::startup();
		$cssFile = $this->lessCompiler->getCss('styles.less', true);
		$this->template->cssFile = $cssFile['final'];
		$this->template->config = $this->config;
		$this->template->recaptchaPublic = $this->config['recaptcha_public'];
		// $this->template->actualLink = $this->link('this');
		if (!$this->isAjax() && $this->sitemapGenerator->needsRegeneration()) {
			$this->sitemapGenerator->generate();
		}
		$this->seoData();
	}

	public function beforeRender() {
		parent::beforeRender();
		$this->template->navbarMenu = $this->processNavbarMenu();
	}

	protected function createComponentArticleAsset($string) {
		return $this->articleAssetControlFactory->create();
	}

	public function disableForm(Form $form): void{
		foreach ($form->getControls() as $control) {
			$control->setDisabled();
		}
	}

	private function processNavbarMenu(): array {
		$cache = new Cache($this->storage);

		$menu = $cache->load(self::MENU_CACHE_KEY, function (&$dependencies) {
			$dependencies[Cache::Tags] = [self::MENU_CACHE_KEY];

			$menu = [];
			$menuItems = $this->menuRepository->findByKeyStructured('main_horizontal', true);

			foreach ($menuItems as $item) {
				if (empty($item['item']->presenter)) {
					$children = [];

					foreach ($item['children'] as $child) {
						$children[] = $this->processNavbarMenuItem(['item' => $child]);
					}

					$menu[] = [
						'label' => $item['item']->label,
						'isParent' => true,
						'children' => $children,
						'isActive' => false,
					];
				} else {
					$menu[] = $this->processNavbarMenuItem($item);
				}
			}

			return $menu;
		});

		if ($this->getUser()->isLoggedIn()) {
			$menu[] = [
				'label' => 'Admin',
				'link' => $this->link('Administration:default'),
				'isParent' => false,
				'isActive' => false,
			];
		}

		// 🔥 oživení active stavů
		return $this->markActiveItems($menu);
	}

	public function processNavbarMenuItem(array $item): array {
		$itemParams = $item['item']->params
			? json_decode($item['item']->params, true)
			: [];

		return [
			'label' => $item['item']->label,
			'link' => $this->link(
				$item['item']->presenter . ':' . $item['item']->action,
				$itemParams
			),
			'isParent' => false,
			'isActive' => false,
			'item' => $item['item']->toArray(),
		];
	}

	private function markActiveItems(array $items): array {
		foreach ($items as &$item) {

			if (!empty($item['children'])) {
				$item['children'] = $this->markActiveItems($item['children']);

				$item['isActive'] = false;
				foreach ($item['children'] as $child) {
					if (!empty($child['isActive'])) {
						$item['isActive'] = true;
						break;
					}
				}
				continue;
			}

			if (!empty($item['link']) && !empty($item['item'])) {
				bdump($item);
				$item['isActive'] = (
					$this->isLinkCurrent($item['item']['presenter'] . ':' . $item['item']['action'], $item['item']['params'] ? json_decode($item['item']['params'], true) : [])
					|| ($item['item']['presenter'] === 'Gallery' && $this->getName() === 'Gallery')
				);
			}
		}

		return $items;
	}

	public function seoData(): void {
		$this->seo = new SeoData(
			title: $this->config['seo_default_title'],
			ogTitle: $this->config['seo_default_title_og'] ?: $this->config['seo_default_title'],
			description: $this->config['seo_default_description'],
			ogDescription: $this->config['seo_default_description_og'] ?: $this->config['seo_default_description'],
			ogImage: $this->config['seo_default_og_image'],
			canonical: $this->link('//this', []),
		);
		$this->template->seo = $this->seo;
	}

}
