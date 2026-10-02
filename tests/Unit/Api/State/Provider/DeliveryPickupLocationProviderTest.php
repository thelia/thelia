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

namespace Thelia\Tests\Unit\Api\State\Provider;

use ApiPlatform\Metadata\Get;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Api\State\Provider\DeliveryPickupLocationProvider;
use Thelia\Core\Event\Delivery\PickupLocationEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * `DataAccessService::resources()` (what a front theme calls) hands the query parameters over in `$context['filters']`:
 * there is no HTTP request behind the call, so a provider that reads the request only never saw them. The country, the
 * address and the radius of a pickup point search were lost, and the carrier modules searched without a country.
 */
final class DeliveryPickupLocationProviderTest extends TestCase
{
    public function testTheParametersOfTheContextReachTheModules(): void
    {
        $event = $this->provide(new RequestStack(), ['filters' => ['address' => '5 rue Neuve', 'radius' => '15000', 'maxRelays' => '10', 'orderWeight' => '2', 'moduleIds' => [7]]]);

        self::assertSame('5 rue Neuve', $event->getAddress());
        self::assertSame(15000, $event->getRadius());
        self::assertSame(10, $event->getMaxRelays());
        self::assertSame(2, $event->getOrderWeight());
        self::assertSame([7], $event->getModuleIds());
        self::assertSame('Bruxelles', $event->getCity());
        self::assertSame('1000', $event->getZipCode());
    }

    public function testTheRequestIsStillReadWhenTheContextHoldsNone(): void
    {
        $stack = new RequestStack();
        $stack->push(new Request(['address' => '1 rue de la Gare', 'radius' => '500']));

        $event = $this->provide($stack, []);

        self::assertSame('1 rue de la Gare', $event->getAddress());
        self::assertSame(500, $event->getRadius());
    }

    public function testTheContextWinsOverTheRequest(): void
    {
        $stack = new RequestStack();
        $stack->push(new Request(['address' => 'from the request']));

        $event = $this->provide($stack, ['filters' => ['address' => 'from the context']]);

        self::assertSame('from the context', $event->getAddress());
    }

    /**
     * @param array<string, mixed> $context
     */
    private function provide(RequestStack $stack, array $context): PickupLocationEvent
    {
        $dispatcher = new EventDispatcher();
        $received = null;
        $dispatcher->addListener(TheliaEvents::MODULE_DELIVERY_GET_PICKUP_LOCATIONS, static function (PickupLocationEvent $event) use (&$received): void {
            $received = $event;
        });

        (new DeliveryPickupLocationProvider($dispatcher, $stack))->provide(new Get(), ['city' => 'Bruxelles', 'zipcode' => '1000'], $context);

        self::assertInstanceOf(PickupLocationEvent::class, $received);

        return $received;
    }
}
