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
	public string $baseUrl;
	public Cache $cache;

	public function __construct(
		private string $wwwDir,
		private Explorer $db,
		private Storage $storage,
		private Config $config,
	) {
		$this->xmlPath = $this->wwwDir . '/sitemap.xml';
		$this->cache = new Cache($this->storage);
		$this->baseUrl = rtrim($this->config['base_url'], '/') . '/';
	}

	public function needsRegeneration(): bool {
		return $this->cache->load('sitemap-check', function (&$dependencies) {
			$dependencies[Cache::Expire] = '1 hour';	
			if (!file_exists($this->xmlPath)) {
				return true;
			}	
			$lastModified = filemtime($this->xmlPath);
			if ($lastModified === false) {
				return true;
			}	
			return (time() - $lastModified) > self::MAX_AGE;
		});
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
			$urlNode->addChild('loc', htmlspecialchars($url['loc'], ENT_XML1));

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
		$urls = [
			[
				'loc' => $this->baseUrl,
				'changefreq' => 'daily',
				'priority' => '1.0',
			],
			[
				'loc' => $this->baseUrl . 'gallery',
				'changefreq' => 'weekly',
				'priority' => '0.8',
			],
		];
		$urls = array_merge($urls, $this->getArticleUrls(), $this->getGalleryUrls());

		return $urls;
	}

	public function getArticleUrls(): array {
		$slugs = $this->cache->load(\App\Repository\ArticleRepository::ALL_ARTICLE_SLUGS_CACHE_KEY);
		if (empty($slugs)) {
			return [];
		}
		$updated = $this->db->table(\App\Repository\ArticleRepository::ARTICLES_TABLE)
			->select('slug, updated_at, created_at')
			->where('slug IN ?', $slugs)
			->where('type', 'article')
			->fetchAll();

		$articles = [];
		foreach ($updated as $article) {
			$articles[$article->slug] = $article->updated_at ?? $article->created_at;
		}

		$urls = [];
		foreach ($slugs as $slug) {
			if (!array_key_exists($slug, $articles)) {
				continue;
			}
			$item = [
				'loc' => $this->baseUrl . $slug,
				'changefreq' => 'weekly',
				'priority' => '0.7',
			];
			if ($articles[$slug]) {
				$item['lastmod'] = $articles[$slug]->format('c');
			}
			$urls[] = $item;
		}
		return $urls;
	}

	public function getGalleryUrls(): array {
		$galleryItems = $this->db->table(\App\Repository\GalleryRepository::GALLERIES_TABLE)
			->select('id, updated_at')
			->where('is_published', 1)
			->fetchPairs('id', 'updated_at');

		$urls = [];
		foreach ($galleryItems as $id => $updatedAt) {
			$urls[] = [
				'loc' => $this->baseUrl . 'gallery/view/' . $id,
				'lastmod' => $updatedAt?->format('c'),
				'changefreq' => 'monthly',
				'priority' => '0.6',
			];
		}
		return $urls;
	}

}