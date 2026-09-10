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

namespace Thelia\Tests\Unit\Domain\Order;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Order\Service\OrderStatusTransitionGraph;

/**
 * Statuses: 1 not_paid, 2 paid, 3 processing, 4 sent, 5 canceled, 6 refunded,
 * 7 "paid on delivery" equivalent to paid, 8 "shipped by carrier" equivalent to sent,
 * 9 a custom status with no equivalence.
 */
final class OrderStatusTransitionGraphTest extends TestCase
{
    private const EQUIVALENTS = [1 => [1], 2 => [2], 3 => [3], 4 => [4], 5 => [5], 6 => [6], 7 => [7, 2], 8 => [8, 4], 9 => [9]];

    public function testAStatusWithNoDeclaredTransitionIsFreeAndReachesEverything(): void
    {
        $graph = new OrderStatusTransitionGraph([2 => [3]], self::EQUIVALENTS);

        self::assertTrue($graph->isFree(1));
        self::assertTrue($graph->allows(1, 4));
        self::assertNull($graph->targetsFrom(1));
    }

    public function testADeclaredStatusOnlyReachesItsTargets(): void
    {
        $graph = new OrderStatusTransitionGraph([4 => [6]], self::EQUIVALENTS);

        self::assertFalse($graph->isFree(4));
        self::assertTrue($graph->allows(4, 6));
        self::assertFalse($graph->allows(4, 1));
        self::assertSame([6], $graph->targetsFrom(4));
    }

    public function testStayingOnTheSameStatusIsAlwaysAllowed(): void
    {
        $graph = new OrderStatusTransitionGraph([4 => [6]], self::EQUIVALENTS);

        self::assertTrue($graph->allows(4, 4));
    }

    public function testACustomStatusFollowsTheTransitionsOfTheCanonicalStatusItStandsFor(): void
    {
        $graph = new OrderStatusTransitionGraph([2 => [3, 6]], self::EQUIVALENTS);

        self::assertFalse($graph->isFree(7), 'paid on delivery inherits the constraint of paid');
        self::assertTrue($graph->allows(7, 3));
        self::assertFalse($graph->allows(7, 4));
    }

    public function testATransitionToACanonicalStatusCoversItsCustomEquivalents(): void
    {
        $graph = new OrderStatusTransitionGraph([3 => [4]], self::EQUIVALENTS);

        self::assertTrue($graph->allows(3, 8), 'processing to "shipped by carrier" counts as processing to sent');
        self::assertFalse($graph->allows(3, 5));
    }

    public function testOwnTransitionsOfACustomStatusAddUpToTheInheritedOnes(): void
    {
        $graph = new OrderStatusTransitionGraph([2 => [3], 7 => [5]], self::EQUIVALENTS);

        self::assertSame([5, 3], $graph->targetsFrom(7));
        self::assertTrue($graph->allows(7, 5));
        self::assertTrue($graph->allows(7, 3));
    }

    public function testNoStatusIsUnreachableWhileAnotherStatusIsFree(): void
    {
        $graph = new OrderStatusTransitionGraph([2 => [3]], self::EQUIVALENTS);

        self::assertSame([], $graph->unreachableStatusIds([1, 2, 3, 4, 5, 6]));
    }

    public function testTheOnlyFreeStatusIsUnreachableWhenNothingLeadsToIt(): void
    {
        $graph = new OrderStatusTransitionGraph([1 => [2], 2 => [1]], self::EQUIVALENTS);

        self::assertSame([9], $graph->unreachableStatusIds([1, 2, 9]));
    }

    public function testAStatusNoTransitionLeadsToIsReportedUnreachableWhileEquivalentsOfReachedStatusesAreNot(): void
    {
        $graph = new OrderStatusTransitionGraph(
            [1 => [2, 5], 2 => [3], 3 => [4], 4 => [6], 5 => [1], 6 => [1]],
            self::EQUIVALENTS,
        );

        self::assertSame([9], $graph->unreachableStatusIds([1, 2, 3, 4, 5, 6, 7, 8, 9]));
    }

    public function testACustomStatusReachedThroughItsCanonicalStatusIsNotUnreachable(): void
    {
        $graph = new OrderStatusTransitionGraph(
            [1 => [2], 2 => [1], 7 => [1]],
            self::EQUIVALENTS,
        );

        self::assertNotContains(7, $graph->unreachableStatusIds([1, 2, 7]));
    }
}
