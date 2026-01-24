<?php

declare(strict_types=1);

namespace App\Repository;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

class MenuRepository {

	public const MENUS_TABLE = 'menus';

	public const FORBIDEN_MENU_KEYS = [
		'0',
		0,
	];

	public function __construct(
		private Explorer $db,
	) {}

	public function findKeys(): array {
		return $this->db->table('menus')
			->select('DISTINCT menu_key')
			->fetchPairs('menu_key', 'menu_key');
	}

	public function findByKey(string $menuKey, bool $onlyActive = false): array {
		$query = $this->db->table('menus')
			->where('menu_key', $menuKey)
			->order('position ASC');
		if ($onlyActive) {
			$query->where('is_active', 1);
		}
		return $query->fetchAll();
	}

	public function getById(int $id, ?string $menuKey = null): array {
		$query = $this->db->table(self::MENUS_TABLE);
		if ($menuKey !== null) {
			$query->where('menu_key', $menuKey);
		}
		$db = $query->get($id);
		return [
			'db' => $db,
			'processed' => $this->backProcessForForm($db),
		];
	}

	public function create(array $values): int {
		return $this->db->table(self::MENUS_TABLE)->insert($values)->id;
	}

	public function update(int $id, array $values): void {
		$this->db->table(self::MENUS_TABLE)->where('id', $id)->update($values);
	}

	public function delete(int $id): void {
		$this->db->table(self::MENUS_TABLE)->where('id', $id)->delete();
	}

	public function createMenuItem(\stdClass $values): int {
		$insert = [
			'menu_key' => $values->menu_key,
			'label' => $values->label,
			'is_active' => $values->is_active ? 1 : 0,
			'presenter' => $this->resolvePresenter($values),
			'action' => $this->resolveAction($values),
			'params' => $this->resolveParams($values),
		];
		return $this->db->table(self::MENUS_TABLE)->insert($insert)->id;
	}

	public function updateMenuItem(\stdClass $values, int $id): void {
		$update = [
			'label' => $values->label,
			'is_active' => $values->is_active ? 1 : 0,
			'presenter' => $this->resolvePresenter($values),
			'action' => $this->resolveAction($values),
			'params' => $this->resolveParams($values),
		];
		$this->db->table(self::MENUS_TABLE)->where('id', $id)->update($update);
	}

	public function resolvePresenter(\stdClass $values): string {
		return match ($values->linkType) {
			'article' => 'Article',
			'gallery' => 'Gallery',
			default => 'Home',
		};
	}

	public function resolveAction(\stdClass $values): string {
		switch ($values->linkType) {
			case 'gallery':
				if (is_numeric($values->galleryId)) {
					return 'view';
				}
			default: return 'default';
		};
	}

	public function resolveParams(\stdClass $values): ?string {
		switch ($values->linkType) {
			case 'article':
				return json_encode(['slug' => $values->linkedArticleSlug]);
			case 'gallery':
				if (is_numeric($values->galleryId)) {
					return json_encode(['id' => (int) $values->galleryId]);
				}
			default: return null;
		}
	}

	public function backProcessForForm(ActiveRow $row): array {
		if ($row->presenter === 'Article' && $row->action === 'default') {
			return [
				'linkType' => 'article',
				'linkedArticleSlug' => json_decode((string) $row->params, true)['slug'] ?? null,
			];
		}
		return [
			'linkType' => 'index',
			'linkedArticleSlug' => null,
		];
	}

}
