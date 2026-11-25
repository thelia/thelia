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

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\DTO\CheckoutPlacementRequest;
use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;
use Thelia\Domain\Checkout\Exception\CheckoutRefusedException;
use Thelia\Domain\Checkout\Service\CheckoutPlacementService;
use Thelia\Domain\Company\Checkout\Exception\CompanyOrderRefusedException;
use Thelia\Domain\Quote\Acceptance\QuotePlacement;
use Thelia\Domain\Quote\Enum\QuoteState;
use Thelia\Domain\Quote\Event\QuoteEvents;
use Thelia\Domain\Quote\Event\QuoteOrderedEvent;
use Thelia\Domain\Quote\Exception\QuoteAlreadyOrderedException;
use Thelia\Domain\Quote\Exception\QuoteCartChangedException;
use Thelia\Domain\Quote\Exception\QuoteLinesUnavailableException;
use Thelia\Domain\Quote\Exception\QuoteTransitionRefusedException;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Country;
use Thelia\Model\Customer;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderProductTaxQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;

/**
 * Where the accepted quote becomes an order: the checkout orders the cart of the quote like any
 * cart, the order keeps what was negotiated, and the quote follows the order.
 */
final class QuoteOrderFlowTest extends QuoteAcceptanceTestCase
{
    public function testTheOrderCarriesWhatWasNegotiatedAndNotTheCatalog(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $cart = $this->quotes->accept($quote, $scenario['buyer'], 2);

        $order = $this->pay($cart);

        self::assertSame($quote->getId(), $order->getQuoteId());
        self::assertSame($cart->getId(), $order->getCartId());
        self::assertSame(30.0, (float) $order->getDiscount());
        self::assertSame(18.0, (float) $order->getPostage(), 'the postage of the quote with its taxes: 15 and 3');
        self::assertSame(3.0, (float) $order->getPostageTax());
        self::assertSame(1620.0, round($order->getTotalAmount(), 2), '(1360 + 15) x 1.2 - 30');

        $lines = [...OrderProductQuery::create()->filterByOrderId($order->getId())->orderById()->find()];
        self::assertCount(3, $lines);
        self::assertSame([90.0, 45.0, 20.0], array_map(static fn ($line): float => round((float) $line->getPrice(), 6), $lines), 'the negotiated prices, not 100, 50 and 20 of the catalog');
        self::assertSame([12.0, 4.0, 5.0], array_map(static fn ($line): float => (float) $line->getQuantity(), $lines));

        $taxes = array_map(
            static fn ($line): float => array_sum(array_map(
                static fn ($tax): float => (float) $tax->getAmount(),
                [...OrderProductTaxQuery::create()->filterByOrderProductId($line->getId())->find()],
            )),
            $lines,
        );
        self::assertSame([18.0, 9.0, 4.0], array_map(static fn (float $tax): float => round($tax, 6), $taxes), '20 % of the negotiated price');
    }

    public function testTheQuoteIsOrderedWithItsOrderAndTheShopIsToldOnceTheOrderIsCommitted(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $cart = $this->quotes->accept($quote, $scenario['buyer'], 2);
        self::assertSame(QuoteState::Accepted->value, $this->reread($quote)->getState(), 'accepted is not ordered');

        $this->pay($cart);

        self::assertSame(QuoteState::Ordered->value, $this->reread($quote)->getState());
        $heard = $this->eventsOf(QuoteEvents::ORDERED);
        self::assertCount(1, $heard);
        self::assertInstanceOf(QuoteOrderedEvent::class, $heard[0]);
        self::assertSame(2, $heard[0]->getRevisionNumber());
        self::assertSame($quote->getId(), $heard[0]->getQuote()->getId());
        self::assertSame(self::TEST_TRANSACTION_LEVELS, $this->quoteEvents[QuoteEvents::ORDERED][0]['transactionLevel']);
    }

    public function testAnOrderThatIsRefusedLeavesTheQuoteAcceptedAndTellsNothing(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company, 'buyer', null, '100');
        $this->fixtures->address($buyer, Country::getDefaultCountry())->setIsDefault(1)->save($this->getPropelConnection());
        $scenario = $this->quotedScenario($buyer);
        $cart = $this->quotes->accept($scenario['quote'], $buyer, 2);

        try {
            $this->pay($cart);
            self::fail('An order over the limit of the buyer was placed.');
        } catch (CompanyOrderRefusedException) {
        }

        // The order rows of the refused attempt are not undone here: the rollback of a transaction
        // nested in the one of the test only marks it. What is read is the quote, which is written last.
        self::assertSame(QuoteState::Accepted->value, $this->reread($scenario['quote'])->getState());
        self::assertSame([], $this->eventsOf(QuoteEvents::ORDERED));
    }

    public function testAnOrderThatIsCancelledGivesTheQuoteBackAndThePaymentIsTriedAgain(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $cart = $this->quotes->accept($quote, $scenario['buyer'], 2);
        $order = $this->pay($cart);

        $order->setCancelled($this->getService(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class));

        self::assertSame(QuoteState::Accepted->value, $this->reread($quote)->getState(), 'nothing stands any more for the quote');

        $again = $this->pay($cart);

        self::assertNotSame($order->getId(), $again->getId());
        self::assertSame($quote->getId(), $again->getQuoteId());
        self::assertSame(QuoteState::Ordered->value, $this->reread($quote)->getState());
        self::assertCount(2, $this->eventsOf(QuoteEvents::ORDERED));
        self::assertSame(1, OrderQuery::create()->filterByQuoteId($quote->getId())->count(), 'the cancelled order let the quote go');
    }

    public function testTheBuyerWhoseOrderWasCancelledAcceptsAgainAndGetsTheSameCart(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $cart = $this->quotes->accept($quote, $scenario['buyer'], 2);
        $this->pay($cart)->setCancelled($this->getService(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class));

        $again = $this->quotes->accept($quote, $scenario['buyer'], 2);

        self::assertSame($cart->getId(), $again->getId(), 'a cancelled order is no order: the cart is still the one to pay');
        self::assertCount(1, $this->eventsOf(QuoteEvents::ACCEPTED), 'the buyer was told once');
    }

    public function testTheCancelledOrderLetsGoOfTheQuoteWhenTheNextOneIsBegun(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $cart = $this->quotes->accept($quote, $scenario['buyer'], 2);
        $order = $this->pay($cart);
        $order->setCancelled($this->getService(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class));
        self::assertSame($quote->getId(), OrderQuery::create()->findPk($order->getId())?->getQuoteId(), 'still named on the cancelled order');

        $beingOrdered = $this->getService(QuotePlacement::class)->begin($this->reloaded($cart), $this->getPropelConnection());

        self::assertSame($quote->getId(), $beingOrdered?->quoteId);
        self::assertSame(2, $beingOrdered?->revisionNumber);
        OrderTableMap::clearInstancePool();
        self::assertNull(OrderQuery::create()->findPk($order->getId())?->getQuoteId());
    }

    public function testAnOrderThatComesBackToLifeTakesTheQuoteAgain(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $order = $this->pay($this->quotes->accept($quote, $scenario['buyer'], 2));
        $dispatcher = $this->getService(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class);
        $order->setCancelled($dispatcher);
        self::assertSame(QuoteState::Accepted->value, $this->reread($quote)->getState());

        $notPaid = OrderStatusQuery::getNotPaidStatus();
        $dispatcher->dispatch((new OrderEvent($order))->setStatus((int) $notPaid?->getId())->forceStatusTransition(), TheliaEvents::ORDER_UPDATE_STATUS);

        self::assertSame(QuoteState::Ordered->value, $this->reread($quote)->getState());
    }

    public function testAnOrderOfAnotherStatusChangeLeavesTheQuoteAlone(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $order = $this->pay($this->quotes->accept($quote, $scenario['buyer'], 2));
        $dispatcher = $this->getService(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class);
        $paid = OrderStatusQuery::create()->findOneByCode('paid');

        $dispatcher->dispatch((new OrderEvent($order))->setStatus((int) $paid?->getId()), TheliaEvents::ORDER_UPDATE_STATUS);

        self::assertSame(QuoteState::Ordered->value, $this->reread($quote)->getState());
    }

    public function testAQuoteGivesOneOrderWhateverTheCartThatIsPaid(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company);
        $colleague = $this->buyerOf($company, 'company_admin');
        $scenario = $this->quotedScenario($buyer);
        $quote = $scenario['quote'];
        $firstCart = $this->withAddresses($this->quotes->accept($quote, $buyer, 2));
        $secondCart = $this->withAddresses($this->quotes->accept($quote, $colleague, 2));
        self::assertNotSame($firstCart->getId(), $secondCart->getId(), 'each of them holds a cart of the quote');

        $order = $this->pay($firstCart, $buyer);

        try {
            $this->pay($secondCart, $colleague);
            self::fail('A quote gave two orders.');
        } catch (QuoteAlreadyOrderedException $refusal) {
            self::assertSame('quote_already_ordered', $refusal->reason());
        }

        self::assertSame(1, OrderQuery::create()->filterByQuoteId($quote->getId())->count());
        self::assertSame(0, OrderQuery::create()->filterByCartId($secondCart->getId())->count());
        self::assertSame($order->getId(), OrderQuery::create()->filterByQuoteId($quote->getId())->findOne()?->getId());
        self::assertCount(1, $this->eventsOf(QuoteEvents::ORDERED));
    }

    public function testTheCheckoutAnswersASecondOrderOfTheQuoteLikeItAnswersEveryRefusal(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company);
        $colleague = $this->buyerOf($company, 'company_admin');
        $scenario = $this->quotedScenario($buyer);
        $quote = $scenario['quote'];
        $firstCart = $this->withAddresses($this->quotes->accept($quote, $buyer, 2));
        $secondCart = $this->withAddresses($this->quotes->accept($quote, $colleague, 2));
        $this->pay($firstCart, $buyer);
        $secondCart->setPaymentModuleId($this->paymentModuleId())->save($this->getPropelConnection());

        try {
            $this->getService(CheckoutPlacementService::class)->place(new CheckoutPlacementRequest(
                $this->reloaded($secondCart),
                $colleague,
                $this->theCurrencyOf($quote),
                $this->fixtures->lang(),
            ));
            self::fail('A quote gave two orders.');
        } catch (CheckoutRefusedException $refusal) {
            self::assertSame([CheckoutViolationCode::QuoteAlreadyOrdered->value], array_map(static fn ($violation): string => $violation->code, $refusal->violations));
            self::assertSame('An order was already placed for this quote.', $refusal->violations[0]->message);
        }

        self::assertSame(1, OrderQuery::create()->filterByQuoteId($quote->getId())->count());
    }

    public function testAnOrderedQuoteCannotBeAcceptedAgain(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $this->pay($this->quotes->accept($quote, $scenario['buyer'], 2));

        $this->expectException(QuoteTransitionRefusedException::class);

        $this->quotes->accept($quote, $scenario['buyer'], 2);
    }

    public function testAProductDeletedAfterTheAcceptanceRefusesTheOrderAndTheQuoteStaysAccepted(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $cart = $this->quotes->accept($quote, $scenario['buyer'], 2);
        $this->getPropelConnection()->exec('DELETE FROM `product` WHERE `id` = '.(int) $scenario['c']->getId());
        self::assertSame(2, CartItemQuery::create()->filterByCartId($cart->getId())->count(), 'the line went with the product, and the cart still looks sound');

        try {
            $this->pay($cart);
            self::fail('The cart of a quote was ordered without one of the lines that were accepted.');
        } catch (QuoteLinesUnavailableException $refusal) {
            self::assertSame('quote_lines_unavailable', $refusal->reason());
            self::assertSame([['reference' => $scenario['c']->getRef(), 'title' => 'Product C']], $refusal->lines());
        }

        self::assertSame(QuoteState::Accepted->value, $this->reread($quote)->getState());
        self::assertSame(0, OrderQuery::create()->filterByCartId($cart->getId())->count());
        self::assertSame([], $this->eventsOf(QuoteEvents::ORDERED));
    }

    public function testTheCheckoutAnswersAQuoteThatCannotBeOrderedAnyMoreLikeItAnswersEveryRefusal(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $cart = $this->quotes->accept($quote, $scenario['buyer'], 2);
        $cart->setPaymentModuleId($this->paymentModuleId())->save($this->getPropelConnection());
        $this->getPropelConnection()->exec('DELETE FROM `product` WHERE `id` = '.(int) $scenario['c']->getId());

        try {
            $this->getService(CheckoutPlacementService::class)->place(new CheckoutPlacementRequest(
                $this->reloaded($cart),
                $scenario['buyer'],
                $this->theCurrencyOf($quote),
                $this->fixtures->lang(),
            ));
            self::fail('The cart of a quote was ordered without one of the lines that were accepted.');
        } catch (CheckoutRefusedException $refusal) {
            self::assertSame([CheckoutViolationCode::QuoteNoLongerOrderable->value], array_map(static fn ($violation): string => $violation->code, $refusal->violations));
            self::assertSame('Some lines of this quote are no longer sold, so it cannot be accepted.', $refusal->violations[0]->message);
        }

        self::assertSame(0, OrderQuery::create()->filterByCartId($cart->getId())->count());
        self::assertSame(QuoteState::Accepted->value, $this->reread($quote)->getState());
    }

    public function testALineOfTheRevisionThatNamesNothingToSellAnyMoreRefusesTheOrder(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $cart = $this->quotes->accept($quote, $scenario['buyer'], 2);
        $line = $this->lineOf($this->revisionOf($quote, 2), $scenario['a']->getRef());
        $this->getPropelConnection()->exec('UPDATE `quote_item` SET `product_sale_elements_id` = NULL WHERE `id` = '.(int) $line->getId());

        try {
            $this->pay($cart);
            self::fail('The cart of a quote was ordered with a line that sells nothing.');
        } catch (QuoteLinesUnavailableException $refusal) {
            self::assertSame([['reference' => $scenario['a']->getRef(), 'title' => 'Product A']], $refusal->lines());
        }

        self::assertSame(QuoteState::Accepted->value, $this->reread($quote)->getState());
        self::assertSame(0, OrderQuery::create()->filterByCartId($cart->getId())->count());
    }

    /**
     * @return iterable<string, array{string}> what is done to the cart of the quote, the id of which takes the place of %d
     */
    public static function changesToTheCartOfAQuote(): iterable
    {
        yield 'a quantity' => ['UPDATE `cart_item` SET `quantity` = 1 WHERE `cart_id` = %d'];
        yield 'a price' => ['UPDATE `cart_item` SET `price` = 1 WHERE `cart_id` = %d'];
        yield 'the discount' => ['UPDATE `cart` SET `discount` = 0 WHERE `id` = %d'];
        yield 'a line removed' => ['DELETE FROM `cart_item` WHERE `cart_id` = %d ORDER BY `id` LIMIT 1'];
        yield 'a line that the quote does not hold' => ['UPDATE `cart_item` SET `quote_item_id` = NULL WHERE `cart_id` = %d ORDER BY `id` LIMIT 1'];
    }

    #[DataProvider('changesToTheCartOfAQuote')]
    public function testACartThatIsNoLongerWhatWasAcceptedIsNotOrdered(string $change): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $cart = $this->quotes->accept($quote, $scenario['buyer'], 2);
        $this->getPropelConnection()->exec(\sprintf($change, $cart->getId()));

        try {
            $this->pay($cart);
            self::fail('A cart that is not the accepted revision was ordered.');
        } catch (QuoteCartChangedException $refusal) {
            self::assertSame('quote_cart_changed', $refusal->reason());
        }

        self::assertSame(QuoteState::Accepted->value, $this->reread($quote)->getState());
        self::assertSame(0, OrderQuery::create()->filterByCartId($cart->getId())->count());
        self::assertSame([], $this->eventsOf(QuoteEvents::ORDERED));
    }

    public function testACartWhoseQuoteIsNotAcceptedIsNotOrdered(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $cart = $this->quotes->accept($quote, $scenario['buyer'], 2);
        $quote->setState(QuoteState::Quoted->value)->save($this->getPropelConnection());

        try {
            $this->pay($cart);
            self::fail('A cart of a quote that is not accepted was ordered.');
        } catch (QuoteTransitionRefusedException $refusal) {
            self::assertSame(QuoteState::Ordered, $refusal->to());
        }

        self::assertSame(0, OrderQuery::create()->filterByCartId($cart->getId())->count());
    }

    public function testAnOrdinaryCartIsOrderedAsItAlwaysWasAndNamesNoQuote(): void
    {
        $buyer = $this->buyerWithAnAddress();
        $cart = $this->cartOf($buyer, [[$this->product('10', 'Plain'), 1.0, '10']]);
        $cart
            ->setDeliveryModuleId($this->deliveryModuleId())
            ->setAddressDeliveryId($this->cartAddressOf($buyer))
            ->setAddressInvoiceId($this->cartAddressOf($buyer))
            ->setPostage('0')->setPostageTax('0')
            ->save($this->getPropelConnection());

        $order = $this->pay($cart, $buyer);

        self::assertNull($order->getQuoteId());
        self::assertSame([], $this->eventsOf(QuoteEvents::ORDERED));
    }

    public function testTheKeyOfTheDatabaseThatHoldsAQuoteToOneOrderIsAnsweredAsTheQuoteAnswersIt(): void
    {
        $scenario = $this->quotedScenario();
        $quoteId = (int) $scenario['quote']->getId();
        $first = $this->fixtures->order($scenario['buyer'])->setQuoteId($quoteId);
        $first->save($this->getPropelConnection());
        $second = $this->fixtures->order($scenario['buyer'])->setQuoteId($quoteId);
        OrderTableMap::clearInstancePool();

        try {
            $second->save($this->getPropelConnection());
            self::fail('The database let a quote give two orders.');
        } catch (\Throwable $failure) {
            $refusal = $this->getService(QuotePlacement::class)->refusalOf($failure);
        }

        self::assertInstanceOf(QuoteAlreadyOrderedException::class, $refusal);
        self::assertSame('quote_already_ordered', $refusal->reason());
        self::assertNotNull($refusal->getPrevious());
    }

    public function testACartThatAlreadyHasALiveOrderIsNotGivenBackToBePaidAgain(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $cart = $this->quotes->accept($quote, $scenario['buyer'], 2);
        $this->fixtures->order($scenario['buyer'])->setCartId($cart->getId())->save($this->getPropelConnection());

        $again = $this->quotes->accept($quote, $scenario['buyer'], 2);

        self::assertNotSame($cart->getId(), $again->getId());
    }

    public function testTheDatabaseHoldsWhenTheStateOfTheQuoteDoesNotSayItIsOrdered(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $cart = $this->quotes->accept($quote, $scenario['buyer'], 2);
        // An order that names the quote while the quote still stands accepted: what the lock and the state exist to prevent.
        $this->fixtures->order($scenario['buyer'])->setQuoteId($quote->getId())->save($this->getPropelConnection());
        self::assertSame(QuoteState::Accepted->value, $this->reread($quote)->getState());

        try {
            $this->pay($cart);
            self::fail('A quote gave two orders.');
        } catch (QuoteAlreadyOrderedException $refusal) {
            self::assertSame('quote_already_ordered', $refusal->reason());
            self::assertNotNull($refusal->getPrevious(), 'the refusal carries the error of the database');
        }

        self::assertSame(1, OrderQuery::create()->filterByQuoteId($quote->getId())->count());
    }

    private function cartAddressOf(Customer $buyer): int
    {
        return (int) $this->fixtures->cartAddress($buyer->getDefaultAddress())->getId();
    }
}
