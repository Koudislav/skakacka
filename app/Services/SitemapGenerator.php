<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Config;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Utils\FileSystem;

class SitemapGenerator {

	public const MAX_AGE = 24 * 60 * 60;
	public string $xmlPath;
	public Cache $cache;

	public function __construct(
		private string $wwwDir,
		private Explorer $db,
		private Storage $storage,
		private Config $config,
	) {
		$this->xmlPath = $this->wwwDir . '/sitemap.xml';
		$this->cache = new Cache($this->storage);
	}

	public function needsRegeneration(): bool {
		if (!file_exists($this->xmlPath)) {
			return true;
		}

		$lastModified = filemtime($this->xmlPath);
		if ($lastModified === false) {
			return true;
		}

		return (time() - $lastModified) > self::MAX_AGE;
	}

	public function generate(): void {
		$xml = $this->buildXml();
		FileSystem::write($this->xmlPath, $xml);
	}

	private function buildXml(): string {
		$urls = $this->getUrls();
		$xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset/>');
		$xml->addAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

		foreach ($urls as $url) {
			$urlNode = $xml->addChild('url');
			$urlNode->addChild('loc', $url['loc']);

			if (!empty($url['lastmod'])) {
				$urlNode->addChild('lastmod', $url['lastmod']);
			}
			if (!empty($url['changefreq'])) {
				$urlNode->addChild('changefreq', $url['changefreq']);
			}
			if (!empty($url['priority'])) {
				$urlNode->addChild('priority', $url['priority']);
			}
		}
		return $xml->asXML();
	}


	private function getUrls(): array {
		$baseUrl = $this->config['base_url'];
		$urls = [
			[
				'loc' => $baseUrl,
				'changefreq' => 'daily',
				'priority' => '1.0',
			],
			[
				'loc' => $baseUrl . 'gallery',
				'changefreq' => 'weekly',
				'priority' => '0.8',
			],
		];
		$urls = array_merge($urls, $this->getArticleUrls($baseUrl), $this->getGalleryUrls($baseUrl));

		return $urls;
	}

	public function getArticleUrls(string $baseUrl): array {
		$slugs = $this->cache->load(\App\Repository\ArticleRepository::ALL_ARTICLE_SLUGS_CACHE_KEY);
		$updated = $this->db->table(\App\Repository\ArticleRepository::ARTICLES_TABLE)
			->select('slug, updated_at')
			->where('slug IN ?', $slugs)
			->where('type', 'article')
			->fetchPairs('slug', 'updated_at');

		$urls = [];
		foreach ($slugs as $slug) {
			if (!isset($updated[$slug])) {
				continue;
			}
			$urls[] = [
				'loc' => $baseUrl . $slug,
				'lastmod' => $updated[$slug]->format('c'),
				'changefreq' => 'weekly',
				'priority' => '0.7',
			];
		}
		return $urls;
	}

	public function getGalleryUrls(string $baseUrl): array {
		$galleryItems = $this->db->table(\App\Repository\GalleryRepository::GALLERIES_TABLE)
			->select('id, updated_at')
			->where('is_published', 1)
			->fetchPairs('id', 'updated_at');

		$urls = [];
		foreach ($galleryItems as $id => $updatedAt) {
			$urls[] = [
				'loc' => $baseUrl . 'gallery/view/' . $id,
				'lastmod' => $updatedAt->format('c'),
				'changefreq' => 'monthly',
				'priority' => '0.6',
			];
		}
		return $urls;
	}

}