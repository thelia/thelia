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

namespace Thelia\Tests\Unit\Core\Event\Delivery;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Core\Event\Delivery\PickupLocationEvent;
use Thelia\Model\Address;
use Thelia\Model\Country;
use Thelia\Model\State;

/**
 * A pickup location search needs somewhere to start from, and falls back on the search
 * window the delivery modules expect when the caller gives none.
 */
final class PickupLocationEventTest extends TestCase
{
    public function testTheSearchWindowDefaultsToARadiusOf20000AndFifteenRelays(): void
    {
        $event = new PickupLocationEvent(city: 'Clermont-Ferrand');

        self::assertSame(20000, $event->getRadius());
        self::assertSame(15, $event->getMaxRelays());
    }

    public function testTheCallerSearchWindowWins(): void
    {
        $event = new PickupLocationEvent(radius: 5000, maxRelays: 3, city: 'Clermont-Ferrand');

        self::assertSame(5000, $event->getRadius());
        self::assertSame(3, $event->getMaxRelays());
    }

    /**
     * @return \Generator<string, array{array<string, string>}>
     */
    public static function enoughToStartASearch(): \Generator
    {
        yield 'a street alone' => [['address' => '10 rue de la Paix']];
        yield 'a city alone' => [['city' => 'Paris']];
        yield 'a zip code alone' => [['zipCode' => '75002']];
    }

    /**
     * @param array<string, string> $location
     */
    #[DataProvider('enoughToStartASearch')]
    public function testAnyOneOfStreetCityOrZipCodeIsEnough(array $location): void
    {
        $event = new PickupLocationEvent(...$location);

        self::assertSame($location['address'] ?? null, $event->getAddress());
        self::assertSame($location['city'] ?? null, $event->getCity());
        self::assertSame($location['zipCode'] ?? null, $event->getZipCode());
    }

    public function testASearchWithoutAnyPlaceIsRefused(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Not enough informations to retrieve pickup locations');

        new PickupLocationEvent(radius: 1000);
    }

    public function testASavedAddressProvidesTheWholeLocation(): void
    {
        $state = $this->createStub(State::class);
        $country = $this->createStub(Country::class);
        $address = $this->createStub(Address::class);
        $address->method('getAddress1')->willReturn('10 rue de la Paix');
        $address->method('getCity')->willReturn('Paris');
        $address->method('getZipcode')->willReturn('75002');
        $address->method('getState')->willReturn($state);
        $address->method('getCountry')->willReturn($country);

        $event = new PickupLocationEvent($address);

        self::assertSame('10 rue de la Paix', $event->getAddress());
        self::assertSame('Paris', $event->getCity());
        self::assertSame('75002', $event->getZipCode());
        self::assertSame($state, $event->getState());
        self::assertSame($country, $event->getCountry());
    }
}
