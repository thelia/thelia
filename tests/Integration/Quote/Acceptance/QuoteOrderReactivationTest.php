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

namespace Thelia\Tests\Integration\Quote\Acceptance;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Exception\OrderStatusRefusedByQuoteException;
use Thelia\Domain\Order\Exception\OrderStatusTransitionRefusedException;
use Thelia\Domain\Quote\Enum\QuoteState;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\Quote;

/**
 * An order that was cancelled lets go of its quote when the next one is begun. Brought back to
 * life later, it cannot be the second order of a quote that gives one at most: it is refused while
 * another order that is alive holds the quote, and takes the quote again when none does.
 */
final class QuoteOrderReactivationTest extends QuoteAcceptanceTestCase
{
    public function testAnOrderBroughtBackToLifeWhileAnotherOrderHoldsTheQuoteIsRefused(): void
    {
        [$quote, $first, $second] = $this->twoOrdersTheFirstOneCancelled();

        try {
            $this->bringBackToLife($first, forced: false);
            self::fail('A quote gives one order at most.');
        } catch (OrderStatusRefusedByQuoteException $refusal) {
            self::assertInstanceOf(OrderStatusTransitionRefusedException::class, $refusal, 'answered as every refused transition is');
            self::assertSame($first->getRef(), $refusal->getOrderRef());
            self::assertSame(OrderStatus::CODE_CANCELED, $refusal->getFromStatusCode());
        }

        $first = $this->fresh($first);
        self::assertTrue($first->isCancelled(), 'the status was not written');
        self::assertNull($first->getQuoteId());
        self::assertSame($quote->getId(), $this->fresh($second)->getQuoteId());
        self::assertSame(QuoteState::Ordered->value, $this->reread($quote)->getState());
        self::assertSame(1, OrderQuery::create()->filterByQuoteId($quote->getId())->count());
    }

    public function testTheRefusalHoldsWhenTheTransitionIsForcedToo(): void
    {
        [, $first] = $this->twoOrdersTheFirstOneCancelled();

        $this->expectException(OrderStatusRefusedByQuoteException::class);

        $this->bringBackToLife($first, forced: true);
    }

    public function testAnOrderBroughtBackToLifeWhenTheOtherOrderIsCancelledToTakesTheQuoteBack(): void
    {
        [$quote, $first, $second] = $this->twoOrdersTheFirstOneCancelled();
        $second->setCancelled($this->getService(EventDispatcherInterface::class));
        self::assertSame(QuoteState::Accepted->value, $this->reread($quote)->getState());

        $this->bringBackToLife($first);

        $first = $this->fresh($first);
        self::assertFalse($first->isCancelled());
        self::assertSame($quote->getId(), $first->getQuoteId(), 'the link is back');
        self::assertNull($this->fresh($second)->getQuoteId(), 'the order that was cancelled and held the quote let it go');
        self::assertSame(QuoteState::Ordered->value, $this->reread($quote)->getState());
        self::assertSame(1, OrderQuery::create()->filterByQuoteId($quote->getId())->count());
    }

    public function testTheOrderThatHoldsTheQuoteComesBackAsItAlwaysDid(): void
    {
        [$quote, , $second] = $this->twoOrdersTheFirstOneCancelled();
        $second->setCancelled($this->getService(EventDispatcherInterface::class));

        $this->bringBackToLife($second);

        self::assertSame($quote->getId(), $this->fresh($second)->getQuoteId());
        self::assertSame(QuoteState::Ordered->value, $this->reread($quote)->getState());
    }

    public function testAnOrdinaryOrderComesBackToLifeAsItAlwaysDid(): void
    {
        $order = $this->fixtures->order($this->customer(), ['statusCode' => OrderStatus::CODE_CANCELED]);

        $this->bringBackToLife($order);

        self::assertFalse($this->fresh($order)->isCancelled());
        self::assertNull($this->fresh($order)->getQuoteId());
    }

    /**
     * Two orders of one quote: the first was cancelled and let go of the quote when the second was
     * begun, the second holds it and is alive.
     *
     * @return array{Quote, Order, Order}
     */
    private function twoOrdersTheFirstOneCancelled(): array
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $cart = $this->quotes->accept($quote, $scenario['buyer'], 2);
        $first = $this->pay($cart);
        $first->setCancelled($this->getService(EventDispatcherInterface::class));
        $second = $this->pay($cart);

        self::assertNull($this->fresh($first)->getQuoteId(), 'the first order let go of the quote');
        self::assertSame($quote->getId(), $this->fresh($second)->getQuoteId());
        self::assertSame(QuoteState::Ordered->value, $this->reread($quote)->getState());

        return [$quote, $this->fresh($first), $second];
    }

    private function bringBackToLife(Order $order, bool $forced = true): void
    {
        $event = (new OrderEvent($this->fresh($order)))->setStatus((int) OrderStatusQuery::getNotPaidStatus()?->getId());

        if ($forced) {
            $event->forceStatusTransition();
        }

        $this->getService(EventDispatcherInterface::class)->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
    }

    private function fresh(Order $order): Order
    {
        OrderTableMap::clearInstancePool();

        return OrderQuery::create()->findPk($order->getId()) ?? throw new \LogicException('The order is gone.');
    }
}
