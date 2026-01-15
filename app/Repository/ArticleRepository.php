<?php

declare(strict_types=1);

namespace App\Repository;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Strings;

class ArticleRepository {

	public const ARTICLES_TABLE = 'articles';
	public const ALL_ARTICLE_SLUGS_CACHE_KEY = 'all_article_slugs';

	public const FORBIDEN_SLUGS = [
		'administration',
	];

	public function __construct(
		private Explorer $db,
	) {}

	public function findAll() {
		return $this->db->table(self::ARTICLES_TABLE)->order('title ASC');
	}

	public function getArticleById(int $articleId): ?ActiveRow {
		return $this->db->table(self::ARTICLES_TABLE)
			->get($articleId) ?: null;
	}

	public function updateArticle(int $articleId, \stdClass $values, int $userId): bool {
		$article = $this->getArticleById($articleId);

		if ($article) {
			$data = [];
			$update = false;
			$toUpdate = ['title', 'slug', 'content', 'type', 'show_title', 'is_published'];
			foreach ($toUpdate as $key) {
				$data[$key] = is_bool($values->$key) ? ($values->$key ? 1 : 0) : $values->$key;

				if (!$update && (!is_bool($values->$key) && $article->$key != $values->$key) || (is_bool($values->$key) && (bool)$article->$key !== $values->$key)) {
					$update = true;
				}
			}
			if (!$update) {
				return false;
			}

			$data['updated_at'] = new \DateTime();
			$data['updated_by'] = $userId;

			return $article->update($data);
		}
		return false;
	}

	public function createArticle(\stdClass $values, int $userId): array {
		$return = ['success' => true, 'messages' => [['success' => 'Článek byl úspěšně vytvořen.']]];
		$slug = $this->generateAvailableSlug(
			!empty($values->slug)
			? $values->slug
			: Strings::webalize($values->title)
		);
		if ($slug !== $values->slug) {
			$return['messages'][] = ['info' => 'Poznámka: Zadaný slug již existoval, byl změněn na unikátní hodnotu.'];
		}
		$data = [
			'title' => $values->title,
			'slug' => $slug,
			'content' => $values->content,
			'type' => $values->type,
			'show_title' => $values->show_title ? 1 : 0,
			'is_published' => $values->is_published ? 1 : 0,
			'created_at' => new \DateTime(),
			'created_by' => $userId,
		];

		$newArticle = $this->db->table(self::ARTICLES_TABLE)->insert($data);
		$return['articleId'] = $newArticle->id;
		return $return;
	}

	public function generateAvailableSlug(string $slug, ?int $excludeArticleId = null): string {
		$baseSlug = $slug;
		$i = 0;
	
		$query = $this->db->table(self::ARTICLES_TABLE);
	
		// kontrolujeme, jestli slug je volny, pripadne pridame postfix
		while (true) {
			$checkSlug = $i === 0 ? $baseSlug : $baseSlug . '-' . $i;
	
			$slugQuery = $query->where('slug', $checkSlug);
			if ($excludeArticleId !== null) {
				$slugQuery->where('id != ?', $excludeArticleId);
			}
	
			if ($slugQuery->count('*') === 0 && $checkSlug !== '' && !in_array($checkSlug, self::FORBIDEN_SLUGS, true)) {
				return $checkSlug;
			}
			$i++;
		}
	}

	public function getBySlug(string $slug): ?ActiveRow {
		return $this->db->table(self::ARTICLES_TABLE)
			->where('slug', $slug)
			->fetch() ?: null;
	}

	public function getAllSlugs(bool $onlyPublished = true): array {
		$slugs = [];
		$query = $this->db->table(self::ARTICLES_TABLE);
		if ($onlyPublished) {
			$query->where('is_published', 1);
		}
		$rows = $query->select('slug')->fetchAll();
		foreach ($rows as $row) {
			$slugs[] = $row->slug;
		}
		return $slugs;
	}

	public function getArticleListForSelect(): array {
		$articles = $this->db->table(self::ARTICLES_TABLE)
			->order('title ASC')
			->fetchAll();
		$result = [];
		foreach ($articles as $article) {
			$result[$article->slug] = ($article->is_published != 1 ? 'NEPUBLIKOVANO! ' : '') . $article->title . ' /// ' . $article->slug;
		}
		return $result;
	}

}
