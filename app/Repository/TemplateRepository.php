<?php

declare(strict_types=1);

namespace App\Repository;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

class TemplateRepository {

	public const TEMPLATES_TABLE = 'templates';
	public const HISTORY_TABLE = 'templates_history';
	public const TEMPLATES_TYPES = [
		'content' => 'HTML',
		'component' => 'Komponenta',
		'layout' => 'Layout',
	];
	public const TEMPLATES_VARIABLE_TYPES = [
		'text' => 'Text',
		'html' => 'HTML',
		'repeater' => 'Opakovatelná skupina',
		'image' => 'Obrázek',
	];
	public const TEMPLATES_REPEATER_TYPES = [
		'text' => 'Text',
		'html' => 'HTML',
		'image' => 'Obrázek',
	];
	public const VARIABLE_REGEX = '/\{\{([a-z0-9_]+)\}\}/i';

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
			$toUpdate = ['name', 'description', 'content', 'type'];
			if ($values->type === 'layout') {
				$newVars = $this->extractVariables($values->content);
				$oldSchema = json_decode($template->placeholders_json ?? '{}', true);
				$formSchema = $this->createFormSchemaFromVariables($values);
				$newSchema = [];

				foreach ($newVars as $var) {
					if (isset($formSchema[$var])) {
						// priorita: user input
						$newSchema[$var] = array_merge(
							$this->defaultSchemaForVariable($var),
							$formSchema[$var]
						);
					} elseif (isset($oldSchema[$var])) {
						// fallback: stare
						$newSchema[$var] = $oldSchema[$var];
					} else {
						// nova promenna
						$newSchema[$var] = $this->defaultSchemaForVariable($var);
					}
				}
				$data['placeholders_json'] = json_encode($newSchema);
				if ($data['placeholders_json'] !== $template->placeholders_json) {
					$update = true;
				}
			}
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

		if ($values->type === 'layout') {
			$variables = $this->extractVariables($values->content);
			$schema = [];
			foreach ($variables as $var) {
				$schema[$var] = $this->defaultSchemaForVariable($var);
			}
			$data['placeholders_json'] = json_encode($schema);
			if (!empty($variables)) {
				$return['messages'][] = ['info' => 'Šablona obsahuje následující proměnné: ' . implode(', ', $variables)];
			}
		}

		$newTemplate = $this->db->table(self::TEMPLATES_TABLE)->insert($data);
		$return['templateId'] = $newTemplate->id;
		return $return;
	}

	public function extractVariables(string $template): array {
		preg_match_all(self::VARIABLE_REGEX, $template, $matches);
		return array_unique($matches[1]);
	}

	private function defaultSchemaForVariable(string $variable): array {
		return [
			'type' => 'text',
			'required' => true,
			'label' => ucfirst(str_replace('_', ' ', $variable)),
		];
	}

	private function createFormSchemaFromVariables($values): array {
		$formSchema = [];

		foreach ((array)$values as $key => $value) {
			if (str_starts_with($key, 'placeholders_')) {
				$name = str_replace('placeholders_', '', $key);

				$formSchema[$name] = [
					'type' => $value['type'] ?? 'text',
					'required' => !empty($value['required']),
					'label' => $value['label'] ?? ucfirst(str_replace('_', ' ', $name)),
				];
				if ($value['type'] === 'repeater') {
					$formSchema[$name]['repeater_type'] = $value['repeater_type'] ?? 'text';
				}

				unset($values->$key);
			}
		}
		return $formSchema;
	}

	public function findLayouts(): array {
		return $this->db->table(self::TEMPLATES_TABLE)
			->where('type', 'layout')
			->fetchAll();
	}

	public function getById(int $id): ?ActiveRow {
		return $this->db->table(self::TEMPLATES_TABLE)
			->get($id) ?: null;
	}

	public function resolveDataDefaults(array $placeholders, ?string $dataJson): array {
		$data = $dataJson ? json_decode($dataJson, true) : [];
		$resolved = [];
		foreach ($placeholders as $key => $config) {
			$resolved[$key] = $data[$key] ?? ($config['default'] ?? null);
		}
		return $resolved;
	}

	public function getTemplateHistory(int $templateId, int $templateVersion): ?ActiveRow {
		return $this->db->table(self::HISTORY_TABLE)
			->where('template_id', $templateId)
			->where('version', $templateVersion)
			->fetch();
	}

}
