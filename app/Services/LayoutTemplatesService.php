<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\TemplateRepository;
use Latte\Engine;
use Latte\Loaders\StringLoader;
use Tracy\Debugger;

class LayoutTemplatesService {

	public static function renderLayout(string $layout, array $variableSchema, array $data, ?array $params = null): string {
		$latte = new Engine();
		$latte->setLoader(new StringLoader());

		$preparsedLayout = self::parseForeachBlocks($layout);

		$parsedLayout = self::holdersToVariables($preparsedLayout, $variableSchema, $data);

		return $latte->renderToString($parsedLayout, $data);
	}

	public static function holdersToVariables(string $layout, array $variableSchema, array $data): string {
		$data = self::resolveDataForVariables($variableSchema, $data);
		return preg_replace_callback(TemplateRepository::VARIABLE_REGEX, function ($matches) use ($variableSchema, $data) {
			$variableName = $matches[1];
			return self::resolveVariableStringForTeplate($variableName, $variableSchema[$variableName] ?? null, $data[$variableName] ?? null);
		}, $layout);
	}

	public static function sanitizeImageUrl(?string $url): ?string {
		$holder = '/assets/images/picture-not-found.webp';
		if (!$url) return $holder;
		$url = trim($url);
		if (str_starts_with($url, '/')) return $url;
		if (!filter_var($url, FILTER_VALIDATE_URL)) return $holder;
		$scheme = parse_url($url, PHP_URL_SCHEME);
		if (!in_array(strtolower($scheme), ['http', 'https'], true)) return $holder;
		return $url;
	}

	public static function resolveVariableStringForTeplate(string $variableName, ?array $config, string|array|null $value): string {
		if (!$config) {
			Debugger::log("Variable '{$variableName}' not found in schema.", 'warning');
			return '';
		}
		if ($config['type'] === 'image') {
			return self::imageLatte($variableName);
		}
		if ($config['type'] === 'text') {
			if ($config['required'] && empty($value)) {
				Debugger::log("Variable '{$variableName}' is required but has no value.", 'warning');
				throw new \InvalidArgumentException("Variable '{$variableName}' is required but has no value.");
			} else {
				return "{\$$variableName}";
			}
		}
		if ($config['type'] === 'html') {
			return "{\$$variableName|noescape}";
		}

		if ($config['type'] === 'repeater') {
			if (empty($config['repeater_type']) || $config['repeater_type'] !== 'ul') {

			}
			return "<ul class='repeater-list'>{foreach \$$variableName as \$item}<li>{\$item['value']}</li>{/foreach}</ul>";
		}

		if ($config['type' === 'image']) {
			return self::imageLatte($variableName);
		}

		return "{\${$variableName}}";
	}

	public static function imageLatte(?string $variableName): string {
		return "<img src=\"{\$$variableName|noescape}\" alt=\"Image\" class=\"layout-image img-fluid\" />";
	}

	public static function resolveDataForVariables(array $variableSchema, array $data): array {
		$resolved = [];
		foreach ($variableSchema as $name => $config) {
			$value = $data[$name] ?? null;
			if ($config['type'] === 'image') {
				$resolved[$name] = self::sanitizeImageUrl($value);
			} else {
				$resolved[$name] = $value;
			}
		}
		return $resolved;
	}

	public static function parseForeachBlocks(string $layout): string {
		return preg_replace_callback(
			'/\{\{FOREACH\s+(\w+)\}\}(.*?)\{\{\/FOREACH\}\}/s',
			function ($matches) {
				$varName = $matches[1];
				$content = $matches[2];
				// uvnitř foreach nahradíme {{repeater}} → {$item['value']}
				$content = preg_replace(
					'/\{\{' . $varName . '\}\}/',
					"{\$item['value']}",
					$content
				);

				return "{foreach \$$varName as \$item}$content{/foreach}";
			},
			$layout
		);
	}

}
