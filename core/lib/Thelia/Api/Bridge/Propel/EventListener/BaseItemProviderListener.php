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

        $alreadyFilteredProperty = [];
        $reflector = new \ReflectionClass($event->getResourceClass());

        $compositeIdentifiers = $this->apiResourceService->getResourceCompositeIdentifierValues(reflector: $reflector, param: 'keys');

        $columnValues = $this->apiResourceService->getColumnValues(reflector: $reflector,columns: $compositeIdentifiers);

        foreach ($columnValues as $propertyName => $columnValue){
            if (isset($columnValue["propelQueryFilter"]) && method_exists($query, $columnValue["propelQueryFilter"])){
                $propelQueryFilter = $columnValue["propelQueryFilter"];
                $value = $event->getUriVariables()[$propertyName];
                $query->$propelQueryFilter($value);
                $alreadyFilteredProperty []= $propertyName;
            }
        }

        foreach ($event->getUriVariables() as $field => $value) {
            $filterName = "filterBy".ucfirst($field)."Id";
            if (method_exists($query, $filterName) && !in_array($field,$alreadyFilteredProperty)) {
                $query->$filterName($value);
                $alreadyFilteredProperty []= $field;
            }
        }

        foreach ($event->getUriVariables() as $field => $value) {
            $filterName = "filterBy".ucfirst($field);
            if (method_exists($query, $filterName) && !in_array($field,$alreadyFilteredProperty)) {
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
