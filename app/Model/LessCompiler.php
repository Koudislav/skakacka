<?php

declare(strict_types=1);

namespace App\Model;

use Less_Cache;

final class LessCompiler {

	private const PROD_CHECK_INTERVAL = 3600;

	public function __construct (
		private string $inputDir,
		private string $outputDir,
		private string $finalCssFolder,
		private string $wwwDir,
		private bool $debugMode,
	) {}

	public function getCss(string $entryFile, bool $minify = false): array {
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

}