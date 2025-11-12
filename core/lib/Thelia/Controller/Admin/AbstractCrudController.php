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
use Propel\Runtime\Event\ActiveRecordEvent;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\BaseForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Tools\TokenProvider;

/**
 * An abstract CRUD controller for Thelia ADMIN, to manage basic CRUD operations on a givent object.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
abstract class AbstractCrudController extends BaseAdminController
{
    public function __construct(
        protected string $objectName,
        protected ?string $defaultListOrder,
        protected ?string $orderRequestParameterName,
        protected string $resourceCode,
        protected ?string $createEventIdentifier,
        protected ?string $updateEventIdentifier,
        protected ?string $deleteEventIdentifier,
        protected ?string $visibilityToggleEventIdentifier = null,
        protected ?string $changePositionEventIdentifier = null,
        protected ?string $moduleCode = null,
    ) {
    }

    abstract protected function getCreationForm(): ?BaseForm;

    abstract protected function getUpdateForm(): ?BaseForm;

    abstract protected function hydrateObjectForm(ParserContext $parserContext, ActiveRecordInterface $object): BaseForm;

    abstract protected function getCreationEvent(array $formData): ActionEvent|ActiveRecordEvent|null;

    abstract protected function getUpdateEvent(array $formData): ActionEvent|ActiveRecordEvent|null;

    abstract protected function getDeleteEvent(): ActiveRecordEvent|ActionEvent|null;

    abstract protected function getExistingObject(): ?ActiveRecordInterface;

    abstract protected function getObjectLabel(ActiveRecordInterface $object): ?string;

    abstract protected function getObjectId(ActiveRecordInterface $object): int;

    abstract protected function renderListTemplate(string $currentOrder): Response;

    abstract protected function renderEditionTemplate(): Response;

    abstract protected function redirectToEditionTemplate(): Response|RedirectResponse;

    abstract protected function redirectToListTemplate(): Response|RedirectResponse;

    protected function eventContainsObject(Event $event): bool
    {
        if (method_exists($event, 'getModel')) {
            return null !== $event->getModel();
        }

        // If event doesn't have "getModel" method this function must be overrided
        return false;
    }

    /**
     * @throws \Exception
     */
    protected function getObjectFromEvent(Event $event): mixed
    {
        if (method_exists($event, 'getModel')) {
            return $event->getModel();
        }

        throw new \Exception("If your event doesn't have  \"getModel\" method you must override \"getObjectFromEvent\" function.");
    }

    protected function createUpdatePositionEvent(int $positionChangeMode, int $positionValue): ActionEvent
    {
        throw new \LogicException('Position Update is not supported for this object');
    }

    protected function createToggleVisibilityEvent(): ActionEvent
    {
        throw new \LogicException('Toggle Visibility is not supported for this object');
    }

    protected function performAdditionalCreateAction(ActionEvent|ActiveRecordEvent|null $createEvent): ?Response
    {
        return null;
    }

    protected function performAdditionalUpdateAction(EventDispatcherInterface $eventDispatcher, ActionEvent|ActiveRecordEvent|null $updateEvent): ?Response
    {
        return null;
    }

    protected function performAdditionalDeleteAction(ActionEvent|ActiveRecordEvent|null $deleteEvent): ?Response
    {
        return null;
    }

    protected function performAdditionalUpdatePositionAction(ActionEvent $positionChangeEvent): ?Response
    {
        return null;
    }

    protected function getCurrentListOrder(): ?string
    {
        return $this->getListOrderFromSession(
            $this->objectName,
            $this->orderRequestParameterName,
            $this->defaultListOrder,
        );
    }

    protected function getModuleCode(): array
    {
        if (null !== $this->moduleCode) {
            return [$this->moduleCode];
        }

        return [];
    }

    protected function renderList(): Response
    {
        return $this->renderListTemplate($this->getCurrentListOrder());
    }

    public function defaultAction(): Response
    {
        // Check current user authorization
        if (($response = $this->checkAuth($this->resourceCode, $this->getModuleCode(), AccessManager::VIEW)) instanceof Response) {
            return $response;
        }

        return $this->renderList();
    }

    public function createAction(
        EventDispatcherInterface $eventDispatcher,
        TranslatorInterface $translator,
    ): RedirectResponse|Response {
        // Check current user authorization
        if (($response = $this->checkAuth($this->resourceCode, $this->getModuleCode(), AccessManager::CREATE)) instanceof Response) {
            return $response;
        }

        // Create the Creation Form
        $creationForm = $this->getCreationForm();

        try {
            // Check the form against constraints violations
            $form = $this->validateForm($creationForm, 'POST');

            // Get the form field values
            $data = $form->getData();

            // Create a new event object with the modified fields
            $createEvent = $this->getCreationEvent($data);

            if (method_exists($createEvent, 'bindForm')) {
                $createEvent->bindForm($form);
            } elseif ($createEvent instanceof ActiveRecordEvent) {
                $this->bindFormToPropelEvent($createEvent, $form);
            }

            // Dispatch Create Event
            $eventDispatcher->dispatch($createEvent, $this->createEventIdentifier);

            // Check if object exist
            if (!$this->eventContainsObject($createEvent)) {
                throw new \LogicException($translator->trans('No %obj was created.', ['%obj' => $this->objectName]));
            }

            // Log object creation
            if (null !== $createdObject = $this->getObjectFromEvent($createEvent)) {
                $this->adminLogAppend(
                    $this->resourceCode,
                    AccessManager::CREATE,
                    \sprintf(
                        '%s %s (ID %s) created',
                        ucfirst($this->objectName),
                        $this->getObjectLabel($createdObject),
                        $this->getObjectId($createdObject),
                    ),
                    $this->getObjectId($createdObject),
                );
            }

            // Execute additional Action
            $response = $this->performAdditionalCreateAction($createEvent);

            if (!$response instanceof Response) {
                // Substitute _ID_ in the URL with the ID of the created object
                $successUrl = str_replace('_ID_', (string) $this->getObjectId($createdObject), $creationForm->getSuccessUrl());

                // Redirect to the success URL
                return $this->generateRedirect($successUrl);
            }

            return $response;
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $errorMessage = $this->createStandardFormValidationErrorMessage($ex);
            $errorCode = 400;
        }

        // At this point, the form has error, and should be redisplayed.
        $this->setupFormErrorContext(
            $translator->trans('%obj creation', ['%obj' => $this->objectName]),
            $errorMessage,
            $creationForm,
            $ex,
        );

        return $this->renderList()
            ->setStatusCode($errorCode);
    }

    public function updateAction(
        ParserContext $parserContext,
    ): Response {
        // Check current user authorization
        if (($response = $this->checkAuth($this->resourceCode, $this->getModuleCode(), AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        // Load object if exist
        if (($object = $this->getExistingObject()) instanceof ActiveRecordInterface) {
            // Hydrate the form abd pass it to the parser
            $changeForm = $this->hydrateObjectForm($parserContext, $object);

            // Pass it to the parser
            $parserContext->addForm($changeForm);
        }

        // Render the edition template.
        return $this->renderEditionTemplate();
    }

    public function processUpdateAction(
        Request $request,
        EventDispatcherInterface $eventDispatcher,
        TranslatorInterface $translator,
    ): Response|RedirectResponse {
        // Check current user authorization
        if (($response = $this->checkAuth($this->resourceCode, $this->getModuleCode(), AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }
        // Create the Form from the request
        $changeForm = $this->getUpdateForm();

        try {
            // Check the form against constraints violations
            $form = $this->validateForm($changeForm, 'POST');

            // Get the form field values
            $data = $form->getData();

            // Create a new event object with the modified fields
            $changeEvent = $this->getUpdateEvent($data);
            if (method_exists($changeEvent, 'bindForm')) {
                $changeEvent->bindForm($form);
            } elseif ($changeEvent instanceof ActiveRecordEvent) {
                $this->bindFormToPropelEvent($changeEvent, $form);
            }
            // Dispatch Update Event
            $eventDispatcher->dispatch($changeEvent, $this->updateEventIdentifier);

            // Check if object exist
            if (!$this->eventContainsObject($changeEvent)) {
                throw new \LogicException($translator->trans('No %obj was updated.', ['%obj' => $this->objectName]));
            }

            // Log object modification
            if (($changedObject = $this->getObjectFromEvent($changeEvent)) instanceof ActiveRecordInterface) {
                $this->adminLogAppend(
                    $this->resourceCode,
                    AccessManager::UPDATE,
                    \sprintf(
                        '%s %s (ID %s) modified',
                        ucfirst($this->objectName),
                        $this->getObjectLabel($changedObject),
                        $this->getObjectId($changedObject),
                    ),
                    $this->getObjectId($changedObject),
                );
            }

            // Execute additional Action
            $response = $this->performAdditionalUpdateAction($eventDispatcher, $changeEvent);

            if (!$response instanceof Response) {
                // If we have to stay on the same page, do not redirect to the successUrl,
                // just redirect to the edit page again.
                if ('stay' === $request->get('save_mode')) {
                    return $this->redirectToEditionTemplate();
                }

                // Redirect to the success URL
                return $this->generateSuccessRedirect($changeForm);
            }

            return $response;
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $errorMessage = $this->createStandardFormValidationErrorMessage($ex);
            $errorCode = 500;
        }

        // At this point, the form has errors, and should be redisplayed.
        $this->setupFormErrorContext(
            $translator->trans('%obj modification', ['%obj' => $this->objectName]),
            $errorMessage,
            $changeForm,
            $ex,
        );

        return $this->renderEditionTemplate()
            ->setStatusCode($errorCode);
    }

    public function updatePositionAction(
        Request $request,
        EventDispatcherInterface $eventDispatcher,
    ): mixed {
        // Check current user authorization
        if (($response = $this->checkAuth($this->resourceCode, $this->getModuleCode(), AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        try {
            $mode = $request->get('mode');

            if ('up' === $mode) {
                $mode = UpdatePositionEvent::POSITION_UP;
            } elseif ('down' === $mode) {
                $mode = UpdatePositionEvent::POSITION_DOWN;
            } else {
                $mode = UpdatePositionEvent::POSITION_ABSOLUTE;
            }

            $position = (int) $request->get('position');

            $event = $this->createUpdatePositionEvent($mode, $position);

            $eventDispatcher->dispatch($event, $this->changePositionEventIdentifier);
        } catch (\Exception $exception) {
            // Any error
            return $this->errorPage($exception);
        }

        $response = $this->performAdditionalUpdatePositionAction($event);

        if (!$response instanceof Response) {
            return $this->redirectToListTemplate();
        }

        return $response;
    }

    protected function genericUpdatePositionAction(
        Request $request,
        EventDispatcherInterface $eventDispatcher,
        mixed $object,
        ?string $eventName,
        $doFinalRedirect = true,
    ): RedirectResponse|Response|null {
        // Check current user authorization
        if (($response = $this->checkAuth($this->resourceCode, $this->getModuleCode(), AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        if (null !== $object) {
            try {
                $mode = $request->get('mode');

                if ('up' === $mode) {
                    $mode = UpdatePositionEvent::POSITION_UP;
                } elseif ('down' === $mode) {
                    $mode = UpdatePositionEvent::POSITION_DOWN;
                } else {
                    $mode = UpdatePositionEvent::POSITION_ABSOLUTE;
                }

                $position = (int) $request->get('position');

                $event = new UpdatePositionEvent((int) $object->getId(), $mode, $position);

                $eventDispatcher->dispatch($event, $eventName);
            } catch (\Exception $ex) {
                // Any error
                return $this->errorPage($ex);
            }
        }

        if ($doFinalRedirect) {
            return $this->redirectToEditionTemplate();
        }

        return null;
    }

    public function setToggleVisibilityAction(
        EventDispatcherInterface $eventDispatcher,
    ): ?Response {
        // Check current user authorization
        if (($response = $this->checkAuth($this->resourceCode, $this->getModuleCode(), AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $changeEvent = $this->createToggleVisibilityEvent();

        try {
            $eventDispatcher->dispatch($changeEvent, $this->visibilityToggleEventIdentifier);
        } catch (\Exception $exception) {
            // Any error
            return $this->errorPage($exception);
        }

        return $this->nullResponse();
    }

    public function deleteAction(
        Request $request,
        TokenProvider $tokenProvider,
        EventDispatcherInterface $eventDispatcher,
        ParserContext $parserContext,
    ): Response|RedirectResponse {
        // Check current user authorization
        if (($response = $this->checkAuth($this->resourceCode, $this->getModuleCode(), AccessManager::DELETE)) instanceof Response) {
            return $response;
        }

        try {
            // Check token
            $tokenProvider->checkToken(
                $request->query->get('_token'),
            );

            // Get the currency id, and dispatch the delete request
            $deleteEvent = $this->getDeleteEvent();

            $eventDispatcher->dispatch($deleteEvent, $this->deleteEventIdentifier);

            if (null !== $deletedObject = $this->getObjectFromEvent($deleteEvent)) {
                $this->adminLogAppend(
                    $this->resourceCode,
                    AccessManager::DELETE,
                    \sprintf(
                        '%s %s (ID %s) deleted',
                        ucfirst($this->objectName),
                        $this->getObjectLabel($deletedObject),
                        $this->getObjectId($deletedObject),
                    ),
                    $this->getObjectId($deletedObject),
                );
            }

            $response = $this->performAdditionalDeleteAction($deleteEvent);

            return $response ?? $this->redirectToListTemplate();
        } catch (\Exception $exception) {
            return $this->renderAfterDeleteError($parserContext, $exception)->setStatusCode(Response::HTTP_BAD_REQUEST);
        }
    }

    protected function renderAfterDeleteError(ParserContext $parserContext, \Exception $e): Response
    {
        $errorMessage = \sprintf(
            "Unable to delete '%s'. Error message: %s",
            $this->objectName,
            $e->getMessage(),
        );

        $parserContext
            ->setGeneralError($errorMessage);

        return $this->defaultAction();
    }

    protected function bindFormToPropelEvent(ActiveRecordEvent $propelEvent, Form $form): void
    {
        $fields = $form->getIterator();

        /** @var Form $field */
        foreach ($fields as $field) {
            $functionName = \sprintf('set%s', Container::camelize($field->getName()));

            if (method_exists($propelEvent, $functionName)) {
                $getFunctionName = \sprintf('get%s', Container::camelize($field->getName()));

                if (method_exists($propelEvent, $getFunctionName)) {
                    if (null === $propelEvent->{$getFunctionName}()) {
                        $propelEvent->{$functionName}($field->getData());
                    }
                } else {
                    $propelEvent->{$functionName}($field->getData());
                }
            } else {
                $propelEvent->{$field->getName()} = $field->getData();
            }
        }
    }
}
