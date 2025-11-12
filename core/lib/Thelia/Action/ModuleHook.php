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

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
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
 * Class ModuleHook.
 *
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleHook extends BaseAction implements EventSubscriberInterface
{
    public function __construct(protected $cacheDir, protected EventDispatcherInterface $dispatcher)
    {
    }

    public function toggleModuleActivation(ModuleToggleActivationEvent $event): ModuleToggleActivationEvent
    {
        if (null !== $module = ModuleQuery::create()->findPk($event->getModuleId())) {
            ModuleHookQuery::create()
                ->filterByModuleId($module->getId())
                ->update(['ModuleActive' => (BaseModule::IS_ACTIVATED === $module->getActivate())]);
        }

        return $event;
    }

    public function deleteModule(ModuleDeleteEvent $event): ModuleDeleteEvent
    {
        if (0 !== $event->getModuleId()) {
            ModuleHookQuery::create()
                ->filterByModuleId($event->getModuleId())
                ->delete();
        }

        return $event;
    }

    protected function isModuleActive($module_id)
    {
        return null !== ($module = ModuleQuery::create()->findPk($module_id))
            ? $module->getActivate()
            : false;
    }

    protected function isHookActive($hook_id)
    {
        return null !== ($hook = HookQuery::create()->findPk($hook_id))
            ? $hook->getActivate()
            : false;
    }

    protected function getLastPositionInHook($hook_id): int
    {
        $result = ModuleHookQuery::create()
            ->filterByHookId($hook_id)
            ->withColumn('MAX(ModuleHook.position)', 'maxPos')
            ->groupBy('ModuleHook.hook_id')
            ->select(['maxPos'])
            ->findOne();

        return (int) $result + 1;
    }

    public function createModuleHook(ModuleHookCreateEvent $event): void
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

    public function updateModuleHook(ModuleHookUpdateEvent $event): void
    {
        if (null === $moduleHook = ModuleHookQuery::create()->findPk($event->getModuleHookId())) {
            return;
        }
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

        $this->cacheClear();

    }

    public function deleteModuleHook(ModuleHookDeleteEvent $event): void
    {
        if (null === $moduleHook = ModuleHookQuery::create()->findPk($event->getModuleHookId())) {
            return;
        }
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

        $this->cacheClear();

    }

    public function toggleModuleHookActivation(ModuleHookToggleActivationEvent $event): ModuleHookToggleActivationEvent
    {
        if (($moduleHook = $event->getModuleHook()) instanceof ModuleHookModel) {
            if ($moduleHook->getModuleActive()) {
                $moduleHook->setActive(!$moduleHook->getActive());
                $moduleHook->save();
            } else {
                throw new \LogicException(Translator::getInstance()->trans('The module has to be activated.'));
            }
        }

        $this->cacheClear();

        return $event;
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @return UpdatePositionEvent $event
     */
    public function updateModuleHookPosition(UpdatePositionEvent $event): UpdatePositionEvent
    {
        $this->genericUpdatePosition(ModuleHookQuery::create(), $event);
        $this->cacheClear();

        return $event;
    }

    public function updateHook(HookUpdateEvent $event): void
    {
        if (!$event->hasHook()) {
            return;
        }
        $hook = $event->getHook();
        ModuleHookQuery::create()
            ->filterByHookId($hook->getId())
            ->update(['HookActive' => $hook->getActivate()]);
        $this->cacheClear();
    }

    public function toggleHookActivation(HookToggleActivationEvent $event): void
    {
        if (!$event->hasHook()) {
            return;
        }
        $hook = $event->getHook();
        if (null === $hook) {
            return;
        }
        ModuleHookQuery::create()
            ->filterByHookId($hook->getId())
            ->update(['HookActive' => $hook->getActivate()]);
        $this->cacheClear();
    }

    protected function cacheClear(): void
    {
        $cacheEvent = new CacheEvent($this->cacheDir);

        $this->dispatcher->dispatch($cacheEvent, TheliaEvents::CACHE_CLEAR);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::MODULE_HOOK_CREATE => ['createModuleHook', 128],
            TheliaEvents::MODULE_HOOK_UPDATE => ['updateModuleHook', 128],
            TheliaEvents::MODULE_HOOK_DELETE => ['deleteModuleHook', 128],
            TheliaEvents::MODULE_HOOK_UPDATE_POSITION => ['updateModuleHookPosition', 128],
            TheliaEvents::MODULE_HOOK_TOGGLE_ACTIVATION => ['toggleModuleHookActivation', 128],

            TheliaEvents::MODULE_TOGGLE_ACTIVATION => ['toggleModuleActivation', 64],
            TheliaEvents::MODULE_DELETE => ['deleteModule', 64],

            TheliaEvents::HOOK_TOGGLE_ACTIVATION => ['toggleHookActivation', 64],
            TheliaEvents::HOOK_UPDATE => ['updateHook', 64],
        ];
    }
}
