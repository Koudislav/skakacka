<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Caching\Storage;
use App\Repository\ConfigurationRepository;
use Nette\Caching\Cache;

class Config implements \ArrayAccess {
	protected $config = [];
	protected $cache;

	public function __construct(
		private Storage $storage,
		private ConfigurationRepository $configurationRepository,
		) {
		$this->cache = new Cache($this->storage);
		$this->config = $this->loadConfig();
	}

	public function getArray(): array {
		return $this->config;
	}

	protected function loadConfig(): array {
		$config = $this->cache->load('app_config', function (&$dependencies) {
			$dependencies[Cache::Expire] = '10 minutes';
			$rawConfig = $this->configurationRepository->getAll(true);
			$config = $this->processConfig($rawConfig);
			return $config ?? [];
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
				default:
					break;
			}
		}
		return $config;
	}

	public function offsetExists($offset): bool {
		return isset($this->config[$offset]);
	}

	public function offsetGet($offset):mixed {
		if (!isset($this->config[$offset])) {
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

}

namespace Exception;

class ConfigException extends \Exception {
    //put your code here
}
