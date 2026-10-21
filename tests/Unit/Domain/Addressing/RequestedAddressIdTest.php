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

namespace Thelia\Tests\Unit\Domain\Addressing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Addressing\Service\AddressService;

/**
 * The address a request names is a strictly positive decimal integer: anything else names
 * none, and the list of carriers is then judged on the cart's own copy of its address.
 */
final class RequestedAddressIdTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, int|null}>
     */
    public static function values(): iterable
    {
        yield 'digits' => ['7', 7];
        yield 'an int of a JSON body' => [7, 7];
        yield 'leading zeros' => ['007', null];
        yield 'a plus sign' => ['+7', null];
        yield 'a minus sign' => ['-5', null];
        yield 'a leading space' => [' 7', null];
        yield 'a trailing space' => ['7 ', null];
        yield 'zero' => ['0', null];
        yield 'a zero int' => [0, null];
        yield 'a negative int' => [-5, null];
        yield 'a decimal' => ['7.0', null];
        yield 'a float of a JSON body' => [7.0, null];
        yield 'true of a JSON body' => [true, null];
        yield 'false of a JSON body' => [false, null];
        yield 'text' => ['abc', null];
        yield 'more digits than an int holds' => ['99999999999999999999', null];
    }

    #[DataProvider('values')]
    public function testAnAddressIdIsAStrictlyPositiveDecimalInteger(mixed $value, ?int $expected): void
    {
        self::assertSame($expected, $this->service()->requestedAddressId(new Request([], ['addressId' => $value])));
    }

    public function testABlankRouteAttributeDoesNotHideTheIdOfTheBodyOrTheQuery(): void
    {
        self::assertSame(12, $this->service()->requestedAddressId(new Request([], ['addressId' => '12'], ['addressId' => ' '])));
        self::assertSame(13, $this->service()->requestedAddressId(new Request(['addressId' => '13'], [], ['addressId' => ''])));
    }

    public function testAValueThatNamesNoAddressIsNotReplacedByTheNextSource(): void
    {
        self::assertNull($this->service()->requestedAddressId(new Request(['addressId' => '12'], ['addressId' => '007'])));
    }

    private function service(): AddressService
    {
        return new AddressService($this->createStub(EventDispatcherInterface::class), $this->createStub(Session::class));
    }
}
