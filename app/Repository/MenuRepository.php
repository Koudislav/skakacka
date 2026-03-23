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
		return $this->db->table(self::MENUS_TABLE)
			->where('deleted_at', null)
			->select('DISTINCT menu_key')
			->fetchPairs('menu_key', 'menu_key');
	}

	public function findByKeyStructured(string $menuKey, bool $active = false): array {
		$query = $this->db->table(self::MENUS_TABLE)
			->where('deleted_at', null)
			->where('menu_key', $menuKey)
			->order('position');

		if ($active) {
			$query->where('is_active', 1);
		}

		$items = $query->fetchAll();

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
			'position' => $this->getNextPosition($values->menu_key),
			'parent_id' => $values->parent_id ?: null,
		];
		$insert += $this->resolveParams($values);

		return $this->db->table(self::MENUS_TABLE)->insert($insert)->id;
	}

	public function updateMenuItem(\stdClass $values, int $id): void {
		$update = [
			'label' => $values->label,
			'is_active' => $values->is_active ? 1 : 0,
			'presenter' => $this->resolvePresenter($values),
			'action' => $this->resolveAction($values),
			'parent_id' => $values->parent_id ?: null,
		];
		$update += $this->resolveParams($values);
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

	public function resolveParams(\stdClass $values): array {
		switch ($values->linkType) {
			case 'article':
				return ['path' => $values->linkedArticleSlug];
			case 'gallery':
				if (is_numeric($values->galleryId)) {
					return ['target_id' => (int) $values->galleryId];
				}
			default: return [];
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
				'linkedArticleSlug' => $row->path ?? null,
			];
		}
		if ($row->presenter === 'Gallery') {
			return [
				'linkType' => 'gallery',
				'galleryId' => $row->target_id ?? null,
			] + $basic;
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
			->where('deleted_at', null)
			->where('menu_key', $menuKey)
			->max('position') + 1;
	}

	public function getRootItemsForSelect(string $menuKey, ?int $excludeId = null): array {
		$q = $this->db->table(self::MENUS_TABLE)
			->where('deleted_at', null)
			->where('menu_key', $menuKey)
			->where('parent_id IS NULL')
			->where('presenter', null);

		if ($excludeId) {
			$q->where('id != ?', $excludeId);
		}
		return $q->fetchPairs('id', 'label');
	}

	public function softDelete(int $id, int $userId): void {
		$this->db->beginTransaction();
		$ids = [$id];

		$children = $this->db->table(self::MENUS_TABLE)
			->where('parent_id', $id)
			->fetchAll();

		foreach ($children as $child) {
			$ids[] = $child->id;
		}

		$this->db->table(self::MENUS_TABLE)
			->where('id', $ids)
			->update([
				'deleted_at' => new \DateTime(),
				'deleted_by' => $userId,
			]);
		$this->db->commit();
	}

}
