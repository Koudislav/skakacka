<?php

declare(strict_types=1);

namespace App\Presentation\Administration\Galleries;

use App\Forms\BootstrapFormFactory;
use App\Repository\GalleryRepository;
use App\Service\DiskQuotaService;
use App\Service\ImageService;
use Nette\Forms\Form;

final class GalleriesPresenter extends \App\Presentation\Administration\BaseAdministrationPresenter {

	/** @var GalleryRepository @inject */
	public $galleryRepository;

	/** @var \App\Service\DiskQuotaService @inject */
	public DiskQuotaService $diskQuota;

	/** @var \App\Service\ImageService @inject */
	public ImageService $imageService;

	public function renderDefault(int $galleryId = 0): void {
		$this->template->currentGalleryId = $galleryId;
		$galleries = $this->galleryRepository->findAllGalleries();
		$this->template->galleries = $galleries;
	}

	public function renderGalleryImages(int $galleryId): void {
		$this->template->currentGalleryId = $galleryId;
		$galleryData = $this->galleryRepository->getGalleryById($galleryId);

		if (!$galleryData) {
			$this->flashMessage('Zvolená galerie neexistuje.', 'danger');
			$this->redirect('Galleries:default');
		}
		$this->template->gallery = $galleryData;
		$images = $this->galleryRepository->findPicturesByGalleryId($galleryId);

		if ($images) {
			$coverNotSet = true;
			foreach ($images as $image) {
				$firstId = $firstId ?? $image->id;
				if ($image->is_cover) {
					$coverNotSet = false;
					break;
				}
			}
			if ($coverNotSet) {
				$this->galleryRepository->setGalleryCover($firstId);
				$images = $this->galleryRepository->findPicturesByGalleryId($galleryId);
			}
		}
		$this->template->images = $images;
	}

	public function createComponentGalleryForm() {
		$form = BootstrapFormFactory::create('oneLine');
		$form->addText('title', 'Název galerie:')
			->setRequired('Zadejte název galerie.');
		$form->addTextArea('description', 'Popis galerie:')
			->setHtmlAttribute('class', 'tiny-editor');

		$form->addCheckbox('is_published', 'Publikováno')
			->setDefaultValue(false);
		$form->addSubmit('submit', 'Uložit')
			->setHtmlAttribute('class', 'btn btn-primary');

		if ((int) $this->getParameter('galleryId') !== 0) {
			$galleryData = $this->galleryRepository->getGalleryById((int) $this->getParameter('galleryId'));
			$form->setDefaults([
				'title' => $galleryData->title,
				'description' => $galleryData->description,
				'is_published' => $galleryData->is_published == 1,
			]);
		}
		$form->onSuccess[] = [$this, 'galleryFormSubmitted'];
		return $form;
	}

	public function createComponentUploadPhotosForm(): Form {
		$form = BootstrapFormFactory::create('oneLine');
		$form->addMultiUpload('photos', 'Nahrát fotografie:')
			->setHtmlId('photos-upload-input')
			->setHtmlAttribute('accept', '.jpg,.jpeg,.png,.webp')
			->setRequired('Vyberte alespoň jeden obrázek k nahrání.')
			->addRule(Form::Image, 'Pouze soubory typu JPEG, PNG nebo WebP jsou povoleny.');
		return $form;
	}

	public function galleryFormSubmitted(Form $form, \stdClass $values) {
		$galleryId = (int) $this->getParameter('galleryId');

		if ($galleryId !== 0) {
			$update = $this->galleryRepository->updateGallery($galleryId, $values, $this->user->getId());
			if (!$update) {
				$this->flashMessage('Nebyly provedeny žádné změny.', 'danger');
			} else {
				$this->flashMessage('Galerie byla úspěšně upravena.', 'success');
			}
			$this->redirect('this');
		} else {
			$create = $this->galleryRepository->createGallery($values, $this->user->getId());
			$this->flashMessage('Galerie byla úspěšně vytvořena.', 'success');
			$this->redirect('this', ['galleryId' => $create->id]);
		}
	}

	public function actionUploadPhoto(): void {	
		$file = $this->getHttpRequest()->getFile('photo');
		$galleryId = (int) $this->getHttpRequest()->getPost('galleryId');
		$gallery = $this->galleryRepository->getGalleryById($galleryId);

		if (!$gallery) {
			$this->sendJson([
				'status' => 'error',
				'message' => 'Zvolená galerie neexistuje.',
			]);
			return;
		}

		if (!$file || !$file->isOk() || !$file->isImage()) {
			$this->sendJson([
				'status' => 'error',
				'message' => 'Neplatný soubor. Ujistěte se, že jste vybrali platný obrázek.',
			]);
			return;
		}

		$size = $file->getSize();
		if (!$this->diskQuota->canStore($size)) {
			$this->sendJson([
				'status' => 'error',
				'message' => 'Disková kvóta byla překročena.',
			]);
			$this->flashMessage('Disková kvóta byla překročena. Nelze nahrát další soubor.', 'danger');
			return;
		}
		// TEMP FILE
		$tempPath = $file->getTemporaryFile();
		$originalName = $file->getUntrustedName();
		$originalName = mb_substr($originalName, 0, 255);

		// DB INSERT
		$photoId = $this->galleryRepository->insertPhoto([
			'gallery_id' => $galleryId,
			'original_name' => $originalName,
			'processed' => 'processing',
			'created_at' => new \DateTime(),
			'created_by' => (int) $this->user->getId(),
		]);

		try {
			$paths = $this->imageService->processUpload(
				$tempPath,
				$galleryId,
				$originalName
			);
			$this->galleryRepository->updatePhoto($photoId, array_merge($paths, [
				'processed' => 'done',
			]));

			$status = 'ok';
		}
		catch (\Throwable $e) {
			$this->galleryRepository->updatePhoto($photoId, [
				'processed' => 'error',
			]);
			$this->flashMessage('Nastala chyba při zpracování obrázku: ' . $e->getMessage(), 'danger');
			\Tracy\Debugger::log($e, 'image');

			$status = 'error';
			$message = 'Nastala chyba při zpracování obrázku: ' . $e->getMessage();
		}
		$this->diskQuota->clearCache();
		$response['status'] = $status;
		if (!empty($message)) {
			$response['message'] = $message;
		}	
		$this->sendJson($response);
	}

	public function handleToggleImageVisibility(?int $imageId): void {
		if (!$imageId) {
			$this->sendJson(['status' => 'error']);
			return;
		}
		$this->galleryRepository->toggleImageVisibility($imageId);
		$this->sendJson(['status' => 'ok']);
	}

	public function handleDeleteImage(?int $imageId): void {
		if (!$imageId) {
			$this->sendJson(['status' => 'error']);
			return;
		}

		$image = $this->galleryRepository->getImageById($imageId);
			if (!$image) {
			$this->sendJson(['status' => 'error']);
			return;
		}
		$isCover = $image->is_cover;

		// 1) smaž soubory z filesystemu
		$this->imageService->deleteImageFiles([
			'path_original' => $image->path_original,
			'path_big' => $image->path_big,
			'path_medium' => $image->path_medium,
			'path_small' => $image->path_small,
		]);

		// 2) smaž z DB
		$this->galleryRepository->deleteImage($imageId);

		$this->diskQuota->clearCache();
		$response = ['status' => 'ok'];
		if ($isCover) {
			$response['refresh'] = true;
		}
		$this->sendJson($response);
	}

	public function handleUpdateImageDescription(?int $imageId, ?string $description): void {
		if (!$imageId || $description === null) {
			$this->sendJson(['status' => 'error']);
			return;
		}
		$this->galleryRepository->updateImageDescription($imageId, $description);
		$this->sendJson(['status' => 'ok']);
	}

	public function handleSetGalleryCover(?int $imageId): void {
		if (!$imageId) {
			$this->sendJson(['status' => 'error']);
			return;
		}
		$this->galleryRepository->setGalleryCover($imageId);
		$this->sendJson(['status' => 'ok']);
	}

	public function handleReorderGalleries(): void {
		if (!$this->isAjax()) {
			$this->error('Invalid request');
		}
		$data = json_decode($this->getHttpRequest()->getRawBody(), true);
		if (!isset($data['order'])) {
			$this->sendJson(['status' => 'error']);
			return;
		}
		$this->galleryRepository->updateGalleryPositions($data['order']);
		$this->sendJson(['status' => 'ok']);
	}

	public function handleReorderImages(): void {
		if (!$this->isAjax()) {
			$this->error('Invalid request');
		}
		$data = json_decode($this->getHttpRequest()->getRawBody(), true);
		if (!isset($data['order'])) {
			$this->sendJson(['status' => 'error']);
			return;
		}
		$this->galleryRepository->updateImagePositions($data['order']);
		$this->sendJson(['status' => 'ok']);
	}

}
