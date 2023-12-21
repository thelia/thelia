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

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\Hook\HookCreateAllEvent;
use Thelia\Core\Event\Hook\HookCreateEvent;
use Thelia\Core\Event\Hook\HookDeactivationEvent;
use Thelia\Core\Event\Hook\HookDeleteEvent;
use Thelia\Core\Event\Hook\HookToggleActivationEvent;
use Thelia\Core\Event\Hook\HookToggleNativeEvent;
use Thelia\Core\Event\Hook\HookUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Hook as HookModel;
use Thelia\Model\HookQuery;

/**
 * Class HookAction.
 *
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class Hook extends BaseAction implements EventSubscriberInterface
{
    /** @var string */
    protected $cacheDir;

    /** @var EventDispatcherInterface */
    protected $dispatcher;

    public function __construct($kernelCacheDir, EventDispatcherInterface $dispatcher)
    {
        $this->cacheDir = $kernelCacheDir;
        $this->dispatcher = $dispatcher;
    }

    public function create(HookCreateEvent $event): void
    {
        $hook = new HookModel();

        $hook
            ->setLocale($event->getLocale())
            ->setCode($event->getCode())
            ->setType($event->getType())
            ->setNative($event->getNative())
            ->setActivate($event->getActive())
            ->setTitle($event->getTitle())
            ->save();

        $event->setHook($hook);

        $this->cacheClear();
    }

    public function update(HookUpdateEvent $event): void
    {
        if (null !== $hook = HookQuery::create()->findPk($event->getHookId())) {
            $hook
                ->setLocale($event->getLocale())
                ->setCode($event->getCode())
                ->setType($event->getType())
                ->setNative($event->getNative())
                ->setActivate($event->getActive())
                ->setBlock($event->getBlock())
                ->setByModule($event->getByModule())
                ->setTitle($event->getTitle())
                ->setChapo($event->getChapo())
                ->setDescription($event->getDescription())
                ->save();

            $event->setHook($hook);
            $this->cacheClear();
        }
    }

    public function delete(HookDeleteEvent $event): void
    {
        if (null !== $hook = HookQuery::create()->findPk($event->getHookId())) {
            $hook->delete();
            $event->setHook($hook);

            $this->cacheClear();
        }
    }

    public function createAll(HookCreateAllEvent $event): void
    {
        $hook = new HookModel();

        $hook
            ->setLocale($event->getLocale())
            ->setCode($event->getCode())
            ->setType($event->getType())
            ->setNative($event->getNative())
            ->setActivate($event->getActive())
            ->setBlock($event->getBlock())
            ->setByModule($event->getByModule())
            ->setTitle($event->getTitle())
            ->setChapo($event->getChapo())
            ->setDescription($event->getDescription())
            ->save();

        $event->setHook($hook);
    }

    public function deactivation(HookDeactivationEvent $event): void
    {
        if (null !== $hook = HookQuery::create()->findPk($event->getHookId())) {
            $hook
                ->setActivate(false)
                ->save();
            $event->setHook($hook);
        }
    }

    public function toggleNative(HookToggleNativeEvent $event): void
    {
        if (null !== $hook = HookQuery::create()->findPk($event->getHookId())) {
            $hook
                ->setNative(!$hook->getNative())
                ->save();
            $event->setHook($hook);
        }
    }

    public function toggleActivation(HookToggleActivationEvent $event): void
    {
        if (null !== $hook = HookQuery::create()->findPk($event->getHookId())) {
            $hook
                ->setActivate(!$hook->getActivate())
                ->save();
            $event->setHook($hook);

            $this->cacheClear();
        }
    }

    protected function cacheClear(): void
    {
        $cacheEvent = new CacheEvent($this->cacheDir);

        $this->dispatcher->dispatch($cacheEvent, TheliaEvents::CACHE_CLEAR);
    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::HOOK_CREATE => ['create', 128],
            TheliaEvents::HOOK_UPDATE => ['update', 128],
            TheliaEvents::HOOK_DELETE => ['delete', 128],
            TheliaEvents::HOOK_TOGGLE_ACTIVATION => ['toggleActivation', 128],
            TheliaEvents::HOOK_TOGGLE_NATIVE => ['toggleNative', 128],
            TheliaEvents::HOOK_CREATE_ALL => ['createAll', 128],
            TheliaEvents::HOOK_DEACTIVATION => ['deactivation', 128],
        ];
    }
}
