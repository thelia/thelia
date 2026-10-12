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
use Thelia\Condition\Exception\UnmatchableConditionException;

/**
 * The message a condition gives when it cannot be checked yet is the one the
 * customer reads at checkout, so it travels unchanged with the exception.
 */
final class UnmatchableConditionExceptionTest extends TestCase
{
    public function testTheConditionMessageReachesTheCustomerUnchanged(): void
    {
        $exception = new UnmatchableConditionException('You must choose a delivery method before using this coupon.');

        self::assertSame('You must choose a delivery method before using this coupon.', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
    }

    public function testTheCodeAndTheCauseAreKept(): void
    {
        $cause = new \RuntimeException('No address selected');

        $exception = new UnmatchableConditionException('You must choose a billing address before using this coupon.', 42, $cause);

        self::assertSame(42, $exception->getCode());
        self::assertSame($cause, $exception->getPrevious());
    }
}
