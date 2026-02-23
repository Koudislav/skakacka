<?php

declare(strict_types=1);

namespace App\Presentation\Error\Error4xx;

use App\Components\ArticleAsset\ArticleAssetControlFactory;
use App\Model\Config;
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

	public function getColorScheme(): array {
		$templateBg = ['template_bg_content', 'template_bg_navbar', 'template_bg_page', 'template_color_scheme'];
		foreach ($templateBg as $bg) {
			if (!empty($this->config[$bg])) {
				$scheme[str_replace('template_', '', $bg)] = $this->config[$bg];
			}
		}
		return $scheme;
	}

}
