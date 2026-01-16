<?php

declare(strict_types=1);

namespace App\Repository;

use Nette\Database\Explorer;
use stdClass;

class GalleryRepository {

	public const GALLERIES_TABLE = 'galleries';
	public const PICTURES_TABLE = 'pictures';

	public function __construct(
		private Explorer $db,
	) {}

	//Galeries

	public function findAllGalleries($onlyPublished = false) {
		$query = $this->db->table(self::GALLERIES_TABLE)
			->order('created_at DESC');
		if ($onlyPublished) {
			$query->where('published', 1);
		}
		return $query->fetchAll();
	}

	public function getGalleryById(int $galleryId) {
		return $this->db->table(self::GALLERIES_TABLE)
			->where('id', $galleryId)
			->fetch();
	}

	public function createGallery(stdClass $values, int $userId) {
		return $this->db->table(self::GALLERIES_TABLE)->insert([
			'title' => $values->title,
			'description' => $values->description,
			'is_published' => $values->is_published ? 1 : 0,
			'created_at' => new \DateTime(),
			'created_by' => $userId,
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


	//Images

	public function findPicturesByGalleryId(int $galleryId) {
		return $this->db->table(self::PICTURES_TABLE)
			->where('gallery_id', $galleryId)
			// ->order('uploaded_at DESC')
			->fetchAll();
	}

	public function insertPhoto(array $values, int $userId) {
		return $this->db->table(self::PICTURES_TABLE)->insert([
			'gallery_id' => $values['galleryId'],
			'original_name' => $values['original_name'],
			'path_original' => $values['path_original'],
			'created_at' => new \DateTime(),
			'created_by' => $userId,
		]);
	}
}
