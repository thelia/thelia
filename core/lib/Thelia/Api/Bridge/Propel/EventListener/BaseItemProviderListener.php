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

    public function baseProvider(ItemProviderQueryEvent $event): void
    {
        $query = $event->getQuery();

        $reflector = new \ReflectionClass($event->getResourceClass());

        $compositeIdentifiers = $this->apiResourceService->getResourceCompositeIdentifierValues(reflector: $reflector, param: 'keys');

        $columnValues = $this->apiResourceService->getColumnValues(reflector: $reflector, columns: $compositeIdentifiers);

        foreach ($event->getUriVariables() as $field => $value) {
            $filterMethod = null;
            $filterName = $columnValues[$field]['propelQueryFilter'] ?? null;
            if ($filterName && method_exists($query, $filterName)) {
                $filterMethod = $columnValues[$field]['propelQueryFilter'];
                $value = $event->getUriVariables()[$field];
            }

            $filterName = 'filterBy'.ucfirst($field).'Id';
            if (null === $filterMethod && method_exists($query, $filterName)) {
                $filterMethod = $filterName;
            }

            $filterName = 'filterBy'.ucfirst($field);
            if (null === $filterMethod && method_exists($query, $filterName)) {
                $filterMethod = $filterName;
            }

            if ($filterMethod !== null) {
                $query->$filterMethod($value);
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ItemProviderQueryEvent::class => [
                ['baseProvider', 128],
            ],
        ];
    }
}
