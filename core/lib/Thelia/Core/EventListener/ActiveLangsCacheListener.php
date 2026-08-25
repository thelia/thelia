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
use Thelia\Model\Event\LangEvent;
use Thelia\Model\Lang;

/**
 * Keeps {@see Lang::getActiveLangs()} honest: the memoized list never outlives a request or
 * a console command, and a write on a language drops it at once.
 */
readonly class ActiveLangsCacheListener
{
    #[AsEventListener(event: LangEvent::POST_SAVE)]
    #[AsEventListener(event: LangEvent::POST_DELETE)]
    public function onLangWrite(): void
    {
        Lang::resetActiveLangsCache();
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 4096)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            Lang::resetActiveLangsCache();
        }
    }

    #[AsEventListener(event: ConsoleEvents::COMMAND)]
    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        Lang::resetActiveLangsCache();
    }
}
