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

namespace Carousel\Controller;

use Carousel\Model\Carousel;
use Carousel\Model\CarouselQuery;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Tools\URL;

/**
 * Class ConfigurationController
 * @package Carousel\Controller
 * @author manuel raynaud <mraynaud@openstudio.fr>
 */
class ConfigurationController extends BaseAdminController
{

    public function uploadImage()
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ['carousel'], AccessManager::CREATE)) {
            return $response;
        }

        $request = $this->getRequest();
        $form = $this->createForm('carousel.image');
        $error_message = null;
        try {
            $this->validateForm($form);

            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $fileBeingUploaded */
            $fileBeingUploaded = $request->files->get(sprintf('%s[file]', $form->getName()), null, true);

            $fileModel = new Carousel();

            $fileCreateOrUpdateEvent = new FileCreateOrUpdateEvent(1);
            $fileCreateOrUpdateEvent->setModel($fileModel);
            $fileCreateOrUpdateEvent->setUploadedFile($fileBeingUploaded);

            $this->dispatch(
                TheliaEvents::IMAGE_SAVE,
                $fileCreateOrUpdateEvent
            );

            // Compensate issue #1005
            $langs = LangQuery::create()->find();

            /** @var Lang $lang */
            foreach ($langs as $lang) {
                $fileCreateOrUpdateEvent->getModel()->setLocale($lang->getLocale())->setTitle('')->save();
            }

            $response =  $this->redirectToConfigurationPage();

        } catch (FormValidationException $e) {
            $error_message = $this->createStandardFormValidationErrorMessage($e);
        }

        if (null !== $error_message) {
            $this->setupFormErrorContext(
                'carousel upload',
                $error_message,
                $form
            );

            $response = $this->render(
                "module-configure",
                [
                    'module_code' => 'Carousel'
                ]
            );
        }

        return $response;
    }

    /**
     * @param Form $form
     * @param string $fieldName
     * @param int $id
     * @return string
     */
    protected function getFormFieldValue($form, $fieldName, $id)
    {
        $value = $form->get(sprintf('%s%d', $fieldName, $id))->getData();

        return $value;
    }

    public function updateAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ['carousel'], AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm('carousel.update');

        $error_message = null;

        try {
            $updateForm = $this->validateForm($form);

            $carousels = CarouselQuery::create()->findAllByPosition();

            $locale = $this->getCurrentEditionLocale();

            /** @var Carousel $carousel */
            foreach ($carousels as $carousel) {
                $id = $carousel->getId();

                $carousel
                    ->setPosition($this->getFormFieldValue($updateForm, 'position', $id))
                    ->setUrl($this->getFormFieldValue($updateForm, 'url', $id))
                    ->setLocale($locale)
                    ->setTitle($this->getFormFieldValue($updateForm, 'title', $id))
                    ->setAlt($this->getFormFieldValue($updateForm, 'alt', $id))
                    ->setChapo($this->getFormFieldValue($updateForm, 'chapo', $id))
                    ->setDescription($this->getFormFieldValue($updateForm, 'description', $id))
                    ->setPostscriptum($this->getFormFieldValue($updateForm, 'postscriptum', $id))
                ->save();
            }

            $response =  $this->redirectToConfigurationPage();

        } catch (FormValidationException $e) {
            $error_message = $this->createStandardFormValidationErrorMessage($e);
        }

        if (null !== $error_message) {
            $this->setupFormErrorContext(
                'carousel upload',
                $error_message,
                $form
            );

            $response = $this->render("module-configure", [ 'module_code' => 'Carousel' ]);
        }

        return $response;

    }

    public function deleteAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ['carousel'], AccessManager::DELETE)) {
            return $response;
        }

        $imageId = $this->getRequest()->request->get('image_id');

        if ($imageId != "") {
            $carousel = CarouselQuery::create()->findPk($imageId);

            if (null !== $carousel) {
                $carousel->delete();
            }
        }

        return $this->redirectToConfigurationPage();
    }

    protected function redirectToConfigurationPage()
    {
        return RedirectResponse::create(URL::getInstance()->absoluteUrl('/admin/module/Carousel'));
    }
}