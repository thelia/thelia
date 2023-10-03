<?php

namespace Thelia\Api\Bridge\Propel\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Api\Bridge\Propel\Event\ItemProviderEvent;

class BaseItemProviderListener implements EventSubscriberInterface
{
    public function baseProvider(ItemProviderEvent $event)
    {
        $query = $event->getQuery();

        foreach ($event->getUriVariables() as $field => $value) {
            $filterName = "filterBy".ucfirst($field);
            if (method_exists($query, $filterName)) {
                $query->$filterName($value);
            }
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            ItemProviderEvent::class => [
                ['baseProvider', 128]
            ],
        ];
    }
}
