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

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\Hook\HookToggleActivationEvent;
use Thelia\Core\Event\Hook\HookUpdateEvent;
use Thelia\Core\Event\Hook\ModuleHookCreateEvent;
use Thelia\Core\Event\Hook\ModuleHookDeleteEvent;
use Thelia\Core\Event\Hook\ModuleHookToggleActivationEvent;
use Thelia\Core\Event\Hook\ModuleHookUpdateEvent;
use Thelia\Core\Event\Module\ModuleDeleteEvent;
use Thelia\Core\Event\Module\ModuleToggleActivationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Base\IgnoredModuleHookQuery;
use Thelia\Model\HookQuery;
use Thelia\Model\IgnoredModuleHook;
use Thelia\Model\ModuleHook as ModuleHookModel;
use Thelia\Model\ModuleHookQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Class ModuleHook
 * @package Thelia\Action
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleHook extends BaseAction implements EventSubscriberInterface
{
    /** @var string */
    protected $cacheDir;

    public function __construct($cacheDir)
    {
        $this->cacheDir = $cacheDir;
    }

    public function toggleModuleActivation(ModuleToggleActivationEvent $event)
    {
        if (null !== $module = ModuleQuery::create()->findPk($event->getModuleId())) {
            ModuleHookQuery::create()
                ->filterByModuleId($module->getId())
                ->update(array('ModuleActive' => ($module->getActivate() == BaseModule::IS_ACTIVATED)));
        }

        return $event;
    }

    public function deleteModule(ModuleDeleteEvent $event)
    {
        if ($event->getModuleId()) {
            ModuleHookQuery::create()
                ->filterByModuleId($event->getModuleId())
                ->delete();
        }

        return $event;
    }

    protected function isModuleActive($module_id)
    {
        if (null !== $module = ModuleQuery::create()->findPk($module_id)) {
            return $module->getActivate();
        }

        return false;
    }

    protected function isHookActive($hook_id)
    {
        if (null !== $hook = HookQuery::create()->findPk($hook_id)) {
            return $hook->getActivate();
        }

        return false;
    }

    protected function getLastPositionInHook($hook_id)
    {
        $result = ModuleHookQuery::create()
            ->filterByHookId($hook_id)
            ->withColumn('MAX(ModuleHook.position)', 'maxPos')
            ->groupBy('ModuleHook.hook_id')
            ->select(array('maxPos'))
            ->findOne();

        return intval($result) + 1;
    }

    public function createModuleHook(ModuleHookCreateEvent $event)
    {
        $moduleHook = new ModuleHookModel();

        // todo: test if classname and method exists
        $moduleHook
            ->setModuleId($event->getModuleId())
            ->setHookId($event->getHookId())
            ->setActive(false)
            ->setClassname($event->getClassname())
            ->setMethod($event->getMethod())
            ->setModuleActive($this->isModuleActive($event->getModuleId()))
            ->setHookActive($this->isHookActive($event->getHookId()))
            ->setPosition($this->getLastPositionInHook($event->getHookId()))
            ->setTemplates($event->getTemplates())
            ->save();

        // Be sure to delete this module hook from the ignored module hook table
        IgnoredModuleHookQuery::create()
            ->filterByHookId($event->getHookId())
            ->filterByModuleId($event->getModuleId())
            ->delete();

        $event->setModuleHook($moduleHook);
    }

    public function updateModuleHook(ModuleHookUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $moduleHook = ModuleHookQuery::create()->findPk($event->getModuleHookId())) {
            // todo: test if classname and method exists
            $moduleHook
                ->setHookId($event->getHookId())
                ->setModuleId($event->getModuleId())
                ->setClassname($event->getClassname())
                ->setMethod($event->getMethod())
                ->setActive($event->getActive())
                ->setHookActive($this->isHookActive($event->getHookId()))
                ->setTemplates($event->getTemplates())
                ->save();

            $event->setModuleHook($moduleHook);

            $this->cacheClear($dispatcher);
        }
    }

    public function deleteModuleHook(ModuleHookDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $moduleHook = ModuleHookQuery::create()->findPk($event->getModuleHookId())) {
            $moduleHook->delete();
            $event->setModuleHook($moduleHook);

            // Prevent hook recreation by RegisterListenersPass::registerHook()
            // We store the method here to be able to retreive it when
            // we need to get all hook declared by a module
            $imh = new IgnoredModuleHook();
            $imh
                ->setModuleId($moduleHook->getModuleId())
                ->setHookId($moduleHook->getHookId())
                ->setMethod($moduleHook->getMethod())
                ->setClassname($moduleHook->getClassname())
                ->save();

            $this->cacheClear($dispatcher);
        }
    }

    public function toggleModuleHookActivation(ModuleHookToggleActivationEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $moduleHook = $event->getModuleHook()) {
            if ($moduleHook->getModuleActive()) {
                $moduleHook->setActive(!$moduleHook->getActive());
                $moduleHook->save();
            } else {
                throw new \LogicException(Translator::getInstance()->trans("The module has to be activated."));
            }
        }
        $this->cacheClear($dispatcher);

        return $event;
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param UpdatePositionEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     *
     * @return UpdatePositionEvent $event
     */
    public function updateModuleHookPosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $this->genericUpdatePosition(ModuleHookQuery::create(), $event, $dispatcher);
        $this->cacheClear($dispatcher);

        return $event;
    }

    public function updateHook(HookUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if ($event->hasHook()) {
            $hook = $event->getHook();
            ModuleHookQuery::create()
                ->filterByHookId($hook->getId())
                ->update(array('HookActive' => $hook->getActivate()));
            $this->cacheClear($dispatcher);
        }
    }

    public function toggleHookActivation(HookToggleActivationEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if ($event->hasHook()) {
            $hook = $event->getHook();
            ModuleHookQuery::create()
                ->filterByHookId($hook->getId())
                ->update(array('HookActive' => $hook->getActivate()));
            $this->cacheClear($dispatcher);
        }
    }

    protected function cacheClear(EventDispatcherInterface $dispatcher)
    {
        $cacheEvent = new CacheEvent($this->cacheDir);

        $dispatcher->dispatch(TheliaEvents::CACHE_CLEAR, $cacheEvent);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::MODULE_HOOK_CREATE            => array('createModuleHook', 128),
            TheliaEvents::MODULE_HOOK_UPDATE            => array('updateModuleHook', 128),
            TheliaEvents::MODULE_HOOK_DELETE            => array('deleteModuleHook', 128),
            TheliaEvents::MODULE_HOOK_UPDATE_POSITION   => array('updateModuleHookPosition', 128),
            TheliaEvents::MODULE_HOOK_TOGGLE_ACTIVATION => array('toggleModuleHookActivation', 128),

            TheliaEvents::MODULE_TOGGLE_ACTIVATION      => array('toggleModuleActivation', 64),
            TheliaEvents::MODULE_DELETE                 => array('deleteModule', 64),

            TheliaEvents::HOOK_TOGGLE_ACTIVATION        => array('toggleHookActivation', 64),
            TheliaEvents::HOOK_UPDATE                   => array('updateHook', 64),

        );
    }
}
