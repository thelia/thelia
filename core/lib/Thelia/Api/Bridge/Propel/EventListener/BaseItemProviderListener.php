<?php

namespace Thelia\Api\Bridge\Propel\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Api\Bridge\Propel\Event\ItemProviderEvent;
use Thelia\Api\Bridge\Propel\Service\ApiResourceService;

class BaseItemProviderListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly ApiResourceService $apiResourceService,
    ) {
    }
    public function baseProvider(ItemProviderEvent $event)
    {
        $query = $event->getQuery();

        $reflector = new \ReflectionClass($event->getResourceClass());

        $compositeIdentifiers = $this->apiResourceService->getResourceCompositeIdentifierValues(reflector: $reflector, param: 'keys');

        $columnValues = $this->apiResourceService->getColumnValues(reflector: $reflector,columns: $compositeIdentifiers);

        foreach ($event->getUriVariables() as $field => $value) {
            if (isset($columnValues[$field]["propelQueryFilter"]) && method_exists($query, $columnValues[$field]["propelQueryFilter"])){
                    $propelQueryFilter = $columnValues[$field]["propelQueryFilter"];
                    $value = $event->getUriVariables()[$field];
                    $query->$propelQueryFilter($value);
                    continue;
            }
            $filterName = "filterBy".ucfirst($field)."Id";
            if (method_exists($query, $filterName)) {
                $query->$filterName($value);
                continue;
            }
            $filterName = "filterBy".ucfirst($field);
            if (method_exists($query, $filterName)) {
                $query->$filterName($value);
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ItemProviderEvent::class => [
                ['baseProvider', 128]
            ],
        ];
    }
}
