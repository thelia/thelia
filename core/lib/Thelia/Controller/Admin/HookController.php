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

use Thelia\Core\Event\Hook\HookCreateEvent;
use Thelia\Core\Event\Hook\HookDeleteEvent;
use Thelia\Core\Event\Hook\HookUpdateEvent;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Form\HookCreationForm;
use Thelia\Form\HookModificationForm;
use Thelia\Model\HookQuery;

/**
 * Class HookController
 * @package Thelia\Controller\Admin
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookController extends AbstractCrudController
{

    public function __construct()
    {
        parent::__construct(
            'hook',
            'manual',
            'hook_order',

            AdminResources::HOOK,

            TheliaEvents::HOOK_CREATE,
            TheliaEvents::HOOK_UPDATE,
            TheliaEvents::HOOK_DELETE
        );
    }

    /**
     * Return the creation form for this object
     */
    protected function getCreationForm()
    {
        return new HookCreationForm($this->getRequest());
    }

    /**
     * Return the update form for this object
     */
    protected function getUpdateForm()
    {
        return new HookModificationForm($this->getRequest());
    }

    /**
     * Hydrate the update form for this object, before passing it to the update template
     *
     * @param \Thelia\Model\Hook $object
     */
    protected function hydrateObjectForm($object)
    {
        $data = array(
            'id' => $object->getId(),
            'code' => $object->getCode(),
            'type' => $object->getType(),
            'native' => $object->getNative(),
            'locale' => $object->getLocale(),
            'title' => $object->getTitle(),
            'description' => $object->getTitle(),
        );

        return new HookModificationForm($this->getRequest(), 'form', $data);
    }

    /**
     * Creates the creation event with the provided form data
     *
     * @param unknown $formData
     */
    protected function getCreationEvent($formData)
    {
        $event = new HookCreateEvent();

        return $this->hydrateEvent($event, $formData);
    }

    /**
     * Creates the update event with the provided form data
     *
     * @param unknown $formData
     */
    protected function getUpdateEvent($formData)
    {
        $event = new HookUpdateEvent($formData['id']);

        return $this->hydrateEvent($event, $formData);
    }

    protected function hydrateEvent($event, $formData)
    {
        $event
            ->setLocale($formData['locale'])
            ->setTitle($formData['title'])
            ->setType($formData['type'])
            ->setCode($formData['code'])
            ->setNative($formData['native'])
            ->setDescription($formData['description'])
        ;

        return $event;
    }

    /**
     * Creates the delete event with the provided form data
     */
    protected function getDeleteEvent()
    {
        return new HookDeleteEvent($this->getRequest()->get('hook_id'));
    }

    /**
     * Return true if the event contains the object, e.g. the action has updated the object in the event.
     *
     * @param unknown $event
     */
    protected function eventContainsObject($event)
    {
        return $event->hasHook();
    }

    /**
     * Get the created object from an event.
     *
     * @param unknown $createEvent
     */
    protected function getObjectFromEvent($event)
    {
        return $event->getHook();
    }

    /**
     * Load an existing object from the database
     */
    protected function getExistingObject()
    {
        $hook = HookQuery::create()
            ->findPk($this->getRequest()->get('hook_id', 0));

        if (null !== $hook) {
            $hook->setLocale($this->getCurrentEditionLocale());
        }

        return $hook;
    }

    /**
     * Returns the object label form the object event (name, title, etc.)
     *
     * @param \Thelia\Model\Hook $object
     */
    protected function getObjectLabel($object)
    {
        return $object->getTitle();
    }

    /**
     * Returns the object ID from the object
     *
     * @param \Thelia\Model\Hook $object
     */
    protected function getObjectId($object)
    {
        return $object->getId();
    }

    /**
     * Render the main list template
     *
     * @param unknown $currentOrder, if any, null otherwise.
     */
    protected function renderListTemplate($currentOrder)
    {
        return $this->render("hooks", array("display_hook" => 20));
    }

    /**
     * Render the edition template
     */
    protected function renderEditionTemplate()
    {
        return $this->render('hook-edit', $this->getEditionArgument());
    }

    protected function getEditionArgument()
    {
        return array(
            'hook_id'  => $this->getRequest()->get('hook_id', 0)
        );
    }

    /**
     * Redirect to the edition template
     */
    protected function redirectToEditionTemplate()
    {
        $this->redirectToRoute('admin.hook.update', array(), array(
                "hook_id" => $this->getRequest()->get('hook_id', 0)
            )
        );
    }

    /**
     * Redirect to the list template
     */
    protected function redirectToListTemplate()
    {
        $this->redirectToRoute('admin.hook.default');
    }

    public function toggleNativeAction()
    {
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) return $response;
        $content = null;
        if (null !== $hook_id = $this->getRequest()->get('hook_id')) {
            $toggleDefaultEvent = new HookToggleNativeEvent($hook_id);
            try {
                $this->dispatch(TheliaEvents::HOOK_TOGGLE_NATIVE, $toggleDefaultEvent);

                if ($toggleDefaultEvent->hasHook()) {
                    return $this->nullResponse();
                }
            } catch (\Exception $ex) {
                $content = $ex->getMessage();
            }
        }

        return $this->nullResponse(500);
    }

    public function toggleActivationAction()
    {
        if (null !== $response = $this->checkAuth($this->resourceCode, array(), AccessManager::UPDATE)) return $response;
        $content = null;
        if (null !== $hook_id = $this->getRequest()->get('hook_id')) {
            $toggleDefaultEvent = new HookToggleActivationEvent($hook_id);
            try {
                $this->dispatch(TheliaEvents::HOOK_TOGGLE_ACTIVATION, $toggleDefaultEvent);

                if ($toggleDefaultEvent->hasHook()) {
                    return $this->nullResponse();
                }
            } catch (\Exception $ex) {
                $content = $ex->getMessage();
            }
        }

        return $this->nullResponse(500);
    }

}
