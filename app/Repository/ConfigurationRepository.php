<?php

declare(strict_types=1);

namespace App\Repository;

use Nette\Database\Explorer;

class ConfigurationRepository {

	public const CONFIGURATION_TABLE = 'configuration';

	public function __construct(
		private Explorer $db,
	) {}

	public function getAll(bool $onlyActive = false) {
		$query = $this->db->table(self::CONFIGURATION_TABLE)
			->order('category ASC');
		if ($onlyActive) {
			$query->where('active', 1);
		}
		return $query->fetchAll();
	}

}
