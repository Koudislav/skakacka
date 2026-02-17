<?php

namespace App\Presentation\Calendar;

use App\Components\Calendar\CalendarControl;
use App\Repository\CalendarRepository;

class CalendarPresenter extends \App\Presentation\BasePresenter {

	/** @var CalendarRepository @inject */
	public $calendarRepository;

	public function actionDefault(): void {
		$this->seo->breadcrumbs = [
			$this->homeString => $this->link('//Home:default'),
			'Kalendář' => $this->link('//Calendar:default'),
		];
	}

	protected function createComponentCalendar(): CalendarControl {
		$c = new CalendarControl();

		// přepnutí režimu
		$mode = CalendarControl::MODE_BINARY; // nebo MODE_EVENTS
		$c->setMode($mode);

		$c->onLoadData['dataCallback'] = function (\DateTimeImmutable $from, \DateTimeImmutable $to) use ($mode) {
			return $this->calendarRepository->getCalendarData(
				$from,
				$to,
				$mode,
				null // nebo resource_id
			);
		};
		$c->onLoadData['toggleCallback'] = function (\DateTimeImmutable $date) {
			$this->calendarRepository->toggleBlockingDay($date);
		};

			return $c;
	}

}
