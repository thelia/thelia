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

namespace Thelia\Tests\Integration\Domain\Promotion\Coupon\Exception;

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Domain\Promotion\Coupon\Exception\CouponExpiredException;
use Thelia\Domain\Promotion\Coupon\Exception\CouponNotReleaseException;
use Thelia\Domain\Promotion\Coupon\Exception\CouponNoUsageLeftException;
use Thelia\Domain\Promotion\Coupon\Exception\InactiveCouponException;
use Thelia\Test\IntegrationTestCase;

/**
 * The customer reads these messages when a code they typed is refused: each one
 * names the code, in whatever language the shop translates it to.
 */
final class CouponExceptionMessageTest extends IntegrationTestCase
{
    /**
     * @return iterable<string, array{class-string<\Exception>}>
     */
    public static function refusals(): iterable
    {
        yield 'expired' => [CouponExpiredException::class];
        yield 'not started yet' => [CouponNotReleaseException::class];
        yield 'used up' => [CouponNoUsageLeftException::class];
        yield 'disabled' => [InactiveCouponException::class];
    }

    /**
     * @param class-string<\Exception> $exceptionClass
     */
    #[DataProvider('refusals')]
    public function testTheMessageNamesTheCodeThatWasRefused(string $exceptionClass): void
    {
        $message = (new $exceptionClass('SUMMER-42'))->getMessage();

        self::assertStringContainsString('SUMMER-42', $message);
        self::assertStringNotContainsString('%code', $message);
    }
}
