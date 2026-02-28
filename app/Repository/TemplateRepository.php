<?php

declare(strict_types=1);

namespace App\Repository;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

class TemplateRepository {

	public const TEMPLATES_TABLE = 'templates';

	public function __construct(
		private Explorer $db,
	) {}

	public function findAll() {
		return $this->db->table(self::TEMPLATES_TABLE);
	}

	public function getTemplateById(int $templateId): ?ActiveRow {
		return $this->db->table(self::TEMPLATES_TABLE)
			->get($templateId) ?: null;
	}

	public function updateTemplate(int $templateId, \stdClass $values, int $userId): bool {
		$template = $this->getTemplateById($templateId);

		if ($template) {
			$data = [];
			$update = false;
			$toUpdate = ['name', 'description', 'content'];
			foreach ($toUpdate as $key) {
				$data[$key] = is_bool($values->$key) ? ($values->$key ? 1 : 0) : $values->$key;

				if (!$update && (!is_bool($values->$key) && $template->$key != $values->$key) || (is_bool($values->$key) && (bool)$template->$key !== $values->$key)) {
					$update = true;
				}
			}
			if (!$update) {
				return false;
			}

			$data['updated_at'] = new \DateTime();
			$data['updated_by'] = $userId;

			return $template->update($data);
		}
		return false;
	}

	public function createTemplate(\stdClass $values, int $userId): array {
		$return = ['success' => true, 'messages' => [['success' => 'Šablona byla úspěšně vytvořena.']]];
		$data = (array) $values;
		$data['created_by'] = $userId;

		$newTemplate = $this->db->table(self::TEMPLATES_TABLE)->insert($data);
		$return['templateId'] = $newTemplate->id;
		return $return;
	}

}
