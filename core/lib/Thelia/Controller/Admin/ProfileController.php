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

use Thelia\Core\Event\Profile\ProfileEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Definition\AdminForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Form\ProfileUpdateModuleAccessForm;
use Thelia\Form\ProfileUpdateResourceAccessForm;
use Thelia\Model\Profile;
use Thelia\Model\ProfileQuery;

class ProfileController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'profile',
            'manual',
            'order',
            AdminResources::PROFILE,
            TheliaEvents::PROFILE_CREATE,
            TheliaEvents::PROFILE_UPDATE,
            TheliaEvents::PROFILE_DELETE
        );
    }

    protected function getCreationForm()
    {
        return $this->createForm(AdminForm::PROFILE_CREATION);
    }

    protected function getUpdateForm()
    {
        return $this->createForm(AdminForm::PROFILE_MODIFICATION);
    }

    protected function getCreationEvent($formData)
    {
        $event = new ProfileEvent();

        $event->setLocale($formData['locale']);
        $event->setCode($formData['code']);
        $event->setTitle($formData['title']);
        $event->setChapo($formData['chapo']);
        $event->setDescription($formData['description']);
        $event->setPostscriptum($formData['postscriptum']);

        return $event;
    }

    protected function getUpdateEvent($formData)
    {
        $event = new ProfileEvent();

        $event->setLocale($formData['locale']);
        $event->setId($formData['id']);
        $event->setTitle($formData['title']);
        $event->setChapo($formData['chapo']);
        $event->setDescription($formData['description']);
        $event->setPostscriptum($formData['postscriptum']);

        return $event;
    }

    protected function getDeleteEvent()
    {
        $event = new ProfileEvent();

        $event->setId(
            $this->getRequest()->get('profile_id', 0)
        );

        return $event;
    }

    protected function eventContainsObject($event)
    {
        return $event->hasProfile();
    }

    protected function hydrateObjectForm($object)
    {
        $data = array(
            'id'           => $object->getId(),
            'locale'       => $object->getLocale(),
            'title'        => $object->getTitle(),
            'description'  => $object->getDescription(),
            'code'         => $object->getCode(),
        );

        // Setup the object form
        return $this->createForm(AdminForm::PROFILE_MODIFICATION, "form", $data);
    }

    /**
     * @param Profile $object
     * @return \Thelia\Form\BaseForm
     */
    protected function hydrateResourceUpdateForm($object)
    {
        $data = array(
            'id'           => $object->getId(),
        );

        // Setup the object form
        return $this->createForm(AdminForm::PROFILE_UPDATE_RESOURCE_ACCESS, "form", $data);
    }

    /**
     * @param Profile $object
     * @return \Thelia\Form\BaseForm
     */
    protected function hydrateModuleUpdateForm($object)
    {
        $data = array(
            'id'           => $object->getId(),
        );

        // Setup the object form
        return $this->createForm(AdminForm::PROFILE_UPDATE_MODULE_ACCESS, "form", $data);
    }

    protected function getObjectFromEvent($event)
    {
        return $event->hasProfile() ? $event->getProfile() : null;
    }

    protected function getExistingObject()
    {
        $profile = ProfileQuery::create()
            ->findOneById($this->getRequest()->get('profile_id', 0));

        if (null !== $profile) {
            $profile->setLocale($this->getCurrentEditionLocale());
        }

        return $profile;
    }

    /**
     * @param Profile $object
     * @return string
     */
    protected function getObjectLabel($object)
    {
        return $object->getTitle();
    }

    /**
     * @param Profile $object
     * @return int
     */
    protected function getObjectId($object)
    {
        return $object->getId();
    }

    protected function getViewArguments()
    {
        return array();
    }

    protected function getRouteArguments($profile_id = null)
    {
        return array(
            'profile_id' => $profile_id === null ? $this->getRequest()->get('profile_id') : $profile_id,
        );
    }

    protected function renderListTemplate($currentOrder)
    {
        // We always return to the feature edition form
        return $this->render(
            'profiles',
            array()
        );
    }

    protected function renderEditionTemplate()
    {
        // We always return to the feature edition form
        return $this->render('profile-edit', array_merge($this->getViewArguments(), $this->getRouteArguments()));
    }

    protected function redirectToEditionTemplate($request = null, $country = null)
    {
        // We always return to the feature edition form
        return $this->generateRedirectFromRoute(
            "admin.configuration.profiles.update",
            $this->getViewArguments($country),
            $this->getRouteArguments()
        );
    }

    /**
     * Put in this method post object creation processing if required.
     *
     * @param  ProfileEvent $createEvent the create event
     * @return Response     a response, or null to continue normal processing
     */
    protected function performAdditionalCreateAction($createEvent)
    {
        return $this->generateRedirectFromRoute(
            "admin.configuration.profiles.update",
            $this->getViewArguments(),
            $this->getRouteArguments($createEvent->getProfile()->getId())
        );
    }

    protected function redirectToListTemplate()
    {
        return $this->generateRedirectFromRoute("admin.configuration.profiles.list");
    }

    public function updateAction()
    {
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $object = $this->getExistingObject();

        if ($object != null) {
            // Hydrate the form and pass it to the parser
            $resourceAccessForm = $this->hydrateResourceUpdateForm($object);
            $moduleAccessForm = $this->hydrateModuleUpdateForm($object);

            // Pass it to the parser
            $this->getParserContext()->addForm($resourceAccessForm);
            $this->getParserContext()->addForm($moduleAccessForm);
        }

        return parent::updateAction();
    }

    protected function getUpdateResourceAccessEvent($formData)
    {
        $event = new ProfileEvent();

        $event->setId($formData['id']);
        $event->setResourceAccess($this->getResourceAccess($formData));

        return $event;
    }

    protected function getUpdateModuleAccessEvent($formData)
    {
        $event = new ProfileEvent();

        $event->setId($formData['id']);
        $event->setModuleAccess($this->getModuleAccess($formData));

        return $event;
    }

    protected function getResourceAccess($formData)
    {
        $requirements = array();
        foreach ($formData as $data => $value) {
            if (!strstr($data, ':')) {
                continue;
            }

            $explosion = explode(':', $data);

            $prefix = array_shift($explosion);

            if ($prefix != ProfileUpdateResourceAccessForm::RESOURCE_ACCESS_FIELD_PREFIX) {
                continue;
            }

            $requirements[implode('.', $explosion)] = $value;
        }

        return $requirements;
    }

    protected function getModuleAccess($formData)
    {
        $requirements = array();
        foreach ($formData as $data => $value) {
            if (!strstr($data, ':')) {
                continue;
            }

            $explosion = explode(':', $data);

            $prefix = array_shift($explosion);

            if ($prefix != ProfileUpdateModuleAccessForm::MODULE_ACCESS_FIELD_PREFIX) {
                continue;
            }

            $requirements[implode('.', $explosion)] = $value;
        }

        return $requirements;
    }

    public function processUpdateResourceAccess()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $error_msg = false;

        // Create the form from the request
        $changeForm = $this->createForm(AdminForm::PROFILE_UPDATE_RESOURCE_ACCESS);

        try {
            // Check the form against constraints violations
            $form = $this->validateForm($changeForm, "POST");

            // Get the form field values
            $data = $form->getData();

            $changeEvent = $this->getUpdateResourceAccessEvent($data);

            $this->dispatch(TheliaEvents::PROFILE_RESOURCE_ACCESS_UPDATE, $changeEvent);

            if (! $this->eventContainsObject($changeEvent)) {
                throw new \LogicException(
                    $this->getTranslator()->trans("No %obj was updated.", array('%obj', $this->objectName))
                );
            }

            // Log object modification
            if (null !== $changedObject = $this->getObjectFromEvent($changeEvent)) {
                $this->adminLogAppend(
                    $this->resourceCode,
                    AccessManager::UPDATE,
                    sprintf(
                        "%s %s (ID %s) modified",
                        ucfirst($this->objectName),
                        $this->getObjectLabel($changedObject),
                        $this->getObjectId($changedObject)
                    ),
                    $this->getObjectId($changedObject)
                );
            }

            if ($response == null) {
                return $this->redirectToEditionTemplate($this->getRequest(), isset($data['country_list'][0]) ? $data['country_list'][0] : null);
            } else {
                return $response;
            }
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext($this->getTranslator()->trans("%obj modification", array('%obj' => 'taxrule')), $error_msg, $changeForm, $ex);

        // At this point, the form has errors, and should be redisplayed.
        return $this->renderEditionTemplate();
    }

    public function processUpdateModuleAccess()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $error_msg = false;

        // Create the form from the request
        $changeForm = $this->createForm(AdminForm::PROFILE_UPDATE_MODULE_ACCESS);

        try {
            // Check the form against constraints violations
            $form = $this->validateForm($changeForm, "POST");

            // Get the form field values
            $data = $form->getData();

            $changeEvent = $this->getUpdateModuleAccessEvent($data);

            $this->dispatch(TheliaEvents::PROFILE_MODULE_ACCESS_UPDATE, $changeEvent);

            if (! $this->eventContainsObject($changeEvent)) {
                throw new \LogicException(
                    $this->getTranslator()->trans("No %obj was updated.", array('%obj', $this->objectName))
                );
            }

            // Log object modification
            if (null !== $changedObject = $this->getObjectFromEvent($changeEvent)) {
                $this->adminLogAppend(
                    $this->resourceCode,
                    AccessManager::UPDATE,
                    sprintf(
                        "%s %s (ID %s) modified",
                        ucfirst($this->objectName),
                        $this->getObjectLabel($changedObject),
                        $this->getObjectId($changedObject)
                    ),
                    $this->getObjectId($changedObject)
                );
            }

            if ($response == null) {
                return $this->redirectToEditionTemplate($this->getRequest(), isset($data['country_list'][0]) ? $data['country_list'][0] : null);
            } else {
                return $response;
            }
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext($this->getTranslator()->trans("%obj modification", array('%obj' => 'taxrule')), $error_msg, $changeForm, $ex);

        // At this point, the form has errors, and should be redisplayed.
        return $this->renderEditionTemplate();
    }
}
