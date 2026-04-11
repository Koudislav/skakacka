<?php

declare(strict_types=1);

namespace App\Repository;

use Caxy\HtmlDiff\HtmlDiff;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Strings;
use stdClass;

class ArticleRepository {

	public const ARTICLES_TABLE = 'articles';
	public const ARTICLES_HISTORY_TABLE = 'articles_history';
	public const ALL_ARTICLE_PATHS_CACHE_KEY = 'all_article_paths';
	private const BASE_RETURN_MESSAGES = ['success' => true, 'messages' => [['success' => 'Článek byl úspěšně vytvořen.']]];

	public const FORBIDEN_SLUGS = [
		'administration',
		'gallery',
		'upload',
		'verify-email',
		'verifyemail',
		'auth',
		'unsubscribe',
		'novinky',
		'novinka',
	];

	public function __construct(
		private Explorer $db,
	) {}

	public function findAll() {
		return $this->db->table(self::ARTICLES_TABLE)
			->where('deleted_at', null)
			->order('title ASC');
	}

	public function getArticleById(int $articleId): ?ActiveRow {
		return $this->db->table(self::ARTICLES_TABLE)
			->where('deleted_at', null)
			->get($articleId) ?: null;
	}

	public function updateArticle(int $articleId, \stdClass $values, int $userId): bool {
		$article = $this->getArticleById($articleId);

		if ($article) {
			$data = [];
			$update = false;
			$parentId = $values->parent_id ?? null;
			if ($parentId === $articleId) {
				throw new \Exception('Článek nemůže být sám sobě rodičem.');
			}
			if ($parentId !== null) {
				$this->assertNoCycle($articleId, $parentId);
			}

			$toUpdate = ['title', 'slug', 'parent_id', 'content', 'type', 'show_title', 'is_published', 'seo_title', 'seo_description', 'og_image'];
			if ($article->is_system) {
				$toUpdate = ['content'];
			} else {
				$parentChanged = $article->parent_id != $parentId;
				$slugChanged = $article->slug !== $values->slug;

				if ($parentChanged || $slugChanged) {
					$oldPath = $article->path;
					$newPath = $this->buildPath($values->slug, $parentId);
					$data['path'] = $newPath;
				}
			}

			foreach ($toUpdate as $key) {
				$data[$key] = is_bool($values->$key) ? ($values->$key ? 1 : 0) : $values->$key;

				if (!$update && ((!is_bool($values->$key) && $article->$key != $values->$key) || (is_bool($values->$key) && (bool)$article->$key !== $values->$key))) {
					$update = true;
				}
			}
			if (!$update) {
				return false;
			}

			$data['updated_at'] = new \DateTime();
			$data['updated_by'] = $userId;

			$article->update($data);
			if ($parentChanged || $slugChanged) {
				$this->updateChildrenPaths($article->id, $oldPath, $newPath);
			}
			return true;
		}
		return false;
	}

	public function createArticle(\stdClass $values, int $userId): array {
		$return = self::BASE_RETURN_MESSAGES;
		$slug = $this->generateAvailableSlug(!empty($values->slug) ? $values->slug : Strings::webalize($values->title), null, $values->parent_id ?? null);
		if ($slug !== strtolower($values->slug)) {
			$return['messages'][] = ['info' => 'Poznámka: Zadaný slug již existoval, byl změněn na unikátní hodnotu.'];
		}

		$parentId = $values->parent_id ?? null;
		$path = $this->buildPath($slug, $parentId);

		$data = [
			'title' => $values->title,
			'slug' => $slug,
			'parent_id' => $parentId,
			'path' => $path,
			'content' => $values->content,
			'type' => $values->type,
			'show_title' => $values->show_title ? 1 : 0,
			'is_published' => $values->is_published ? 1 : 0,
			'seo_title' => $values->seo_title ?: null,
			'seo_description' => $values->seo_description ?: null,
			'og_image' => $values->og_image ?: null,
			'created_at' => new \DateTime(),
			'created_by' => $userId,
		];

		$newArticle = $this->db->table(self::ARTICLES_TABLE)->insert($data);
		$return['articleId'] = $newArticle->id;
		return $return;
	}

	public function createArticleFromArray(array $data, int $userId): array {
		$return = self::BASE_RETURN_MESSAGES;
		$data['slug'] = $this->generateAvailableSlug(!empty($data['slug']) ? $data['slug'] : Strings::webalize($data['title']), null, $data['parent_id'] ?? null);
		$data['path'] = $this->buildPath($data['slug'], $data['parent_id'] ?? null);
		$data['created_by'] = $userId;
		$newArticle = $this->db->table(self::ARTICLES_TABLE)->insert($data);
		$return['articleId'] = $newArticle->id;
		return $return;
	}

	public function updateArticleFromArray(int $articleId, array $data, int $userId): bool {
		$article = $this->getArticleById($articleId);
		if (!$article) {
			return false;
		}
		if (isset($data['slug']) || isset($data['parent_id'])) {
			$slug = $data['slug'] ?? $article->slug;
			$parentId = $data['parent_id'] ?? null;
			$data['slug'] = $this->generateAvailableSlug($slug, $articleId, $parentId);
			$data['path'] = $this->buildPath($data['slug'], $parentId);
		}
		$data['updated_at'] = new \DateTime();
		$data['updated_by'] = $userId;
		return (bool)$article->update($data);
	}

	public function createTemplateJson(array|stdClass $data): string {
		$input = (array) $data;
		$output = [];
		foreach ($input as $key => $value) {
			if (str_starts_with($key, 'repeater_')) {
				$newValues = [];
				$newKey = str_replace('repeater_', '', $key);
				foreach ($value as $item) {
					if (!empty($item['value'])) {
						$newValues[] = $item;
					}
				}
				$output[$newKey] = $newValues;
			} else {
				$output[$key] = $value;
			}
		}
		return json_encode($output);
	}

	public function generateAvailableSlug(string $slug, ?int $excludeArticleId = null, ?int $parentId = null): string {
		$baseSlug = $slug;
		$i = 0;

		// kontrolujeme, jestli slug je volny, pripadne pridame postfix
		while (true) {
			$checkSlug = $i === 0 ? $baseSlug : $baseSlug . '-' . $i;

			$query = $this->db->table(self::ARTICLES_TABLE)
				->where('slug', $checkSlug)
				->where('parent_id', $parentId);

			if ($excludeArticleId !== null) {
				$query->where('id != ?', $excludeArticleId);
			}

			if ($query->count('*') === 0 && $checkSlug !== '' && !in_array($checkSlug, self::FORBIDEN_SLUGS, true)) {
				return $checkSlug;
			}
			$i++;
		}
	}

	public function getAllPaths(bool $onlyPublished = true): array {
		$slugs = [];
		$query = $this->db->table(self::ARTICLES_TABLE);
		if ($onlyPublished) {
			$query->where('is_published', 1);
		}
		$rows = $query->select('path')->fetchAll();
		foreach ($rows as $row) {
			$slugs[] = $row->path;
		}
		return $slugs;
	}

	public function getIndexes(bool $onlyPublished = true) {
		$query = $this->db->table(self::ARTICLES_TABLE)
			->where('deleted_at', null)
			->where('type', 'index')
			->order('created_at DESC');
		if ($onlyPublished) {
			$query->where('is_published', 1);
		}
		return $query->fetchAll();
	}

	public function deleteArticle(int $articleId, int $userId): bool {
		$article = $this->getArticleById($articleId);
		if (!$article) {
			return false;
		}
		return $article->update([
			'deleted_at' => new \DateTime(),
			'deleted_by' => $userId,
			'slug' => $this->generateAvailableSlug('deleted-article-' . $article->slug),
		]);
	}

	public function getHistoryByArticleId(int $articleId): array {
		return $this->db->table(self::ARTICLES_HISTORY_TABLE)
			->where('article_id', $articleId)
			->order('changed_at DESC')
			->fetchAll();
	}

	public function generateDiff(string $oldContent, string $newContent): string {
		$oldContent = $this->normalizeHtml($oldContent);
		$newContent = $this->normalizeHtml($newContent);

		if ($oldContent === $newContent) {
			return '<span class="text-muted">Beze změny</span>';
		}
		$diff = new HtmlDiff($oldContent, $newContent);
		$diffHtml = $diff->build();
		return $diffHtml;
	}

	private function normalizeHtml(string $html): string {
		$html = trim($html);
		$html = preg_replace('/\s+/', ' ', $html);
		$html = preg_replace('/\sdata-mce-[^=]+="[^"]*"/', '', $html);
		return $html;
	}

	private function buildPath(string $slug, ?int $parentId): string {
		if ($parentId === null) {
			return $slug;
		}
		$parent = $this->getArticleById($parentId);
		if (!$parent) {
			throw new \RuntimeException('Parent not found.');
		}
		return $parent->path . '/' . $slug;
	}

	private function updateChildrenPaths(int $parentId, string $oldPath, string $newPath): void {
		$children = $this->db->table(self::ARTICLES_TABLE)
			->where('path LIKE ?', $oldPath . '/%')
			->fetchAll();

		foreach ($children as $child) {
			$newChildPath = preg_replace(
				'#^' . preg_quote($oldPath, '#') . '#',
				$newPath,
				$child->path
			);

			$this->db->table(self::ARTICLES_TABLE)
				->where('id', $child->id)
				->update([
					'path' => $newChildPath,
				]);
		}
	}

	public function getArticleOptions(?int $excludeId = null, array $params = ['returnKey' => 'id']): array {
		$rows = $this->db->table(self::ARTICLES_TABLE)
			->where('deleted_at', null)
			->order('path ASC')
			->fetchAll();

		$options = [];
		foreach ($rows as $row) {
			if ($excludeId !== null && $row->id === $excludeId) {
				continue;
			}
			// depth podle path
			$depth = substr_count(($row->path ?? ''), '/');
			// odsazení
			$prefix = str_repeat('— ', $depth);
			$options[$row->{$params['returnKey']}] = $prefix . $row->title . ' (' . $row->slug . ')';
		}
		return $options;
	}

	private function assertNoCycle(int $articleId, int $parentId): void {
		while ($parentId !== null) {
			if ($parentId === $articleId) {
				throw new \Exception('Nelze vytvořit cyklus ve stromu.');
			}

			$parent = $this->getArticleById($parentId);
			if (!$parent) break;

			$parentId = $parent->parent_id;
		}
	}

	public function getArticleTree(?int $parentId = null): array {
		$rows = $this->db->table(self::ARTICLES_TABLE)
			->where('deleted_at', null)
			->order('path ASC') // nebo 'position ASC', pokud máš pořadí
			->fetchAll();

		$tree = [];

		// sestavíme pole podle parent_id
		$byParent = [];
		foreach ($rows as $row) {
			$pid = $row->parent_id ?? 0;
			$byParent[$pid][] = $row;
		}

		$buildTree = function ($parentId) use (&$buildTree, $byParent) {
			$branch = [];
			foreach ($byParent[$parentId] ?? [] as $row) {
				$branch[] = [
					'article' => $row,
					'children' => $buildTree($row->id),
				];
			}
			return $branch;
		};

		return $buildTree($parentId ?? 0);
	}

	public function getByPath(string $path): ?ActiveRow {
		return $this->db->table(self::ARTICLES_TABLE)
			->where('path', $path)
			->where('is_published', 1)
			->fetch() ?: null;
	}

}
