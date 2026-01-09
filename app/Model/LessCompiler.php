<?php

declare(strict_types=1);

namespace App\Model;

use Less_Cache;

final class LessCompiler {

	public function __construct (
		private string $inputDir,
		private string $outputDir,
		private string $finalCssFolder,
		private string $wwwDir,
	) {}

	public function getCss(string $entryFile, bool $minify = false): array {
		$finalCssFile = $this->finalCssFolder . '/' . substr($entryFile, 0, -5) . '.css';
		$options = [
			'cache_dir' => $this->outputDir,
			'compress'  => $minify,
			'sourceMap' => !$minify,
		];

		// Less_Cache hlídá změny ve všech souborech (entry + importy)
		$files = [$this->inputDir . '/' . $entryFile => '/css/'];

		$cachedFileName = Less_Cache::Get($files, $options);

		if (!file_exists($finalCssFile) || filemtime($finalCssFile) < filemtime($this->outputDir . '/' . $cachedFileName)) {
			copy($this->outputDir . '/' . $cachedFileName, $finalCssFile);
		}
		$final = str_replace($this->wwwDir, '', $finalCssFile) . '?v=' . filemtime($finalCssFile);
		return ['cache' => $this->outputDir . '/' . $cachedFileName, 'final' => $final];
	}

}