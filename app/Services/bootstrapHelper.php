<?php

declare(strict_types=1);

namespace App\Services;

use Nette\Utils\Html;
use Stringable;

class BootstrapHelper {

	public const BOOTSTRAP_POSITION_ENUM = [
		'start' => 'Vlevo',
		'center' => 'Uprostřed',
		'end' => 'Vpravo',
		'between' => 'Roztáhnout do krajů',
		'around' => 'Roztáhnout (s mezerami kolem)',
	];

	public const BOOTSTRAP_TEXT_COLOR_ENUM = [
		'primary' => 'Primární',
		'secondary' => 'Sekundární',
		'success' => 'Úspěch',
		'danger' => 'Nebezpečí',
		'warning' => 'Varování',
		'info' => 'Info',
		'light' => 'Světlá',
		'dark' => 'Tmavá',

		'body' => 'Text body',
		'body-secondary' => 'Text body secondary',
		'body-tertiary' => 'Text body tertiary',
		'body-emphasis' => 'Text emphasis',
		'black' => 'Černá',
		'white' => 'Bílá',
		'muted' => 'Tlumená',
	];

	public const BOOTSTRAP_BG_COLOR_ENUM = [
		'primary' => 'Primární',
		'primary-subtle' => 'Primární jemná',
		'secondary' => 'Sekundární',
		'secondary-subtle' => 'Sekundární jemná',
		'success' => 'Úspěch',
		'success-subtle' => 'Úspěch jemná',
		'danger' => 'Nebezpečí',
		'danger-subtle' => 'Nebezpečí jemná',
		'warning' => 'Varování',
		'warning-subtle' => 'Varování jemná',
		'info' => 'Info',
		'info-subtle' => 'Info jemná',
		'light' => 'Světlá',
		'light-subtle' => 'Světlá jemná',
		'dark' => 'Tmavá',
		'dark-subtle' => 'Tmavá jemná',

		'body' => 'Pozadí body',
		'body-secondary' => 'Pozadí body secondary',
		'body-tertiary' => 'Pozadí body tertiary',
		'transparent' => 'Transparentní',
		'white' => 'Bílá',
		'black' => 'Černá',
	];

	public static function getBootstrapPositionEnum(): array {
		return self::BOOTSTRAP_POSITION_ENUM;
	}

	public static function getBootstrapTextColorEnum(): array {
		return self::BOOTSTRAP_TEXT_COLOR_ENUM;
	}

	public static function getBootstrapBgColorEnum(): array {
		return self::BOOTSTRAP_BG_COLOR_ENUM;
	}

	public static function getEnum($type) {
		$enum = match ($type) {
			'position' => self::getBootstrapPositionEnum(),
			'color' => self::getBootstrapTextColorEnum(),
			'bgColor' => self::getBootstrapBgColorEnum(),
			default => throw new \InvalidArgumentException("Neznámý typ enum: $type"),
		};
		return self::putBadges($enum, $type);
	}

	public static function putBadges(array $items, $type): array {
		if ($type === 'position') {
			return $items;
		}
		$badges = [];
		foreach ($items as $key => $value) {
			$badges[$key] = self::putBadge($key, $value, $type);
		}
		return $badges;
	}

	public static function putBadge(string $color, string $text, $type): Stringable {
		$prefix = match ($type) {
			'color' => 'text-',
			'bgColor' => 'bg-',
			default => '',
		};
		$label = Html::el('span')
			->class('badge ' . $prefix . $color)
			->setText($text ?: $color);
		return $label;
	}

}
