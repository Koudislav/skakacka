<?php

declare(strict_types=1);

namespace App\Repository;

use Nette\Database\Explorer;
use stdClass;

class GalleryRepository {

	public const GALLERIES_TABLE = 'galleries';
	public const PICTURES_TABLE = 'gallery_pictures';

	public function __construct(
		private Explorer $db,
	) {}

	//Galeries

	public function findAllGalleries($onlyPublished = false) {
		$query = $this->db->table(self::GALLERIES_TABLE)
			->order('position DESC, created_at DESC');
		if ($onlyPublished) {
			$query->where('is_published', 1);
		}
		return $query->fetchAll();
	}

	public function getGalleryById(int $galleryId) {
		return $this->db->table(self::GALLERIES_TABLE)
			->where('id', $galleryId)
			->fetch();
	}

	public function createGallery(stdClass $values, int $userId) {
		$max = $this->db->table(self::GALLERIES_TABLE)->max('position');
		return $this->db->table(self::GALLERIES_TABLE)->insert([
			'title' => $values->title,
			'description' => $values->description,
			'is_published' => $values->is_published ? 1 : 0,
			'created_at' => new \DateTime(),
			'created_by' => $userId,
			'position' => $max ? $max + 1 : 0,
		]);
	}

	public function updateGallery(int $galleryId, stdClass $values, int $userId) {
		return $this->db->table(self::GALLERIES_TABLE)
			->where('id', $galleryId)
			->update([
				'title' => $values->title,
				'description' => $values->description,
				'is_published' => $values->is_published ? 1 : 0,
				'updated_at' => new \DateTime(),
				'updated_by' => $userId,
			]);
	}

	public function findCoverPicturesByGalleryIds(array $ids) {
		return $this->db->table(self::PICTURES_TABLE)
			->where('gallery_id', $ids)
			->where('is_cover', 1)
			->fetchAll();
	}

	public function findCoverPictureByGalleryId(int $id) {
		return $this->db->table(self::PICTURES_TABLE)
			->where('gallery_id', $id)
			->where('is_cover', 1)
			->fetch();
	}

	public function getGalleryListForSelect(bool $onlyPublished = false): array {
		$query = $this->db->table(self::GALLERIES_TABLE)
			->select('id, title')
			->order('title ASC');
		if ($onlyPublished) {
			$query->where('is_published', 1);
		}
		$galleries = [];
		foreach ($query->fetchAll() as $gallery) {
			$galleries[$gallery->id] = $gallery->title;
		}
		return $galleries;
	}

	//Images

	public function findPicturesByGalleryId(int $galleryId, bool $onlyPublished = false) {
		$query = $this->db->table(self::PICTURES_TABLE)
			->where('gallery_id', $galleryId)
			->order('position ASC, created_at DESC');
		if ($onlyPublished) {
			$query->where('is_visible', 1);
		}
		return $query->fetchAll();
	}

	public function getImageById(int $id) {
		return $this->db->table(self::PICTURES_TABLE)
			->where('id', $id)
			->fetch();
	}

	public function insertPhoto(array $data): int {
		$max = $this->db->table(self::PICTURES_TABLE)
			->where('gallery_id', $data['gallery_id'])
			->max('position');
		$data['position'] = $max ? $max + 1 : 0;
		return (int) $this->db->table(self::PICTURES_TABLE)->insert($data)->id;
	}

	public function updatePhoto(int $id, array $data): void {
		$this->db->table(self::PICTURES_TABLE)
			->where('id', $id)
			->update($data);
	}

	public function toggleImageVisibility(int $id): void {
		$this->db->query('
			UPDATE ' . self::PICTURES_TABLE . '
			SET is_visible = NOT is_visible
			WHERE id = ?', $id
		);
	}

	public function updateImageDescription(int $id, string $description): void {
		$this->db->query('
			UPDATE ' . self::PICTURES_TABLE . '
			SET description = ?
			WHERE id = ?', $description, $id
		);
	}

	public function deleteImage(int $id): void {
		// TODO - smazani souboru
		$this->db->table(self::PICTURES_TABLE)
			->where('id', $id)
			->delete();
	}

	public function setGalleryCover(int $id) {
		$table = $this->db->table(self::PICTURES_TABLE);
		$image = $table->get($id);
		$table->where('gallery_id', $image->gallery_id)
			->update(['is_cover' => 0]);
		$image->update(['is_cover' => 1]);
	}

	public function updateGalleryPositions(array $order): void {
		foreach ($order as $item) {
			$this->db->table(self::GALLERIES_TABLE)
				->where('id', $item['id'])
				->update(['position' => $item['position']]);
		}
	}

	public function updateImagePositions(array $order): void {
		foreach ($order as $item) {
			$this->updatePhoto((int) $item['id'], ['position' => $item['position']]);
		}
	}

}
