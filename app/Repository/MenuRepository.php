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

	public function findByKeyStructured(string $menuKey): array {
		$items = $this->db->table('menus')
			->where('menu_key', $menuKey)
			->order('position')
			->fetchAll();

		$tree = [];
		foreach ($items as $item) {
			if ($item->parent_id === null) {
				$tree[$item->id] = [
					'item' => $item,
					'children' => [],
				];
			}
		}

		foreach ($items as $item) {
			if ($item->parent_id !== null && isset($tree[$item->parent_id])) {
				$tree[$item->parent_id]['children'][] = $item;
			}
		}
		return $tree;
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
			'position' => $this->getNextPosition($values->menu_key),
			'parent_id' => $values->parent_id ?: null,
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
			'parent_id' => $values->parent_id ?: null,
		];
		$this->db->table(self::MENUS_TABLE)->where('id', $id)->update($update);
	}

	public function resolvePresenter(\stdClass $values): ?string {
		return match ($values->linkType) {
			'article' => 'Article',
			'gallery' => 'Gallery',
			'parent' => null,
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
		$basic = ['linkedArticleSlug' => null];

		if ($row->presenter === null) {
			return ['linkType' => 'parent'] + $basic;
		}
		if ($row->presenter === 'Article' && $row->action === 'default') {
			return [
				'linkType' => 'article',
				'linkedArticleSlug' => json_decode((string) $row->params, true)['slug'] ?? null,
			];
		}
		return ['linkType' => 'index'] + $basic;
	}

	public function updatePositions(array $order): void {
		$this->db->beginTransaction();

		foreach ($order as $row) {
			$this->db->table(self::MENUS_TABLE)
				->where('id', (int) $row['id'])
				->update([
					'position' => (int) $row['position'],
				]);
		}

		$this->db->commit();
	}

	private function getNextPosition(string $menuKey): int {
		return (int) $this->db->table(self::MENUS_TABLE)
			->where('menu_key', $menuKey)
			->max('position') + 1;
	}

	public function getRootItemsForSelect(string $menuKey, ?int $excludeId = null): array {
		$q = $this->db->table('menus')
			->where('menu_key', $menuKey)
			->where('parent_id IS NULL')
			->where('presenter', null);

		if ($excludeId) {
			$q->where('id != ?', $excludeId);
		}
		return $q->fetchPairs('id', 'label');
	}

}
