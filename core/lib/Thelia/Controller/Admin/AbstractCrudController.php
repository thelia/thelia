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

namespace Thelia\Controller\Admin;

use Propel\Runtime\Event\ActiveRecordEvent;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\Form;
use Symfony\Component\Security\Core\Authentication\RememberMe\TokenProviderInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Translation\Translator;
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
    protected $objectName;

    // List ordering
    protected $defaultListOrder;
    protected $orderRequestParameterName;

    // Permissions
    protected $resourceCode;
    protected $moduleCode;

    // Events
    protected $createEventIdentifier;
    protected $updateEventIdentifier;
    protected $deleteEventIdentifier;
    protected $visibilityToggleEventIdentifier;
    protected $changePositionEventIdentifier;

    /**
     * @param string $objectName the lower case object name. Example. "message"
     *
     * @param string $defaultListOrder          the default object list order, or null if list is not sortable. Example: manual
     * @param string $orderRequestParameterName Name of the request parameter that set the list order (null if list is not sortable)
     *
     * @param string $resourceCode the 'resource' code. Example: "admin.configuration.message"
     *
     * @param string $createEventIdentifier the dispatched create TheliaEvent identifier. Example: TheliaEvents::MESSAGE_CREATE
     * @param string $updateEventIdentifier the dispatched update TheliaEvent identifier. Example: TheliaEvents::MESSAGE_UPDATE
     * @param string $deleteEventIdentifier the dispatched delete TheliaEvent identifier. Example: TheliaEvents::MESSAGE_DELETE
     *
     * @param string $visibilityToggleEventIdentifier the dispatched visibility toggle TheliaEvent identifier, or null if the object has no visible options. Example: TheliaEvents::MESSAGE_TOGGLE_VISIBILITY
     * @param string $changePositionEventIdentifier   the dispatched position change TheliaEvent identifier, or null if the object has no position. Example: TheliaEvents::MESSAGE_UPDATE_POSITION
     * @param string $moduleCode The module code for ACL
     */
    public function __construct(
        $objectName,
        $defaultListOrder,
        $orderRequestParameterName,
        $resourceCode,
        $createEventIdentifier,
        $updateEventIdentifier,
        $deleteEventIdentifier,
        $visibilityToggleEventIdentifier = null,
        $changePositionEventIdentifier = null,
        $moduleCode = null
    ) {
        $this->objectName = $objectName;

        $this->defaultListOrder = $defaultListOrder;
        $this->orderRequestParameterName = $orderRequestParameterName;

        $this->resourceCode  = $resourceCode;

        $this->createEventIdentifier = $createEventIdentifier;
        $this->updateEventIdentifier = $updateEventIdentifier;
        $this->deleteEventIdentifier = $deleteEventIdentifier;
        $this->visibilityToggleEventIdentifier = $visibilityToggleEventIdentifier;
        $this->changePositionEventIdentifier = $changePositionEventIdentifier;

        $this->moduleCode = $moduleCode;
    }

    /**
     * Return the creation form for this object
     * @return BaseForm
     */
    abstract protected function getCreationForm();

    /**
     * Return the update form for this object
     * @return BaseForm
     */
    abstract protected function getUpdateForm();

    /**
     * Hydrate the update form for this object, before passing it to the update template
     *
     * @return BaseForm
     */
    abstract protected function hydrateObjectForm(ParserContext $parserContext, $object);

    /**
     * Creates the creation event with the provided form data
     *
     * @return \Thelia\Core\Event\ActionEvent
     */
    abstract protected function getCreationEvent($formData);

    /**
     * Creates the update event with the provided form data
     *
     * @return \Thelia\Core\Event\ActionEvent
     */
    abstract protected function getUpdateEvent($formData);

    /**
     * Creates the delete event with the provided form data
     * @return \Thelia\Core\Event\ActionEvent
     */
    abstract protected function getDeleteEvent();

    /**
     * Return true if the event contains the object, e.g. the action has updated the object in the event.
     *
     */
    protected function eventContainsObject($event)
    {
        if (method_exists($event, 'getModel')) {
            return null !== $event->getModel();
        }

        // If event doesn't have "getModel" method this function must be overrided
        return false;
    }

    /**
     * Get the created object from an event.
     *
     */
    protected function getObjectFromEvent($event)
    {
        if (method_exists($event, 'getModel')) {
            return $event->getModel();
        }

        throw new \Exception("If your event doesn't have  \"getModel\" method you must override \"getObjectFromEvent\" function.");
    }

    /**
     * Load an existing object from the database
     */
    abstract protected function getExistingObject();

    /**
     * Returns the object label form the object event (name, title, etc.)
     *
     * @param string|null $object
     */
    abstract protected function getObjectLabel($object);

    /**
     * Returns the object ID from the object
     *
     * @param int|null $object
     */
    abstract protected function getObjectId($object);

    /**
     * Render the main list template
     *
     * @return \Thelia\Core\HttpFoundation\Response
     */
    abstract protected function renderListTemplate($currentOrder);

    /**
     * Render the edition template
     * @return \Thelia\Core\HttpFoundation\Response
     */
    abstract protected function renderEditionTemplate();

    /**
     * Must return a RedirectResponse instance
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    abstract protected function redirectToEditionTemplate();

    /**
     * Must return a RedirectResponse instance
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    abstract protected function redirectToListTemplate();

    /**
     * @param $positionChangeMode
     * @param $positionValue
     * @return ActionEvent
     */
    protected function createUpdatePositionEvent($positionChangeMode, $positionValue)
    {
        throw new \LogicException("Position Update is not supported for this object");
    }

    /**
     * @return ActionEvent
     */
    protected function createToggleVisibilityEvent()
    {
        throw new \LogicException("Toggle Visibility is not supported for this object");
    }

    /**
     * Put in this method post object creation processing if required.
     *
     * @param  ActionEvent $createEvent the create event
     * @return Response a response, or null to continue normal processing
     */
    protected function performAdditionalCreateAction($createEvent)
    {
        return null;
    }

    /**
     * Put in this method post object update processing if required.
     *
     * @param ActionEvent $updateEvent the update event
     * @return Response a response, or null to continue normal processing
     */
    protected function performAdditionalUpdateAction($updateEvent)
    {
        return null;
    }

    /**
     * Put in this method post object delete processing if required.
     *
     * @param ActionEvent $deleteEvent the delete event
     * @return Response a response, or null to continue normal processing
     */
    protected function performAdditionalDeleteAction($deleteEvent)
    {
        return null;
    }

    /**
     * Put in this method post object position change processing if required.
     *
     * @param ActionEvent $positionChangeEvent the delete event
     * @return Response|null a response, or null to continue normal processing
     */
    protected function performAdditionalUpdatePositionAction($positionChangeEvent)
    {
        return null;
    }

    /**
     * Return the current list order identifier, updating it in the same time.
     */
    protected function getCurrentListOrder($update_session = true)
    {
        return $this->getListOrderFromSession(
            $this->objectName,
            $this->orderRequestParameterName,
            $this->defaultListOrder
        );
    }

    protected function getModuleCode()
    {
        if (null !== $this->moduleCode) {
            return [$this->moduleCode];
        }
            return [];
    }

    /**
     * Render the object list, ensuring the sort order is set.
     *
     * @return \Thelia\Core\HttpFoundation\Response the response
     */
    protected function renderList()
    {
        return $this->renderListTemplate($this->getCurrentListOrder());
    }

    /**
     * The default action is displaying the list.
     *
     * @return \Thelia\Core\HttpFoundation\Response the response
     */
    public function defaultAction()
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, $this->getModuleCode(), AccessManager::VIEW)) {
            return $response;
        }

        return $this->renderList();
    }

    /**
     * Create a new object
     *
     * @return \Thelia\Core\HttpFoundation\Response the response
     */
    public function createAction(
        EventDispatcherInterface $eventDispatcher,
        TranslatorInterface $translator
    ) {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, $this->getModuleCode(), AccessManager::CREATE)) {
            return $response;
        }

        // Error (Default: false)
        $error_msg = false;

        // Create the Creation Form
        $creationForm = $this->getCreationForm();

        try {
            // Check the form against constraints violations
            $form = $this->validateForm($creationForm, "POST");

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
            if (! $this->eventContainsObject($createEvent)) {
                throw new \LogicException(
                    $translator->trans("No %obj was created.", ['%obj' => $this->objectName])
                );
            }

            // Log object creation
            if (null !== $createdObject = $this->getObjectFromEvent($createEvent)) {
                $this->adminLogAppend(
                    $this->resourceCode,
                    AccessManager::CREATE,
                    sprintf(
                        "%s %s (ID %s) created",
                        ucfirst($this->objectName),
                        $this->getObjectLabel($createdObject),
                        $this->getObjectId($createdObject)
                    ),
                    $this->getObjectId($createdObject)
                );
            }

            // Execute additional Action
            $response = $this->performAdditionalCreateAction($createEvent);

            if ($response == null) {
                // Substitute _ID_ in the URL with the ID of the created object
                $successUrl = str_replace('_ID_', $this->getObjectId($createdObject), $creationForm->getSuccessUrl());

                // Redirect to the success URL
                return $this->generateRedirect($successUrl);
            }
                return $response;
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        if (false !== $error_msg) {
            $this->setupFormErrorContext(
                $translator->trans("%obj creation", ['%obj' => $this->objectName]),
                $error_msg,
                $creationForm,
                $ex
            );

            // At this point, the form has error, and should be redisplayed.
            return $this->renderList();
        }
    }

    /**
     * Load a object for modification, and display the edit template.
     *
     * @return \Thelia\Core\HttpFoundation\Response the response
     */
    public function updateAction(
        ParserContext $parserContext
    )
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, $this->getModuleCode(), AccessManager::UPDATE)) {
            return $response;
        }

        // Load object if exist
        if (null !== $object = $this->getExistingObject()) {
            // Hydrate the form abd pass it to the parser
            $changeForm = $this->hydrateObjectForm($parserContext, $object);

            // Pass it to the parser
            $parserContext->addForm($changeForm);
        }

        // Render the edition template.
        return $this->renderEditionTemplate();
    }

    /**
     * Save changes on a modified object, and either go back to the object list, or stay on the edition page.
     *
     * @return \Thelia\Core\HttpFoundation\Response the response
     */
    public function processUpdateAction(
        Request $request,
        EventDispatcherInterface $eventDispatcher,
        TranslatorInterface $translator
    )
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, $this->getModuleCode(), AccessManager::UPDATE)) {
            return $response;
        }

        // Create the Form from the request
        $changeForm = $this->getUpdateForm();

        try {
            // Check the form against constraints violations
            $form = $this->validateForm($changeForm, "POST");

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
            if (! $this->eventContainsObject($changeEvent)) {
                throw new \LogicException(
                    $translator->trans("No %obj was updated.", ['%obj' => $this->objectName])
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

            // Execute additional Action
            $response = $this->performAdditionalUpdateAction($changeEvent);

            if ($response == null) {
                // If we have to stay on the same page, do not redirect to the successUrl,
                // just redirect to the edit page again.
                if ($request->get('save_mode') == 'stay') {
                    return $this->redirectToEditionTemplate();
                }

                // Redirect to the success URL
                return $this->generateSuccessRedirect($changeForm);
            }
                return $response;
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        if (false !== $error_msg) {
            // At this point, the form has errors, and should be redisplayed.
            $this->setupFormErrorContext(
                $translator->trans("%obj modification", ['%obj' => $this->objectName]),
                $error_msg,
                $changeForm,
                $ex
            );

            return $this->renderEditionTemplate();
        }
    }

    /**
     * Update object position (only for objects whichsupport that)
     * @return mixed|string|\Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response|Response|null
     */
    public function updatePositionAction(
        Request $request,
        EventDispatcherInterface $eventDispatcher
    )
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, $this->getModuleCode(), AccessManager::UPDATE)) {
            return $response;
        }

        try {
            $mode = $request->get('mode', null);

            if ($mode == 'up') {
                $mode = UpdatePositionEvent::POSITION_UP;
            } elseif ($mode == 'down') {
                $mode = UpdatePositionEvent::POSITION_DOWN;
            } else {
                $mode = UpdatePositionEvent::POSITION_ABSOLUTE;
            }

            $position = $request->get('position', null);

            $event = $this->createUpdatePositionEvent($mode, $position);

            $eventDispatcher->dispatch($event, $this->changePositionEventIdentifier);
        } catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        $response = $this->performAdditionalUpdatePositionAction($event);

        if ($response == null) {
            return $this->redirectToListTemplate();
        }
            return $response;
    }

    protected function genericUpdatePositionAction(Request $request, EventDispatcherInterface $eventDispatcher, $object, $eventName, $doFinalRedirect = true)
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, $this->getModuleCode(), AccessManager::UPDATE)) {
            return $response;
        }

        if ($object != null) {
            try {
                $mode = $request->get('mode', null);

                if ($mode == 'up') {
                    $mode = UpdatePositionEvent::POSITION_UP;
                } elseif ($mode == 'down') {
                    $mode = UpdatePositionEvent::POSITION_DOWN;
                } else {
                    $mode = UpdatePositionEvent::POSITION_ABSOLUTE;
                }

                $position = $request->get('position', null);

                $event = new UpdatePositionEvent($object->getId(), $mode, $position);

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

    /**
     * Online status toggle (only for object which support it)
     */
    public function setToggleVisibilityAction(
        EventDispatcherInterface $eventDispatcher
    )
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, $this->getModuleCode(), AccessManager::UPDATE)) {
            return $response;
        }

        $changeEvent = $this->createToggleVisibilityEvent();

        try {
            $eventDispatcher->dispatch($changeEvent, $this->visibilityToggleEventIdentifier);
        } catch (\Exception $ex) {
            // Any error
            return $this->errorPage($ex);
        }

        return $this->nullResponse();
    }

    /**
     * Delete an object
     *
     * @return \Thelia\Core\HttpFoundation\Response the response
     */
    public function deleteAction(
        Request $request,
        TokenProvider $tokenProvider,
        EventDispatcherInterface $eventDispatcher,
        ParserContext $parserContext
    )
    {
        // Check current user authorization
        if (null !== $response = $this->checkAuth($this->resourceCode, $this->getModuleCode(), AccessManager::DELETE)) {
            return $response;
        }

        try {
            // Check token
            $tokenProvider->checkToken(
                $request->query->get("_token")
            );

            // Get the currency id, and dispatch the delete request
            $deleteEvent = $this->getDeleteEvent();

            $eventDispatcher->dispatch($deleteEvent, $this->deleteEventIdentifier);

            if (null !== $deletedObject = $this->getObjectFromEvent($deleteEvent)) {
                $this->adminLogAppend(
                    $this->resourceCode,
                    AccessManager::DELETE,
                    sprintf(
                        "%s %s (ID %s) deleted",
                        ucfirst($this->objectName),
                        $this->getObjectLabel($deletedObject),
                        $this->getObjectId($deletedObject)
                    ),
                    $this->getObjectId($deletedObject)
                );
            }

            $response = $this->performAdditionalDeleteAction($deleteEvent);

            if ($response == null) {
                return $this->redirectToListTemplate();
            }
                return $response;
        } catch (\Exception $e) {
            return $this->renderAfterDeleteError($parserContext,$e);
        }
    }

    /**
     * @return \Thelia\Core\HttpFoundation\Response
     */
    protected function renderAfterDeleteError(ParserContext $parserContext, \Exception $e)
    {
        $errorMessage = sprintf(
            "Unable to delete '%s'. Error message: %s",
            $this->objectName,
            $e->getMessage()
        );

        $parserContext
            ->setGeneralError($errorMessage)
        ;

        return $this->defaultAction();
    }

    /**
     * @return \Thelia\Core\HttpFoundation\Request
     * @since 2.3
     *
     * @deprecated since Thelia 2.5, use autowiring instead.
     */
    protected function getRequest()
    {
        return $this->container->get('request_stack')->getCurrentRequest();
    }

    protected function bindFormToPropelEvent(ActiveRecordEvent $propelEvent, Form $form)
    {
        $fields = $form->getIterator();

        /** @var \Symfony\Component\Form\Form $field */
        foreach ($fields as $field) {
            $functionName = sprintf("set%s", Container::camelize($field->getName()));
            if (method_exists($propelEvent, $functionName)) {
                $getFunctionName = sprintf("get%s", Container::camelize($field->getName()));
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
