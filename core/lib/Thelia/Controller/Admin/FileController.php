<?php
/**********************************************************************************/
/*                                                                                */
/*      Thelia	                                                                  */
/*                                                                                */
/*      Copyright (c) OpenStudio                                                  */
/*      email : info@thelia.net                                                   */
/*      web : http://www.thelia.net                                               */
/*                                                                                */
/*      This program is free software; you can redistribute it and/or modify      */
/*      it under the terms of the GNU General Public License as published by      */
/*      the Free Software Foundation; either version 3 of the License             */
/*                                                                                */
/*      This program is distributed in the hope that it will be useful,           */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of            */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             */
/*      GNU General Public License for more details.                              */
/*                                                                                */
/*      You should have received a copy of the GNU General Public License         */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.      */
/*                                                                                */
/**********************************************************************************/

namespace Thelia\Controller\Admin;

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Event\Document\DocumentCreateOrUpdateEvent;
use Thelia\Core\Event\Document\DocumentDeleteEvent;
use Thelia\Core\Event\Image\ImageCreateOrUpdateEvent;
use Thelia\Core\Event\Image\ImageDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\CategoryDocument;
use Thelia\Model\CategoryImage;
use Thelia\Model\ContentDocument;
use Thelia\Model\ContentImage;
use Thelia\Model\FolderDocument;
use Thelia\Model\FolderImage;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductImage;
use Thelia\Tools\FileManager;
use Thelia\Tools\Rest\ResponseRest;

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
     * Manage how a image collection has to be saved
     *
     * @param int    $parentId   Parent id owning images being saved
     * @param string $parentType Parent Type owning images being saved
     *
     * @return Response
     */
    public function saveImageAjaxAction($parentId, $parentType)
    {
        $this->checkAuth(AdminResources::retrieve($parentType), AccessManager::UPDATE);
        $this->checkXmlHttpRequest();

        if ($this->isParentTypeValid($parentType)) {
            if ($this->getRequest()->isMethod('POST')) {

                /** @var UploadedFile $fileBeingUploaded */
                $fileBeingUploaded = $this->getRequest()->files->get('file');

                $fileManager = new FileManager($this->container);

                // Validate if file is too big
                if ($fileBeingUploaded->getError() == 1) {
                    $message = $this->getTranslator()
                    ->trans(
                        'File is too heavy, please retry with a file having a size less than %size%.',
                        array('%size%' => ini_get('post_max_size')),
                        'image'
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

                $parentModel = $fileManager->getParentFileModel($parentType, $parentId);
                $imageModel = $fileManager->getImageModel($parentType);

                if ($parentModel === null || $imageModel === null || $fileBeingUploaded === null) {
                    return new Response('', 404);
                }

                $defaultTitle = $parentModel->getTitle();
                $imageModel->setParentId($parentId);
                $imageModel->setTitle($defaultTitle);

                $imageCreateOrUpdateEvent = new ImageCreateOrUpdateEvent(
                    $parentType,
                    $parentId
                );
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
                        'Saving images for %parentName% parent id %parentId% (%parentType%)',
                        array(
                            '%parentName%' => $imageCreateOrUpdateEvent->getParentName(),
                            '%parentId%' => $imageCreateOrUpdateEvent->getParentId(),
                            '%parentType%' => $imageCreateOrUpdateEvent->getImageType()
                        ),
                        'image'
                    )
                );

                return new ResponseRest(array('status' => true, 'message' => ''));
            }
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
        $this->checkAuth(AdminResources::retrieve($parentType), AccessManager::UPDATE);
        $this->checkXmlHttpRequest();

        if ($this->isParentTypeValid($parentType)) {
            if ($this->getRequest()->isMethod('POST')) {

                /** @var UploadedFile $fileBeingUploaded */
                $fileBeingUploaded = $this->getRequest()->files->get('file');

                $fileManager = new FileManager($this->container);

                // Validate if file is too big
                if ($fileBeingUploaded->getError() == 1) {
                    $message = $this->getTranslator()
                        ->trans(
                            'File is too heavy, please retry with a file having a size less than %size%.',
                            array('%size%' => ini_get('post_max_size')),
                            'document'
                        );

                    return new ResponseRest($message, 'text', 403);
                }

                $parentModel = $fileManager->getParentFileModel($parentType, $parentId);
                $documentModel = $fileManager->getDocumentModel($parentType);

                if ($parentModel === null || $documentModel === null || $fileBeingUploaded === null) {
                    return new Response('', 404);
                }

                $documentModel->setParentId($parentId);
                $documentModel->setTitle($fileBeingUploaded->getClientOriginalName());

                $documentCreateOrUpdateEvent = new DocumentCreateOrUpdateEvent(
                    $parentType,
                    $parentId
                );
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
                        'Saving documents for %parentName% parent id %parentId% (%parentType%)',
                        array(
                            '%parentName%' => $documentCreateOrUpdateEvent->getParentName(),
                            '%parentId%' => $documentCreateOrUpdateEvent->getParentId(),
                            '%parentType%' => $documentCreateOrUpdateEvent->getDocumentType()
                        ),
                        'document'
                    )
                );

                return new ResponseRest(array('status' => true, 'message' => ''));
            }
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
        $this->checkAuth(AdminResources::retrieve($parentType), AccessManager::UPDATE);
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
        $this->checkAuth(AdminResources::retrieve($parentType), AccessManager::UPDATE);
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
        $this->checkAuth(AdminResources::retrieve($parentType), AccessManager::UPDATE);
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
        $this->checkAuth(AdminResources::retrieve($parentType), AccessManager::UPDATE);
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
        if (null !== $response = $this->checkAuth(AdminResources::retrieve($parentType), AccessManager::UPDATE)) {
            return $response;
        }
        try {
            $fileManager = new FileManager($this->container);
            $image = $fileManager->getImageModelQuery($parentType)->findPk($imageId);
            $redirectUrl = $fileManager->getRedirectionUrl($parentType, $image->getParentId(), FileManager::FILE_TYPE_IMAGES);

            return $this->render('image-edit', array(
                'imageId' => $imageId,
                'imageType' => $parentType,
                'redirectUrl' => $redirectUrl,
                'formId' => $fileManager->getFormId($parentType, FileManager::FILE_TYPE_IMAGES)
            ));
        } catch (\Exception $e) {
            $this->pageNotFound();
        }
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
        if (null !== $response = $this->checkAuth(AdminResources::retrieve($parentType), AccessManager::UPDATE)) {
            return $response;
        }
        try {
            $fileManager = new FileManager($this->container);
            $document = $fileManager->getDocumentModelQuery($parentType)->findPk($documentId);
            $redirectUrl = $fileManager->getRedirectionUrl($parentType, $document->getParentId(), FileManager::FILE_TYPE_DOCUMENTS);

            return $this->render('document-edit', array(
                    'documentId' => $documentId,
                    'documentType' => $parentType,
                    'redirectUrl' => $redirectUrl,
                    'formId' => $fileManager->getFormId($parentType, FileManager::FILE_TYPE_DOCUMENTS)
                ));
        } catch (\Exception $e) {
            $this->pageNotFound();
        }
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
        if (null !== $response = $this->checkAuth(AdminResources::retrieve($parentType), AccessManager::UPDATE)) {
            return $response;
        }

        $message = false;

        $fileManager = new FileManager($this->container);
        $imageModification = $fileManager->getImageForm($parentType, $this->getRequest());

        try {
            $image = $fileManager->getImageModelQuery($parentType)->findPk($imageId);
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
                $this->redirectToRoute('admin.images');
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

        $redirectUrl = $fileManager->getRedirectionUrl($parentType, $image->getParentId(), FileManager::FILE_TYPE_IMAGES);

        return $this->render('image-edit', array(
            'imageId' => $imageId,
            'imageType' => $parentType,
                'redirectUrl' => $redirectUrl,
            'formId' => $fileManager->getFormId($parentType, FileManager::FILE_TYPE_IMAGES)
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
        if (null !== $response = $this->checkAuth(AdminResources::retrieve($parentType), AccessManager::UPDATE)) {
            return $response;
        }

        $message = false;

        $fileManager = new FileManager($this->container);
        $documentModification = $fileManager->getDocumentForm($parentType, $this->getRequest());

        try {
            $document = $fileManager->getDocumentModelQuery($parentType)->findPk($documentId);
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
                $this->redirectToRoute('admin.documents');
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

        $redirectUrl = $fileManager->getRedirectionUrl($parentType, $document->getParentId(), FileManager::FILE_TYPE_DOCUMENTS);

        return $this->render('document-edit', array(
                'documentId' => $documentId,
                'documentType' => $parentType,
                'redirectUrl' => $redirectUrl,
                'formId' => $fileManager->getFormId($parentType, FileManager::FILE_TYPE_DOCUMENTS)
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
        $this->checkAuth(AdminResources::retrieve($parentType), AccessManager::UPDATE);
        $this->checkXmlHttpRequest();

        $fileManager = new FileManager($this->container);
        $imageModelQuery = $fileManager->getImageModelQuery($parentType);
        $model = $imageModelQuery->findPk($imageId);

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
        }

        $message = $this->getTranslator()
            ->trans(
                'Images deleted successfully',
                array(),
                'image'
            );

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
        $this->checkAuth(AdminResources::retrieve($parentType), AccessManager::UPDATE);
        $this->checkXmlHttpRequest();

        $fileManager = new FileManager($this->container);
        $documentModelQuery = $fileManager->getDocumentModelQuery($parentType);
        $model = $documentModelQuery->findPk($documentId);

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
     * Check if parent type is valid or not
     *
     * @param string $parentType Parent type
     *
     * @return bool
     */
    public function isParentTypeValid($parentType)
    {
        return (in_array($parentType, FileManager::getAvailableTypes()));
    }

    /**
     * Create Image Event instance
     *
     * @param string                                              $parentType Parent Type owning images being saved
     * @param CategoryImage|ProductImage|ContentImage|FolderImage $model      Image model
     * @param array                                               $data       Post data
     *
     * @return ImageCreateOrUpdateEvent
     */
    protected function createImageEventInstance($parentType, $model, $data)
    {
        $imageCreateEvent = new ImageCreateOrUpdateEvent($parentType, null);

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
     * @param string                                                          $parentType Parent Type owning documents being saved
     * @param CategoryDocument|ProductDocument|ContentDocument|FolderDocument $model      Document model
     * @param array                                                           $data       Post data
     *
     * @return DocumentCreateOrUpdateEvent
     */
    protected function createDocumentEventInstance($parentType, $model, $data)
    {
        $documentCreateEvent = new DocumentCreateOrUpdateEvent($parentType, null);

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
