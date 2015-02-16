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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\HttpFoundation\JsonResponse;
use Thelia\Core\Event\Hook\HookCreateAllEvent;
use Thelia\Core\Event\Hook\HookCreateEvent;
use Thelia\Core\Event\Hook\HookDeactivationEvent;
use Thelia\Core\Event\Hook\HookDeleteEvent;
use Thelia\Core\Event\Hook\HookToggleActivationEvent;
use Thelia\Core\Event\Hook\HookToggleNativeEvent;
use Thelia\Core\Event\Hook\HookUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Definition\AdminForm;
use Thelia\Log\Tlog;
use Thelia\Model\Hook;
use Thelia\Model\HookQuery;
use Thelia\Model\Lang;

/**
 * Class HookController
 * @package Thelia\Controller\Admin
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'hook',
            'id',
            'order',
            AdminResources::HOOK,
            TheliaEvents::HOOK_CREATE,
            TheliaEvents::HOOK_UPDATE,
            TheliaEvents::HOOK_DELETE
        );
    }

    public function indexAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::HOOK, [], AccessManager::VIEW)) {
            return $response;
        }

        return $this->renderList();
    }

    public function discoverAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::HOOK, [], AccessManager::VIEW)) {
            return $response;
        }

        $templateType = intval($this->getRequest()->get("template_type", TemplateDefinition::FRONT_OFFICE));

        $json_data = [];
        try {
            // parse the current template
            $hookHelper = $this->container->get("thelia.hookHelper");
            $hooks      = $hookHelper->parseActiveTemplate($templateType);

            // official hook
            $allHooks = $this->getAllHooks($templateType);

            // diff
            $newHooks      = [];
            $existingHooks = [];
            foreach ($hooks as $hook) {
                if (array_key_exists($hook["code"], $allHooks)) {
                    $existingHooks[] = $hook["code"];
                } else {
                    $newHooks[] = $hook;
                }
            }

            foreach ($existingHooks as $code) {
                unset($allHooks[$code]);
            }

            $json_data = [
                "success" => true,
                "new"     => $newHooks,
                "missing" => $allHooks
            ];

            $response = JsonResponse::create($json_data);
        } catch (\Exception $e) {
            $response = JsonResponse::create(["error" => $e->getMessage()], 500);
        }

        return $response;
    }

    public function discoverSaveAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::HOOK, [], AccessManager::UPDATE)) {
            return $response;
        }

        $errors = [];

        $templateType = $this->getRequest()->request->get("templateType");

        // new hooks in the template
        if (null !== $newHooks = $this->getRequest()->request->get("new", null)) {
            foreach ($newHooks as $hook) {
                $event = $this->getDiscoverCreationEvent($hook, $templateType);

                $this->dispatch(TheliaEvents::HOOK_CREATE_ALL, $event);

                if (!$event->hasHook()) {
                    $errors[] = sprintf(
                        Translator::getInstance()->trans("Failed to create new hook %s"),
                        $hook["code"]
                    );
                }
            }
        }

        // missing official hooks
        if (null !== $missingHooks = $this->getRequest()->request->get("missing")) {
            foreach ($missingHooks as $hookId) {
                $event = new HookDeactivationEvent($hookId);

                $this->dispatch(TheliaEvents::HOOK_DEACTIVATION, $event);

                if (!$event->hasHook()) {
                    $errors[] = sprintf(
                        Translator::getInstance()->trans("Failed to deactivate hook with id %s"),
                        $hookId
                    );
                }
            }
        }

        $json_data = [
            "success" => true
        ];

        if (count($errors)) {
            $response = JsonResponse::create(["error" => $errors], 500);
        } else {
            $response = JsonResponse::create($json_data);
        }

        return $response;
    }

    protected function getDiscoverCreationEvent($data, $type)
    {
        $event = new HookCreateAllEvent();

        $event
            ->setLocale(Lang::getDefaultLanguage()->getLocale())
            ->setType($type)
            ->setCode($data['code'])
            ->setNative(false)
            ->setActive(true)
            ->setTitle(($data['title'] != "") ? $data['title'] : $data['code'])
            ->setByModule($data['module'])
            ->setBlock($data['block'])
            ->setChapo("")
            ->setDescription("");

        return $event;
    }

    protected function getDeactivationEvent($code, $type)
    {
        $event = null;

        $hook_id = HookQuery::create()
            ->filterByActivate(true, Criteria::EQUAL)
            ->filterByType($type, Criteria::EQUAL)
            ->filterByCode($code, Criteria::EQUAL)
            ->select("Id")
            ->findOne();

        if (null !== $hook_id) {
            $event = new HookDeactivationEvent($hook_id);
        }

        return $event;
    }

    protected function getAllHooks($templateType)
    {
        // get the all hooks
        $hooks = HookQuery::create()
            ->filterByType($templateType, Criteria::EQUAL)
            ->find();

        $ret = [];
        /** @var Hook $hook */
        foreach ($hooks as $hook) {
            $ret[$hook->getCode()] = [
                "id"       => $hook->getId(),
                "code"     => $hook->getCode(),
                "native"   => $hook->getNative(),
                "activate" => $hook->getActivate(),
                "title"    => $hook->getTitle()
            ];
        }

        return $ret;
    }

    /**
     * Return the creation form for this object
     */
    protected function getCreationForm()
    {
        return $this->createForm(AdminForm::HOOK_CREATION);
    }

    /**
     * Return the update form for this object
     */
    protected function getUpdateForm()
    {
        return $this->createForm(AdminForm::HOOK_MODIFICATION);
    }

    /**
     * Hydrate the update form for this object, before passing it to the update template
     *
     * @param \Thelia\Model\Hook $object
     *
     * @return \Thelia\Form\HookModificationForm
     */
    protected function hydrateObjectForm($object)
    {
        $data = [
            'id'          => $object->getId(),
            'code'        => $object->getCode(),
            'type'        => $object->getType(),
            'native'      => $object->getNative(),
            'by_module'   => $object->getByModule(),
            'block'       => $object->getBlock(),
            'active'      => $object->getActivate(),
            'locale'      => $object->getLocale(),
            'title'       => $object->getTitle(),
            'chapo'       => $object->getChapo(),
            'description' => $object->getDescription(),
        ];

        return $this->createForm(AdminForm::HOOK_MODIFICATION, 'form', $data);
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

        return $this->hydrateEvent($event, $formData, true);
    }

    protected function hydrateEvent($event, $formData, $update = false)
    {
        $event
            ->setLocale($formData['locale'])
            ->setType($formData['type'])
            ->setCode($formData['code'])
            ->setNative($formData['native'])
            ->setActive($formData['active'])
            ->setTitle($formData['title']);
        if ($update) {
            $event
                ->setByModule($formData['by_module'])
                ->setBlock($formData['block'])
                ->setChapo($formData['chapo'])
                ->setDescription($formData['description']);
        }

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
     * @param unknown $event
     *
     * @return
     * @internal param \Thelia\Controller\Admin\unknown $createEvent
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
     *
     * @return string
     */
    protected function getObjectLabel($object)
    {
        return $object->getTitle();
    }

    /**
     * Returns the object ID from the object
     *
     * @param \Thelia\Model\Hook $object
     *
     * @return int
     */
    protected function getObjectId($object)
    {
        return $object->getId();
    }

    /**
     * Render the main list template
     *
     * @param unknown $currentOrder , if any, null otherwise.
     */
    protected function renderListTemplate($currentOrder)
    {
        return $this->render("hooks", ['order' => $currentOrder]);
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
        return [
            'hook_id' => $this->getRequest()->get('hook_id', 0)
        ];
    }

    /**
     * Redirect to the edition template
     */
    protected function redirectToEditionTemplate()
    {
        return $this->generateRedirectFromRoute(
            'admin.hook.update',
            [],
            [
                "hook_id" => $this->getRequest()->get('hook_id', 0),
            ]
        );
    }

    /**
     * Redirect to the list template
     */
    protected function redirectToListTemplate()
    {
        return $this->generateRedirectFromRoute('admin.hook');
    }

    public function toggleNativeAction()
    {
        if (null !== $response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) {
            return $response;
        }

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
                Tlog::getInstance()->debug(sprintf("%s", $content));
            }
        }

        return $this->nullResponse(500);
    }

    public function toggleActivationAction()
    {
        if (null !== $response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) {
            return $response;
        }

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
                Tlog::getInstance()->debug(sprintf("%s", $content));
            }
        }

        return $this->nullResponse(500);
    }
}
