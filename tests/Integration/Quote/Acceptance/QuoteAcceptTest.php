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
use Thelia\Domain\Quote\DTO\QuoteItemRequestInput;
use Thelia\Domain\Quote\Enum\QuoteState;
use Thelia\Domain\Quote\Event\QuoteAcceptedEvent;
use Thelia\Domain\Quote\Event\QuoteEvents;
use Thelia\Domain\Quote\Exception\QuoteActionRefusedException;
use Thelia\Domain\Quote\Exception\QuoteCurrencyUnavailableException;
use Thelia\Domain\Quote\Exception\QuoteLinesUnavailableException;
use Thelia\Domain\Quote\Exception\QuoteTransitionRefusedException;
use Thelia\Model\CartAddressQuery;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\ProductPriceQuery;

/**
 * The buyer accepts a revision of a quote: the quote becomes accepted, and the buyer gets the cart
 * they pay it with, which holds the lines of the revision at the prices of the quote.
 */
final class QuoteAcceptTest extends QuoteAcceptanceTestCase
{
    public function testAcceptingTheCurrentRevisionGivesTheCartOfTheQuote(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $buyer = $scenario['buyer'];

        $cart = $this->quotes->accept($quote, $buyer, 2);

        self::assertSame(QuoteState::Accepted->value, $this->reread($quote)->getState());
        self::assertSame($buyer->getId(), $cart->getCustomerId());
        self::assertSame($quote->getId(), $cart->getQuoteId());
        self::assertSame(2, $cart->getQuoteRevision(), 'the revision that was accepted');
        self::assertSame($quote->getCurrencyId(), $cart->getCurrencyId());
        self::assertSame(30.0, (float) $this->reloaded($cart)->getDiscount(), 'the discount of the revision, with taxes');
        self::assertSame($this->deliveryModuleId(), $cart->getDeliveryModuleId());
        self::assertNotNull($cart->getAddressDeliveryId());
        self::assertNotNull($cart->getAddressInvoiceId());
        self::assertNotNull($cart->getToken());
        self::assertSame(18.0, (float) $this->reloaded($cart)->getPostage(), 'the postage of the revision with its taxes: 15 and 3');
        self::assertSame(3.0, (float) $this->reloaded($cart)->getPostageTax());

        $lines = [...CartItemQuery::create()->filterByCartId($cart->getId())->orderById()->find()];
        $revisionLines = $this->linesOf($this->revisionOf($quote, 2));

        self::assertCount(3, $lines);

        foreach ($lines as $index => $line) {
            $quoted = $revisionLines[$index];
            self::assertSame($quoted->getId(), $line->getQuoteItemId(), 'the line says which line of the quote it is');
            self::assertSame($quoted->getProductSaleElementsId(), $line->getProductSaleElementsId());
            self::assertSame($quoted->getProductId(), $line->getProductId());
            self::assertSame($quoted->getQuantity(), $line->getQuantity());
            self::assertSame((string) $quoted->getPrice(), (string) $line->getPrice());
            self::assertSame((string) $quoted->getPrice(), (string) $line->getPromoPrice());
            self::assertSame(0, $line->getPromo());
            self::assertSame(0, $line->getIsOffered());
        }

        self::assertSame(['90.000000', '45.000000', '20.000000'], array_map(static fn ($line): string => (string) $line->getPrice(), $lines));
        self::assertSame([12.0, 4.0, 5.0], array_map(static fn ($line): float => (float) $line->getQuantity(), $lines));

        $heard = $this->eventsOf(QuoteEvents::ACCEPTED);
        self::assertCount(1, $heard);
        self::assertInstanceOf(QuoteAcceptedEvent::class, $heard[0]);
        self::assertSame(2, $heard[0]->getRevisionNumber());
        self::assertSame(self::TEST_TRANSACTION_LEVELS, $this->quoteEvents[QuoteEvents::ACCEPTED][0]['transactionLevel'], 'told once the transaction is committed');
    }

    public function testThePricesAreThoseOfTheQuoteWhateverTheCatalogSaysNow(): void
    {
        $scenario = $this->quotedScenario();
        ProductPriceQuery::create()->filterByProductSaleElementsId($this->saleElementId($scenario['a']))->update(['Price' => '500']);

        $cart = $this->quotes->accept($scenario['quote'], $scenario['buyer'], 2);

        $line = CartItemQuery::create()->filterByCartId($cart->getId())->filterByProductId($scenario['a']->getId())->findOne();
        self::assertSame('90.000000', (string) $line?->getPrice());
    }

    public function testACarrierTheShopSwitchedOffAfterTheQuoteWasSentIsNotPutOnTheCart(): void
    {
        $scenario = $this->quotedScenario();
        ModuleQuery::create()->filterById($this->deliveryModuleId())->update(['Activate' => 0]);

        $cart = $this->quotes->accept($scenario['quote'], $scenario['buyer'], 2);

        self::assertNull($this->reloaded($cart)->getDeliveryModuleId(), 'the checkout offers the carriers that are left');
        self::assertSame(QuoteState::Accepted->value, $this->reread($scenario['quote'])->getState());
    }

    public function testTheAddressesOfTheQuoteAreCopiedOnTheCartWhenTheBuyerMayStillUseThem(): void
    {
        $scenario = $this->quotedScenario();
        $address = $scenario['buyer']->getDefaultAddress();

        $cart = $this->quotes->accept($scenario['quote'], $scenario['buyer'], 2);

        $delivery = CartAddressQuery::create()->findPk($cart->getAddressDeliveryId());
        self::assertSame($address?->getAddress1(), $delivery?->getAddress1());
        self::assertSame($address?->getCity(), CartAddressQuery::create()->findPk($cart->getAddressInvoiceId())?->getCity());
    }

    public function testAnAddressThatIsGoneOrIsNoLongerTheBuyersIsLeftForTheCheckoutToAsk(): void
    {
        $scenario = $this->quotedScenario();
        $stranger = $this->fixtures->address($this->customer());
        $quote = $scenario['quote'];
        $quote->setDeliveryAddressId($stranger->getId())->setInvoiceAddressId(null)->save($this->getPropelConnection());

        $cart = $this->quotes->accept($quote, $scenario['buyer'], 2);

        self::assertNull($cart->getAddressDeliveryId(), 'the address of someone else is never copied');
        self::assertNull($cart->getAddressInvoiceId());
        self::assertSame(QuoteState::Accepted->value, $this->reread($quote)->getState(), 'no error for that');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function statesThatCannotBeAccepted(): iterable
    {
        foreach (['draft', 'requested', 'countered', 'rejected', 'expired', 'ordered'] as $state) {
            yield $state => [$state];
        }
    }

    #[DataProvider('statesThatCannotBeAccepted')]
    public function testAQuoteThatIsNotWaitingForTheBuyerCannotBeAccepted(string $state): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $quote->setState($state)->save($this->getPropelConnection());

        try {
            $this->quotes->accept($quote, $scenario['buyer'], 2);
            self::fail('A quote that is '.$state.' was accepted.');
        } catch (QuoteTransitionRefusedException $refusal) {
            self::assertSame('transition_refused', $refusal->reason());
            self::assertSame(QuoteState::Accepted, $refusal->to());
        }

        self::assertSame($state, $this->reread($quote)->getState());
        self::assertSame(0, CartQuery::create()->filterByQuoteId($quote->getId())->count());
        self::assertSame([], $this->eventsOf(QuoteEvents::ACCEPTED));
    }

    public function testACounterProposalDoesNotLetTheBuyerAcceptTheRevisionTheyCounteredUntilTheShopSendsAgain(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $line = $this->lineOf($this->revisionOf($quote, 2), $scenario['a']->getRef());
        $this->quotes->counterOffer($quote, $scenario['buyer'], 2, [new QuoteItemRequestInput((int) $line->getId(), null, '85')]);

        $this->expectException(QuoteTransitionRefusedException::class);

        $this->quotes->accept($quote, $scenario['buyer'], 2);
    }

    public function testTheBuyerNamesTheRevisionTheQuoteStandsAtAndNoOtherOne(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];

        foreach ([1, 3] as $number) {
            try {
                $this->quotes->accept($quote, $scenario['buyer'], $number);
                self::fail('Revision '.$number.' was accepted.');
            } catch (QuoteActionRefusedException $refusal) {
                self::assertSame('revision_stale', $refusal->reason());
            }
        }

        self::assertSame(QuoteState::Quoted->value, $this->reread($quote)->getState());
        self::assertSame(0, CartQuery::create()->filterByQuoteId($quote->getId())->count());
        self::assertSame([], $this->eventsOf(QuoteEvents::ACCEPTED));
    }

    public function testTheLastValidDayStillCountsTheDayAfterDoesNotAndAnExtensionBringsItBack(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $quote->setValidUntil('2026-12-16')->save($this->getPropelConnection());

        // Sent on the first of December, valid for fifteen days: the sixteenth is the last day.
        $this->clock->modify('2026-12-16 18:00:00');
        $cart = $this->quotes->accept($quote, $scenario['buyer'], 2);
        self::assertSame(QuoteState::Accepted->value, $this->reread($quote)->getState());
        self::assertNotNull($cart->getId());

        $second = $this->quotedScenario();
        $late = $second['quote'];
        $late->setValidUntil('2026-12-16')->save($this->getPropelConnection());
        $this->clock->modify('2026-12-17 09:00:00');

        try {
            $this->quotes->accept($late, $second['buyer'], 2);
            self::fail('A quote past its validity was accepted.');
        } catch (QuoteTransitionRefusedException $refusal) {
            self::assertSame(QuoteState::Expired, $refusal->from(), 'expired although the console has not written it');
        }

        self::assertSame(QuoteState::Quoted->value, $this->reread($late)->getState(), 'nothing was written');

        $this->quotes->extend($late, new \DateTimeImmutable('2026-12-24'));
        $this->quotes->accept($late, $second['buyer'], 2);

        self::assertSame(QuoteState::Accepted->value, $this->reread($late)->getState());
    }

    public function testAcceptingTwiceGivesTheSameCartAndTellsTheShopOnce(): void
    {
        $scenario = $this->quotedScenario();

        $first = $this->quotes->accept($scenario['quote'], $scenario['buyer'], 2);
        $second = $this->quotes->accept($scenario['quote'], $scenario['buyer'], 2);

        self::assertSame($first->getId(), $second->getId());
        self::assertSame(1, CartQuery::create()->filterByQuoteId($scenario['quote']->getId())->count());
        self::assertSame(3, CartItemQuery::create()->filterByCartId($first->getId())->count());
        self::assertCount(1, $this->eventsOf(QuoteEvents::ACCEPTED));
    }

    public function testAcceptingAgainAfterTheCartWasPurgedBuildsANewOneAndTellsNothing(): void
    {
        $scenario = $this->quotedScenario();
        $first = $this->quotes->accept($scenario['quote'], $scenario['buyer'], 2);
        $this->getPropelConnection()->exec('UPDATE `cart` SET `created_at` = \'2020-01-01 00:00:00\' WHERE `id` = '.(int) $first->getId());
        CartTableMap::clearInstancePool();

        $purged = $this->getService(\Thelia\Domain\Cart\Service\CartPurger::class)->purgeCartsWithoutOrder(30);
        CartTableMap::clearInstancePool();
        CartItemTableMap::clearInstancePool();

        self::assertGreaterThanOrEqual(1, $purged);
        self::assertNull(CartQuery::create()->findPk($first->getId()), 'the purge takes the cart of a quote that was never ordered');
        self::assertSame(QuoteState::Accepted->value, $this->reread($scenario['quote'])->getState(), 'the quote stays accepted');

        $again = $this->quotes->accept($scenario['quote'], $scenario['buyer'], 2);

        self::assertNotSame($first->getId(), $again->getId());
        self::assertSame(3, CartItemQuery::create()->filterByCartId($again->getId())->count());
        self::assertCount(1, $this->eventsOf(QuoteEvents::ACCEPTED), 'the buyer was told once, when the quote was accepted');
    }

    public function testACartThatNoLongerHoldsTheLinesOfTheRevisionIsNotGivenBack(): void
    {
        $scenario = $this->quotedScenario();
        $first = $this->quotes->accept($scenario['quote'], $scenario['buyer'], 2);
        CartItemQuery::create()->filterByCartId($first->getId())->orderById()->findOne()?->delete();
        CartItemTableMap::clearInstancePool();

        $again = $this->quotes->accept($scenario['quote'], $scenario['buyer'], 2);

        self::assertNotSame($first->getId(), $again->getId());
        self::assertSame(3, CartItemQuery::create()->filterByCartId($again->getId())->count());
    }

    public function testACartThatNoLongerHasTheDiscountOrTheQuantitiesOrThePricesOfTheRevisionIsNotGivenBack(): void
    {
        foreach ([
            'discount' => 'UPDATE `cart` SET `discount` = 0 WHERE `id` = %d',
            'quantity' => 'UPDATE `cart_item` SET `quantity` = 1 WHERE `cart_id` = %d',
            'price' => 'UPDATE `cart_item` SET `price` = 1 WHERE `cart_id` = %d',
        ] as $changed => $statement) {
            $scenario = $this->quotedScenario();
            $first = $this->quotes->accept($scenario['quote'], $scenario['buyer'], 2);
            $this->getPropelConnection()->exec(\sprintf($statement, $first->getId()));
            CartTableMap::clearInstancePool();
            CartItemTableMap::clearInstancePool();

            $again = $this->quotes->accept($scenario['quote'], $scenario['buyer'], 2);

            self::assertNotSame($first->getId(), $again->getId(), 'the '.$changed.' of the cart was changed');
        }
    }

    public function testALineWhoseProductWasDeletedRefusesTheAcceptanceAndNamesTheLine(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $this->getPropelConnection()->exec('DELETE FROM `product` WHERE `id` = '.(int) $scenario['b']->getId());

        try {
            $this->quotes->accept($quote, $scenario['buyer'], 2);
            self::fail('A quote with a line that cannot be sold was accepted.');
        } catch (QuoteLinesUnavailableException $refusal) {
            self::assertSame('quote_lines_unavailable', $refusal->reason());
            self::assertSame([['reference' => $scenario['b']->getRef(), 'title' => 'Product B']], $refusal->lines());
        }

        self::assertSame(QuoteState::Quoted->value, $this->reread($quote)->getState());
        self::assertSame(0, CartQuery::create()->filterByQuoteId($quote->getId())->count());
        self::assertSame([], $this->eventsOf(QuoteEvents::ACCEPTED));
        self::assertCount(3, $this->linesOf($this->revisionOf($quote, 2)), 'the quote stays readable');
        self::assertSame('Product B', $this->lineOf($this->revisionOf($quote, 2), $scenario['b']->getRef())->getTitle());
    }

    public function testEveryLineThatCannotBeSoldIsNamedNotTheFirstOne(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $lineOfA = $this->lineOf($this->revisionOf($quote, 2), $scenario['a']->getRef());
        $connection = $this->getPropelConnection();
        $connection->exec('UPDATE `quote_item` SET `product_sale_elements_id` = NULL WHERE `id` = '.(int) $lineOfA->getId());
        $connection->exec('DELETE FROM `product_sale_elements` WHERE `id` = '.(int) $this->saleElementId($scenario['c']));

        try {
            $this->quotes->accept($quote, $scenario['buyer'], 2);
            self::fail('A quote with lines that cannot be sold was accepted.');
        } catch (QuoteLinesUnavailableException $refusal) {
            self::assertEqualsCanonicalizing(
                [$scenario['a']->getRef(), $scenario['c']->getRef()],
                array_column($refusal->lines(), 'reference'),
            );
        }

        self::assertSame(QuoteState::Quoted->value, $this->reread($quote)->getState());
    }

    public function testAProductThatBecameABundleCannotBeOrderedAsALineOfAQuote(): void
    {
        $scenario = $this->quotedScenario();
        $this->getPropelConnection()->exec('UPDATE `product` SET `is_bundle` = 1 WHERE `id` = '.(int) $scenario['c']->getId());

        try {
            $this->quotes->accept($scenario['quote'], $scenario['buyer'], 2);
            self::fail('A bundle was accepted as a simple line.');
        } catch (QuoteLinesUnavailableException $refusal) {
            self::assertSame([$scenario['c']->getRef()], array_column($refusal->lines(), 'reference'));
        }
    }

    public function testAQuoteWrittenInACurrencyThatIsGoneCannotBeAccepted(): void
    {
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $gone = $this->fixtures->currency(['code' => 'XQG', 'symbol' => 'XG', 'rate' => 1.0]);
        $connection = $this->getPropelConnection();
        $connection->exec('SET FOREIGN_KEY_CHECKS = 0');
        $connection->exec('UPDATE `quote` SET `currency_id` = '.(int) $gone->getId().' WHERE `id` = '.(int) $quote->getId());
        $connection->exec('DELETE FROM `currency` WHERE `id` = '.(int) $gone->getId());
        $connection->exec('SET FOREIGN_KEY_CHECKS = 1');

        try {
            $this->quotes->accept($quote, $scenario['buyer'], 2);
            self::fail('A quote in a currency that is gone was accepted.');
        } catch (QuoteCurrencyUnavailableException $refusal) {
            self::assertSame('quote_currency_unavailable', $refusal->reason());
        }

        self::assertSame(QuoteState::Quoted->value, $this->reread($quote)->getState());
        self::assertSame(0, CartQuery::create()->filterByQuoteId($quote->getId())->count());
    }

    public function testAccountsThatDoNotShareTheQuoteNeverSeeItAccepted(): void
    {
        $first = $this->quotedScenario();
        $second = $this->quotedScenario();

        foreach ([[$first['quote'], $second['buyer']], [$second['quote'], $first['buyer']]] as [$quote, $stranger]) {
            try {
                $this->quotes->accept($quote, $stranger, 2);
                self::fail('A quote was accepted by an account that is not the buyer.');
            } catch (\Thelia\Domain\Quote\Exception\UnknownQuoteException $refusal) {
                self::assertSame('quote_not_found', $refusal->reason(), 'as if the quote did not exist');
            }

            self::assertSame(QuoteState::Quoted->value, $this->reread($quote)->getState());
            self::assertSame(0, CartQuery::create()->filterByQuoteId($quote->getId())->count());
        }

        self::assertSame([], $this->eventsOf(QuoteEvents::ACCEPTED));
    }

    public function testAnAccountThatIsNotTheBuyerOfTheQuoteCannotAcceptWithTheQuoteOfTheirCompanyEither(): void
    {
        $company = $this->company('S1');
        $other = $this->company('S2');
        $buyer = $this->buyerOf($company);
        $otherAdmin = $this->buyerOf($other, 'company_admin');
        $scenario = $this->quotedScenario($buyer);

        $this->expectException(\Thelia\Domain\Quote\Exception\UnknownQuoteException::class);

        $this->quotes->accept($scenario['quote'], $otherAdmin, 2);
    }

    public function testAColleagueWhoMayOrderAcceptsForTheCompanyAndHoldsTheCart(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company);
        $colleague = $this->buyerOf($company, 'company_admin');
        $scenario = $this->quotedScenario($buyer);

        $cart = $this->quotes->accept($scenario['quote'], $colleague, 2);

        self::assertSame($colleague->getId(), $cart->getCustomerId(), 'the cart is the one of whoever accepted, who places the order');
        self::assertSame(QuoteState::Accepted->value, $this->reread($scenario['quote'])->getState());
    }

    /**
     * @return iterable<string, array{list<string>, string|null}> the rights of the colleague, and the refusal they meet (null: they may accept)
     */
    public static function rolesOfAColleague(): iterable
    {
        yield 'reads, asks for quotes and places orders' => [['order.view_company', 'quote.request', 'order.place'], null];
        yield 'reads and asks for quotes, cannot place orders' => [['order.view_company', 'quote.request'], 'not_permitted'];
        yield 'reads and places orders, cannot answer quotes' => [['order.view_company', 'order.place'], 'not_permitted'];
        yield 'asks and places but does not read the quotes of the company' => [['quote.request', 'order.place'], 'quote_not_found'];
    }

    /**
     * @param list<string> $permissions
     */
    #[DataProvider('rolesOfAColleague')]
    public function testAColleagueNeedsToReadAnswerAndOrder(array $permissions, ?string $refusal): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company);
        $this->roleOf('quote_colleague', $company, $permissions);
        $colleague = $this->buyerOf($company, 'quote_colleague');
        $scenario = $this->quotedScenario($buyer);

        if (null === $refusal) {
            self::assertNotNull($this->quotes->accept($scenario['quote'], $colleague, 2)->getId());

            return;
        }

        try {
            $this->quotes->accept($scenario['quote'], $colleague, 2);
            self::fail('A colleague who lacks a right accepted the quote.');
        } catch (\Thelia\Domain\Quote\Exception\QuoteException $exception) {
            self::assertSame($refusal, $exception->reason());
        }

        self::assertSame(QuoteState::Quoted->value, $this->reread($scenario['quote'])->getState());
        self::assertSame(0, CartQuery::create()->filterByQuoteId($scenario['quote']->getId())->count());
    }

    public function testABuyerOfASuspendedCompanyAcceptsNothing(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company);
        $scenario = $this->quotedScenario($buyer);
        $this->facade->suspend($company);

        try {
            $this->quotes->accept($scenario['quote'], $buyer, 2);
            self::fail('A buyer of a suspended company accepted a quote.');
        } catch (QuoteActionRefusedException $refused) {
            self::assertSame(QuoteActionRefusedException::COMPANY_SUSPENDED, $refused->reason());
        }

        self::assertSame(QuoteState::Quoted->value, $this->reread($scenario['quote'])->getState());
        self::assertSame(0, CartQuery::create()->filterByQuoteId($scenario['quote']->getId())->count());
        self::assertSame([], $this->eventsOf(QuoteEvents::ACCEPTED));
    }

    public function testAnApproverWhoCannotAskForQuotesCannotAccept(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company);
        $approver = $this->buyerOf($company, 'approver');
        $scenario = $this->quotedScenario($buyer);

        $this->expectException(QuoteActionRefusedException::class);

        $this->quotes->accept($scenario['quote'], $approver, 2);
    }
}
