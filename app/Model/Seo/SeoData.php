<?php

declare(strict_types=1);

namespace App\Model\Seo;

final class SeoData {

	public function __construct(
		public string $title = '',
		public string $description = '',
		public string $ogTitle = '',
		public string $ogDescription = '',
		public string $ogImage = '',
		public string $canonical = '',
		public string $schemaType = 'WebPage',
		public array $breadcrumbs = [],
		public ?string $publishedAt = null,
		public ?string $modifiedAt = null,
		public ?string $author = null,
	) {}

	public function generateJsonLd(): array {
		$entities = [];

		switch ($this->schemaType) {
			case 'Article':
				$entities[] = $this->generateArticle();
				break;

			case 'WebSite':
				$entities[] = $this->generateWebSite();
				$entities[] = $this->generateWebPage();
				break;

			default:
				$entities[] = $this->generateWebPage();
		}

		if ($this->breadcrumbs) {
			$entities[] = $this->generateBreadcrumbs();
		}

		return $entities;
	}

	public function generateArticle(): array {
		return [
			'@context' => 'https://schema.org',
			'@type' => 'Article',
			'headline' => $this->title,
			'description' => $this->description,
			'image' => $this->ogImage,
			'mainEntityOfPage' => $this->canonical,
	
			'author' => $this->author ? [
				'@type' => 'Person',
				'name' => $this->author,
			] : null,
	
			'datePublished' => $this->publishedAt,
			'dateModified' => $this->modifiedAt,
		];
	}

	public function generateWebPage(): array {
		return [
			'@context' => 'https://schema.org',
			'@type' => 'WebPage',
			'name' => $this->title,
			'description' => $this->description,
			'url' => $this->canonical,
		];
	}

	private function generateWebSite(): array {
		return [
			'@context' => 'https://schema.org',
			'@type' => 'WebSite',
			'name' => $this->title,
			'url' => $this->canonical,
		];
	}

	private function generateBreadcrumbs(): array {
		$list = [];
		$pos = 1;
	
		foreach ($this->breadcrumbs as $name => $url) {
			$list[] = [
				'@type' => 'ListItem',
				'position' => $pos++,
				'name' => $name,
				'item' => $url,
			];
		}
	
		return [
			'@context' => 'https://schema.org',
			'@type' => 'BreadcrumbList',
			'itemListElement' => $list,
		];
	}

	public function getTitle(): string {
		return $this->title ?: $this->ogTitle;
	}

	public function getDescription(): string {
		return $this->description ?: $this->ogDescription;
	}

	public function getOgTitle(): string {
		return $this->ogTitle ?: $this->title;
	}

	public function getOgDescription(): string {
		return $this->ogDescription ?: $this->description;
	}

	public function getOgImage(): string {
		return $this->ogImage;
	}

	public function getCanonical(): string {
		return $this->canonical;
	}

}
