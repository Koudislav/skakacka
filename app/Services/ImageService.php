<?php

declare(strict_types=1);

namespace App\Service;

use Nette\Utils\FileSystem;
use Nette\Utils\Image;
use Nette\Utils\Random;

final class ImageService {

	private const SIZES = [
		//[width, height, quality]
		'big' => [1600, 1200, 85],
		'medium' => [800, 600, 80],
		'small' => [400, 300, 75],
	];

	public function __construct(
		private string $wwwDir,
	) {}

	/**
	 * Uloží obrázek + vygeneruje náhledy (jpg)
	 */
	public function processUpload(string $sourcePath, int $galleryId, string $originalName): array {
		$info = getimagesize($sourcePath);

		if ($info === false) {
			throw new \RuntimeException('Neplatný obrázek.');
		}
		[$w, $h] = $info;

		if ($w * $h > 40_000_000) {
			throw new \RuntimeException('Obrázek je příliš velký.');
		}

		$originalExt = match ($info[2]) {
			IMAGETYPE_JPEG => 'jpg',
			IMAGETYPE_PNG  => 'png',
			IMAGETYPE_WEBP => 'webp',
			default => throw new \RuntimeException('Nepodporovaný formát obrázku.'),
		};

		$filename = Random::generate(20);

		$galleryDir = $this->wwwDir . '/assets/gallery/' . $galleryId;
		FileSystem::createDir($galleryDir . '/original');


		$originalFilename = $filename . '.' . $originalExt;
		$originalPath = $galleryDir . '/original/' . $originalFilename;

		FileSystem::copy($sourcePath, $originalPath);

		$paths = [
			'path_original' => 'assets/gallery/' . $galleryId . '/original/' . $originalFilename,
			'path_big' => null,
			'path_medium' => null,
			'path_small' => null,
			'filename' => $filename,
			'original_name' => $originalName,
		];

		// === THUMBNAILS ===
		foreach (self::SIZES as $key => [$w, $h, $q]) {
			$img = Image::fromFile($originalPath);
			$img->resize($w, $h, Image::ShrinkOnly);

			$thumbFilename = $key . '_' . $filename . '.jpg';
			$thumbPath = $galleryDir . '/' . $thumbFilename;
			$img->save($thumbPath, $q, Image::JPEG);
			unset($img);

			$paths['path_' . $key] = 'assets/gallery/' . $galleryId . '/' . $thumbFilename;
		}

		return $paths;
	}

	public function deleteImageFiles(array $imagePaths): void {
		foreach (['path_original', 'path_big', 'path_medium', 'path_small'] as $key) {
			if (!empty($imagePaths[$key])) {
				$filePath = $this->wwwDir . '/' . $imagePaths[$key];
				if (file_exists($filePath)) {
					@unlink($filePath);
				}
			}
		}
	}

}