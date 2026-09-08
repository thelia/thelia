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
use Thelia\Model\Country;
use Thelia\Model\Event\CountryEvent;

/**
 * Keeps {@see Country::getDefaultCountry()}'s static memo honest.
 *
 * The lookup it wraps is read constantly (once per product card that falls
 * back to the shop's default delivery country) and almost never written, so
 * a plain static holding the resolved model is the right cache. A write to
 * the row that carries `by_default` drops it at once, and that is the only
 * thing that needs to: a persistent worker runtime (FrankenPHP, RoadRunner)
 * serves every other request from the same memo, at no cost. Only a chain of
 * separate console command processes needs the reset below, since nothing
 * here can assume they share a runtime.
 */
readonly class DefaultCountryCacheListener
{
    #[AsEventListener(event: CountryEvent::POST_SAVE)]
    #[AsEventListener(event: CountryEvent::POST_DELETE)]
    public function onCountryWrite(): void
    {
        Country::resetDefaultCountryCache();
    }

    #[AsEventListener(event: ConsoleEvents::COMMAND)]
    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        Country::resetDefaultCountryCache();
    }
}
