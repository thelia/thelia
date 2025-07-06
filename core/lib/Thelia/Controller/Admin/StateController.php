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
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\State\StateCreateEvent;
use Thelia\Core\Event\State\StateDeleteEvent;
use Thelia\Core\Event\State\StateToggleVisibilityEvent;
use Thelia\Core\Event\State\StateUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\BaseForm;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\State;
use Thelia\Model\StateQuery;

/**
 * Class StateController.
 *
 * @author Julien Chanséaume <manu@raynaud.io>
 */
class StateController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'state',
            'manual',
            'state_order',
            AdminResources::STATE,
            TheliaEvents::STATE_CREATE,
            TheliaEvents::STATE_UPDATE,
            TheliaEvents::STATE_DELETE,
            TheliaEvents::STATE_TOGGLE_VISIBILITY
        );
    }

    /**
     * Return the creation form for this object.
     */
    protected function getCreationForm(): BaseForm
    {
        return $this->createForm(AdminForm::STATE_CREATION);
    }

    /**
     * Return the update form for this object.
     */
    protected function getUpdateForm(): BaseForm
    {
        return $this->createForm(AdminForm::STATE_MODIFICATION);
    }

    /**
     * Hydrate the update form for this object, before passing it to the update template.
     *
     * @param State $object
     */
    protected function hydrateObjectForm(ParserContext $parserContext, ActiveRecordInterface $object): BaseForm
    {
        $data = [
            'id' => $object->getId(),
            'locale' => $object->getLocale(),
            'visible' => (bool) $object->getVisible(),
            'country_id' => $object->getCountryId(),
            'title' => $object->getTitle(),
            'isocode' => $object->getIsocode(),
        ];

        return $this->createForm(AdminForm::STATE_MODIFICATION, FormType::class, $data);
    }

    /**
     * Creates the creation event with the provided form data.
     *
     * @param unknown $formData
     */
    protected function getCreationEvent(array $formData): ActionEvent
    {
        $event = new StateCreateEvent();

        return $this->hydrateEvent($event, $formData);
    }

    /**
     * Creates the update event with the provided form data.
     *
     * @param unknown $formData
     */
    protected function getUpdateEvent(array $formData): ActionEvent
    {
        $event = new StateUpdateEvent($formData['id']);

        return $this->hydrateEvent($event, $formData);
    }

    protected function hydrateEvent($event, array $formData)
    {
        $event
            ->setLocale($formData['locale'])
            ->setVisible($formData['visible'])
            ->setCountry($formData['country_id'])
            ->setTitle($formData['title'])
            ->setIsocode($formData['isocode'])
        ;

        return $event;
    }

    /**
     * Creates the delete event with the provided form data.
     */
    protected function getDeleteEvent(): StateDeleteEvent
    {
        return new StateDeleteEvent($this->getRequest()->get('state_id'));
    }

    /**
     * Return true if the event contains the object, e.g. the action has updated the object in the event.
     *
     * @param unknown $event
     */
    protected function eventContainsObject($event): bool
    {
        return $event->hasState();
    }

    /**
     * Get the created object from an event.
     */
    protected function getObjectFromEvent($event): mixed
    {
        return $event->getState();
    }

    /**
     * Load an existing object from the database.
     */
    protected function getExistingObject(): ?ActiveRecordInterface
    {
        $state = StateQuery::create()
            ->findPk($this->getRequest()->get('state_id', 0))
        ;

        if (null !== $state) {
            $state->setLocale($this->getCurrentEditionLocale());
        }

        return $state;
    }

    /**
     * Returns the object label form the object event (name, title, etc.).
     *
     * @param State $object
     */
    protected function getObjectLabel(activeRecordInterface $object): ?string    {
        return $object->getTitle();
    }

    /**
     * Returns the object ID from the object.
     *
     * @param State $object
     */
    protected function getObjectId(ActiveRecordInterface $object): int
    {
        return $object->getId();
    }

    /**
     * Render the main list template.
     *
     * @param unknown $currentOrder , if any, null otherwise
     */
    protected function renderListTemplate($currentOrder): Response
    {
        return $this->render(
            'states',
            [
                'page' => $this->getRequest()->get('page', 1),
                'page_limit' => $this->getRequest()->get('page_limit', 50),
                'page_order' => $this->getRequest()->get('page_order', 1),
            ]
        );
    }

    /**
     * Render the edition template.
     */
    protected function renderEditionTemplate(): Response
    {
        return $this->render('state-edit', $this->getEditionArgument());
    }

    protected function getEditionArgument(): array
    {
        return [
            'state_id' => $this->getRequest()->get('state_id', 0),
            'page' => $this->getRequest()->get('page', 1),
            'page_order' => $this->getRequest()->get('page_order', 1),
        ];
    }

    /**
     * Redirect to the edition template.
     */
    protected function redirectToEditionTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute(
            'admin.configuration.states.update',
            [],
            [
                'state_id' => $this->getRequest()->get('state_id', 0),
            ]
        );
    }

    /**
     * Redirect to the list template.
     */
    protected function redirectToListTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute('admin.configuration.states.default');
    }

    /**
     * @return StateToggleVisibilityEvent|void
     */
    protected function createToggleVisibilityEvent(): StateToggleVisibilityEvent
    {
        return new StateToggleVisibilityEvent($this->getExistingObject());
    }
}
