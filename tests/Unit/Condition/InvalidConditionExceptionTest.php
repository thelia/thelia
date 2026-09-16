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

namespace Thelia\Tests\Unit\Condition;

use PHPUnit\Framework\TestCase;
use Thelia\Condition\Exception\InvalidConditionException;
use Thelia\Condition\Implementation\MatchForTotalAmount;

/**
 * A coupon carrying no condition is a normal thing for a merchant to create, and the
 * checkout handles it by ignoring the coupon. The exception that says so used to write
 * an ERROR line of its own, which put a failure in the shop log on a nominal path — and
 * made the exception impossible to build without a database, since the logger reads its
 * configuration from one.
 */
final class InvalidConditionExceptionTest extends TestCase
{
    public function testItCarriesTheOffendingClassNameWithoutLoggingOrBootingAnything(): void
    {
        $exception = new InvalidConditionException(MatchForTotalAmount::class);

        self::assertSame('Invalid Condition given to '.MatchForTotalAmount::class, $exception->getMessage());
        self::assertInstanceOf(\RuntimeException::class, $exception);
    }
}
