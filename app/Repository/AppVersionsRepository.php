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

	public function getOldestForMail() {
		return $this->db->table(self::VERSIONS_TABLE)
			->where('email_send', 0)
			->order('created_at ASC')
			->fetch();
	}

	public function getCurrentVersion(): ?string {
		$version = $this->db->table(self::VERSIONS_TABLE)
			->order('created_at DESC')
			->fetch();

		return $version ? $version->version : null;
	}

}
