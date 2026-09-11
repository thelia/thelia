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

namespace Thelia\Tests\Unit\Domain\OrderReturn;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\OrderReturn\OrderReturnStateMachine;
use Thelia\Model\OrderReturnStatus;

final class OrderReturnStateMachineTest extends TestCase
{
    private OrderReturnStateMachine $stateMachine;

    protected function setUp(): void
    {
        $this->stateMachine = new OrderReturnStateMachine();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function authorizedTransitions(): iterable
    {
        yield 'requested to accepted' => [OrderReturnStatus::CODE_REQUESTED, OrderReturnStatus::CODE_ACCEPTED];
        yield 'requested to refused' => [OrderReturnStatus::CODE_REQUESTED, OrderReturnStatus::CODE_REFUSED];
        yield 'requested to info awaited' => [OrderReturnStatus::CODE_REQUESTED, OrderReturnStatus::CODE_INFO_AWAITED];
        yield 'info awaited back to requested' => [OrderReturnStatus::CODE_INFO_AWAITED, OrderReturnStatus::CODE_REQUESTED];
        yield 'accepted to received' => [OrderReturnStatus::CODE_ACCEPTED, OrderReturnStatus::CODE_RECEIVED];
        yield 'accepted to expired' => [OrderReturnStatus::CODE_ACCEPTED, OrderReturnStatus::CODE_EXPIRED];
        yield 'received to settled' => [OrderReturnStatus::CODE_RECEIVED, OrderReturnStatus::CODE_SETTLED];
    }

    #[DataProvider('authorizedTransitions')]
    public function testAnAuthorizedTransitionIsAllowed(string $from, string $to): void
    {
        self::assertTrue($this->stateMachine->canTransition($from, $to));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function forbiddenTransitions(): iterable
    {
        yield 'requested cannot jump to received' => [OrderReturnStatus::CODE_REQUESTED, OrderReturnStatus::CODE_RECEIVED];
        yield 'requested cannot jump to settled' => [OrderReturnStatus::CODE_REQUESTED, OrderReturnStatus::CODE_SETTLED];
        yield 'accepted cannot go back to requested' => [OrderReturnStatus::CODE_ACCEPTED, OrderReturnStatus::CODE_REQUESTED];
        yield 'received cannot be refused' => [OrderReturnStatus::CODE_RECEIVED, OrderReturnStatus::CODE_REFUSED];
        yield 'a refused return does not move' => [OrderReturnStatus::CODE_REFUSED, OrderReturnStatus::CODE_ACCEPTED];
        yield 'a settled return does not move' => [OrderReturnStatus::CODE_SETTLED, OrderReturnStatus::CODE_RECEIVED];
        yield 'an expired return does not move' => [OrderReturnStatus::CODE_EXPIRED, OrderReturnStatus::CODE_ACCEPTED];
    }

    #[DataProvider('forbiddenTransitions')]
    public function testAForbiddenTransitionIsRejected(string $from, string $to): void
    {
        self::assertFalse($this->stateMachine->canTransition($from, $to));
    }

    public function testTerminalStatusesLeadNowhere(): void
    {
        self::assertTrue($this->stateMachine->isTerminal(OrderReturnStatus::CODE_REFUSED));
        self::assertTrue($this->stateMachine->isTerminal(OrderReturnStatus::CODE_SETTLED));
        self::assertTrue($this->stateMachine->isTerminal(OrderReturnStatus::CODE_EXPIRED));
    }

    public function testNonTerminalStatusesHaveTargets(): void
    {
        self::assertFalse($this->stateMachine->isTerminal(OrderReturnStatus::CODE_REQUESTED));
        self::assertFalse($this->stateMachine->isTerminal(OrderReturnStatus::CODE_ACCEPTED));
        self::assertFalse($this->stateMachine->isTerminal(OrderReturnStatus::CODE_RECEIVED));
        self::assertFalse($this->stateMachine->isTerminal(OrderReturnStatus::CODE_INFO_AWAITED));
    }

    public function testAnUnknownStatusHasNoTargetAndIsTerminal(): void
    {
        self::assertSame([], $this->stateMachine->allowedTargets('no_such_code'));
        self::assertTrue($this->stateMachine->isTerminal('no_such_code'));
    }
}
