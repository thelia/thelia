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
use Thelia\Model\Event\LangEvent;
use Thelia\Model\Lang;

/**
 * Keeps {@see Lang::getActiveLangs()} answering from the database of the
 * current process, not the current request.
 *
 * A write on a language is what actually changes the answer, and that already
 * drops the memo; a persistent worker runtime (FrankenPHP, RoadRunner) serves
 * every other request from the same one, at no cost. Only a chain of separate
 * console command processes needs the reset below, since nothing here can
 * assume they share a runtime.
 */
readonly class ActiveLangsCacheListener
{
    #[AsEventListener(event: LangEvent::POST_SAVE)]
    #[AsEventListener(event: LangEvent::POST_DELETE)]
    public function onLangWrite(): void
    {
        Lang::resetActiveLangsCache();
    }

    #[AsEventListener(event: ConsoleEvents::COMMAND)]
    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        Lang::resetActiveLangsCache();
    }
}
