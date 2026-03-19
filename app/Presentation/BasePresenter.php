<?php

declare(strict_types=1);

namespace App\Presentation;

use Nette;
use App\Components\ArticleAsset\ArticleAssetControlFactory;
use App\Config\Config;
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
	public Cache $cache;

	public string $homeString = 'home';

	protected const WWW_PATH = '/../../www';

	public const MENU_CACHE_KEY = 'navbar_menu';

	public const JUSTIFY_MAP = [
		'start' => 'justify-content-start',
		'center' => 'justify-content-center',
		'end' => 'justify-content-end',
		'between' => 'justify-content-between',
		'around' => 'justify-content-around',
	];

	public function startup() {
		parent::startup();
		$this->cache = new Cache($this->storage);
		$cssFile = $this->lessCompiler->getCss('styles.less', true);
		$this->template->cssFile = $cssFile['final'];
		$this->template->config = $this->config;
		$this->template->recaptchaPublic = $this->config['recaptcha_public'];
		// $this->template->actualLink = $this->link('this');
		if (!$this->isAjax() && $this->sitemapGenerator->needsRegeneration()) {
			$this->sitemapGenerator->generate();
		}
		$this->seoData();

		if (!empty($this->config['ga4_id'])) {
			$this->template->ga4Id = $this->config['ga4_id'];
			if (!empty($this->config['ga4_stream_id'])) {
				$this->template->ga4Stream = $this->config['ga4_stream_id'];
			}
		}
	}

	public function beforeRender() {
		parent::beforeRender();
		$this->template->navbarMenu = $this->processNavbarMenu();
		$this->basicVariables();
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
		$menu = $this->cache->load(self::MENU_CACHE_KEY, function (&$dependencies) {
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
				'link' => $this->link('Administration:Dashboard:default'),
				'isParent' => false,
				'isActive' => false,
			];
		}

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

	public function basicVariables(): void {
		$this->template->justifyMap = self::JUSTIFY_MAP;
		$this->template->logoPath = $this->logoPath();
		$this->template->navbarLayout = $this->getNavbarLayout();
		$this->template->colorScheme = $this->getColorScheme();
		$this->template->templateSpacing = $this->getTemplateSpacing();
		$this->template->favicons = $this->favicons();
	}

	public function getNavbarLayout(): array {
		$pos = $this->config['template_menu_position'] ?? 'center';
		$justifyClass = self::JUSTIFY_MAP[$pos] ?? self::JUSTIFY_MAP['center'];
		$isWide = in_array($pos, ['between', 'around'], true);

		return [
			'wrapperClass' => $isWide ? null : $justifyClass,
			'ulClass' => $isWide ? $justifyClass . ' w-100' : null,
			'position' => $pos,
		];
	}

	public function getTemplateSpacing(): array {
		$templateSpacing = ['template_p_content'];
		foreach ($templateSpacing as $configKey) {
			if (!empty($this->config[$configKey])) {
				$spacing[str_replace('template_', '', $configKey)] = $this->config[$configKey];
			}
		}
		return $spacing ?? [];
	}

	public function getColorScheme(): array {
		$templateBg = ['template_bg_content', 'template_bg_navbar', 'template_bg_page', 'template_color_scheme'];
		foreach ($templateBg as $bg) {
			if (!empty($this->config[$bg])) {
				$scheme[str_replace('template_', '', $bg)] = $this->config[$bg];
			}
		}
		return $scheme ?? [];
	}

	public function logoPath(): bool|string {
		$logoPath = $this->config['logo_path'];
		if (!empty($logoPath) && str_starts_with($logoPath, '/')) {
			$basePath = __DIR__ . self::WWW_PATH;
			if (!realpath($basePath . $logoPath)) {
				return false;
			}
		}
		return $logoPath;
	}

	public function favicons(): array {
		$favicons = [];
		if (is_file(__DIR__ . self::WWW_PATH . '/upload/favicon.ico')) {
			$favicons[] = ['path' => '/upload/favicon.ico'];
		} else {
			$favicons[] = ['path' => '/favicon.ico'];
		}
		return $favicons;
	}

}
