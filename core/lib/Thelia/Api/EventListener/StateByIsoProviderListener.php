<?php

namespace Thelia\Api\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Api\Bridge\Propel\Event\ItemProviderEvent;
use Thelia\Model\StateQuery;

class StateByIsoProviderListener implements EventSubscriberInterface
{
    public function stateByIsoProvider(ItemProviderEvent $event)
    {
        if (
            $event->getOperation()->getName() !== "state_by_iso"
            ||
            !isset($event->getUriVariables()['stateIso'])
            ||
            !isset($event->getUriVariables()['countryIso3'])
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

    public static function getSubscribedEvents()
    {
        return [
            ItemProviderEvent::class => [
                ['stateByIsoProvider', 1234]
            ],
        ];
    }
}
