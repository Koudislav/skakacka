<?php

declare(strict_types=1);

namespace App\Components\Calendar;

use DateTimeImmutable;
use Nette\Application\UI\Control;

final class CalendarControl extends Control {

	public const MODE_BINARY = 'binary';
	public const MODE_EVENTS = 'events';

	/** @var callable[] */
	public array $onLoadData = [];

	private string $mode = self::MODE_BINARY;
	private int $year;
	private int $month;
	private $formatter;

	public function __construct(?int $year = null, ?int $month = null) {
		$now = new DateTimeImmutable();

		$this->year = $year ?? (int)$now->format('Y');
		$this->month = $month ?? (int)$now->format('n');
		$this->formatter = new \IntlDateFormatter('cs_CZ', \IntlDateFormatter::LONG, \IntlDateFormatter::NONE, 'Europe/Prague', null, 'LLLL');
	}

	public function setMode(string $mode): void {
		$this->mode = $mode;
	}

	public function handleChangeMonth(int $year, int $month): void {
		if ($month < 1) {
			$month = 12;
			$year--;
		} elseif ($month > 12) {
			$month = 1;
			$year++;
		}

		$this->year = $year;
		$this->month = $month;
	
		if ($this->getPresenter()->isAjax()) {
			$this->redrawControl('calendarArea');
			$this->redrawControl('calendar');
		} else {
			$this->redirect('this');
		}
	}

	public function handleToggleDay(string $date): void {
		bdump("Toggle day: $date");
		$dateObj = DateTimeImmutable::createFromFormat('Y-m-d', $date);
		$this->year = (int)$dateObj->format('Y');
		$this->month = (int)$dateObj->format('n');
		foreach ($this->onLoadData as $name => $callback) {
			if ($name === 'toggleCallback') {
				$callback($dateObj);
			}
		}
		if ($this->getPresenter()->isAjax()) {
			$this->redrawControl('calendarArea');
			$this->redrawControl('calendar');
		} else {
			$this->redirect('this');
		}
	}

	public function render(): void {
		$template = $this->getTemplate();
		$template->setFile(__DIR__ . '/CalendarControl.latte');

		$firstDay = new DateTimeImmutable(sprintf('%04d-%02d-01', $this->year, $this->month));
		$monthName = $this->formatter->format($firstDay);
		$daysInMonth = (int)$firstDay->format('t');
		$firstDayOfWeek = (int)$firstDay->format('N');

		// rozsah pro data
		$from = $firstDay->setTime(0, 0, 0);
		$to = $firstDay->modify('last day of this month')->setTime(23, 59, 59);

		// načtení dat přes callback
		$data = [];
		foreach ($this->onLoadData as $name => $callback) {
			if ($name === 'dataCallback') {
				$data = $callback($from, $to);
			}
		}

		// generování gridu
		$calendar = [];

		for ($i = 1; $i < $firstDayOfWeek; $i++) {
			$calendar[] = null;
		}

		for ($day = 1; $day <= $daysInMonth; $day++) {
			$date = sprintf('%04d-%02d-%02d', $this->year, $this->month, $day);

			$calendar[] = [
				'day' => $day,
				'date' => $date,
				'data' => $data[$date] ?? null,
			];
		}

		while (count($calendar) % 7 !== 0) {
			$calendar[] = null;
		}

		$template->today = (new DateTimeImmutable())->format('Y-m-d');
		$template->calendar = $calendar;
		$template->mode = $this->mode;
		$template->year = $this->year;
		$template->month = $this->month;
		$template->monthName = $monthName;

		$template->render();
	}

}
