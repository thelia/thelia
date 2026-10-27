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

namespace Thelia\Tests\Unit\Domain\Customer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Customer\Exception\DefaultCustomerGroupHasNoRegistrationLinkException;
use Thelia\Domain\Customer\Exception\InvalidRegistrationTokenException;
use Thelia\Domain\Customer\Service\CustomerGroupRegistration;
use Thelia\Model\CustomerGroup;

/**
 * What a registration token may be is decided before the database is asked: a unit test runs with no
 * connection, so a string that reached the query would fail here instead of answering null.
 */
final class CustomerGroupRegistrationTokenTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function stringsThatAreNoToken(): iterable
    {
        yield 'empty' => [''];
        yield 'one character' => ['a'];
        yield 'one short' => [str_repeat('a', 63)];
        yield 'one long' => [str_repeat('a', 65)];
        yield 'not hexadecimal' => [str_repeat('g', 64)];
        yield 'uppercase hexadecimal' => [str_repeat('A', 64)];
        yield 'a trailing newline' => [str_repeat('a', 64)."\n"];
        yield 'spaces' => [' '.str_repeat('a', 62).' '];
        yield 'quote' => [str_repeat('a', 63)."'"];
        yield 'multibyte' => [str_repeat('é', 32)];
    }

    #[DataProvider('stringsThatAreNoToken')]
    public function testAStringThatCannotBeATokenOpensNoGroupAndAsksNoQuery(string $token): void
    {
        self::assertNull((new CustomerGroupRegistration())->groupForToken($token));
    }

    public function testTheTokenIsSixtyFourCharacters(): void
    {
        self::assertSame(64, CustomerGroupRegistration::TOKEN_LENGTH);
    }

    public function testTheDefaultGroupIsRefusedBeforeAnythingIsWritten(): void
    {
        $default = (new CustomerGroup())->setIsDefault(1);
        $registration = new CustomerGroupRegistration();

        foreach ([$registration->open(...), $registration->regenerate(...)] as $attempt) {
            try {
                $attempt($default);
                self::fail('The default group has no link.');
            } catch (DefaultCustomerGroupHasNoRegistrationLinkException $exception) {
                self::assertStringContainsString('default', $exception->getMessage());
            }
        }

        self::assertNull($default->getRegistrationToken());
    }

    public function testTheRefusalOfATokenSaysTheSameWhateverTheCause(): void
    {
        self::assertSame('This registration link is not valid.', InvalidRegistrationTokenException::create()->getMessage());
    }
}
