<?php

declare(strict_types=1);

namespace App\Model;

class Config implements \ArrayAccess {

	const SCHEMA_PATH = 'config/schema.neon';
	const CONFIG_PATH = 'config/config.neon';

	protected $default = [];
	protected $custom = [];
	protected $config = [];

	public function __construct(
			protected $appDir,
		) {
		$this->default = $this->loadDefaults();
		$this->custom = $this->loadConfig();
		$this->config = $this->custom + $this->default;
	}

	public function getArray(): array {
		return $this->config;
	}

	public function getDefault(): array {
		return $this->default;
	}

	public function getCustom(): array {
		return $this->custom;
	} 

	protected function loadDefaults(): array {
		$schema = \Nette\Neon\Neon::decode(file_get_contents($this->appDir . self::SCHEMA_PATH));
		$defaultConfig = [];
		foreach ($schema as $name => $section) {
			foreach ($section['items'] as $key => $value) {
				$defaultConfig[$key] = $value['default'];
			}
		}
		return $defaultConfig;
	}

	protected function loadConfig(): array {
		$config = \Nette\Neon\Neon::decode(file_get_contents($this->appDir . self::CONFIG_PATH));
		return $config ?? [];
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
