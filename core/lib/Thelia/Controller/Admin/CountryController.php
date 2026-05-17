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

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\Country\CountryCreateEvent;
use Thelia\Core\Event\Country\CountryDeleteEvent;
use Thelia\Core\Event\Country\CountryToggleDefaultEvent;
use Thelia\Core\Event\Country\CountryToggleVisibilityEvent;
use Thelia\Core\Event\Country\CountryUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\BaseForm;
use Thelia\Form\Definition\AdminForm;
use Thelia\Log\Tlog;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\State;
use Thelia\Model\StateQuery;

/**
 * Class CustomerController.
 *
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
            TheliaEvents::COUNTRY_TOGGLE_VISIBILITY,
        );
    }

    /**
     * Return the creation form for this object.
     */
    protected function getCreationForm(): BaseForm
    {
        return $this->createForm(AdminForm::COUNTRY_CREATION);
    }

    /**
     * Return the update form for this object.
     */
    protected function getUpdateForm(): BaseForm
    {
        return $this->createForm(AdminForm::COUNTRY_MODIFICATION);
    }

    /**
     * Hydrate the update form for this object, before passing it to the update template.
     *
     * @param Country $object
     */
    protected function hydrateObjectForm(ParserContext $parserContext, ActiveRecordInterface $object): BaseForm
    {
        $data = [
            'id' => $object->getId(),
            'locale' => $object->getLocale(),
            'visible' => (bool) $object->getVisible(),
            'title' => $object->getTitle(),
            'chapo' => $object->getChapo(),
            'description' => $object->getDescription(),
            'postscriptum' => $object->getPostscriptum(),
            'isocode' => $object->getIsocode(),
            'isoalpha2' => $object->getIsoalpha2(),
            'isoalpha3' => $object->getIsoalpha3(),
            'has_states' => (bool) $object->getHasStates(),
            'need_zip_code' => (bool) $object->getNeedZipCode(),
            'zip_code_format' => $object->getZipCodeFormat(),
        ];

        return $this->createForm(AdminForm::COUNTRY_MODIFICATION, FormType::class, $data);
    }

    /**
     * Creates the creation event with the provided form data.
     *
     * @return CountryCreateEvent
     */
    protected function getCreationEvent(array $formData): ActionEvent
    {
        $event = new CountryCreateEvent();

        return $this->hydrateEvent($event, $formData);
    }

    /**
     * Creates the update event with the provided form data.
     *
     * @return CountryUpdateEvent
     */
    protected function getUpdateEvent(array $formData): ActionEvent
    {
        $event = new CountryUpdateEvent((int) $formData['id']);

        $event = $this->hydrateEvent($event, $formData);

        $event
            ->setChapo($formData['chapo'])
            ->setDescription($formData['description'])
            ->setPostscriptum($formData['postscriptum'])
            ->setNeedZipCode($formData['need_zip_code'])
            ->setZipCodeFormat($formData['zip_code_format']);

        return $event;
    }

    protected function hydrateEvent($event, array $formData)
    {
        $event
            ->setLocale($formData['locale'])
            ->setVisible($formData['visible'])
            ->setTitle($formData['title'])
            ->setIsocode($formData['isocode'])
            ->setIsoAlpha2($formData['isoalpha2'])
            ->setIsoAlpha3($formData['isoalpha3'])
            ->setHasStates($formData['has_states']);

        return $event;
    }

    /**
     * Creates the delete event with the provided form data.
     */
    protected function getDeleteEvent(): CountryDeleteEvent
    {
        return new CountryDeleteEvent($this->getRequest()->get('country_id'));
    }

    /**
     * Return true if the event contains the object, e.g. the action has updated the object in the event.
     */
    protected function eventContainsObject(Event $event): bool
    {
        return $event->hasCountry();
    }

    /**
     * Get the created object from an event.
     *
     * @return Country
     */
    protected function getObjectFromEvent($event): mixed
    {
        return $event->getCountry();
    }

    /**
     * Load an existing object from the database.
     */
    protected function getExistingObject(): ?ActiveRecordInterface
    {
        $country = CountryQuery::create()
            ->findPk($this->getRequest()->get('country_id', 0));

        if (null !== $country) {
            $country->setLocale($this->getCurrentEditionLocale());
        }

        return $country;
    }

    /**
     * Returns the object label form the object event (name, title, etc.).
     *
     * @param Country $object
     */
    protected function getObjectLabel(ActiveRecordInterface $object): ?string
    {
        return $object->getTitle();
    }

    /**
     * Returns the object ID from the object.
     *
     * @param Country $object
     */
    protected function getObjectId(ActiveRecordInterface $object): int
    {
        return $object->getId();
    }

    /**
     * Render the main list template.
     */
    protected function renderListTemplate(string $currentOrder): Response
    {
        return $this->render('countries', ['display_country' => 20]);
    }

    /**
     * Render the edition template.
     */
    protected function renderEditionTemplate(): Response
    {
        return $this->render('country-edit', $this->getEditionArgument());
    }

    protected function getEditionArgument(): array
    {
        return [
            'country_id' => $this->getRequest()->get('country_id', 0),
        ];
    }

    /**
     * Redirect to the edition template.
     */
    protected function redirectToEditionTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute(
            'admin.configuration.countries.update',
            [],
            [
                'country_id' => $this->getRequest()->get('country_id', 0),
            ],
        );
    }

    /**
     * Redirect to the list template.
     */
    protected function redirectToListTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute('admin.configuration.countries.default');
    }

    public function toggleDefaultAction(EventDispatcherInterface $eventDispatcher): Response
    {
        if (($response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        if (null !== $country_id = $this->getRequest()->get('country_id')) {
            $toogleDefaultEvent = new CountryToggleDefaultEvent($country_id);

            try {
                $eventDispatcher->dispatch($toogleDefaultEvent, TheliaEvents::COUNTRY_TOGGLE_DEFAULT);

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
    protected function createToggleVisibilityEvent(): CountryToggleVisibilityEvent
    {
        return new CountryToggleVisibilityEvent($this->getExistingObject());
    }

    public function getDataAction($visible = true, $locale = null): Response
    {
        $response = $this->checkAuth($this->resourceCode, [], AccessManager::VIEW);

        if ($response instanceof Response) {
            return $response;
        }

        if (null === $locale) {
            $locale = $this->getCurrentEditionLocale();
        }

        $responseData = [];

        $countries = CountryQuery::create()
            ->_if($visible)
            ->filterByVisible(true)
            ->_endif()
            ->joinWithI18n($locale);

        /** @var Country $country */
        foreach ($countries as $country) {
            $currentCountry = [
                'id' => $country->getId(),
                'title' => $country->getTitle(),
                'hasStates' => $country->getHasStates(),
                'states' => [],
            ];

            if ($country->getHasStates()) {
                $states = StateQuery::create()
                    ->filterByCountryId($country->getId())
                    ->_if($visible)
                    ->filterByVisible(true)
                    ->_endif()
                    ->joinWithI18n($locale);

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
