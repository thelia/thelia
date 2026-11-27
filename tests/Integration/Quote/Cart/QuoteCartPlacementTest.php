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

namespace Thelia\Tests\Integration\Quote\Cart;

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Domain\Checkout\DTO\CheckoutPlacementRequest;
use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;
use Thelia\Domain\Checkout\Exception\CheckoutRefusedException;
use Thelia\Domain\Checkout\Exception\QuoteRequiredException;
use Thelia\Domain\Checkout\Service\CheckoutPlacementService;
use Thelia\Domain\Quote\Exception\QuoteAlreadyOrderedException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\OrderCouponQuery;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderQuery;

/**
 * Where a quote gives an order: the mode of the shop decides which cart may be ordered, the order
 * names the quote, keeps its prices and its discount and its postage, and a quote gives one order.
 */
final class QuoteCartPlacementTest extends QuoteCartTestCase
{
    /**
     * @return iterable<string, array{string, bool}> the mode, and whether an ordinary cart may be ordered
     */
    public static function modes(): iterable
    {
        yield 'disabled' => ['disabled', true];
        yield 'alongside' => ['alongside', true];
        yield 'only' => ['only', false];
        yield 'a value nobody knows is the mode a shop starts with' => ['whatever', true];
    }

    #[DataProvider('modes')]
    public function testAnOrdinaryCartIsOrderedOrRefusedAccordingToTheModeOfTheShop(string $mode, bool $mayBeOrdered): void
    {
        $this->setTheMode($mode);
        $ordinaryCart = $this->ordinaryCart();

        if ($mayBeOrdered) {
            self::assertNotNull($this->pay($ordinaryCart)->getId());

            return;
        }

        try {
            $this->pay($ordinaryCart);
            self::fail('An ordinary cart was ordered in a shop that only sells through quotes.');
        } catch (QuoteRequiredException $refusal) {
            self::assertSame(CheckoutViolationCode::QuoteRequired->value, $refusal->violationCode());
            self::assertSame('This shop only sells through quotes: ask for a quote for this cart.', $refusal->getMessage());
        }

        self::assertSame(0, OrderQuery::create()->filterByCartId($ordinaryCart->getId())->count());
    }

    #[DataProvider('modes')]
    public function testTheCartOfAQuoteIsOrderedWhateverTheMode(string $mode): void
    {
        $this->setTheMode($mode);
        $quoteCart = $this->quoteCart();

        self::assertNotNull($this->pay($quoteCart)->getId());
    }

    public function testThePlacementAnswersTheRefusalWithTheOthers(): void
    {
        $this->setTheMode('only');
        $ordinaryCart = $this->ordinaryCart();

        try {
            $this->getService(CheckoutPlacementService::class)->place(new CheckoutPlacementRequest(
                $ordinaryCart,
                $this->buyer,
                $this->factory->currency(),
                $this->factory->lang(),
            ));
            self::fail('An ordinary cart was placed in a shop that only sells through quotes.');
        } catch (CheckoutRefusedException $refusal) {
            self::assertSame([CheckoutViolationCode::QuoteRequired->value], array_map(static fn ($violation): string => $violation->code, $refusal->violations));
        }
    }

    public function testTheOrderNamesTheQuoteAndKeepsWhatWasAgreedEvenWhereTheShopPricesTheBuyerApart(): void
    {
        $this->priceTheBuyerApart();
        $this->tenPercentOffPromotion();
        $quoteCart = $this->quoteCart();

        $order = $this->pay($quoteCart);

        self::assertSame($this->quote->getId(), $order->getQuoteId());
        self::assertSame(30.0, (float) $order->getDiscount(), 'the discount of the quote, not the promotion');
        self::assertSame(18.0, (float) $order->getPostage());
        self::assertSame(3.0, (float) $order->getPostageTax());
        self::assertSame(0, OrderCouponQuery::create()->filterByOrderId($order->getId())->count());

        $lines = OrderProductQuery::create()->filterByOrderId($order->getId())->find();
        self::assertCount(1, $lines);
        self::assertSame(90.0, round((float) $lines->getFirst()->getPrice(), 6), 'the price of the quote, not the 85 of the group');
        self::assertSame(12.0, (float) $lines->getFirst()->getQuantity());
    }

    public function testAnOrdinaryOrderNamesNoQuote(): void
    {
        self::assertNull($this->pay($this->ordinaryCart())->getQuoteId());
    }

    public function testAQuoteGivesOneOrder(): void
    {
        $first = $this->quoteCart();
        $order = $this->pay($first);

        // The cart that already has its order, which waits for its payment, is not ordered twice: the
        // cheque cannot be presented again, so the order that waits is cancelled and the new one stands.
        $replacing = $this->pay($first);
        self::assertNotSame($order->getId(), $replacing->getId());
        self::assertSame(1, OrderQuery::create()->filterByQuoteId($this->quote->getId())->count());

        // Another cart of the same quote is refused: the quote is ordered.
        $second = $this->ordinaryCart();
        $second->setQuoteId($this->quote->getId())->setQuoteRevision(2)->save($this->getPropelConnection());

        try {
            $this->pay($second);
            self::fail('A quote gave two orders.');
        } catch (QuoteAlreadyOrderedException) {
        }

        self::assertSame(1, OrderQuery::create()->filterByQuoteId($this->quote->getId())->count());
    }

    public function testTheBuyerWhoseOrderWasCancelledPlacesItAgain(): void
    {
        $quoteCart = $this->quoteCart();
        $cancelled = $this->pay($quoteCart);
        $cancelled->setCancelled($this->dispatcher);

        $again = $this->pay($quoteCart);

        self::assertNotSame($cancelled->getId(), $again->getId());
        self::assertSame($this->quote->getId(), $again->getQuoteId());
        self::assertNull(OrderQuery::create()->findPk($cancelled->getId())?->getQuoteId());
    }

    public function testTheBuyerWhoPaysAnotherWayCancelsTheOrderThatWaitsAndPlacesANewOne(): void
    {
        $quoteCart = $this->quoteCart();
        $waiting = $this->pay($quoteCart);

        $other = $this->pay($quoteCart, $this->factory->currency(['code' => 'XQP', 'symbol' => 'XQ', 'rate' => 1.0]));

        self::assertNotSame($waiting->getId(), $other->getId(), 'another currency is another order');
        self::assertSame($this->quote->getId(), $other->getQuoteId());
        self::assertNull(OrderQuery::create()->findPk($waiting->getId())?->getQuoteId());
        self::assertSame(1, OrderQuery::create()->filterByQuoteId($this->quote->getId())->count());
    }

    private function setTheMode(string $mode): void
    {
        ConfigQuery::write('quote_mode', $mode);
        ConfigQuery::resetCache();
    }
}
