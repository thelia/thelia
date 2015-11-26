<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Controller\Admin;

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Core\Event\File\FileToggleVisibilityEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdateFilePositionEvent;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Files\Exception\ProcessFileException;
use Thelia\Files\FileConfiguration;
use Thelia\Files\FileManager;
use Thelia\Files\FileModelInterface;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\Lang;
use Thelia\Tools\Rest\ResponseRest;
use Thelia\Tools\URL;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Control View and Action (Model) via Events
 * Control Files and Images
 *
 * @package File
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class FileController extends BaseAdminController
{
    /**
     * Get the FileManager
     *
     * @return FileManager
     */
    public function getFileManager()
    {
        return $this->container->get('thelia.file_manager');
    }

    /**
     * Manage how a file collection has to be saved
     *
     * @param  int      $parentId       Parent id owning files being saved
     * @param  string   $parentType     Parent Type owning files being saved (product, category, content, etc.)
     * @param  string   $objectType     Object type, e.g. image or document
     * @param  array    $validMimeTypes an array of valid mime types. If empty, any mime type is allowed.
     * @param  array    $extBlackList   an array of blacklisted extensions.
     * @return Response
     */
    public function saveFileAjaxAction(
        $parentId,
        $parentType,
        $objectType,
        $validMimeTypes = array(),
        $extBlackList = array()
    ) {
        if (null !== $response = $this->checkAuth(AdminResources::retrieve($parentType), array(), AccessManager::UPDATE)) {
            return $response;
        }

        $this->checkXmlHttpRequest();


        if ($this->getRequest()->isMethod('POST')) {
            /** @var UploadedFile $fileBeingUploaded */
            $fileBeingUploaded = $this->getRequest()->files->get('file');
            try {
                $this->processImage(
                    $fileBeingUploaded,
                    $parentId,
                    $parentType,
                    $objectType,
                    $validMimeTypes,
                    $extBlackList
                );
            } catch (ProcessFileException $e) {
                return new ResponseRest($e->getMessage(), 'text', $e->getCode());
            }

            return new ResponseRest(array('status' => true, 'message' => ''));
        }

        return new Response('', 404);
    }

    /**
     *
     * Process file uploaded
     *
     * @param UploadedFile $fileBeingUploaded
     * @param int $parentId
     * @param string $parentType
     * @param string $objectType
     * @param array $validMimeTypes
     * @param array $extBlackList
     * @return ResponseRest
     */
    public function processImage(
        $fileBeingUploaded,
        $parentId,
        $parentType,
        $objectType,
        $validMimeTypes = array(),
        $extBlackList = array()
    ) {
        $fileManager = $this->getFileManager();

        // Validate if file is too big
        if ($fileBeingUploaded->getError() == 1) {
            $message = $this->getTranslator()
                ->trans(
                    'File is too large, please retry with a file having a size less than %size%.',
                    array('%size%' => ini_get('upload_max_filesize')),
                    'core'
                );

            throw new ProcessFileException($message, 403);
        }

        $message = null;
        $realFileName = $fileBeingUploaded->getClientOriginalName();

        if (! empty($validMimeTypes)) {
            $mimeType = $fileBeingUploaded->getMimeType();

            if (!isset($validMimeTypes[$mimeType])) {
                $message = $this->getTranslator()
                    ->trans(
                        'Only files having the following mime type are allowed: %types%',
                        [ '%types%' => implode(', ', array_keys($validMimeTypes))]
                    );
            } else {
                $regex = "#^(.+)\.(".implode("|", $validMimeTypes[$mimeType]).")$#i";

                if (!preg_match($regex, $realFileName)) {
                    $message = $this->getTranslator()
                        ->trans(
                            "There's a conflict between your file extension \"%ext\" and the mime type \"%mime\"",
                            [
                                '%mime' => $mimeType,
                                '%ext' => $fileBeingUploaded->getClientOriginalExtension()
                            ]
                        );
                }
            }
        }

        if (!empty($extBlackList)) {
            $regex = "#^(.+)\.(".implode("|", $extBlackList).")$#i";

            if (preg_match($regex, $realFileName)) {
                $message = $this->getTranslator()
                    ->trans(
                        'Files with the following extension are not allowed: %extension, please do an archive of the file if you want to upload it',
                        [
                            '%extension' => $fileBeingUploaded->getClientOriginalExtension(),
                        ]
                    );
            }
        }

        if ($message !== null) {
            throw new ProcessFileException($message, 415);
        }

        $fileModel = $fileManager->getModelInstance($objectType, $parentType);

        $parentModel = $fileModel->getParentFileModel();

        if ($parentModel === null || $fileModel === null || $fileBeingUploaded === null) {
            throw new ProcessFileException('', 404);
        }

        $fileModel
            ->setParentId($parentId)
            ->setLocale(Lang::getDefaultLanguage()->getLocale())
            ->setTitle($parentModel->getTitle())
        ;

        $fileCreateOrUpdateEvent = new FileCreateOrUpdateEvent($parentId);
        $fileCreateOrUpdateEvent->setModel($fileModel);
        $fileCreateOrUpdateEvent->setUploadedFile($fileBeingUploaded);
        $fileCreateOrUpdateEvent->setParentName($parentModel->getTitle());

        // Dispatch Event to the Action
        $this->dispatch(
            TheliaEvents::IMAGE_SAVE,
            $fileCreateOrUpdateEvent
        );

        $this->adminLogAppend(
            AdminResources::retrieve($parentType),
            AccessManager::UPDATE,
            $this->getTranslator()->trans(
                'Saving %obj% for %parentName% parent id %parentId%',
                array(
                    '%parentName%' => $fileCreateOrUpdateEvent->getParentName(),
                    '%parentId%' => $fileCreateOrUpdateEvent->getParentId(),
                    '%obj%' => $objectType
                )
            )
        );

        //return new ResponseRest(array('status' => true, 'message' => ''));
        return $fileCreateOrUpdateEvent;
    }

    /**
     * Manage how a image collection has to be saved
     *
     * @param int    $parentId   Parent id owning images being saved
     * @param string $parentType Parent Type owning images being saved
     *
     * @return Response
     */
    public function saveImageAjaxAction($parentId, $parentType)
    {
        $config = FileConfiguration::getImageConfig();
        return $this->saveFileAjaxAction(
            $parentId,
            $parentType,
            $config['objectType'],
            $config['validMimeTypes'],
            $config['extBlackList']
        );
    }

    /**
     * Manage how a document collection has to be saved
     *
     * @param int    $parentId   Parent id owning documents being saved
     * @param string $parentType Parent Type owning documents being saved
     *
     * @return Response
     */
    public function saveDocumentAjaxAction($parentId, $parentType)
    {
        $config = FileConfiguration::getDocumentConfig();
        return $this->saveFileAjaxAction(
            $parentId,
            $parentType,
            $config['objectType'],
            $config['validMimeTypes'],
            $config['extBlackList']
        );
    }

    /**
     * Manage how a image list will be displayed in AJAX
     *
     * @param int    $parentId   Parent id owning images being saved
     * @param string $parentType Parent Type owning images being saved
     *
     * @return Response
     */
    public function getImageListAjaxAction($parentId, $parentType)
    {
        $this->checkAuth(AdminResources::retrieve($parentType), array(), AccessManager::UPDATE);
        $this->checkXmlHttpRequest();
        $args = array('imageType' => $parentType, 'parentId' => $parentId);

        return $this->render('includes/image-upload-list-ajax', $args);
    }

    /**
     * Manage how a document list will be displayed in AJAX
     *
     * @param int    $parentId   Parent id owning documents being saved
     * @param string $parentType Parent Type owning documents being saved
     *
     * @return Response
     */
    public function getDocumentListAjaxAction($parentId, $parentType)
    {
        $this->checkAuth(AdminResources::retrieve($parentType), array(), AccessManager::UPDATE);
        $this->checkXmlHttpRequest();
        $args = array('documentType' => $parentType, 'parentId' => $parentId);

        return $this->render('includes/document-upload-list-ajax', $args);
    }

    /**
     * Manage how an image list will be uploaded in AJAX
     *
     * @param int    $parentId   Parent id owning images being saved
     * @param string $parentType Parent Type owning images being saved
     *
     * @return Response
     */
    public function getImageFormAjaxAction($parentId, $parentType)
    {
        $this->checkAuth(AdminResources::retrieve($parentType), array(), AccessManager::UPDATE);
        $this->checkXmlHttpRequest();
        $args = array('imageType' => $parentType, 'parentId' => $parentId);

        return $this->render('includes/image-upload-form', $args);
    }

    /**
     * Manage how an document list will be uploaded in AJAX
     *
     * @param int    $parentId   Parent id owning documents being saved
     * @param string $parentType Parent Type owning documents being saved
     *
     * @return Response
     */
    public function getDocumentFormAjaxAction($parentId, $parentType)
    {
        $this->checkAuth(AdminResources::retrieve($parentType), array(), AccessManager::UPDATE);
        $this->checkXmlHttpRequest();
        $args = array('documentType' => $parentType, 'parentId' => $parentId);

        return $this->render('includes/document-upload-form', $args);
    }

    /**
     * Manage how an image is viewed
     *
     * @param int    $imageId    Parent id owning images being saved
     * @param string $parentType Parent Type owning images being saved
     *
     * @return Response
     */
    public function viewImageAction($imageId, $parentType)
    {
        if (null !== $response = $this->checkAuth(AdminResources::retrieve($parentType), array(), AccessManager::UPDATE)) {
            return $response;
        }
        $fileManager = $this->getFileManager();
        $imageModel = $fileManager->getModelInstance('image', $parentType);

        $image = $imageModel->getQueryInstance()->findPk($imageId);

        $redirectUrl = $image->getRedirectionUrl();

        return $this->render('image-edit', array(
            'imageId' => $imageId,
            'imageType' => $parentType,
            'redirectUrl' => $redirectUrl,
            'formId' => $imageModel->getUpdateFormId(),
            'breadcrumb' => $image->getBreadcrumb(
                $this->getRouter($this->getCurrentRouter()),
                $this->container,
                'images',
                $this->getCurrentEditionLocale()
            )
        ));
    }

    /**
     * Manage how an document is viewed
     *
     * @param int    $documentId Parent id owning images being saved
     * @param string $parentType Parent Type owning images being saved
     *
     * @return Response
     */
    public function viewDocumentAction($documentId, $parentType)
    {
        if (null !== $response = $this->checkAuth(AdminResources::retrieve($parentType), array(), AccessManager::UPDATE)) {
            return $response;
        }

        $fileManager = $this->getFileManager();
        $documentModel = $fileManager->getModelInstance('document', $parentType);

        $document = $documentModel->getQueryInstance()->findPk($documentId);

        $redirectUrl = $document->getRedirectionUrl();

        return $this->render('document-edit', array(
            'documentId' => $documentId,
            'documentType' => $parentType,
            'redirectUrl' => $redirectUrl,
            'formId' => $documentModel->getUpdateFormId(),
            'breadcrumb' => $document->getBreadcrumb(
                $this->getRouter($this->getCurrentRouter()),
                $this->container,
                'documents',
                $this->getCurrentEditionLocale()
            )
        ));
    }

    /**
     * Manage how a file is updated
     *
     * @param int    $fileId     File identifier
     * @param string $parentType Parent Type owning file being saved
     * @param string $objectType the type of the file, image or document
     * @param string $eventName  the event type.
     *
     * @return FileModelInterface
     */
    protected function updateFileAction($fileId, $parentType, $objectType, $eventName)
    {
        $message = false;

        $fileManager = $this->getFileManager();

        $fileModelInstance = $fileManager->getModelInstance($objectType, $parentType);

        $fileUpdateForm = $this->createForm($fileModelInstance->getUpdateFormId());

        /** @var FileModelInterface $file */
        $file = $fileModelInstance->getQueryInstance()->findPk($fileId);

        try {
            $oldFile = clone $file;

            if (null === $file) {
                throw new \InvalidArgumentException(sprintf('%d %s id does not exist', $fileId, $objectType));
            }

            $data = $this->validateForm($fileUpdateForm)->getData();

            $event = new FileCreateOrUpdateEvent(null);

            if (array_key_exists('visible', $data)) {
                $file->setVisible($data['visible'] ? 1 : 0);
            }

            $file->setLocale($data['locale']);

            if (array_key_exists('title', $data)) {
                $file->setTitle($data['title']);
            }
            if (array_key_exists('chapo', $data)) {
                $file->setChapo($data['chapo']);
            }
            if (array_key_exists('description', $data)) {
                $file->setDescription($data['description']);
            }
            if (array_key_exists('postscriptum', $data)) {
                $file->setPostscriptum($data['postscriptum']);
            }

            if (isset($data['file'])) {
                $file->setFile($data['file']);
            }

            $event->setModel($file);
            $event->setOldModel($oldFile);

            $files = $this->getRequest()->files;

            $fileForm = $files->get($fileUpdateForm->getName());

            if (isset($fileForm['file'])) {
                $event->setUploadedFile($fileForm['file']);
            }

            $this->dispatch($eventName, $event);

            $fileUpdated = $event->getModel();

            $this->adminLogAppend(
                AdminResources::retrieve($parentType),
                AccessManager::UPDATE,
                sprintf(
                    '%s with Ref %s (ID %d) modified',
                    ucfirst($objectType),
                    $fileUpdated->getTitle(),
                    $fileUpdated->getId()
                ),
                $fileUpdated->getId()
            );

            if ($this->getRequest()->get('save_mode') == 'close') {
                if ($objectType == 'document') {
                    $tab = 'documents';
                } else {
                    $tab = 'images';
                }

                return $this->generateRedirect(
                    URL::getInstance()->absoluteUrl($file->getRedirectionUrl(), ['current_tab' => $tab])
                );
            } else {
                return $this->generateSuccessRedirect($fileUpdateForm);
            }
        } catch (FormValidationException $e) {
            $message = sprintf('Please check your input: %s', $e->getMessage());
        } catch (PropelException $e) {
            $message = $e->getMessage();
        } catch (\Exception $e) {
            $message = sprintf('Sorry, an error occurred: %s', $e->getMessage().' '.$e->getFile());
        }

        if ($message !== false) {
            Tlog::getInstance()->error(sprintf('Error during %s editing : %s.', $objectType, $message));

            $fileUpdateForm->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($fileUpdateForm)
                ->setGeneralError($message);
        }

        return $file;
    }

    /**
     * Manage how an image is updated
     *
     * @param int    $imageId    Parent id owning images being saved
     * @param string $parentType Parent Type owning images being saved
     *
     * @return Response
     */
    public function updateImageAction($imageId, $parentType)
    {
        if (null !== $response = $this->checkAuth(AdminResources::retrieve($parentType), array(), AccessManager::UPDATE)) {
            return $response;
        }

        $imageInstance = $this->updateFileAction($imageId, $parentType, 'image', TheliaEvents::IMAGE_UPDATE);

        if ($imageInstance instanceof \Symfony\Component\HttpFoundation\Response) {
            return $imageInstance;
        } else {
            return $this->render('image-edit', array(
                'imageId' => $imageId,
                'imageType' => $parentType,
                'redirectUrl' => $imageInstance->getRedirectionUrl(),
                'formId' => $imageInstance->getUpdateFormId()
            ));
        }
    }

    /**
     * Manage how an document is updated
     *
     * @param int    $documentId Parent id owning documents being saved
     * @param string $parentType Parent Type owning documents being saved
     *
     * @return Response
     */
    public function updateDocumentAction($documentId, $parentType)
    {
        if (null !== $response = $this->checkAuth(AdminResources::retrieve($parentType), array(), AccessManager::UPDATE)) {
            return $response;
        }

        $documentInstance = $this->updateFileAction($documentId, $parentType, 'document', TheliaEvents::DOCUMENT_UPDATE);

        if ($documentInstance instanceof \Symfony\Component\HttpFoundation\Response) {
            return $documentInstance;
        } else {
            return $this->render('document-edit', array(
                'documentId' => $documentId,
                'documentType' => $parentType,
                'redirectUrl' => $documentInstance->getRedirectionUrl(),
                'formId' => $documentInstance->getUpdateFormId()
            ));
        }
    }

    /**
     * Manage how a file has to be deleted
     *
     * @param int    $fileId     Parent id owning file being deleted
     * @param string $parentType Parent Type owning file being deleted
     * @param string $objectType the type of the file, image or document
     * @param string $eventName  the event type.
     *
     * @return Response
     */
    public function deleteFileAction($fileId, $parentType, $objectType, $eventName)
    {
        $message = null;

        $this->checkAuth(AdminResources::retrieve($parentType), array(), AccessManager::UPDATE);
        $this->checkXmlHttpRequest();

        $fileManager = $this->getFileManager();
        $modelInstance = $fileManager->getModelInstance($objectType, $parentType);

        $model = $modelInstance->getQueryInstance()->findPk($fileId);

        if ($model == null) {
            return $this->pageNotFound();
        }

        // Feed event
        $fileDeleteEvent = new FileDeleteEvent($model);

        // Dispatch Event to the Action
        try {
            $this->dispatch($eventName, $fileDeleteEvent);

            $this->adminLogAppend(
                AdminResources::retrieve($parentType),
                AccessManager::UPDATE,
                $this->getTranslator()->trans(
                    'Deleting %obj% for %id% with parent id %parentId%',
                    array(
                        '%obj%' => $objectType,
                        '%id%' => $fileDeleteEvent->getFileToDelete()->getId(),
                        '%parentId%' => $fileDeleteEvent->getFileToDelete()->getParentId(),
                    )
                ),
                $fileDeleteEvent->getFileToDelete()->getId()
            );
        } catch (\Exception $e) {
            $message = $this->getTranslator()->trans(
                'Fail to delete  %obj% for %id% with parent id %parentId% (Exception : %e%)',
                array(
                    '%obj%' => $objectType,
                    '%id%' => $fileDeleteEvent->getFileToDelete()->getId(),
                    '%parentId%' => $fileDeleteEvent->getFileToDelete()->getParentId(),
                    '%e%' => $e->getMessage()
                )
            );

            $this->adminLogAppend(
                AdminResources::retrieve($parentType),
                AccessManager::UPDATE,
                $message,
                $fileDeleteEvent->getFileToDelete()->getId()
            );
        }

        if (null === $message) {
            $message = $this->getTranslator()->trans(
                '%obj%s deleted successfully',
                ['%obj%' => ucfirst($objectType)],
                'image'
            );
        }

        return new Response($message);
    }

    /**
     * Manage how an image has to be deleted (AJAX)
     *
     * @param int    $imageId    Parent id owning image being deleted
     * @param string $parentType Parent Type owning image being deleted
     *
     * @return Response
     */
    public function deleteImageAction($imageId, $parentType)
    {
        return $this->deleteFileAction($imageId, $parentType, 'image', TheliaEvents::IMAGE_DELETE);
    }

    /**
     * Manage how a document has to be deleted (AJAX)
     *
     * @param int    $documentId Parent id owning document being deleted
     * @param string $parentType Parent Type owning document being deleted
     *
     * @return Response
     */
    public function deleteDocumentAction($documentId, $parentType)
    {
        return $this->deleteFileAction($documentId, $parentType, 'document', TheliaEvents::DOCUMENT_DELETE);
    }

    public function updateFilePositionAction($parentType, $parentId, $objectType, $eventName)
    {
        $message = null;

        $position = $this->getRequest()->request->get('position');

        $this->checkAuth(AdminResources::retrieve($parentType), array(), AccessManager::UPDATE);
        $this->checkXmlHttpRequest();

        $fileManager = $this->getFileManager();
        $modelInstance = $fileManager->getModelInstance($objectType, $parentType);
        $model = $modelInstance->getQueryInstance()->findPk($parentId);

        if ($model === null || $position === null) {
            return $this->pageNotFound();
        }

        // Feed event
        $event = new UpdateFilePositionEvent(
            $modelInstance->getQueryInstance($parentType),
            $parentId,
            UpdateFilePositionEvent::POSITION_ABSOLUTE,
            $position
        );

        // Dispatch Event to the Action
        try {
            $this->dispatch($eventName, $event);
        } catch (\Exception $e) {
            $message = $this->getTranslator()->trans(
                'Fail to update %type% position: %err%',
                [ '%type%' => $objectType, '%err%' => $e->getMessage() ]
            );
        }

        if (null === $message) {
            $message = $this->getTranslator()->trans(
                '%type% position updated',
                [ '%type%' => ucfirst($objectType) ]
            );
        }

        return new Response($message);
    }

    public function updateImagePositionAction($parentType, /** @noinspection PhpUnusedParameterInspection */  $parentId)
    {
        $imageId = $this->getRequest()->request->get('image_id');

        return $this->updateFilePositionAction($parentType, $imageId, 'image', TheliaEvents::IMAGE_UPDATE_POSITION);
    }

    public function updateDocumentPositionAction($parentType, /** @noinspection PhpUnusedParameterInspection */  $parentId)
    {
        $documentId = $this->getRequest()->request->get('document_id');

        return $this->updateFilePositionAction($parentType, $documentId, 'document', TheliaEvents::DOCUMENT_UPDATE_POSITION);
    }

    public function toggleVisibilityFileAction($documentId, $parentType, $objectType, $eventName)
    {
        $message = null;

        //$position = $this->getRequest()->request->get('position');

        $this->checkAuth(AdminResources::retrieve($parentType), array(), AccessManager::UPDATE);
        $this->checkXmlHttpRequest();

        $fileManager = $this->getFileManager();
        $modelInstance = $fileManager->getModelInstance($objectType, $parentType);

        $model = $modelInstance->getQueryInstance()->findPk($documentId);

        if ($model === null) {
            return $this->pageNotFound();
        }

        // Feed event
        $event = new FileToggleVisibilityEvent(
            $modelInstance->getQueryInstance(),
            $documentId
        );

        // Dispatch Event to the Action
        try {
            $this->dispatch($eventName, $event);
        } catch (\Exception $e) {
            $message = $this->getTranslator()->trans(
                'Fail to update %type% visibility: %err%',
                [ '%type%' => $objectType, '%err%' => $e->getMessage() ]
            );
        }

        if (null === $message) {
            $message = $this->getTranslator()->trans(
                '%type% visibility updated',
                [ '%type%' => ucfirst($objectType) ]
            );
        }

        return new Response($message);
    }

    public function toggleVisibilityDocumentAction($parentType, $documentId)
    {
        return $this->toggleVisibilityFileAction($documentId, $parentType, 'document', TheliaEvents::DOCUMENT_TOGGLE_VISIBILITY);
    }

    public function toggleVisibilityImageAction($parentType, $documentId)
    {
        return $this->toggleVisibilityFileAction($documentId, $parentType, 'image', TheliaEvents::IMAGE_TOGGLE_VISIBILITY);
    }
}
