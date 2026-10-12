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

namespace Thelia\Tests\Unit\Core\Event\Address;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Core\Event\Address\AddressCreateOrUpdateEvent;
use Thelia\Model\Address;
use Thelia\Model\Customer;

/**
 * The event reads back what an address form submitted: identifiers and flags arrive as
 * strings or integers from the form, and are handed to the listeners as typed values.
 */
final class AddressCreateOrUpdateEventTest extends TestCase
{
    public function testAnAddressIsNotTheDefaultOneUnlessTheCallerSaysSo(): void
    {
        $event = new AddressCreateOrUpdateEvent(
            'Home',
            1,
            'Ada',
            'Lovelace',
            '10 rue de la Paix',
            '',
            '',
            '75002',
            'Paris',
            64,
            '0600000000',
            '0100000000',
            null,
        );

        self::assertSame(0, $event->getIsDefault());
    }

    /**
     * @return \Generator<string, array{mixed, int}>
     */
    public static function defaultFlagsAsSubmitted(): \Generator
    {
        yield 'a checked box submitted as a string' => ['1', 1];
        yield 'the integer one' => [1, 1];
        yield 'an unchecked box submitted as a string' => ['0', 0];
        yield 'the integer zero' => [0, 0];
        yield 'an unchecked box submitted as nothing' => [null, 0];
    }

    #[DataProvider('defaultFlagsAsSubmitted')]
    public function testTheDefaultFlagIsReadBackAsZeroOrOne(mixed $submitted, int $expected): void
    {
        self::assertSame($expected, $this->event($submitted)->getIsDefault());
    }

    public function testTheStateSubmittedAsAStringIsReadBackAsAnInteger(): void
    {
        self::assertSame(42, $this->event(0, '42')->getState());
    }

    public function testAnAddressWithoutStateReadsBackNull(): void
    {
        self::assertNull($this->event(0, null)->getState());
        self::assertNull($this->event(0, '')->getState());
    }

    public function testBindingTheSavedAddressBindsItsCustomerToo(): void
    {
        $customer = $this->createStub(Customer::class);
        $address = $this->createStub(Address::class);
        $address->method('getCustomer')->willReturn($customer);

        $event = $this->event();
        $event->setAddress($address);

        self::assertSame($address, $event->getAddress());
        self::assertSame($customer, $event->getCustomer());
    }

    private function event(mixed $isDefault = 0, mixed $state = null): AddressCreateOrUpdateEvent
    {
        return new AddressCreateOrUpdateEvent(
            'Home',
            1,
            'Ada',
            'Lovelace',
            '10 rue de la Paix',
            '',
            '',
            '75002',
            'Paris',
            64,
            '0600000000',
            '0100000000',
            null,
            $isDefault,
            $state,
        );
    }
}
