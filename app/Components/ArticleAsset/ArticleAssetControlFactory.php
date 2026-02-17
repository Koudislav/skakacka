<?php

declare(strict_types=1);

namespace App\Components\ArticleAsset;

interface ArticleAssetControlFactory {

	public function create(): ArticleAssetControl;

}
