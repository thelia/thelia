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

namespace Thelia\Api\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Api\Bridge\Propel\Event\ItemProviderQueryEvent;
use Thelia\Model\StateQuery;

class StateByIsoProviderListener implements EventSubscriberInterface
{
    public function stateByIsoProvider(ItemProviderQueryEvent $event): void
    {
        if (
            $event->getOperation()->getName() !== 'state_by_iso'
            || !isset($event->getUriVariables()['stateIso'])
            || !isset($event->getUriVariables()['countryIso3'])
        ) {
            return;
        }

        $query = $event->getQuery();

        if (!$query instanceof StateQuery) {
            return;
        }

        $query->filterByIsocode($event->getUriVariables()['stateIso'])
            ->useCountryQuery()
                ->filterByIsoalpha3($event->getUriVariables()['countryIso3'])
            ->endUse();

        $event->stopPropagation();
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
