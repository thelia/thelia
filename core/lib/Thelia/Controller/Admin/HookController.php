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
use Thelia\Core\Event\Hook\ModuleHookToggleActivationEvent;
use Thelia\Core\Event\Hook\ModuleHookUpdatePositionEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\ModuleHookQuery;


/**
 * Class HookController
 * @package Thelia\Controller\Admin
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookController extends AbstractCrudController {

    public function __construct()
    {
        parent::__construct(
            'hook',
            'manual',
            'hook_order',

            AdminResources::HOOK,

            null,
            TheliaEvents::HOOK_UPDATE,
            null,
            null,
            TheliaEvents::HOOK_UPDATE_POSITION
        );
    }

    public function indexAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::HOOK, array(), AccessManager::VIEW)) return $response;

        return $this->renderList();
    }

    public function toggleActivationAction($module_hook_id)
    {
        if (null !== $response = $this->checkAuth(AdminResources::HOOK, array(), AccessManager::UPDATE)) return $response;
        $message = null;

        $event = new ModuleHookToggleActivationEvent($this->getExistingObject());

        try {
            $this->dispatch(TheliaEvents::HOOK_TOGGLE_ACTIVATION, $event);
        } catch (\Exception $ex) {
            $message = $ex->getMessage();
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            if ($message) {
                $response = $this->jsonResponse(json_encode(array(
                    "error" => $message
                )), 500);
            } else {
                $response = $this->nullResponse();
            }
        } else {
            $this->redirectToRoute('admin.hook');
        }

        return $response;
    }

    protected function createUpdatePositionEvent($positionChangeMode, $positionValue)
    {
        return new UpdatePositionEvent(
            $this->getRequest()->get('module_hook_id', null),
            $positionChangeMode,
            $positionValue
        );
    }

    /**
     * Return the creation form for this object
     */
    protected function getCreationForm()
    {
        // TODO: Implement getCreationForm() method.
    }

    /**
     * Return the update form for this object
     */
    protected function getUpdateForm()
    {
        // TODO: Implement getUpdateForm() method.
    }

    /**
     * Hydrate the update form for this object, before passing it to the update template
     *
     * @param unknown $object
     */
    protected function hydrateObjectForm($object)
    {
        // TODO: Implement hydrateObjectForm() method.
    }

    /**
     * Creates the creation event with the provided form data
     *
     * @param unknown $formData
     */
    protected function getCreationEvent($formData)
    {
        // TODO: Implement getCreationEvent() method.
    }

    /**
     * Creates the update event with the provided form data
     *
     * @param unknown $formData
     */
    protected function getUpdateEvent($formData)
    {
        // TODO: Implement getUpdateEvent() method.
    }

    /**
     * Creates the delete event with the provided form data
     */
    protected function getDeleteEvent()
    {
        // TODO: Implement getDeleteEvent() method.
    }

    /**
     * Return true if the event contains the object, e.g. the action has updated the object in the event.
     *
     * @param unknown $event
     */
    protected function eventContainsObject($event)
    {
        // TODO: Implement eventContainsObject() method.
    }

    /**
     * Get the created object from an event.
     *
     * @param unknown $event
     */
    protected function getObjectFromEvent($event)
    {
        // TODO: Implement getObjectFromEvent() method.
    }

    /**
     * Load an existing object from the database
     */
    protected function getExistingObject()
    {
        $moduleHook = ModuleHookQuery::create()
            ->findPK($this->getRequest()->get('module_hook_id', 0));

        return $moduleHook;
    }

    /**
     * Returns the object label form the object event (name, title, etc.)
     *
     * @param unknown $object
     */
    protected function getObjectLabel($object)
    {
        // TODO: Implement getObjectLabel() method.
    }

    /**
     * Returns the object ID from the object
     *
     * @param unknown $object
     */
    protected function getObjectId($object)
    {
        // TODO: Implement getObjectId() method.
    }

    /**
     * Render the main list template
     *
     * @param unknown $currentOrder , if any, null otherwise.
     */
    protected function renderListTemplate($currentOrder)
    {
        return $this->render(
            'hooks',
            array('module_order' => $currentOrder)
        );
    }

    /**
     * Render the edition template
     */
    protected function renderEditionTemplate()
    {
        // TODO: Implement renderEditionTemplate() method.
    }

    /**
     * Redirect to the edition template
     */
    protected function redirectToEditionTemplate($request = null, $country = null)
    {
        // We always return to the module edition form
        $this->redirectToRoute(
            "admin.hook.update",
            $this->getViewArguments(),
            $this->getRouteArguments()
        );
    }

    /**
     * Redirect to the list template
     */
    protected function redirectToListTemplate()
    {
        $this->redirectToRoute(
            "admin.hook"
        );
    }

    protected function getViewArguments()
    {
        return array();
    }

    protected function getRouteArguments($module_hook_id = null)
    {
        return array(
            'module_hook_id' => $module_hook_id === null ? $this->getRequest()->get('module_hook_id') : $module_hook_id,
        );
    }
}