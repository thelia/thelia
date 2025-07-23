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
use Thelia\Core\Event\Delivery\PickupLocationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\CountryQuery;
use Thelia\Model\StateQuery;

readonly class DeliveryPickupLocationProvider implements ProviderInterface
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private Request $request,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (!isset($uriVariables['city'], $uriVariables['zipcode'])) {
            throw new \RuntimeException('City and zipcode are required');
        }

        $stateId = $this->request->get('stateId');
        $state = $stateId
            ? (StateQuery::create())->filterById($stateId)->findOne()
            : null;
        $countryId = $this->request->get('countryId');
        $country = $countryId
            ? (CountryQuery::create())->filterById($countryId)->findOne()
            : null;
        $pickupLocationEvent = new PickupLocationEvent(
            null,
            $this->request->get('radius'),
            $this->request->get('maxRelays'),
            $this->request->get('address'),
            $uriVariables['city'],
            $uriVariables['zipcode'],
            $this->request->get('orderWeight'),
            $state,
            $country,
            $this->request->get('moduleIds'),
        );

        $this->dispatcher->dispatch($pickupLocationEvent, TheliaEvents::MODULE_DELIVERY_GET_PICKUP_LOCATIONS);

        return $pickupLocationEvent->getLocations();
    }
}
