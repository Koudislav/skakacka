<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\TemplateRepository;
use Nette\Application\UI\Presenter;
use Latte\Engine;
use Latte\Loaders\StringLoader;
use Tracy\Debugger;

class SpecialCodesParser {
	private array $allowedActions = [
		'gallery::trapezoid',
		'gallery::preview',
		'form::contact',
		'calendar::basic',
		'news::latest',
	];

	public function __construct(
		private Presenter $presenter
		) {}

	/**
	 * Nahradí text komponentami
	 */
	public function parse(string $text): string {
		$placeholders = [];

		// schovej <code> a <pre>
		$text = preg_replace_callback('#<(code|pre)[^>]*>.*?</\1>#si', function ($m) use (&$placeholders) {
			$key = '###CODE_BLOCK_' . count($placeholders) . '###';
			$placeholders[$key] = $m[0];
			return $key;
		}, $text);

		$text = preg_replace_callback('/\[\[@([a-z0-9:_]+)((?:\|[a-z0-9_]+=[^|\]]*)*)\]\]/i', function ($matches) {
			$action = $matches[1];
			$paramsString = $matches[2]; // |id=6|big=Zážitkový|small=Učení
			$params = [];

			if ($paramsString) {
				// odstraní první "|"
				$paramsString = ltrim($paramsString, '|');
			
				// rozdělí na jednotlivé páry
				$pairs = explode('|', $paramsString);
			
				foreach ($pairs as $pair) {
					[$key, $value] = explode('=', $pair, 2) + [null, null];
					if ($key !== null) {
						$params[$key] = html_entity_decode($value); // dekóduj entity
					}
				}
			}

			if (!in_array($action, $this->allowedActions)) {
				return $matches[0]; // ponech původní text
			}

			try {
				$fn = $this->resolveFunctionName($action);
				return $this->$fn($params);
			} catch (\Throwable $e) {
				Debugger::log("Chyba při renderování komponenty pro akci '$action': " . $e->getMessage(), Debugger::ERROR);
				return $matches[0]; // ponech původní text
			}
		}, $text);

		return strtr($text, $placeholders);
	}

	private function resolveFunctionName(string $action): string {
		$parts = explode('::', $action, 2);
		return 'render' . ucfirst($parts[0]) . ucfirst($parts[1]);
	}

	public function renderGalleryTrapezoid(array $params): string {
		$id = (int)$params['id'];
		$count = $params['count'] ?? 5;
		$photos = $this->presenter->galleryRepository->findPicturesByGalleryId($id);
		$latte = new Engine();

		// Vytvoř komponentu a renderuj do stringu
		$html = $latte->renderToString(
			__DIR__ . '/../Components/GalleryControl/trapezoid.latte',
			[
				'photos' => array_slice($photos, 0, (int)$count),
				'galleryId' => $id,
				'allPhotos' => $photos,
				'top' => $params['top'] ?? null,
				'second' => $params['second'] ?? null,
				'toLeft' => $params['toLeft'] ?? null,
				'height' => $params['height'] ?? null,
			]
		);

		return $html;
	}

	public function renderGalleryPreview($params) {
		$id = (int)$params['id'];
		$count = $params['count'] ?? 5;
		$photos = $this->presenter->galleryRepository->findPicturesByGalleryId($id);
		$latte = new Engine();

		// Vytvoř komponentu a renderuj do stringu
		$html = $latte->renderToString(
			__DIR__ . '/../Components/GalleryControl/basic.latte',
			[
				'photos' => array_slice($photos, 0, (int)$count),
				'galleryId' => $id,
				'allPhotos' => $photos,
				'height' => $params['height'] ?? null,
			]
		);

		return $html;
	}

	public function renderFormContact(array $params): string {
		return $this->presenter->renderContactFormSnippet();
	}

	public function renderCalendarBasic(array $params): string {
		return $this->presenter->renderCalendarSnippet();
	}

	public function renderNewsLatest(array $params): string {
		$count = !empty($params['count']) ? (int)$params['count'] : 3;
		$excerptLength = (!empty($params['perexLength']) && is_numeric($params['perexLength'])) ? (int)$params['perexLength'] : 150;
		$news = $this->presenter->newsRepository->getLatest($count);
		$items = [];
		foreach ($news as $new) {
			$item = $new->toArray();
			$item['link'] = $this->presenter->link('//News:default', ['slug' => $new->slug]);
			if (empty($item['excerpt'])) {
				$item['excerpt'] = strip_tags(html_entity_decode($item['content']));
			}
			if (mb_strlen($item['excerpt']) > $excerptLength + 5) {
				$item['excerpt'] = mb_substr($item['excerpt'], 0, $excerptLength) . '...';
			}
			$items[] = $item;
		}
		if (!empty($params['template'])) {
			try {
				return $this->newsLatestTemplate($items, $params);
			} catch (\Throwable $e) {
				Debugger::log("Chyba při renderování šablony pro news::latest: " . $e->getMessage(), Debugger::ERROR);
			}
		}
		$latte = new Engine();
		return $latte->renderToString(
			__DIR__ . '/../Components/NewsControl/latest.latte',
			[
				'news' => $items,
			]
		);
	}

	public function newsLatestTemplate(array $items, array $params): string {
		$template = $this->presenter->templateRepository->getTemplateById((int)$params['template'])->content;
		$template = $this->holdersToVariables($template, ['content', 'coverImage']);
		$latte = new Engine();
		$latte->setLoader(new StringLoader());
		$stringHtml = '';
		foreach ($items as $item) {
			$stringHtml .= $latte->renderToString($template, [
				'coverImage' => $this->sanitizeImageUrl($item['cover_image']),
				'title' => $item['title'],
				'perex' => $item['excerpt'],
				'content' => $item['content'],
				'link' => $item['link'],
				'publishedAt' => $item['published_at'] instanceof \DateTimeInterface ? $item['published_at']->format('d.m.Y H:i') : null,
			]);
		}
		return $stringHtml;
	}

	public function holdersToVariables(string $template, array $noescaped = []): string {
		return preg_replace_callback(TemplateRepository::VARIABLE_REGEX, function ($matches) use ($noescaped) {
			$variableName = $matches[1];
			return in_array($variableName, $noescaped) ? "{\${$variableName}|noescape}" : "{\${$variableName}}";
		}, $template);
	}

	private function sanitizeImageUrl(?string $url): ?string {
		$holder = '/assets/images/picture-not-found.webp';
		if (!$url) return $holder;
		$url = trim($url);
		if (str_starts_with($url, '/')) return $url;
		if (!filter_var($url, FILTER_VALIDATE_URL)) return $holder;
		$scheme = parse_url($url, PHP_URL_SCHEME);
		if (!in_array(strtolower($scheme), ['http', 'https'], true)) return $holder;
		return $url;
	}

}
