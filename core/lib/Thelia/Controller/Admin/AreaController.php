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

use Thelia\Core\Event\Area\AreaAddCountryEvent;
use Thelia\Core\Event\Area\AreaCreateEvent;
use Thelia\Core\Event\Area\AreaDeleteEvent;
use Thelia\Core\Event\Area\AreaRemoveCountryEvent;
use Thelia\Core\Event\Area\AreaUpdateEvent;
use Thelia\Core\Event\Area\AreaUpdatePostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\BaseForm;
use Thelia\Form\Definition\AdminForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\Area;
use Thelia\Model\AreaQuery;

/**
 * Class AreaController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <manu@raynaud.io>
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class AreaController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'area',
            null,
            null,
            AdminResources::AREA,
            TheliaEvents::AREA_CREATE,
            TheliaEvents::AREA_UPDATE,
            TheliaEvents::AREA_DELETE
        );
    }

    protected function getAreaId()
    {
        return $this->getRequest()->get('area_id', 0);
    }

    /**
     * Return the creation form for this object
     */
    protected function getCreationForm()
    {
        return $this->createForm(AdminForm::AREA_CREATE);
    }

    /**
     * Return the update form for this object
     */
    protected function getUpdateForm()
    {
        return $this->createForm(AdminForm::AREA_MODIFICATION);
    }

    /**
     * Hydrate the update form for this object, before passing it to the update template
     *
     * @param  Area $object
     * @return BaseForm
     */
    protected function hydrateObjectForm($object)
    {
        $data = array(
            'area_id' => $object->getId(),
            'name' => $object->getName()
        );

        return $this->createForm(AdminForm::AREA_MODIFICATION, 'form', $data);
    }

    /**
     * Creates the creation event with the provided form data
     *
     * @param array $formData
     *
     * @return \Thelia\Core\Event\Area\AreaCreateEvent
     */
    protected function getCreationEvent($formData)
    {
        $event = new AreaCreateEvent();

        return $this->hydrateEvent($event, $formData);
    }

    /**
     * Creates the update event with the provided form data
     *
     * @param array $formData
     * @return \Thelia\Core\Event\Area\AreaUpdateEvent
     */
    protected function getUpdateEvent($formData)
    {
        $event = new AreaUpdateEvent();

        $this->hydrateEvent($event, $formData);

        $event->setAreaId($formData['area_id']);

        return $event;
    }

    /**
     * @param \Thelia\Core\Event\Area\AreaCreateEvent $event
     * @param array $formData
     * @return \Thelia\Core\Event\Area\AreaCreateEvent
     */
    private function hydrateEvent($event, $formData)
    {
        $event->setAreaName($formData['name']);

        return $event;
    }

    /**
     * Creates the delete event with the provided form data
     *
     * @return AreaDeleteEvent
     */
    protected function getDeleteEvent()
    {
        return new AreaDeleteEvent($this->getAreaId());
    }

    /**
     * Return true if the event contains the object, e.g. the action has updated the object in the event.
     *
     * @param \Thelia\Core\Event\Area\AreaEvent $event
     * @return bool
     */
    protected function eventContainsObject($event)
    {
        return $event->hasArea();
    }

    /**
     * Get the created object from an event.
     *
     * @param \Thelia\Core\Event\Area\AreaEvent $event
     * @return Area
     */
    protected function getObjectFromEvent($event)
    {
        return $event->getArea();
    }

    /**
     * Load an existing object from the database
     */
    protected function getExistingObject()
    {
        return AreaQuery::create()->findPk($this->getAreaId());
    }

    /**
     * Returns the object label form the object event (name, title, etc.)
     *
     * @param \Thelia\Model\Area $object
     * @return string
     */
    protected function getObjectLabel($object)
    {
        return $object->getName();
    }

    /**
     * Returns the object ID from the object
     *
     * @param \Thelia\Model\Area $object
     * @return int
     */
    protected function getObjectId($object)
    {
        return $object->getId();
    }

    /**
     * Render the main list template
     *
     * @param string|null $currentOrder, if any, null otherwise.
     * @return \Thelia\Core\HttpFoundation\Response
     */
    protected function renderListTemplate($currentOrder)
    {
        return $this->render("shipping-configuration");
    }

    /**
     * Render the edition template
     */
    protected function renderEditionTemplate()
    {
        return $this->render(
            'shipping-configuration-edit',
            array(
            'area_id' => $this->getAreaId()
            )
        );
    }

    /**
     * Redirect to the edition template
     */
    protected function redirectToEditionTemplate()
    {
        return $this->generateRedirectFromRoute(
            'admin.configuration.shipping-configuration.update.view',
            [],
            [
                "area_id" => $this->getAreaId()
            ]
        );
    }

    /**
     * Redirect to the list template
     */
    protected function redirectToListTemplate()
    {
        return $this->generateRedirectFromRoute('admin.configuration.shipping-configuration.default');
    }

    /**
     * add a country to a define area
     */
    public function addCountry()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $areaCountryForm = $this->createForm(AdminForm::AREA_COUNTRY);
        $error_msg = null;
        try {
            $form = $this->validateForm($areaCountryForm);

            $event = new AreaAddCountryEvent($form->get('area_id')->getData(), $form->get('country_id')->getData());

            $this->dispatch(TheliaEvents::AREA_ADD_COUNTRY, $event);

            if (! $this->eventContainsObject($event)) {
                throw new \LogicException(
                    $this->getTranslator()->trans("No %obj was updated.", array('%obj', $this->objectName))
                );
            }

            // Log object modification
            if (null !== $changedObject = $this->getObjectFromEvent($event)) {
                $this->adminLogAppend(
                    $this->resourceCode,
                    AccessManager::UPDATE,
                    sprintf(
                        "%s %s (ID %s) modified, new country added",
                        ucfirst($this->objectName),
                        $this->getObjectLabel($changedObject),
                        $this->getObjectId($changedObject)
                    ),
                    $this->getObjectId($changedObject)
                );
            }

            // Redirect to the success URL
            return $this->generateSuccessRedirect($areaCountryForm);
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext(
            $this->getTranslator()->trans("%obj modification", array('%obj' => $this->objectName)),
            $error_msg,
            $areaCountryForm
        );

        // At this point, the form has errors, and should be redisplayed.
        return $this->renderEditionTemplate();
    }

    /**
     * delete several countries from a shipping zone
     */
    public function removeCountries()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $areaDeleteCountriesForm = $this->createForm(AdminForm::AREA_DELETE_COUNTRY);

        try {
            $form = $this->validateForm($areaDeleteCountriesForm);

            $data = $form->getData();

            foreach ($data['country_id'] as $countryId) {
                $country = explode('-', $countryId);
                $this->removeOneCountryFromArea($data['area_id'], $country[0], $country[1]);
            }
            // Redirect to the success URL
            return $this->generateSuccessRedirect($areaDeleteCountriesForm);
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext(
            $this->getTranslator()->trans("Failed to delete selected countries"),
            $error_msg,
            $areaDeleteCountriesForm
        );

        // At this point, the form has errors, and should be redisplayed.
        return $this->renderEditionTemplate();
    }

    protected function removeOneCountryFromArea($areaId, $countryId, $stateId)
    {
        if (intval($stateId) === 0) {
            $stateId = null;
        }

        $removeCountryEvent = new AreaRemoveCountryEvent($areaId, $countryId, $stateId);

        $this->dispatch(TheliaEvents::AREA_REMOVE_COUNTRY, $removeCountryEvent);

        if (null !== $changedObject = $this->getObjectFromEvent($removeCountryEvent)) {
            $this->adminLogAppend(
                $this->resourceCode,
                AccessManager::UPDATE,
                sprintf(
                    "%s %s (ID %s) removed country ID %s from shipping zone ID %s",
                    ucfirst($this->objectName),
                    $this->getObjectLabel($changedObject),
                    $this->getObjectId($changedObject),
                    $countryId,
                    $areaId
                ),
                $this->getObjectId($changedObject)
            );
        }
    }

    public function removeCountry()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $this->removeOneCountryFromArea(
            $this->getRequest()->get('area_id', 0),
            $this->getRequest()->get('country_id', 0),
            $this->getRequest()->get('state_id', null)
        );

        return $this->redirectToEditionTemplate();
    }

    public function updatePostageAction()
    {
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $areaUpdateForm = $this->createForm(AdminForm::AREA_POSTAGE);
        $error_msg = null;

        try {
            $form = $this->validateForm($areaUpdateForm);

            $event = new AreaUpdatePostageEvent($form->get('area_id')->getData());
            $event->setPostage($form->get('postage')->getData());

            $this->dispatch(TheliaEvents::AREA_POSTAGE_UPDATE, $event);

            if (! $this->eventContainsObject($event)) {
                throw new \LogicException(
                    $this->getTranslator()->trans("No %obj was updated.", array('%obj', $this->objectName))
                );
            }

            // Log object modification
            if (null !== $changedObject = $this->getObjectFromEvent($event)) {
                $this->adminLogAppend(
                    $this->resourceCode,
                    AccessManager::UPDATE,
                    sprintf(
                        "%s %s (ID %s) modified, country remove",
                        ucfirst($this->objectName),
                        $this->getObjectLabel($changedObject),
                        $this->getObjectId($changedObject)
                    ),
                    $this->getObjectId($changedObject)
                );
            }

            // Redirect to the success URL
            return $this->generateSuccessRedirect($areaUpdateForm);
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext(
            $this->getTranslator()->trans("%obj modification", array('%obj' => $this->objectName)),
            $error_msg,
            $areaUpdateForm
        );

        // At this point, the form has errors, and should be redisplayed.
        return $this->renderEditionTemplate();
    }
}
