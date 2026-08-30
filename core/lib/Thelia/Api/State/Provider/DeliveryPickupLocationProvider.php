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

namespace Thelia\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Delivery\PickupLocationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\CountryQuery;
use Thelia\Model\StateQuery;

readonly class DeliveryPickupLocationProvider implements ProviderInterface
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (!isset($uriVariables['city'], $uriVariables['zipcode'])) {
            throw new \RuntimeException('City and zipcode are required');
        }

        $stateId = $this->requestParam('stateId');
        $state = $stateId
            ? (StateQuery::create())->filterById($stateId)->findOne()
            : null;
        $countryId = $this->requestParam('countryId');
        $country = $countryId
            ? (CountryQuery::create())->filterById($countryId)->findOne()
            : null;
        $radius = $this->requestParam('radius');
        $maxRelays = $this->requestParam('maxRelays');
        $orderWeight = $this->requestParam('orderWeight');

        $pickupLocationEvent = new PickupLocationEvent(
            null,
            null !== $radius ? (int) $radius : null,
            null !== $maxRelays ? (int) $maxRelays : null,
            $this->requestParam('address'),
            $uriVariables['city'],
            $uriVariables['zipcode'],
            null !== $orderWeight ? (int) $orderWeight : null,
            $state,
            $country,
            $this->requestParam('moduleIds'),
        );

        $this->dispatcher->dispatch($pickupLocationEvent, TheliaEvents::MODULE_DELIVERY_GET_PICKUP_LOCATIONS);

        return $pickupLocationEvent->getLocations();
    }

    private function requestParam(string $key): mixed
    {
        $request = $this->requestStack->getCurrentRequest() ?? $this->requestStack->getMainRequest();

        return $request?->query->get($key) ?? $request?->request->get($key);
    }
}
