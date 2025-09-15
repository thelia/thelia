<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Controller\Admin;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\File\Exception\ProcessFileException;
use Thelia\Core\File\FileConfiguration;
use Thelia\Core\File\FileManager;
use Thelia\Core\File\Service\FileDeleteService;
use Thelia\Core\File\Service\FilePositionService;
use Thelia\Core\File\Service\FileProcessorService;
use Thelia\Core\File\Service\FileUpdateService;
use Thelia\Core\File\Service\FileVisibilityService;
use Thelia\Core\Security\AccessManager;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Tools\Rest\ResponseRest;
use Thelia\Tools\URL;

/**
 * Controller for file management (images and documents).
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class FileController extends BaseAdminController
{
    public const MODULE_RIGHT = 'thelia';

    public function saveFileAjaxAction(
        EventDispatcherInterface $eventDispatcher,
        FileProcessorService $fileProcessorService,
        int $parentId,
        string $parentType,
        string $objectType,
        array $validMimeTypes = [],
        array $extBlackList = [],
    ): Response {
        if (($response = $this->checkAuth($this->getAdminResources()->getResource($parentType, static::MODULE_RIGHT), [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $this->checkXmlHttpRequest();

        if ($this->getRequest()->isMethod('POST')) {
            /** @var UploadedFile $fileBeingUploaded */
            $fileBeingUploaded = $this->getRequest()->files->get('file');

            try {
                $fileProcessorService->processFile(
                    $eventDispatcher,
                    $fileBeingUploaded,
                    $parentId,
                    $parentType,
                    $objectType,
                    $validMimeTypes,
                    $extBlackList,
                );
            } catch (ProcessFileException $e) {
                return new ResponseRest($e->getMessage(), 'text', $e->getCode());
            }

            return new ResponseRest(['status' => true, 'message' => '']);
        }

        return new Response('', Response::HTTP_NOT_FOUND);
    }

    public function saveImageAjaxAction(
        EventDispatcherInterface $eventDispatcher,
        FileProcessorService $fileProcessorService,
        int $parentId,
        string $parentType,
    ): Response {
        $config = FileConfiguration::getImageConfig();

        return $this->saveFileAjaxAction(
            $eventDispatcher,
            $fileProcessorService,
            $parentId,
            $parentType,
            $config['objectType'],
            $config['validMimeTypes'],
            $config['extBlackList'],
        );
    }

    public function saveDocumentAjaxAction(
        EventDispatcherInterface $eventDispatcher,
        FileProcessorService $fileProcessorService,
        int $parentId,
        string $parentType,
    ): Response {
        $config = FileConfiguration::getDocumentConfig();

        return $this->saveFileAjaxAction(
            $eventDispatcher,
            $fileProcessorService,
            $parentId,
            $parentType,
            $config['objectType'],
            $config['validMimeTypes'],
            $config['extBlackList'],
        );
    }

    public function getImageListAjaxAction(int $parentId, string $parentType): Response
    {
        $this->checkAuth($this->getAdminResources()->getResource($parentType, static::MODULE_RIGHT), [], AccessManager::UPDATE);
        $this->checkXmlHttpRequest();

        $successUrl = $this->getRequest()->get('successUrl');

        $args = [
            'imageType' => $parentType,
            'parentId' => $parentId,
            'successUrl' => $successUrl,
        ];

        return $this->render('includes/image-upload-list-ajax', $args);
    }

    public function getDocumentListAjaxAction(int $parentId, string $parentType): Response
    {
        $this->checkAuth($this->getAdminResources()->getResource($parentType, static::MODULE_RIGHT), [], AccessManager::UPDATE);
        $this->checkXmlHttpRequest();
        $args = ['documentType' => $parentType, 'parentId' => $parentId];

        return $this->render('includes/document-upload-list-ajax', $args);
    }

    public function getImageFormAjaxAction(int $parentId, string $parentType): Response
    {
        $this->checkAuth($this->getAdminResources()->getResource($parentType, static::MODULE_RIGHT), [], AccessManager::UPDATE);
        $this->checkXmlHttpRequest();
        $successUrl = $this->getRequest()->get('successUrl');

        $args = [
            'imageType' => $parentType,
            'parentId' => $parentId,
            'successUrl' => $successUrl,
        ];

        return $this->render('includes/image-upload-form', $args);
    }

    public function getDocumentFormAjaxAction(int $parentId, string $parentType): Response
    {
        $this->checkAuth($this->getAdminResources()->getResource($parentType, static::MODULE_RIGHT), [], AccessManager::UPDATE);
        $this->checkXmlHttpRequest();
        $args = ['documentType' => $parentType, 'parentId' => $parentId];

        return $this->render('includes/document-upload-form', $args);
    }

    public function viewImageAction(
        int $imageId,
        string $parentType,
        FileManager $fileManager,
    ): Response {
        if (($response = $this->checkAuth($this->getAdminResources()->getResource($parentType, static::MODULE_RIGHT), [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $imageModel = $fileManager->getModelInstance('image', $parentType);

        $image = $imageModel->getQueryInstance()->findPk($imageId);

        $redirectUrl = $image->getRedirectionUrl();

        return $this->render('image-edit', [
            'imageId' => $imageId,
            'imageType' => $parentType,
            'redirectUrl' => $redirectUrl,
            'formId' => $imageModel->getUpdateFormId(),
            'breadcrumb' => $image->getBreadcrumb(
                $this->getRouter($this->getCurrentRouter()),
                'images',
                $this->getCurrentEditionLocale(),
            ),
        ]);
    }

    public function viewDocumentAction(
        int $documentId,
        string $parentType,
        FileManager $fileManager,
    ): Response {
        if (($response = $this->checkAuth($this->getAdminResources()->getResource($parentType, static::MODULE_RIGHT), [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $documentModel = $fileManager->getModelInstance('document', $parentType);

        $document = $documentModel->getQueryInstance()->findPk($documentId);

        $redirectUrl = $document->getRedirectionUrl();

        return $this->render('document-edit', [
            'documentId' => $documentId,
            'documentType' => $parentType,
            'redirectUrl' => $redirectUrl,
            'formId' => $documentModel->getUpdateFormId(),
            'breadcrumb' => $document->getBreadcrumb(
                $this->getRouter($this->getCurrentRouter()),
                'documents',
                $this->getCurrentEditionLocale(),
            ),
        ]);
    }

    public function updateImageAction(
        EventDispatcherInterface $eventDispatcher,
        FileUpdateService $fileUpdateService,
        int $imageId,
        string $parentType,
        FileManager $fileManager,
    ): RedirectResponse|Response {
        if (($response = $this->checkAuth($this->getAdminResources()->getResource($parentType, static::MODULE_RIGHT), [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $fileModelInstance = $fileManager->getModelInstance('image', $parentType);
        $fileUpdateForm = $this->createForm($fileModelInstance->getUpdateFormId());

        try {
            $fileInstance = $fileUpdateService->updateFile(
                $eventDispatcher,
                $imageId,
                $parentType,
                'image',
                TheliaEvents::IMAGE_UPDATE,
                $fileUpdateForm,
            );

            $this->adminLogAppend(
                $this->getAdminResources()->getResource($parentType, static::MODULE_RIGHT),
                AccessManager::UPDATE,
                \sprintf(
                    '%s with Ref %s (ID %d) modified',
                    ucfirst('image'),
                    $fileInstance->getTitle(),
                    $fileInstance->getId(),
                ),
                $fileInstance->getId(),
            );

            if ('close' === $this->getRequest()->get('save_mode')) {
                return $this->generateRedirect(
                    URL::getInstance()->absoluteUrl($fileInstance->getRedirectionUrl(), ['current_tab' => 'images']),
                );
            }

            return $this->generateSuccessRedirect($fileUpdateForm);
        } catch (FormValidationException $e) {
            $message = \sprintf('Please check your input: %s', $e->getMessage());
            $fileUpdateService->logUpdateError('image', $message);
            $fileUpdateForm->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($fileUpdateForm)
                ->setGeneralError($message);
        } catch (\Exception $e) {
            $message = \sprintf('Sorry, an error occurred: %s', $e->getMessage().' '.$e->getFile());
            $fileUpdateService->logUpdateError('image', $message);
            $fileUpdateForm->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($fileUpdateForm)
                ->setGeneralError($message);
        }

        return $this->render('image-edit', [
            'imageId' => $imageId,
            'imageType' => $parentType,
            'redirectUrl' => $fileManager->getModelInstance('image', $parentType)->getQueryInstance()->findPk($imageId)->getRedirectionUrl(),
            'formId' => $fileModelInstance->getUpdateFormId(),
        ]);
    }

    public function updateDocumentAction(
        EventDispatcherInterface $eventDispatcher,
        FileUpdateService $fileUpdateService,
        int $documentId,
        string $parentType,
        FileManager $fileManager,
    ): RedirectResponse|Response {
        if (($response = $this->checkAuth($this->getAdminResources()->getResource($parentType, static::MODULE_RIGHT), [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $fileModelInstance = $fileManager->getModelInstance('document', $parentType);
        $fileUpdateForm = $this->createForm($fileModelInstance->getUpdateFormId());

        try {
            $fileInstance = $fileUpdateService->updateFile(
                $eventDispatcher,
                $documentId,
                $parentType,
                'document',
                TheliaEvents::DOCUMENT_UPDATE,
                $fileUpdateForm,
            );

            $this->adminLogAppend(
                $this->getAdminResources()->getResource($parentType, static::MODULE_RIGHT),
                AccessManager::UPDATE,
                \sprintf(
                    '%s with Ref %s (ID %d) modified',
                    ucfirst('document'),
                    $fileInstance->getTitle(),
                    $fileInstance->getId(),
                ),
                $fileInstance->getId(),
            );

            if ('close' === $this->getRequest()->get('save_mode')) {
                return $this->generateRedirect(
                    URL::getInstance()->absoluteUrl($fileInstance->getRedirectionUrl(), ['current_tab' => 'documents']),
                );
            }

            return $this->generateSuccessRedirect($fileUpdateForm);
        } catch (FormValidationException $e) {
            $message = \sprintf('Please check your input: %s', $e->getMessage());
            $fileUpdateService->logUpdateError('document', $message);
            $fileUpdateForm->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($fileUpdateForm)
                ->setGeneralError($message);
        } catch (\Exception $e) {
            $message = \sprintf('Sorry, an error occurred: %s', $e->getMessage().' '.$e->getFile());
            $fileUpdateService->logUpdateError('document', $message);
            $fileUpdateForm->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($fileUpdateForm)
                ->setGeneralError($message);
        }

        return $this->render('document-edit', [
            'documentId' => $documentId,
            'documentType' => $parentType,
            'redirectUrl' => $fileManager->getModelInstance('document', $parentType)->getQueryInstance()->findPk($documentId)->getRedirectionUrl(),
            'formId' => $fileModelInstance->getUpdateFormId(),
        ]);
    }

    public function deleteImageAction(
        EventDispatcherInterface $eventDispatcher,
        FileDeleteService $fileDeleteService,
        int $imageId,
        string $parentType,
    ): Response {
        $this->checkAuth($this->getAdminResources()->getResource($parentType, static::MODULE_RIGHT), [], AccessManager::UPDATE);
        $this->checkXmlHttpRequest();

        try {
            $message = $fileDeleteService->deleteFile(
                $eventDispatcher,
                $imageId,
                $parentType,
                'image',
                TheliaEvents::IMAGE_DELETE,
            );

            $this->adminLogAppend(
                $this->getAdminResources()->getResource($parentType, static::MODULE_RIGHT),
                AccessManager::UPDATE,
                $message,
            );
        } catch (\Exception) {
            return $this->pageNotFound();
        }

        return new Response($message);
    }

    public function deleteDocumentAction(
        EventDispatcherInterface $eventDispatcher,
        FileDeleteService $fileDeleteService,
        int $documentId,
        string $parentType,
    ): Response {
        $this->checkAuth($this->getAdminResources()->getResource($parentType, static::MODULE_RIGHT), [], AccessManager::UPDATE);
        $this->checkXmlHttpRequest();

        try {
            $message = $fileDeleteService->deleteFile(
                $eventDispatcher,
                $documentId,
                $parentType,
                'document',
                TheliaEvents::DOCUMENT_DELETE,
            );

            $this->adminLogAppend(
                $this->getAdminResources()->getResource($parentType, static::MODULE_RIGHT),
                AccessManager::UPDATE,
                $message,
            );
        } catch (\Exception) {
            return $this->pageNotFound();
        }

        return new Response($message);
    }

    public function updateImagePositionAction(
        EventDispatcherInterface $eventDispatcher,
        FilePositionService $filePositionService,
        string $parentType,
    ): Response {
        $this->checkAuth($this->getAdminResources()->getResource($parentType, static::MODULE_RIGHT), [], AccessManager::UPDATE);
        $this->checkXmlHttpRequest();

        $imageId = (int) $this->getRequest()->request->get('image_id');
        $position = (int) $this->getRequest()->request->get('position');

        try {
            $message = $filePositionService->updateFilePosition(
                $eventDispatcher,
                $parentType,
                $imageId,
                'image',
                TheliaEvents::IMAGE_UPDATE_POSITION,
                $position,
            );
        } catch (\Exception) {
            return $this->pageNotFound();
        }

        return new Response($message);
    }

    public function updateDocumentPositionAction(
        EventDispatcherInterface $eventDispatcher,
        FilePositionService $filePositionService,
        string $parentType,
    ): Response {
        $this->checkAuth($this->getAdminResources()->getResource($parentType, static::MODULE_RIGHT), [], AccessManager::UPDATE);
        $this->checkXmlHttpRequest();

        $documentId = (int) $this->getRequest()->request->get('document_id');
        $position = (int) $this->getRequest()->request->get('position');

        try {
            $message = $filePositionService->updateFilePosition(
                $eventDispatcher,
                $parentType,
                $documentId,
                'document',
                TheliaEvents::DOCUMENT_UPDATE_POSITION,
                $position,
            );
        } catch (\Exception) {
            return $this->pageNotFound();
        }

        return new Response($message);
    }

    public function toggleVisibilityImageAction(
        EventDispatcherInterface $eventDispatcher,
        FileVisibilityService $fileVisibilityService,
        string $parentType,
        int $documentId,
    ): Response {
        $this->checkAuth($this->getAdminResources()->getResource($parentType, static::MODULE_RIGHT), [], AccessManager::UPDATE);
        $this->checkXmlHttpRequest();

        try {
            $message = $fileVisibilityService->toggleFileVisibility(
                $eventDispatcher,
                $documentId,
                $parentType,
                'image',
                TheliaEvents::IMAGE_TOGGLE_VISIBILITY,
            );
        } catch (\Exception) {
            return $this->pageNotFound();
        }

        return new Response($message);
    }

    public function toggleVisibilityDocumentAction(
        EventDispatcherInterface $eventDispatcher,
        FileVisibilityService $fileVisibilityService,
        string $parentType,
        int $documentId,
    ): Response {
        $this->checkAuth($this->getAdminResources()->getResource($parentType, static::MODULE_RIGHT), [], AccessManager::UPDATE);
        $this->checkXmlHttpRequest();

        try {
            $message = $fileVisibilityService->toggleFileVisibility(
                $eventDispatcher,
                $documentId,
                $parentType,
                'document',
                TheliaEvents::DOCUMENT_TOGGLE_VISIBILITY,
            );
        } catch (\Exception) {
            return $this->pageNotFound();
        }

        return new Response($message);
    }

    public function updateImageTitleAction(
        FileUpdateService $fileUpdateService,
        int $imageId,
        string $parentType,
    ): RedirectResponse {
        if (($response = $this->checkAuth($this->getAdminResources()->getResource($parentType, static::MODULE_RIGHT), [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $title = $this->getRequest()->request->get('title');
        $locale = $this->getRequest()->request->get('locale');

        $fileUpdateService->updateFileTitle(
            $imageId,
            $parentType,
            'image',
            $title,
            $locale,
        );

        return $this->generateRedirect(
            URL::getInstance()->absoluteUrl($this->getRequest()->request->get('success_url'), ['current_tab' => 'images']),
        );
    }
}
