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

use Thelia\Core\Event\Country\CountryCreateEvent;
use Thelia\Core\Event\Country\CountryDeleteEvent;
use Thelia\Core\Event\Country\CountryToggleDefaultEvent;
use Thelia\Core\Event\Country\CountryToggleVisibilityEvent;
use Thelia\Core\Event\Country\CountryUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\BaseForm;
use Thelia\Form\Definition\AdminForm;
use Thelia\Log\Tlog;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\State;
use Thelia\Model\StateQuery;

/**
 * Class CustomerController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CountryController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'country',
            'manual',
            'country_order',
            AdminResources::COUNTRY,
            TheliaEvents::COUNTRY_CREATE,
            TheliaEvents::COUNTRY_UPDATE,
            TheliaEvents::COUNTRY_DELETE,
            TheliaEvents::COUNTRY_TOGGLE_VISIBILITY
        );
    }

    /**
     * Return the creation form for this object
     */
    protected function getCreationForm()
    {
        return $this->createForm(AdminForm::COUNTRY_CREATION);
    }

    /**
     * Return the update form for this object
     */
    protected function getUpdateForm()
    {
        return $this->createForm(AdminForm::COUNTRY_MODIFICATION);
    }

    /**
     * Hydrate the update form for this object, before passing it to the update template
     *
     * @param \Thelia\Model\Country $object
     * @return BaseForm
     */
    protected function hydrateObjectForm($object)
    {
        $data = array(
            'id' => $object->getId(),
            'locale' => $object->getLocale(),
            'visible' => $object->getVisible() ? true : false,
            'title' => $object->getTitle(),
            'chapo' => $object->getChapo(),
            'description' => $object->getDescription(),
            'postscriptum' => $object->getPostscriptum(),
            'isocode' => $object->getIsocode(),
            'isoalpha2' => $object->getIsoalpha2(),
            'isoalpha3' => $object->getIsoalpha3(),
            'has_states' => $object->getHasStates() ? true : false,
            'need_zip_code' => $object->getNeedZipCode() ? true : false,
            'zip_code_format' => $object->getZipCodeFormat(),
        );

        return $this->createForm(AdminForm::COUNTRY_MODIFICATION, 'form', $data);
    }

    /**
     * Creates the creation event with the provided form data
     *
     * @param array $formData
     * @return CountryCreateEvent
     */
    protected function getCreationEvent($formData)
    {
        $event = new CountryCreateEvent();

        return $this->hydrateEvent($event, $formData);
    }

    /**
     * Creates the update event with the provided form data
     *
     * @param array $formData
     * @return CountryUpdateEvent
     */
    protected function getUpdateEvent($formData)
    {
        $event = new CountryUpdateEvent($formData['id']);

        $event = $this->hydrateEvent($event, $formData);

        $event
            ->setChapo($formData['chapo'])
            ->setDescription($formData['description'])
            ->setPostscriptum($formData['postscriptum'])
            ->setNeedZipCode($formData['need_zip_code'])
            ->setZipCodeFormat($formData['zip_code_format'])
        ;

        return $event;
    }

    protected function hydrateEvent($event, $formData)
    {
        $event
            ->setLocale($formData['locale'])
            ->setVisible($formData['visible'])
            ->setTitle($formData['title'])
            ->setIsocode($formData['isocode'])
            ->setIsoAlpha2($formData['isoalpha2'])
            ->setIsoAlpha3($formData['isoalpha3'])
            ->setHasStates($formData['has_states'])
        ;

        return $event;
    }

    /**
     * Creates the delete event with the provided form data
     */
    protected function getDeleteEvent()
    {
        return new CountryDeleteEvent($this->getRequest()->get('country_id'));
    }

    /**
     * Return true if the event contains the object, e.g. the action has updated the object in the event.
     *
     * @param unknown $event
     */
    protected function eventContainsObject($event)
    {
        return $event->hasCountry();
    }

    /**
     * Get the created object from an event.
     *
     * @param unknown $createEvent
     * @return Country
     */
    protected function getObjectFromEvent($event)
    {
        return $event->getCountry();
    }

    /**
     * Load an existing object from the database
     */
    protected function getExistingObject()
    {
        $country = CountryQuery::create()
            ->findPk($this->getRequest()->get('country_id', 0));

        if (null !== $country) {
            $country->setLocale($this->getCurrentEditionLocale());
        }

        return $country;
    }

    /**
     * Returns the object label form the object event (name, title, etc.)
     *
     * @param \Thelia\Model\Country $object
     * @return string
     */
    protected function getObjectLabel($object)
    {
        return $object->getTitle();
    }

    /**
     * Returns the object ID from the object
     *
     * @param \Thelia\Model\Country $object
     * @return int
     */
    protected function getObjectId($object)
    {
        return $object->getId();
    }

    /**
     * Render the main list template
     *
     * @param unknown $currentOrder, if any, null otherwise.
     * @return Response
     */
    protected function renderListTemplate($currentOrder)
    {
        return $this->render("countries", array("display_country" => 20));
    }

    /**
     * Render the edition template
     */
    protected function renderEditionTemplate()
    {
        return $this->render('country-edit', $this->getEditionArgument());
    }

    protected function getEditionArgument()
    {
        return array(
            'country_id'  => $this->getRequest()->get('country_id', 0)
        );
    }

    /**
     * Redirect to the edition template
     */
    protected function redirectToEditionTemplate()
    {
        return $this->generateRedirectFromRoute(
            'admin.configuration.countries.update',
            [],
            [
                "country_id" => $this->getRequest()->get('country_id', 0)
            ]
        );
    }

    /**
     * Redirect to the list template
     */
    protected function redirectToListTemplate()
    {
        return $this->generateRedirectFromRoute('admin.configuration.countries.default');
    }

    public function toggleDefaultAction()
    {
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) {
            return $response;
        }

        if (null !== $country_id = $this->getRequest()->get('country_id')) {
            $toogleDefaultEvent = new CountryToggleDefaultEvent($country_id);
            try {
                $this->dispatch(TheliaEvents::COUNTRY_TOGGLE_DEFAULT, $toogleDefaultEvent);

                if ($toogleDefaultEvent->hasCountry()) {
                    return $this->nullResponse();
                }
            } catch (\Exception $ex) {
                Tlog::getInstance()->error($ex->getMessage());
            }
        }

        return $this->nullResponse(500);
    }

    /**
     * @return CountryToggleVisibilityEvent|void
     */
    protected function createToggleVisibilityEvent()
    {
        return new CountryToggleVisibilityEvent($this->getExistingObject());
    }

    public function getDataAction($visible = true, $locale = null)
    {
        $response = $this->checkAuth($this->resourceCode, array(), AccessManager::VIEW);
        if (null !== $response) {
            return $response;
        }

        if (null === $locale) {
            $locale = $this->getCurrentEditionLocale();
        }

        $responseData = [];

        /** @var CountryQuery $search */
        $countries = CountryQuery::create()
            ->_if($visible)
                ->filterByVisible(true)
            ->_endif()
            ->joinWithI18n($locale)
        ;

        /** @var Country $country */
        foreach ($countries as $country) {
            $currentCountry = [
                'id' => $country->getId(),
                'title' => $country->getTitle(),
                'hasStates' => $country->getHasStates(),
                'states' => []
            ];

            if ($country->getHasStates()) {
                $states = StateQuery::create()
                    ->filterByCountryId($country->getId())
                    ->_if($visible)
                        ->filterByVisible(true)
                    ->_endif()
                    ->joinWithI18n($locale)
                ;

                /** @var State $state */
                foreach ($states as $state) {
                    $currentCountry['states'][] = [
                        'id' => $state->getId(),
                        'title' => $state->getTitle(),
                    ];
                }
            }

            $responseData[] = $currentCountry;
        }

        return $this->jsonResponse(json_encode($responseData));
    }
}
