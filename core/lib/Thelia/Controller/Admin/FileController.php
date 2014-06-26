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
use Thelia\Core\Event\Document\DocumentCreateOrUpdateEvent;
use Thelia\Core\Event\Document\DocumentDeleteEvent;
use Thelia\Core\Event\Image\ImageCreateOrUpdateEvent;
use Thelia\Core\Event\Image\ImageDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdateFilePositionEvent;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
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
     * Manage how a image collection has to be saved
     *
     * @param int    $parentId   Parent id owning images being saved
     * @param string $parentType Parent Type owning images being saved
     *
     * @return Response
     */
    public function saveImageAjaxAction($parentId, $parentType)
    {
        $this->checkAuth(AdminResources::retrieve($parentType), array(), AccessManager::UPDATE);
        $this->checkXmlHttpRequest();

        if ($this->getRequest()->isMethod('POST')) {

            /** @var UploadedFile $fileBeingUploaded */
            $fileBeingUploaded = $this->getRequest()->files->get('file');

            $fileManager = $this->getFileManager();

            // Validate if file is too big
            if ($fileBeingUploaded->getError() == 1) {
                $message = $this->getTranslator()
                ->trans(
                    'File is too heavy, please retry with a file having a size less than %size%.',
                    array('%size%' => ini_get('upload_max_filesize')),
                    'core'
                );

                return new ResponseRest($message, 'text', 403);
            }
            // Validate if it is a image or file
            if (!$fileManager->isImage($fileBeingUploaded->getMimeType())) {
                $message = $this->getTranslator()
                    ->trans(
                        'You can only upload images (.png, .jpg, .jpeg, .gif)',
                        array(),
                        'image'
                    );

                return new ResponseRest($message, 'text', 415);
            }

            $imageModel = $fileManager->getModelInstance('image', $parentType);

            $parentModel = $imageModel->getParentFileModel();

            if ($parentModel === null || $imageModel === null || $fileBeingUploaded === null) {
                return new Response('', 404);
            }

            $defaultTitle = $parentModel->getTitle();

            if (empty($defaultTitle)) {
                $defaultTitle = $fileBeingUploaded->getClientOriginalName();
            }

            $imageModel
                ->setParentId($parentId)
                ->setLocale(Lang::getDefaultLanguage()->getLocale())
                ->setTitle($defaultTitle)
            ;

            $imageCreateOrUpdateEvent = new ImageCreateOrUpdateEvent($parentId);
            $imageCreateOrUpdateEvent->setModelImage($imageModel);
            $imageCreateOrUpdateEvent->setUploadedFile($fileBeingUploaded);
            $imageCreateOrUpdateEvent->setParentName($parentModel->getTitle());

            // Dispatch Event to the Action
            $this->dispatch(
                TheliaEvents::IMAGE_SAVE,
                $imageCreateOrUpdateEvent
            );

            $this->adminLogAppend(
                AdminResources::retrieve($parentType),
                AccessManager::UPDATE,
                $this->container->get('thelia.translator')->trans(
                    'Saving images for %parentName% parent id %parentId%',
                    array(
                        '%parentName%' => $imageCreateOrUpdateEvent->getParentName(),
                        '%parentId%' => $imageCreateOrUpdateEvent->getParentId()
                    ),
                    'image'
                )
            );

            return new ResponseRest(array('status' => true, 'message' => ''));
        }

        return new Response('', 404);
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
        $this->checkAuth(AdminResources::retrieve($parentType), array(), AccessManager::UPDATE);
        $this->checkXmlHttpRequest();

        if ($this->getRequest()->isMethod('POST')) {

            /** @var UploadedFile $fileBeingUploaded */
            $fileBeingUploaded = $this->getRequest()->files->get('file');

            $fileManager = $this->getFileManager();

            // Validate if file is too big
            if ($fileBeingUploaded->getError() == 1) {
                $message = $this->getTranslator()
                    ->trans(
                        'File is too large, please retry with a file having a size less than %size%.',
                        array('%size%' => ini_get('post_max_size')),
                        'document'
                    );

                return new ResponseRest($message, 'text', 403);
            }

            $documentModel = $fileManager->getModelInstance('document', $parentType);
            $parentModel = $documentModel->getParentFileModel($parentType, $parentId);

            if ($parentModel === null || $documentModel === null || $fileBeingUploaded === null) {
                return new Response('', 404);
            }

            $documentModel->setParentId($parentId);
            $documentModel->setLocale(Lang::getDefaultLanguage()->getLocale());
            $documentModel->setTitle($fileBeingUploaded->getClientOriginalName());

            $documentCreateOrUpdateEvent = new DocumentCreateOrUpdateEvent($parentId);

            $documentCreateOrUpdateEvent->setModelDocument($documentModel);
            $documentCreateOrUpdateEvent->setUploadedFile($fileBeingUploaded);
            $documentCreateOrUpdateEvent->setParentName($parentModel->getTitle());

            // Dispatch Event to the Action
            $this->dispatch(
                TheliaEvents::DOCUMENT_SAVE,
                $documentCreateOrUpdateEvent
            );

            $this->adminLogAppend(
                AdminResources::retrieve($parentType),
                AccessManager::UPDATE,
                $this->container->get('thelia.translator')->trans(
                    'Saving document for %parentName% parent id %parentId%',
                    array(
                        '%parentName%' => $documentCreateOrUpdateEvent->getParentName(),
                        '%parentId%' => $documentCreateOrUpdateEvent->getParentId()
                    ),
                    'document'
                )
            );

            return new ResponseRest(array('status' => true, 'message' => ''));
        }

        return new Response('', 404);
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

        $redirectUrl = $imageModel->getRedirectionUrl($imageId);

        $image = $imageModel->getQueryInstance()->findPk($imageId);

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

        $redirectUrl = $documentModel->getRedirectionUrl($documentId);

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

        $message = false;

        $fileManager = $this->getFileManager();

        $modelInstance = $fileManager->getModelInstance('image', $parentType);

        $imageModification = $modelInstance->getUpdateFormInstance($this->getRequest());

        /** @var FileModelInterface $image */
        $image = $modelInstance->getQueryInstance()->findPk($imageId);

        try {
            $oldImage = clone $image;

            if (null === $image) {
                throw new \InvalidArgumentException(sprintf('%d image id does not exist', $imageId));
            }

            $form = $this->validateForm($imageModification);

            $event = $this->createImageEventInstance($parentType, $image, $form->getData());
            $event->setOldModelImage($oldImage);

            $files = $this->getRequest()->files;
            $fileForm = $files->get($imageModification->getName());
            if (isset($fileForm['file'])) {
                $event->setUploadedFile($fileForm['file']);
            }

            $this->dispatch(TheliaEvents::IMAGE_UPDATE, $event);

            $imageUpdated = $event->getModelImage();

            $this->adminLogAppend(AdminResources::retrieve($parentType), AccessManager::UPDATE, sprintf('Image with Ref %s (ID %d) modified', $imageUpdated->getTitle(), $imageUpdated->getId()));

            if ($this->getRequest()->get('save_mode') == 'close') {
                $this->redirect(URL::getInstance()->absoluteUrl($modelInstance->getRedirectionUrl($imageId)));
            } else {
                $this->redirectSuccess($imageModification);
            }

        } catch (FormValidationException $e) {
            $message = sprintf('Please check your input: %s', $e->getMessage());
        } catch (PropelException $e) {
            $message = $e->getMessage();
        } catch (\Exception $e) {
            $message = sprintf('Sorry, an error occurred: %s', $e->getMessage().' '.$e->getFile());
        }

        if ($message !== false) {
            Tlog::getInstance()->error(sprintf('Error during image editing : %s.', $message));

            $imageModification->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($imageModification)
                ->setGeneralError($message);
        }

        $redirectUrl = $modelInstance->getRedirectionUrl($imageId);

        return $this->render('image-edit', array(
            'imageId' => $imageId,
            'imageType' => $parentType,
            'redirectUrl' => $redirectUrl,
            'formId' => $modelInstance->getUpdateFormId()
        ));
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

        $message = false;

        $fileManager = $this->getFileManager();

        $modelInstance = $fileManager->getModelInstance('document', $parentType);

        $documentModification = $modelInstance->getUpdateFormInstance($this->getRequest());

        /** @var FileModelInterface $document */
        $document = $modelInstance->getQueryInstance()->findPk($documentId);

        try {
            $oldDocument = clone $document;

            if (null === $document) {
                throw new \InvalidArgumentException(sprintf('%d document id does not exist', $documentId));
            }

            $form = $this->validateForm($documentModification);

            $event = $this->createDocumentEventInstance($parentType, $document, $form->getData());
            $event->setOldModelDocument($oldDocument);

            $files = $this->getRequest()->files;
            $fileForm = $files->get($documentModification->getName());
            if (isset($fileForm['file'])) {
                $event->setUploadedFile($fileForm['file']);
            }

            $this->dispatch(TheliaEvents::DOCUMENT_UPDATE, $event);

            $documentUpdated = $event->getModelDocument();

            $this->adminLogAppend(AdminResources::retrieve($parentType), AccessManager::UPDATE, sprintf('Document with Ref %s (ID %d) modified', $documentUpdated->getTitle(), $documentUpdated->getId()));

            if ($this->getRequest()->get('save_mode') == 'close') {
                $this->redirect(URL::getInstance()->absoluteUrl($modelInstance->getRedirectionUrl($documentId)));
            } else {
                $this->redirectSuccess($documentModification);
            }

        } catch (FormValidationException $e) {
            $message = sprintf('Please check your input: %s', $e->getMessage());
        } catch (PropelException $e) {
            $message = $e->getMessage();
        } catch (\Exception $e) {
            $message = sprintf('Sorry, an error occurred: %s', $e->getMessage().' '.$e->getFile());
        }

        if ($message !== false) {
            Tlog::getInstance()->error(sprintf('Error during document editing : %s.', $message));

            $documentModification->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($documentModification)
                ->setGeneralError($message);
        }

        $redirectUrl = $modelInstance->getRedirectionUrl($documentId);

        return $this->render('document-edit', array(
                'documentId' => $documentId,
                'documentType' => $parentType,
                'redirectUrl' => $redirectUrl,
                'formId' => $modelInstance->getUpdateFormId()
            ));
    }

    /**
     * Manage how a image has to be deleted (AJAX)
     *
     * @param int    $imageId    Parent id owning image being deleted
     * @param string $parentType Parent Type owning image being deleted
     *
     * @return Response
     */
    public function deleteImageAction($imageId, $parentType)
    {
        $message = null;

        $this->checkAuth(AdminResources::retrieve($parentType), array(), AccessManager::UPDATE);
        $this->checkXmlHttpRequest();

        $fileManager = $this->getFileManager();
        $modelInstance = $fileManager->getModelInstance('image', $parentType);

        $model = $modelInstance->getQueryInstance()->findPk($imageId);

        if ($model == null) {
            return $this->pageNotFound();
        }

        // Feed event
        $imageDeleteEvent = new ImageDeleteEvent(
            $model,
            $parentType
        );

        // Dispatch Event to the Action
        try {
            $this->dispatch(
                TheliaEvents::IMAGE_DELETE,
                $imageDeleteEvent
            );

            $this->adminLogAppend(
                AdminResources::retrieve($parentType),
                AccessManager::UPDATE,
                $this->container->get('thelia.translator')->trans(
                    'Deleting image for %id% with parent id %parentId%',
                    array(
                        '%id%' => $imageDeleteEvent->getImageToDelete()->getId(),
                        '%parentId%' => $imageDeleteEvent->getImageToDelete()->getParentId(),
                    ),
                    'image'
                )
            );
        } catch (\Exception $e) {
            $this->adminLogAppend(
                AdminResources::retrieve($parentType),
                AccessManager::UPDATE,
                $this->container->get('thelia.translator')->trans(
                    'Fail to delete image for %id% with parent id %parentId% (Exception : %e%)',
                    array(
                        '%id%' => $imageDeleteEvent->getImageToDelete()->getId(),
                        '%parentId%' => $imageDeleteEvent->getImageToDelete()->getParentId(),
                        '%e%' => $e->getMessage()
                    ),
                    'image'
                )
            );
            $message = $this->getTranslator()
                ->trans(
                    'Fail to delete image for %id% with parent id %parentId% (Exception : %e%)',
                    array(
                        '%id%' => $imageDeleteEvent->getImageToDelete()->getId(),
                        '%parentId%' => $imageDeleteEvent->getImageToDelete()->getParentId(),
                        '%e%' => $e->getMessage()
                    ),
                    'image'
                );
        }

        if (null === $message) {
            $message = $this->getTranslator()
                ->trans(
                    'Images deleted successfully',
                    array(),
                    'image'
                );
        }

        return new Response($message);
    }

    public function updateImagePositionAction($parentType, /** @noinspection PhpUnusedParameterInspection */ $parentId)
    {
        $message = null;

        $imageId = $this->getRequest()->request->get('image_id');
        $position = $this->getRequest()->request->get('position');

        $this->checkAuth(AdminResources::retrieve($parentType), array(), AccessManager::UPDATE);
        $this->checkXmlHttpRequest();

        $fileManager = $this->getFileManager();
        $modelInstance = $fileManager->getModelInstance('image', $parentType);
        $model = $modelInstance->getQueryInstance()->findPk($imageId);

        if ($model === null || $position === null) {
            return $this->pageNotFound();
        }

        // Feed event
        $imageUpdateImagePositionEvent = new UpdateFilePositionEvent(
            $modelInstance->getQueryInstance($parentType),
            $imageId,
            UpdateFilePositionEvent::POSITION_ABSOLUTE,
            $position
        );

        // Dispatch Event to the Action
        try {
            $this->dispatch(
                TheliaEvents::IMAGE_UPDATE_POSITION,
                $imageUpdateImagePositionEvent
            );
        } catch (\Exception $e) {

            $message = $this->getTranslator()
                ->trans(
                    'Fail to update image position',
                    array(),
                    'image'
                ) . $e->getMessage();
        }

        if (null === $message) {
            $message = $this->getTranslator()
                ->trans(
                    'Image position updated',
                    array(),
                    'image'
                );
        }

        return new Response($message);
    }

    public function updateDocumentPositionAction($parentType, /** @noinspection PhpUnusedParameterInspection */  $parentId)
    {
        $message = null;

        $documentId = $this->getRequest()->request->get('document_id');
        $position = $this->getRequest()->request->get('position');

        $this->checkAuth(AdminResources::retrieve($parentType), array(), AccessManager::UPDATE);
        $this->checkXmlHttpRequest();

        $fileManager = $this->getFileManager();
        $modelInstance = $fileManager->getModelInstance('document', $parentType);
        $model = $modelInstance->getQueryInstance()->findPk($documentId);

        if ($model === null || $position === null) {
            return $this->pageNotFound();
        }

        // Feed event
        $documentUpdateDocumentPositionEvent = new UpdateFilePositionEvent(
            $modelInstance->getQueryInstance(),
            $documentId,
            UpdateFilePositionEvent::POSITION_ABSOLUTE,
            $position
        );

        // Dispatch Event to the Action
        try {
            $this->dispatch(
                TheliaEvents::DOCUMENT_UPDATE_POSITION,
                $documentUpdateDocumentPositionEvent
            );
        } catch (\Exception $e) {

            $message = $this->getTranslator()
                ->trans(
                    'Fail to update document position',
                    array(),
                    'document'
                ) . $e->getMessage();
        }

        if (null === $message) {
            $message = $this->getTranslator()
                ->trans(
                    'Document position updated',
                    array(),
                    'document'
                );
        }

        return new Response($message);
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
        $this->checkAuth(AdminResources::retrieve($parentType), array(), AccessManager::UPDATE);
        $this->checkXmlHttpRequest();

        $fileManager = $this->getFileManager();
        $modelInstance = $fileManager->getModelInstance('document', $parentType);
        $model = $modelInstance->getQueryInstance()->findPk($documentId);

        if ($model == null) {
            return $this->pageNotFound();
        }

        // Feed event
        $documentDeleteEvent = new DocumentDeleteEvent(
            $model,
            $parentType
        );

        // Dispatch Event to the Action
        try {
            $this->dispatch(
                TheliaEvents::DOCUMENT_DELETE,
                $documentDeleteEvent
            );

            $this->adminLogAppend(
                AdminResources::retrieve($parentType),
                AccessManager::UPDATE,
                $this->container->get('thelia.translator')->trans(
                    'Deleting document for %id% with parent id %parentId%',
                    array(
                        '%id%' => $documentDeleteEvent->getDocumentToDelete()->getId(),
                        '%parentId%' => $documentDeleteEvent->getDocumentToDelete()->getParentId(),
                    ),
                    'document'
                )
            );
        } catch (\Exception $e) {
            $this->adminLogAppend(
                AdminResources::retrieve($parentType),
                AccessManager::UPDATE,
                $this->container->get('thelia.translator')->trans(
                    'Fail to delete document for %id% with parent id %parentId% (Exception : %e%)',
                    array(
                        '%id%' => $documentDeleteEvent->getDocumentToDelete()->getId(),
                        '%parentId%' => $documentDeleteEvent->getDocumentToDelete()->getParentId(),
                        '%e%' => $e->getMessage()
                    ),
                    'document'
                )
            );
        }

        $message = $this->getTranslator()
            ->trans(
                'Document deleted successfully',
                array(),
                'document'
            );

        return new Response($message);
    }

    /**
     * Log error message
     *
     * @param string     $parentType Parent type
     * @param string     $action     Creation|Update|Delete
     * @param string     $message    Message to log
     * @param \Exception $e          Exception to log
     *
     * @return $this
     */
    protected function logError($parentType, $action, $message, $e)
    {
        Tlog::getInstance()->error(
            sprintf(
                'Error during ' . $parentType . ' ' . $action . ' process : %s. Exception was %s',
                $message,
                $e->getMessage()
            )
        );

        return $this;
    }

    /**
     * Create Image Event instance
     *
     * @param string             $parentType Parent Type owning images being saved
     * @param FileModelInterface $model      the model
     * @param array              $data       Post data
     *
     * @return ImageCreateOrUpdateEvent
     */
    protected function createImageEventInstance($parentType, $model, $data)
    {
        $imageCreateEvent = new ImageCreateOrUpdateEvent(null);

        $model->setLocale($data['locale']);

        if (isset($data['title'])) {
            $model->setTitle($data['title']);
        }
        if (isset($data['chapo'])) {
        $model->setChapo($data['chapo']);
        }
        if (isset($data['description'])) {
            $model->setDescription($data['description']);
        }
        if (isset($data['file'])) {
            $model->setFile($data['file']);
        }
        if (isset($data['postscriptum'])) {
            $model->setPostscriptum($data['postscriptum']);
        }

        $imageCreateEvent->setModelImage($model);

        return $imageCreateEvent;
    }

    /**
     * Create Document Event instance
     *
     * @param string             $parentType Parent Type owning documents being saved
     * @param FileModelInterface $model      the model
     * @param array              $data       Post data
     *
     * @return DocumentCreateOrUpdateEvent
     */
    protected function createDocumentEventInstance($parentType, $model, $data)
    {
        $documentCreateEvent = new DocumentCreateOrUpdateEvent(null);

        $model->setLocale($data['locale']);
        if (isset($data['title'])) {
            $model->setTitle($data['title']);
        }
        if (isset($data['chapo'])) {
            $model->setChapo($data['chapo']);
        }
        if (isset($data['description'])) {
            $model->setDescription($data['description']);
        }
        if (isset($data['file'])) {
            $model->setFile($data['file']);
        }
        if (isset($data['postscriptum'])) {
            $model->setPostscriptum($data['postscriptum']);
        }

        $documentCreateEvent->setModelDocument($model);

        return $documentCreateEvent;
    }

}
