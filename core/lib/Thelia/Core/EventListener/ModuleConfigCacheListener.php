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

namespace Thelia\Core\EventListener;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Model\Event\ModuleConfigEvent;
use Thelia\Model\Event\ModuleConfigI18nEvent;
use Thelia\Model\ModuleConfigQuery;

/**
 * Keeps {@see ModuleConfigQuery::getConfigValue()} answering from the database
 * of the current request.
 *
 * The configuration of a module is read many times per page and written from
 * the back office, so what it is read from lives no longer than a request or a
 * console command, and a write on a row drops it at once - including a write
 * made straight on the model, not through setConfigValue().
 */
readonly class ModuleConfigCacheListener
{
    #[AsEventListener(event: ModuleConfigEvent::POST_SAVE)]
    #[AsEventListener(event: ModuleConfigEvent::POST_DELETE)]
    #[AsEventListener(event: ModuleConfigI18nEvent::POST_SAVE)]
    #[AsEventListener(event: ModuleConfigI18nEvent::POST_DELETE)]
    public function onModuleConfigWrite(): void
    {
        ModuleConfigQuery::resetConfigCache();
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 4096)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            ModuleConfigQuery::resetConfigCache();
        }
    }

    #[AsEventListener(event: ConsoleEvents::COMMAND)]
    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        ModuleConfigQuery::resetConfigCache();
    }
}
