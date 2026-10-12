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

        $stateId = $this->requestParam('stateId', $context);
        $state = $stateId
            ? (StateQuery::create())->filterById($stateId)->findOne()
            : null;
        $countryId = $this->requestParam('countryId', $context);
        $country = $countryId
            ? (CountryQuery::create())->filterById($countryId)->findOne()
            : null;
        $radius = $this->requestParam('radius', $context);
        $maxRelays = $this->requestParam('maxRelays', $context);
        $orderWeight = $this->requestParam('orderWeight', $context);

        $pickupLocationEvent = new PickupLocationEvent(
            null,
            null !== $radius ? (int) $radius : null,
            null !== $maxRelays ? (int) $maxRelays : null,
            $this->requestParam('address', $context),
            $uriVariables['city'],
            $uriVariables['zipcode'],
            null !== $orderWeight ? (int) $orderWeight : null,
            $state,
            $country,
            $this->requestParam('moduleIds', $context),
        );

        $this->dispatcher->dispatch($pickupLocationEvent, TheliaEvents::MODULE_DELIVERY_GET_PICKUP_LOCATIONS);

        return $pickupLocationEvent->getLocations();
    }

    /**
     * The parameter as the operation was asked for it: the filters of the context first, which is where a caller that
     * is not an HTTP request (`DataAccessService::resources()`, a front theme) puts them, then the current request.
     *
     * @param array<string, mixed> $context
     */
    private function requestParam(string $key, array $context): mixed
    {
        $filters = $context['filters'] ?? [];

        if (\is_array($filters) && \array_key_exists($key, $filters)) {
            return $filters[$key];
        }

        $request = $this->requestStack->getCurrentRequest() ?? $this->requestStack->getMainRequest();

        return $request?->query->get($key) ?? $request?->request->get($key);
    }
}
