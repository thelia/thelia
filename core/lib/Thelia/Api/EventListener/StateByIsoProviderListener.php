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

namespace Thelia\Api\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Api\Bridge\Propel\Event\ItemProviderQueryEvent;

class StateByIsoProviderListener implements EventSubscriberInterface
{
    public function stateByIsoProvider(ItemProviderQueryEvent $event): void
    {
        if (
            'state_by_iso' !== $event->getOperation()->getName()
            || !isset($event->getUriVariables()['stateIso'])
            || !isset($event->getUriVariables()['countryIso3'])
        ) {
            return;
        }

        $event->getQuery();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ItemProviderQueryEvent::class => [
                ['stateByIsoProvider', 1234],
            ],
        ];
    }
}
