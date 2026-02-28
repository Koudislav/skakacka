<?php

declare(strict_types=1);

namespace App\Config;

use Nette\Caching\Storage;
use App\Repository\ConfigurationRepository;
use App\Services\BootstrapHelper;
use Nette\Caching\Cache;

class Config implements \ArrayAccess {
	protected $config = [];
	protected $cache;

	public const SPECIAL_ENUMS = [
		'bgColor',
		'spacing',
		'padding',
		'margin',
	];
	public const CACHE_KEY = 'app_config';

	public function __construct(
		private Storage $storage,
		private ConfigurationRepository $configurationRepository,
		) {
		$this->cache = new Cache($this->storage);
		$this->config = $this->loadConfig();
	}

	public function toArray(): array {
		return $this->config;
	}

	protected function loadConfig(): array {
		$config = $this->cache->load(self::CACHE_KEY, function (&$dependencies) {
			$dependencies[Cache::Expire] = '10 minutes';
			$this->ensureConsistency();
			$rawConfig = $this->configurationRepository->getAll(true);
			$config = [];

			// defaults from SCHEMA
			foreach (DefaultConfiguration::SCHEMA as $key => $definition) {
				$config[$key] = $definition['default'];
			}
			// override from DB
			$dbConfig = $this->processConfig($rawConfig);
			
			foreach ($dbConfig as $key => $value) {
				$config[$key] = $value;
			}
			return $config;
		});
		return $config;
	}

	public function processConfig($rawConfig): array {
		$config = [];
		foreach ($rawConfig as $item) {
			$key = $item->key;
			$type = $item->type;

			switch ($type) {
				case 'int':
					$config[$key] = (int)$item->value_int;
					break;
				case 'float':
					$config[$key] = (float)$item->value_float;
					break;
				case 'bool':
					$config[$key] = $item->value_bool === 1 ? true : false;
					break;
				case 'string':
					$config[$key] = $item->value_string;
					break;
				case 'enum':
					if (in_array($item->enum_options, self::SPECIAL_ENUMS, true)) {
						$options = $this->resolveSpecials($item->enum_options);
					} else {
						$options = isset($item->enum_options) ? explode(',', $item->enum_options) : [];
					}
					if (!in_array($item->value_string, $options, true)) {
						$config[$key] = DefaultConfiguration::SCHEMA[$key]['default'];
					} else {
						$config[$key] = $item->value_string;
					}
					break;
		
				default:
					break;
			}
		}
		return $config;
	}

	public function offsetExists($offset): bool {
		return array_key_exists($offset, $this->config);
	}

	public function offsetGet($offset): mixed {
		if (!array_key_exists($offset, $this->config)) {
			throw new \Exception\ConfigException("Přístup k neexistující proměnné konfigurace: '{$offset}'");
		}
		return $this->config[$offset];
	}

	public function offsetSet($offset, $value): void {
		throw new \Exception\ConfigException("Přepisování konfigurace za běhu: '{$offset}={$value}'");
	}

	public function offsetUnset($offset): void {
		throw new \Exception\ConfigException("Mazání konfigurace za běhu: '{$offset}'");
	}

	public function resolveSpecials(string $type): array {
		return match ($type) {
			'bgColor' => array_keys(BootstrapHelper::BOOTSTRAP_BG_COLOR_ENUM),
			'spacing' => array_keys(BootstrapHelper::getSpacingOptions()),
			'padding' => array_keys(BootstrapHelper::getSpacingOptions('padding')),
			'margin' => array_keys(BootstrapHelper::getSpacingOptions('margin')),
			default => [],
		};
	}

	public function ensureConsistency(): void {
		// Načti všechny existující konfigurace
		$allConfig = [];
		foreach ($this->configurationRepository->getAll() as $row) {
			$allConfig[$row->key] = $row;
		}

		foreach (DefaultConfiguration::SCHEMA as $key => $item) {
			if (!isset($allConfig[$key])) {
				// Vytvoření nového řádku
				$data = [
					'key' => $key,
					'category' => $item['category'],
					'type' => $item['type'],
					'description' => $item['description'] ?? $key,
					'active' => $item['active'] ?? 1,
					'sort_order' => $item['sort_order'] ?? 1000,
					'edited_by' => null,
				];

				// Nastavení defaultní hodnoty podle typu
				switch ($item['type']) {
					case 'bool':
						$data['value_bool'] = $item['default'] ? 1 : 0;
						break;
					case 'int':
						$data['value_int'] = (int)$item['default'];
						break;
					case 'float':
						$data['value_float'] = (float)$item['default'];
						break;
					case 'string':
					case 'label':
						$data['value_string'] = (string)$item['default'];
						break;
					case 'enum':
						$data['value_string'] = (string)$item['default'];
						$data['enum_options'] = implode(',', $item['enum_options'] ?? []);
						break;
				}

				$this->configurationRepository->insert($data);
				continue;
			}

			$row = $allConfig[$key];

			if ($item['type'] === 'enum') {

				$expectedEnum = implode(',', $item['enum_options'] ?? []);

				if ($row->enum_options !== $expectedEnum) {
					$row->update([
						'enum_options' => $expectedEnum
					]);
				}
			}
			if (isset($item['active']) && $row->active != $item['active']) {
				$row->update([
					'active' => $item['active']
				]);
			}
		}
	}

}

namespace Exception;

class ConfigException extends \Exception {
}
