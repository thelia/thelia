<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Carousel\Controller;

use Carousel\Form\CarouselImageForm;
use Carousel\Form\CarouselUpdateForm;
use Carousel\Model\Carousel;
use Carousel\Model\CarouselQuery;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\EventDispatcher\EventDispatcher;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Tools\URL;

/**
 * Class ConfigurationController.
 *
 * @author manuel raynaud <mraynaud@openstudio.fr>
 */
class ConfigurationController extends BaseAdminController
{
    public function uploadImage(
        Request $request,
        TheliaFormFactory $formFactory,
        EventDispatcher $eventDispatcher
    ) {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ['carousel'], AccessManager::CREATE)) {
            return $response;
        }

        $form = $formFactory->createForm(CarouselImageForm::class);
        $error_message = null;
        try {
            $formData = $this->validateForm($form)->getData();

            /** @var UploadedFile $fileBeingUploaded */
            $fileBeingUploaded = $formData['file'];

            $fileModel = new Carousel();

            $fileCreateOrUpdateEvent = new FileCreateOrUpdateEvent(1);
            $fileCreateOrUpdateEvent->setModel($fileModel);
            $fileCreateOrUpdateEvent->setUploadedFile($fileBeingUploaded);

            $eventDispatcher->dispatch(
                $fileCreateOrUpdateEvent,
                TheliaEvents::IMAGE_SAVE
            );

            // Compensate issue #1005
            $langs = LangQuery::create()->find();

            /** @var Lang $lang */
            foreach ($langs as $lang) {
                $fileCreateOrUpdateEvent->getModel()->setLocale($lang->getLocale())->setTitle('')->save();
            }

            $response = $this->redirectToConfigurationPage();
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
                'module-configure',
                [
                    'module_code' => 'Carousel',
                ]
            );
        }

        return $response;
    }

    /**
     * @param Form   $form
     * @param string $fieldName
     * @param int    $id
     *
     * @return string
     */
    protected function getFormFieldValue($form, $fieldName, $id)
    {
        $value = $form->get(sprintf('%s%d', $fieldName, $id))->getData();

        return $value;
    }

    public function updateAction(
        TheliaFormFactory $formFactory
    ) {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ['carousel'], AccessManager::UPDATE)) {
            return $response;
        }

        $form = $formFactory->createForm(CarouselUpdateForm::class);

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
                    ->setDisable($this->getFormFieldValue($updateForm, 'disable', $id))
                    ->setUrl($this->getFormFieldValue($updateForm, 'url', $id))
                    ->setLocale($locale)
                    ->setTitle($this->getFormFieldValue($updateForm, 'title', $id))
                    ->setAlt($this->getFormFieldValue($updateForm, 'alt', $id))
                    ->setChapo($this->getFormFieldValue($updateForm, 'chapo', $id))
                    ->setDescription($this->getFormFieldValue($updateForm, 'description', $id))
                    ->setPostscriptum($this->getFormFieldValue($updateForm, 'postscriptum', $id))
                    ->setGroup($this->getFormFieldValue($updateForm, 'group', $id))
                    ->setLimited($this->getFormFieldValue($updateForm, 'limited', $id))
                    ->setStartDate($this->getFormFieldValue($updateForm, 'start_date', $id))
                    ->setEndDate($this->getFormFieldValue($updateForm, 'end_date', $id))
                    ->save();
            }

            $response = $this->redirectToConfigurationPage();
        } catch (FormValidationException $e) {
            $error_message = $this->createStandardFormValidationErrorMessage($e);
        }

        if (null !== $error_message) {
            $this->setupFormErrorContext(
                'carousel upload',
                $error_message,
                $form
            );

            $response = $this->render('module-configure', ['module_code' => 'Carousel']);
        }

        return $response;
    }

    public function deleteAction(
        Request $request
    ) {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ['carousel'], AccessManager::DELETE)) {
            return $response;
        }

        $imageId = $request->get('image_id');

        if ($imageId != '') {
            $carousel = CarouselQuery::create()->findPk($imageId);

            if (null !== $carousel) {
                $carousel->delete();
            }
        }

        return $this->redirectToConfigurationPage();
    }

    protected function redirectToConfigurationPage()
    {
        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/Carousel'));
    }
}
