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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Router;
use Thelia\Core\Event\ImageCreateOrUpdateEvent;
use Thelia\Core\Event\ImageDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Form\CategoryImageCreationForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\CategoryImageQuery;
use Thelia\Model\ContentImageQuery;
use Thelia\Model\FolderImageQuery;
use Thelia\Model\ProductImageQuery;

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
     * Manage how a file collection has to be saved
     *
     * @param int    $parentId   Parent id owning files being saved
     * @param string $parentType Parent Type owning files being saved
     * @param string $successUrl Success  URL to be redirected to
     *
     * @return Response
     */
    public function saveFilesAction($parentId, $parentType, $successUrl)
    {

    }

    /**
     * Manage how a image collection has to be saved
     *
     * @param int    $parentId   Parent id owning images being saved
     * @param string $parentType Parent Type owning images being saved
     *
     * @return Response
     */
    public function saveImagesAction($parentId, $parentType)
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth("admin.image.save")) {
            return $response;
        }

        $message = $this->getTranslator()
            ->trans(
                'Images saved successfully',
                array(),
                'image'
            );

        if ($this->isParentTypeValid($parentType)) {
            if ($this->getRequest()->isMethod('POST')) {
                // Create the form from the request
                $creationForm = $this->getImageForm($parentType, $this->getRequest());

                try {
                    // Check the form against constraints violations
                    $form = $this->validateForm($creationForm, 'POST');

                    // Get the form field values
                    $data = $form->getData();

                    // Feed event
                    $imageCreateOrUpdateEvent = new ImageCreateOrUpdateEvent(
                        $parentType,
                        $parentId
                    );
                    if (isset($data) && isset($data['pictures'])) {
                        $imageCreateOrUpdateEvent->setModelImages($data['pictures']);
                        $imageCreateOrUpdateEvent->setUploadedFiles($this->getRequest()->files->get($creationForm->getName())['pictures']);
                    }

                    // Dispatch Event to the Action
                    $this->dispatch(
                        TheliaEvents::IMAGE_SAVE,
                        $imageCreateOrUpdateEvent
                    );

                } catch (FormValidationException $e) {
                    // Invalid data entered
                    $message = 'Please check your input:';
                    $this->logError($parentType, 'image saving', $message, $e);

                } catch (\Exception $e) {
                    // Any other error
                    $message = 'Sorry, an error occurred:';
                    $this->logError($parentType, 'image saving', $message, $e);
                }

                if ($message !== false) {
                    // Mark the form as with error
                    $creationForm->setErrorMessage($message);

                    // Send the form and the error to the parser
                    $this->getParserContext()
                        ->addForm($creationForm)
                        ->setGeneralError($message);

                    // Set flash message to be displayed
                    $flashMessage = $this->getSession()->get('flashMessage');
                    $flashMessage['imageMessage'] = $message;
                    $this->getSession()->set('flashMessage', $flashMessage);
                }
            }
        }

        $this->redirectSuccess($creationForm);
    }

    /**
     * Manage how a image has to be deleted (AJAX)
     *
     * @param int    $imageId    Parent id owning images being saved
     * @param string $parentType Parent Type owning images being saved
     *
     * @return Response
     */
    public function deleteImagesAction($imageId, $parentType)
    {
        $this->checkAuth('ADMIN', 'admin.image.delete');
        $this->checkXmlHttpRequest();

        $model = $this->getImageModel($parentType, $imageId);
        if ($model == null) {
            return $this->pageNotFound();
        }

        // Feed event
        $imageDeleteEvent = new ImageDeleteEvent(
            $model
        );

        // Dispatch Event to the Action
        $this->dispatch(
            TheliaEvents::IMAGE_DELETE,
            $imageDeleteEvent
        );

        $message = $this->getTranslator()
            ->trans(
                'Images deleted successfully',
                array(),
                'image'
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
        return (in_array($parentType, ImageCreateOrUpdateEvent::getAvailableType()));
    }

    /**
     * Get Image form
     *
     * @param string  $parentType Parent type
     * @param Request $request    Request Service
     *
     * @return null|CategoryImageCreationForm|ContentImageCreationForm|FolderImageCreationForm|ProductImageCreationForm
     *
     * @todo refactor make all pictures using propel inheritance and factorise image behaviour into one single clean action
     */
    public function getImageForm($parentType, Request $request)
    {
        // @todo implement other forms
        switch ($parentType) {
//            case ImageCreateOrUpdateEvent::TYPE_PRODUCT:
//                $creationForm = new ProductImageCreationForm($request);
//                break;
            case ImageCreateOrUpdateEvent::TYPE_CATEGORY:
                $creationForm = new CategoryImageCreationForm($request);
                break;
//            case ImageCreateOrUpdateEvent::TYPE_CONTENT:
//                $creationForm = new ContentImageCreationForm($request);
//                break;
//            case ImageCreateOrUpdateEvent::TYPE_FOLDER:
//                $creationForm = new FolderImageCreationForm($request);
//                break;
            default:
                $creationForm = null;
        }

        return $creationForm;

    }

    /**
     * Get image model from type
     *
     * @param string $parentType
     * @param int    $imageId
     *
     * @return null|\Thelia\Model\CategoryImage|\Thelia\Model\ContentImage|\Thelia\Model\FolderImage|\Thelia\Model\ProductImage
     *
     * @todo refactor make all pictures using propel inheritance and factorise image behaviour into one single clean action
     */
    public function getImageModel($parentType, $imageId)
    {
        switch ($parentType) {
            case ImageCreateOrUpdateEvent::TYPE_PRODUCT:
                $model = ProductImageQuery::create()->findPk($imageId);
                break;
            case ImageCreateOrUpdateEvent::TYPE_CATEGORY:
                $model = CategoryImageQuery::create()->findPk($imageId);
                break;
            case ImageCreateOrUpdateEvent::TYPE_CONTENT:
                $model = ContentImageQuery::create()->findPk($imageId);
                break;
            case ImageCreateOrUpdateEvent::TYPE_FOLDER:
                $model = FolderImageQuery::create()->findPk($imageId);
                break;
            default:
                $model = null;
        }

        return $model;

    }
}
