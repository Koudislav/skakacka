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

	public function getCategories(): array {
		return $this->db->table(self::CONFIGURATION_TABLE)
			->where('type', 'label')
			->where('active', 1)
			->where('sort_order', 0)
			->order('description ASC')
			->fetchPairs('category', 'value_string');
	}

	public function getByCategory(string $category) {
		return $this->db->table(self::CONFIGURATION_TABLE)
			->where('category', $category)
			->where('active', 1)
			->order('sort_order, key');
	}

	public function updateValue(string $key, mixed $value, int $userId): void {
		$editedColumn = 'edited_by';
		$data = [$editedColumn => $userId];

		if (is_bool($value)) {
			$data['value_bool'] = $value;
		} elseif (is_int($value)) {
			$data['value_int'] = $value;
		} elseif (is_float($value)) {
			$data['value_float'] = $value;
		} else {
			$data['value_string'] = $value;
		}

		$line = $this->db->table(self::CONFIGURATION_TABLE)
			->where('key', $key)
			->fetch();

		$update = false;
		foreach ($data as $column => $val) {
			if ($column !== $editedColumn && $line->$column != $val) {
				$update = true;
			}
		}
		if ($update) {
			$line->update($data);
		}
	}

	public function insert(array $data): void {
		$this->db->table(self::CONFIGURATION_TABLE)
			->insert($data);
	}

}
