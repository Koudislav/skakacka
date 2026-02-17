<?php

declare(strict_types=1);

namespace App\Repository;

use DateTimeImmutable;
use Nette\Database\Explorer;

final class CalendarRepository {

	private ?int $blockingTypeId = null;

	public function __construct(
		private Explorer $db,
	) {}

	// ============================================================
	// 🔴 BINARY MODE
	// ============================================================
	// vrací: ['YYYY-MM-DD' => true]
	// pouze akce které blokují kapacitu
	// ============================================================

	public function getBinaryData(
		DateTimeImmutable $from,
		DateTimeImmutable $to,
		?int $resourceId = null,
	): array {
		$rows = $this->db->query(
			'
			SELECT
				DATE(eo.starts_at) AS day,
				COUNT(*) AS cnt
			FROM event_occurrences eo
			JOIN events e ON e.id = eo.event_id
			JOIN event_types et ON et.id = e.event_type_id
			WHERE
				e.status = ?
				AND et.blocks_capacity = 1
				AND eo.starts_at <= ?
				AND eo.ends_at >= ?
				' .
				($resourceId ? 'AND e.resource_id = ?' : '') . '
			GROUP BY day
			',
			'confirmed',
			$to,
			$from,
			...($resourceId ? [$resourceId] : []),
		)->fetchAll();

		$out = [];

		bdump($rows);
		foreach ($rows as $row) {
			bdump($row->day->format('Y-m-d'));
			$out[$row->day->format('Y-m-d')] = true;
		}

		return $out;
	}

	// ============================================================
	// 🟡 EVENTS MODE
	// ============================================================
	// vrací:
	// ['YYYY-MM-DD' => [ ['title'=>...], ... ]]
	// ============================================================

	public function getEventsData(
		DateTimeImmutable $from,
		DateTimeImmutable $to,
		?int $resourceId = null,
	): array {
		$rows = $this->db->query(
			'
			SELECT
				DATE(eo.starts_at) AS day,
				e.id,
				e.title,
				e.color_override,
				et.color AS type_color,
				eo.starts_at,
				eo.ends_at
			FROM event_occurrences eo
			JOIN events e ON e.id = eo.event_id
			JOIN event_types et ON et.id = e.event_type_id
			WHERE
				e.status = ?
				AND eo.starts_at <= ?
				AND eo.ends_at >= ?
				' .
				($resourceId ? 'AND e.resource_id = ?' : '') . '
			ORDER BY eo.starts_at ASC
			',
			'confirmed',
			$to,
			$from,
			...($resourceId ? [$resourceId] : []),
		)->fetchAll();

		$out = [];

		foreach ($rows as $row) {
			$color = $row->color_override ?: $row->type_color;

			$out[$row->day][] = [
				'id' => (int)$row->id,
				'title' => $row->title,
				'color' => $color,
				'starts_at' => $row->starts_at,
				'ends_at' => $row->ends_at,
			];
		}

		return $out;
	}

	// ============================================================
	// 🎯 UNIVERZÁLNÍ METODA (doporučená)
	// ============================================================

	public function getCalendarData(
		DateTimeImmutable $from,
		DateTimeImmutable $to,
		string $mode,
		?int $resourceId = null,
	): array {
		return match ($mode) {
			'binary' => $this->getBinaryData($from, $to, $resourceId),
			'events' => $this->getEventsData($from, $to, $resourceId),
			default => [],
		};
	}

	private function getBlockingTypeId(): int {
		if ($this->blockingTypeId !== null) {
			return $this->blockingTypeId;
		}

		$id = $this->db->fetchField(
			'SELECT id FROM event_types WHERE slug = ? AND is_active = 1',
			'blocking'
		);

		if (!$id) {
			throw new \RuntimeException('Event type "blocking" not found.');
		}

		return $this->blockingTypeId = (int) $id;
	}

	public function toggleBlockingDay(\DateTimeImmutable $day, ?int $resourceId = null): void {
		bdump('lala');
		$start = $day->setTime(0, 0, 0);
		$end   = $day->setTime(23, 59, 59);

		$blockingTypeId = $this->getBlockingTypeId();

		// 🔍 existuje už blokace?
		$existing = $this->db->fetch(
			'
			SELECT e.id
			FROM events e
			JOIN event_occurrences eo ON eo.event_id = e.id
			WHERE
				e.event_type_id = ?
				AND eo.starts_at = ?
				AND eo.ends_at = ?
				' . ($resourceId ? 'AND e.resource_id = ?' : ''),
			$blockingTypeId,
			$start,
			$end,
			...($resourceId ? [$resourceId] : [])
		);

		// =============================
		// ❌ existuje → smaž
		// =============================
		if ($existing) {
			$this->db->table('event_occurrences')
				->where('event_id', $existing->id)
				->delete();

			$this->db->table('events')
				->where('id', $existing->id)
				->delete();

			return;
		}

		// =============================
		// ✅ neexistuje → vytvoř
		// =============================

		$eventId = $this->db->table('events')->insert([
			'event_type_id' => $blockingTypeId,
			'title' => 'Blokace',
			'status' => 'confirmed',
			'resource_id' => $resourceId,
			'created_at' => new \DateTimeImmutable(),
		])->getPrimary();

		$this->db->table('event_occurrences')->insert([
			'event_id' => $eventId,
			'starts_at' => $start,
			'ends_at' => $end,
		]);
	}

}