<?php

declare(strict_types=1);

namespace App\Repository;

use Nette\Database\Explorer;

class AppVersionsRepository {

	public const VERSIONS_TABLE = 'system_updates';

	public function __construct(
		private Explorer $db,
	) {}

	public function findAll() {
		return $this->db->table(self::VERSIONS_TABLE)
			->order('created_at DESC');
	}

	public function findFew(int $limit = 5) {
		return $this->db->table(self::VERSIONS_TABLE)
			->order('created_at DESC')
			->limit($limit);
	}

}
