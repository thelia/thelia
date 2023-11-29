<?php

namespace Thelia\Api\Bridge\Propel\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Api\Bridge\Propel\Event\ItemProviderQueryEvent;
use Thelia\Api\Bridge\Propel\Service\ApiResourceService;

class BaseItemProviderListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly ApiResourceService $apiResourceService,
    ) {
    }
    public function baseProvider(ItemProviderQueryEvent $event)
    {
        $query = $event->getQuery();

        $reflector = new \ReflectionClass($event->getResourceClass());

        $compositeIdentifiers = $this->apiResourceService->getResourceCompositeIdentifierValues(reflector: $reflector, param: 'keys');

        $columnValues = $this->apiResourceService->getColumnValues(reflector: $reflector,columns: $compositeIdentifiers);

        foreach ($event->getUriVariables() as $field => $value) {
            $filterMethod = null;
            $filterName = $columnValues[$field]["propelQueryFilter"] ?? null;
            if ($filterName && method_exists($query, $filterName)){
                $filterMethod = $columnValues[$field]["propelQueryFilter"];
                $value = $event->getUriVariables()[$field];
            }

            $filterName = "filterBy".ucfirst($field)."Id";
            if (null === $filterMethod && method_exists($query, $filterName)) {
                $filterMethod = $filterName;
            }

            $filterName = "filterBy".ucfirst($field);
            if (null === $filterMethod && method_exists($query, $filterName)) {
                $filterMethod = $filterName;
            }

            if ($filterMethod !== null){
                $query->$filterMethod($value);
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ItemProviderQueryEvent::class => [
                ['baseProvider', 128]
            ],
        ];
    }
}
