<?php

declare(strict_types=1);

namespace App\Repository;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Strings;

class NewsRepository {

	public const TABLE = 'news';

	public function __construct(
		private Explorer $db,
	) {}

	public function findAll() {
		return $this->db->table(self::TABLE)
			->where('deleted_at', null)
			->order('published_at DESC');
	}

	public function getById(int $id): ?ActiveRow {
		return $this->db->table(self::TABLE)
			->where('deleted_at', null)
			->get($id) ?: null;
	}

	public function getBySlug(string $slug): ?ActiveRow {
		return $this->db->table(self::TABLE)
			->where('slug', $slug)
			->where('is_published', 1)
			->fetch() ?: null;
	}

	public function create(\stdClass $values, int $userId): int {
		$slug = $this->generateSlug($values->slug ?: $values->title);

		$row = $this->db->table(self::TABLE)->insert([
			'title' => $values->title,
			'slug' => $slug,
			'content' => $values->content,
			'excerpt' => $values->excerpt ?: null,
			'cover_image' => $values->cover_image ?: null,
			'is_published' => $values->is_published ? 1 : 0,
			'published_at' => $values->published_at ?: new \DateTime(),
			// 'seo_title' => $values->seo_title ?: null,
			// 'seo_description' => $values->seo_description ?: null,
			'og_image' => $values->cover_image ?: null,
			'created_by' => $userId,
			'created_at' => new \DateTime(),
		]);

		return $row->id;
	}

	public function update(int $id, \stdClass $values, int $userId): bool {
		$row = $this->getById($id);
		if (!$row) return false;

		$data = [
			'title' => $values->title,
			'slug' => $this->generateSlug($values->slug, $id),
			'content' => $values->content,
			'excerpt' => $values->excerpt ?: null,
			'cover_image' => $values->cover_image ?: null,
			'is_published' => $values->is_published ? 1 : 0,
			'published_at' => $values->published_at ?: null,
			// 'seo_title' => $values->seo_title ?: null,
			// 'seo_description' => $values->seo_description ?: null,
			'og_image' => $values->cover_image ?: null,
			'updated_by' => $userId,
			'updated_at' => new \DateTime(),
		];

		$row->update($data);
		return true;
	}

	public function delete(int $id, int $userId): bool {
		$row = $this->getById($id);
		if (!$row) return false;

		$row->update([
			'deleted_at' => new \DateTime(),
			'deleted_by' => $userId,
		]);

		return true;
	}

	private function generateSlug(string $slug, ?int $excludeId = null): string {
		$base = Strings::webalize($slug);
		$i = 0;

		while (true) {
			$test = $i === 0 ? $base : $base . '-' . $i;

			$query = $this->db->table(self::TABLE)
				->where('slug', $test);

			if ($excludeId) {
				$query->where('id != ?', $excludeId);
			}

			if ($query->count('*') === 0) {
				return $test;
			}

			$i++;
		}
	}

	public function getLatest(int $limit = 3): array {
		return $this->db->table(self::TABLE)
			->where('deleted_at', null)
			->where('is_published', 1)
			->order('published_at DESC')
			->limit($limit)
			->fetchAll();
	}

}
