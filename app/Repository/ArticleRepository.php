<?php

declare(strict_types=1);

namespace App\Repository;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Strings;

class ArticleRepository {

	public const ARTICLES_TABLE = 'articles';

	public function __construct(
		private Explorer $db,
	) {}

	public function findAll() {
		return $this->db->table(self::ARTICLES_TABLE);
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
	
			if ($slugQuery->count('*') === 0) {
				return $checkSlug;
			}
			$i++;
		}
	}

}
