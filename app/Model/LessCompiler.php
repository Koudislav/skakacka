<?php

declare(strict_types=1);

namespace App\Model;

use Less_Cache;
use Nette\Utils\FileSystem;

final class LessCompiler {

	private const PROD_CHECK_INTERVAL = 3600;
	private const CHECKS = [
		'hex_pick_' => [
			'valueTest' => '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
			'newKey' => [
				'prefix' => '@',
				'suffix' => '',
				'replace' => [
					[
						'needle' => 'hex_pick_',
						'replace' => '',
					],
					[
						'needle' => '_',
						'replace' => '-',
					]
				],
			],
		],
	];

	public function __construct (
		private string $inputDir,
		private string $outputDir,
		private string $finalCssFolder,
		private string $wwwDir,
		private bool $debugMode,
		private Config $config,
	) {}

	public function getCss(string $entryFile, bool $minify = false): array {
		$this->makeLessFromConfig();
		$finalCssFile = $this->finalCssFolder . '/' . substr($entryFile, 0, -5) . '.css';

		$options = [
			'cache_dir' => $this->outputDir,
			'compress'  => $minify,
			'sourceMap' => !$minify,
		];

		$files = [$this->inputDir . '/' . $entryFile => '/css/'];

		if (!$this->debugMode && file_exists($finalCssFile)) {
			$age = time() - filemtime($finalCssFile);
			if ($age < self::PROD_CHECK_INTERVAL) {
				$final = str_replace($this->wwwDir, '', $finalCssFile) . '?v=' . filemtime($finalCssFile);
				return ['cache' => null, 'final' => $final];
			}
		}

		//DEV or expired
		$cachedFileName = Less_Cache::Get($files, $options);

		if (!file_exists($finalCssFile) || filemtime($finalCssFile) < filemtime($this->outputDir . '/' . $cachedFileName)) {
			copy($this->outputDir . '/' . $cachedFileName, $finalCssFile);
		}

		$final = str_replace($this->wwwDir, '', $finalCssFile) . '?v=' . filemtime($finalCssFile);

		return ['cache' => $this->outputDir . '/' . $cachedFileName, 'final' => $final];
	}

	private function makeLessFromConfig(): void {
		$lessConfigPath = $this->outputDir . '/config.less';
		$needsGenerate = !file_exists($lessConfigPath) || (time() - filemtime($lessConfigPath)) > self::PROD_CHECK_INTERVAL;

		if (!$needsGenerate) {
			return;
		}
		$checks = self::CHECKS;

		$rows = [];
		foreach ($this->config->toArray() as $configKey => $configValue) {
			foreach ($checks as $checkKey => $checkValues) {
				if (str_starts_with($configKey, $checkKey)) {
					if (isset($checkValues['valueTest']) && !preg_match($checkValues['valueTest'], (string)$configValue)) {
						continue 2; // skip to next config item
					}
					$newKey = $checkValues['newKey']['prefix'] ?? '';
					if (isset($checkValues['newKey']['replace'])) {
						foreach ($checkValues['newKey']['replace'] as $replace) {
							$configKey = str_replace($replace['needle'], $replace['replace'], $configKey);
						}
					}
					$newKey .= $configKey;
					$newKey .= $checkValues['newKey']['suffix'] ?? '';
					$rows[$newKey] = $configValue;
				}
			}
		}
		$this->writeToFile($lessConfigPath, $rows);
	}

	private function writeToFile(string $filePath, array $rows): void {
		$content = '';
		foreach ($rows as $key => $value) {
			$content .= "{$key}: {$value};" . PHP_EOL;
		}
		FileSystem::createDir(dirname($filePath));
		FileSystem::write($filePath, $content);
	}

}