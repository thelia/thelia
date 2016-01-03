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

use Symfony\Component\HttpFoundation\JsonResponse;
use Thelia\Core\Event\Hook\ModuleHookCreateEvent;
use Thelia\Core\Event\Hook\ModuleHookDeleteEvent;
use Thelia\Core\Event\Hook\ModuleHookEvent;
use Thelia\Core\Event\Hook\ModuleHookToggleActivationEvent;
use Thelia\Core\Event\Hook\ModuleHookUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Definition\AdminForm;
use Thelia\Form\ModuleHookModificationForm;
use Thelia\Model\IgnoredModuleHook;
use Thelia\Model\IgnoredModuleHookQuery;
use Thelia\Model\ModuleHook;
use Thelia\Model\ModuleHookQuery;

/**
 * Class HookController
 * @package Thelia\Controller\Admin
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleHookController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'hook',
            'manual',
            'hook_order',
            AdminResources::MODULE_HOOK,
            TheliaEvents::MODULE_HOOK_CREATE,
            TheliaEvents::MODULE_HOOK_UPDATE,
            TheliaEvents::MODULE_HOOK_DELETE,
            null,
            TheliaEvents::MODULE_HOOK_UPDATE_POSITION
        );
    }

    public function indexAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE_HOOK, [], AccessManager::VIEW)) {
            return $response;
        }

        return $this->renderList();
    }

    public function toggleActivationAction($module_hook_id)
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE_HOOK, [], AccessManager::UPDATE)) {
            return $response;
        }

        $message = null;

        $event = new ModuleHookToggleActivationEvent($this->getExistingObject());

        try {
            $this->dispatch(TheliaEvents::MODULE_HOOK_TOGGLE_ACTIVATION, $event);
        } catch (\Exception $ex) {
            $message = $ex->getMessage();
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            if ($message) {
                $response = $this->jsonResponse(json_encode([ "error" => $message]), 500);
            } else {
                $response = $this->nullResponse();
            }
        } else {
            $response = $this->generateRedirectFromRoute('admin.module-hook');
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
        return $this->createForm(AdminForm::MODULE_HOOK_CREATION);
    }

    /**
     * Return the update form for this object
     */
    protected function getUpdateForm()
    {
        return $this->createForm(AdminForm::MODULE_HOOK_MODIFICATION);
    }

    /**
     * Hydrate the update form for this object, before passing it to the update template
     *
     * @param ModuleHook $object
     * @return ModuleHookModificationForm
     */
    protected function hydrateObjectForm($object)
    {
        $data = [
            'id'        => $object->getId(),
            'hook_id'   => $object->getHookId(),
            'module_id' => $object->getModuleId(),
            'classname' => $object->getClassname(),
            'method'    => $object->getMethod(),
            'active'    => $object->getActive(),
            'templates' => $object->getTemplates(),
        ];

        return $this->createForm(AdminForm::MODULE_HOOK_MODIFICATION, 'form', $data);
    }

    /**
     * Creates the creation event with the provided form data
     *
     * @param array $formData
     * @return ModuleHookCreateEvent
     */
    protected function getCreationEvent($formData)
    {
        $event = new ModuleHookCreateEvent();

        return $this->hydrateEvent($event, $formData);
    }

    /**
     * Creates the update event with the provided form data
     *
     * @param array $formData
     * @return ModuleHookUpdateEvent
     */
    protected function getUpdateEvent($formData)
    {
        $event = new ModuleHookUpdateEvent();

        return $this->hydrateEvent($event, $formData, true);
    }

    protected function hydrateEvent($event, $formData, $update = false)
    {
        if (!$update) {
            $event
                ->setModuleId($formData['module_id'])
                ->setHookId($formData['hook_id']);
        } else {
            $event
                ->setModuleHookId($formData['id'])
                ->setModuleId($formData['module_id'])
                ->setHookId($formData['hook_id'])
                ->setClassname($formData['classname'])
                ->setMethod($formData['method'])
                ->setActive($formData['active'])
                ->setTemplates($formData['templates'])
            ;
        }

        return $event;
    }

    /**
     * Creates the delete event with the provided form data
     */
    protected function getDeleteEvent()
    {
        return new ModuleHookDeleteEvent($this->getRequest()->get('module_hook_id'));
    }

    /**
     * Return true if the event contains the object, e.g. the action has updated the object in the event.
     *
     * @param ModuleHookEvent $event
     * @return bool
     */
    protected function eventContainsObject($event)
    {
        return $event->hasModuleHook();
    }

    /**
     * Get the created object from an event.
     *
     * @param ModuleHookEvent $event
     * @return null|ModuleHook
     */
    protected function getObjectFromEvent($event)
    {
        return $event->getModuleHook();
    }

    /**
     * Load an existing object from the database
     * @return null|ModuleHook
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
     * @param ModuleHook $object
     * @return string
     */
    protected function getObjectLabel($object)
    {
        try {
            return sprintf(
                "%s on %s",
                $object->getModule()->getTitle(),
                $object->getHook()->getTitle()
            );
        } catch (\Exception $ex) {
            return "Undefined module hook";
        }
    }

    /**
     * Returns the object ID from the object
     *
     * @param ModuleHook $object
     * @return int
     */
    protected function getObjectId($object)
    {
        return $object->getId();
    }

    /**
     * Render the main list template
     *
     * @param string $currentOrder , if any, null otherwise.
     * @return Response
     */
    protected function renderListTemplate($currentOrder)
    {
        return $this->render(
            'module-hooks',
            ['module_order' => $currentOrder]
        );
    }

    /**
     * Render the edition template
     * @return Response
     */
    protected function renderEditionTemplate()
    {
        return $this->render('module-hook-edit', $this->getEditionArgument());
    }

    /**
     * @return array
     */
    protected function getEditionArgument()
    {
        return [
            'module_hook_id' => $this->getRequest()->get('module_hook_id', 0)
        ];
    }

    /**
     * Redirect to the edition template
     * @return Response
     */
    protected function redirectToEditionTemplate($request = null, $country = null)
    {
        return $this->generateRedirectFromRoute(
            "admin.module-hook.update",
            $this->getViewArguments(),
            $this->getRouteArguments()
        );
    }

    /**
     * Redirect to the list template
     */
    protected function redirectToListTemplate()
    {
        return $this->generateRedirectFromRoute("admin.module-hook");
    }

    protected function getViewArguments()
    {
        return [];
    }

    protected function getRouteArguments($module_hook_id = null)
    {
        return [
            'module_hook_id' => $module_hook_id === null ? $this->getRequest()->get('module_hook_id') : $module_hook_id
        ];
    }

    public function getModuleHookClassnames($moduleId)
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE_HOOK, [], AccessManager::VIEW)) {
            return $response;
        }

        $result = [];

        $moduleHooks = ModuleHookQuery::create()
            ->filterByModuleId($moduleId)
            ->groupByClassname()
            ->find();

        /** @var ModuleHook $moduleHook */
        foreach ($moduleHooks as $moduleHook) {
            $result[] = $moduleHook->getClassname();
        }

        $ignoredModuleHooks = IgnoredModuleHookQuery::create()
            ->filterByModuleId($moduleId)
            ->groupByClassname()
            ->find();

        /** @var IgnoredModuleHook $moduleHook */
        foreach ($ignoredModuleHooks as $moduleHook) {
            $className = $moduleHook->getClassname();
            if (null !== $className && ! in_array($className, $result)) {
                $result[] = $className;
            }
        }

        sort($result);

        return new JsonResponse($result);
    }

    public function getModuleHookMethods($moduleId, $className)
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE_HOOK, [], AccessManager::VIEW)) {
            return $response;
        }

        $result = [BaseHook::INJECT_TEMPLATE_METHOD_NAME];

        $moduleHooks = ModuleHookQuery::create()
            ->filterByModuleId($moduleId)
            ->filterByClassname($className)
            ->find();

        /** @var ModuleHook $moduleHook */
        foreach ($moduleHooks as $moduleHook) {
            $method = $moduleHook->getMethod();
            if (! in_array($method, $result)) {
                $result[] = $method;
            }
        }

        $ignoredModuleHooks = IgnoredModuleHookQuery::create()
            ->filterByModuleId($moduleId)
            ->filterByClassname($className)
            ->find();

        /** @var IgnoredModuleHook $moduleHook */
        foreach ($ignoredModuleHooks as $moduleHook) {
            $method = $moduleHook->getMethod();
            if (! in_array($method, $result)) {
                $result[] = $method;
            }
        }

        sort($result);

        return new JsonResponse($result);
    }
}
