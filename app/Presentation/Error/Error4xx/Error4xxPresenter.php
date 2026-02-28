<?php

declare(strict_types=1);

namespace App\Presentation\Error\Error4xx;

use App\Components\ArticleAsset\ArticleAssetControlFactory;
use App\Config\Config;
use App\Model\LessCompiler;
use App\Presentation\BasePresenter;
use App\Repository\MenuRepository;
use Nette;
use Nette\Application\Attributes\Requires;
use Nette\Caching\Cache;
use Nette\Caching\Storage;

/**
 * Handles 4xx HTTP error responses.
 */
#[Requires(methods: '*', forward: true)]
final class Error4xxPresenter extends Nette\Application\UI\Presenter {

	/** @var Config @inject */
	public $config;

	/** @var LessCompiler @inject */
	public $lessCompiler;

	/** @var MenuRepository @inject */
	public $menuRepository;

	/** @var Storage @inject */
	public $storage;

	/** @var ArticleAssetControlFactory @inject */
	public ArticleAssetControlFactory $articleAssetControlFactory;

	protected const WWW_PATH = '/../../../../www';

	public function renderDefault(Nette\Application\BadRequestException $exception): void {
		// renders the appropriate error template based on the HTTP status code
		$cssFile = $this->lessCompiler->getCss('styles.less', true);
		$this->template->cssFile = $cssFile['final'];
		$code = $exception->getCode();
		$file = is_file($file = __DIR__ . "/$code.latte")
			? $file
			: __DIR__ . '/4xx.latte';

		$cache = new Cache($this->storage);
		$this->template->navbarMenu = $cache->load(\App\Presentation\BasePresenter::MENU_CACHE_KEY);

		$this->template->config = $this->config;
		$this->template->httpCode = $code;
		$this->basicVariables();
		$this->template->setFile($file);
	}

	protected function createComponentArticleAsset($string) {
		return $this->articleAssetControlFactory->create();
	}

	public function basicVariables(): void {
		$this->template->justifyMap = BasePresenter::JUSTIFY_MAP;
		$this->template->navbarLayout = $this->getNavbarLayout();
		$this->template->colorScheme = $this->getColorScheme();
		$this->template->logoPath = $this->logoPath();
		$this->template->templateSpacing = $this->getTemplateSpacing();
	}

	public function getNavbarLayout(): array {
		$pos = $this->config['template_menu_position'] ?? 'center';
		$justifyClass = BasePresenter::JUSTIFY_MAP[$pos] ?? BasePresenter::JUSTIFY_MAP['center'];
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
		return $scheme;
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

}
