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

namespace Thelia\Tests\Integration\Quote;

use Psr\Log\AbstractLogger;
use Thelia\Domain\Quote\DTO\QuoteRequestInput;
use Thelia\Domain\Quote\Enum\QuoteRevisionAuthor;
use Thelia\Domain\Quote\Enum\QuoteRevisionState;
use Thelia\Domain\Quote\Enum\QuoteState;
use Thelia\Domain\Quote\Event\QuoteEvents;
use Thelia\Domain\Quote\Event\QuoteRequestedEvent;
use Thelia\Domain\Quote\Exception\QuoteRequestRefusedException;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteQuery;

/**
 * A buyer turns the cart into a request: the first revision of a quote, in front of the prices the
 * buyer was shown, with the cart left exactly as it was.
 */
final class QuoteRequestTest extends QuoteDomainTestCase
{
    public function testTheCartBecomesTheFirstRevisionOfARequestedQuote(): void
    {
        $buyer = $this->customer();
        $productA = $this->product('100', 'Product A');
        $productB = $this->product('50', 'Product B');
        $cart = $this->cartOf($buyer, [[$productA, 10.0, '100'], [$productB, 4.0, '50']]);

        $quote = $this->quotes->requestFromCart($cart, $buyer, new QuoteRequestInput(comment: 'For the new warehouse'));

        $stored = $this->reread($quote);
        self::assertSame(QuoteState::Requested->value, $stored->getState());
        self::assertSame(1, $stored->getCurrentRevision());
        self::assertSame($buyer->getId(), $stored->getCustomerId());
        self::assertNull($stored->getCompanyId());
        self::assertSame(0, $stored->getCouponDropped());
        self::assertSame('For the new warehouse', $stored->getBuyerComment());
        self::assertMatchesRegularExpression('/^QUO\d{8}$/', (string) $stored->getRef());

        $revision = $this->revisionOf($stored, 1);
        self::assertSame(QuoteRevisionAuthor::Customer->value, $revision->getAuthor());
        self::assertSame(QuoteRevisionState::Sent->value, $revision->getState());
        self::assertNotNull($revision->getSentAt());
        self::assertSame(1, QuoteItemQuery::create()->useQuoteRevisionQuery()->filterByQuoteId($stored->getId())->endUse()->groupByQuoteRevisionId()->count());

        $lines = $this->linesOf($revision);
        self::assertCount(2, $lines);
        self::assertSame([1, 2], array_map(static fn ($line): ?int => $line->getPosition(), $lines));
        self::assertSame([$productA->getRef(), $productB->getRef()], array_map(static fn ($line): ?string => $line->getProductRef(), $lines));
        self::assertSame(['Product A', 'Product B'], array_map(static fn ($line): ?string => $line->getTitle(), $lines));
        self::assertSame([10.0, 4.0], array_map(static fn ($line): ?float => $line->getQuantity(), $lines));
        self::assertSame(['100.000000', '50.000000'], array_map(static fn ($line): ?string => $line->getCatalogPrice(), $lines));
        self::assertSame(['100.000000', '50.000000'], array_map(static fn ($line): ?string => $line->getPrice(), $lines));
        self::assertSame($this->vatRule()->getId(), $lines[0]->getTaxRuleId());

        // 1 000 + 200 without taxes, 20 % VAT: the request is frozen with what it comes to.
        self::assertSame('1200.000000', $revision->getTotalUntaxed());
        self::assertSame('1440.000000', $revision->getTotalTaxed());
    }

    public function testTheCartIsLeftAsItWas(): void
    {
        $buyer = $this->customer();
        $cart = $this->cartOf($buyer, [[$this->product('100', 'Product A'), 3.0, '100']]);
        $before = $this->cartState($cart->getId());

        $this->quotes->requestFromCart($cart, $buyer, new QuoteRequestInput());

        self::assertSame($before, $this->cartState($cart->getId()));
    }

    public function testTheShopIsToldOnceTheRequestIsWritten(): void
    {
        $buyer = $this->customer();

        $quote = $this->request($buyer);

        self::assertCount(1, $this->eventsOf(QuoteEvents::REQUESTED));
        $event = $this->eventsOf(QuoteEvents::REQUESTED)[0];
        self::assertInstanceOf(QuoteRequestedEvent::class, $event);
        self::assertSame($quote->getId(), $event->getQuote()->getId());
        self::assertSame(1, $event->getRevisionNumber());
        self::assertSame(self::TEST_TRANSACTION_LEVELS, $this->quoteEvents[QuoteEvents::REQUESTED][0]['transactionLevel'], 'the transaction of the facade was committed before the event');
    }

    public function testTwoHundredAndFiftyLinesAreWrittenInOneInsertPerTwoHundredLines(): void
    {
        $buyer = $this->customer();
        $product = $this->product('10', 'Many');
        $cart = $this->cartOf($buyer, []);

        for ($line = 0; $line < 250; ++$line) {
            $this->fixtures->cartItem($cart, $product, null, ['quantity' => 1.0 + $line, 'price' => '10', 'promoPrice' => '10']);
        }

        $statements = [];
        $this->listenToStatements($statements);
        $quote = $this->quotes->requestFromCart($cart, $buyer, new QuoteRequestInput());
        $this->stopListeningToStatements();

        $inserts = array_filter($statements, static fn (string $sql): bool => str_starts_with($sql, 'INSERT INTO `quote_item`'));
        self::assertCount(2, $inserts, '250 lines, 200 per insert');

        $lines = $this->linesOf($this->revisionOf($quote, 1));
        self::assertCount(250, $lines);
        self::assertSame(range(1, 250), array_map(static fn ($line): ?int => $line->getPosition(), $lines));
        self::assertSame(250.0, $lines[249]->getQuantity(), 'the lines keep the order of the cart');
    }

    public function testTheCouponOfTheCartIsDroppedAndTheBuyerIsToldSo(): void
    {
        $buyer = $this->customer();
        $product = $this->product('100', 'Product A');
        $cart = $this->cartOf($buyer, [[$product, 1.0, '100']]);
        $coupon = $this->fixtures->coupon();
        $this->fixtures->cartItem($cart, $this->product('1', 'Gift'), null, ['quantity' => 1.0, 'price' => '0', 'promoPrice' => '0', 'isOffered' => 1, 'offeredByCouponId' => $coupon->getId()]);
        $cart->setDiscount('5.000000')->save($this->getPropelConnection());

        $quote = $this->quotes->requestFromCart($cart, $buyer, new QuoteRequestInput());

        self::assertSame(1, $this->reread($quote)->getCouponDropped());
        $lines = $this->linesOf($this->revisionOf($quote, 1));
        self::assertCount(1, $lines, 'the line the coupon offered does not come along');
        self::assertSame($product->getRef(), $lines[0]->getProductRef());
    }

    public function testACartDiscountAloneIsACouponThatWasDropped(): void
    {
        $buyer = $this->customer();
        $cart = $this->cartOf($buyer, [[$this->product('100', 'Product A'), 1.0, '100']]);
        $cart->setDiscount('5.000000')->save($this->getPropelConnection());

        $quote = $this->quotes->requestFromCart($cart, $buyer, new QuoteRequestInput());

        self::assertSame(1, $this->reread($quote)->getCouponDropped());
        self::assertSame('100.000000', $this->linesOf($this->revisionOf($quote, 1))[0]->getPrice(), 'the discount is not spread over the lines');
    }

    public function testAQuoteIsRefusedWhenTheShopDoesNotTakeThem(): void
    {
        ConfigQuery::write('quote_mode', 'disabled');
        $buyer = $this->customer();

        $this->assertRefused(QuoteRequestRefusedException::QUOTE_DISABLED, fn () => $this->request($buyer));
        self::assertSame([], $this->eventsOf(QuoteEvents::REQUESTED));
    }

    public function testAnUnreadableModeIsNoQuoteEither(): void
    {
        ConfigQuery::write('quote_mode', 'whenever');

        $this->assertRefused(QuoteRequestRefusedException::QUOTE_DISABLED, fn () => $this->request($this->customer()));
    }

    public function testAnEmptyCartIsRefused(): void
    {
        $buyer = $this->customer();

        $this->assertRefused(QuoteRequestRefusedException::CART_EMPTY, fn () => $this->request($buyer, $this->cartOf($buyer, [])));
    }

    public function testACartOfAnotherAccountIsRefused(): void
    {
        $owner = $this->customer();
        $other = $this->customer();
        $cart = $this->cartOf($owner, [[$this->product('100', 'Product A'), 1.0, '100']]);

        $this->assertRefused(QuoteRequestRefusedException::CART_NOT_OWNED, fn () => $this->request($other, $cart));
    }

    public function testAGuestCannotAskForAQuote(): void
    {
        $guest = $this->guest();
        $cart = $this->cartOf($guest, [[$this->product('100', 'Product A'), 1.0, '100']]);

        $this->assertRefused(QuoteRequestRefusedException::NOT_PERMITTED, fn () => $this->request($guest, $cart));
    }

    public function testALineThatCannotBeQuotedRefusesTheWholeRequestAndNamesTheLine(): void
    {
        $buyer = $this->customer();
        $cart = $this->cartOf($buyer, [[$this->product('100', 'Product A'), 1.0, '100']]);
        $bundle = $this->fixtures->cartItem($cart, $this->product('10', 'Bundle'), null, ['quantity' => 1.0, 'price' => '10', 'promoPrice' => '10']);
        // A model refuses a composition on a product that has none: the row is written as a module or an import would.
        CartItemQuery::create()->filterById($bundle->getId())->update(['BundleComposition' => '[{"bundleItemId":1,"quantity":"1"}]']);

        try {
            $this->request($buyer, $cart);
            self::fail('The request was not refused.');
        } catch (QuoteRequestRefusedException $refused) {
            self::assertSame(QuoteRequestRefusedException::LINE_INVALID, $refused->reason());
            self::assertSame([(int) $bundle->getId()], $refused->cartItemIds());
        }

        self::assertSame(0, QuoteQuery::create()->filterByCustomerId($buyer->getId())->count());
    }

    public function testTheBuyerOfACompanyWhoMayAskForQuotesAsksForTheCompany(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company);

        $quote = $this->request($buyer);

        self::assertSame($company->getId(), $this->reread($quote)->getCompanyId());
    }

    public function testARoleWithoutTheRightToAskForQuotesIsRefused(): void
    {
        $company = $this->company();
        $approver = $this->buyerOf($company, 'approver');

        $this->assertRefused(QuoteRequestRefusedException::NOT_PERMITTED, fn () => $this->request($approver));
    }

    public function testABuyerOfASuspendedCompanyAsksForNoQuote(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company);
        $cart = $this->cartOf($buyer, [[$this->product('100', 'Product A'), 2.0, '100']]);
        $this->facade->suspend($company);

        $this->assertRefused(QuoteRequestRefusedException::COMPANY_SUSPENDED, fn () => $this->request($buyer, $cart));

        self::assertSame(0, QuoteQuery::create()->filterByCustomerId($buyer->getId())->count());
        self::assertSame([], $this->eventsOf(QuoteEvents::REQUESTED));
    }

    public function testAnAccountWithNoCompanyAsksForItself(): void
    {
        $quote = $this->request($this->customer());

        self::assertNull($this->reread($quote)->getCompanyId());
    }

    public function testTheAddressesAreTheOnesTheBuyerMayUse(): void
    {
        $buyer = $this->customer();
        $other = $this->customer();
        $own = $this->addressOf($buyer);
        $foreign = $this->addressOf($other);

        $quote = $this->request($buyer, null, new QuoteRequestInput(invoiceAddressId: $own->getId(), deliveryAddressId: $own->getId()));
        self::assertSame($own->getId(), $this->reread($quote)->getInvoiceAddressId());
        self::assertSame($own->getId(), $this->reread($quote)->getDeliveryAddressId());

        $this->assertRefused(QuoteRequestRefusedException::ADDRESS_REFUSED, fn () => $this->request($buyer, null, new QuoteRequestInput(deliveryAddressId: $foreign->getId())));
    }

    public function testAnAddressSharedWithTheCompanyIsUsable(): void
    {
        $company = $this->company();
        $creator = $this->buyerOf($company);
        $colleague = $this->buyerOf($company);
        $shared = $this->addressOf($creator, $company);

        $quote = $this->request($colleague, null, new QuoteRequestInput(deliveryAddressId: $shared->getId()));

        self::assertSame($shared->getId(), $this->reread($quote)->getDeliveryAddressId());
    }

    public function testTheDayTheGoodsAreNeededByCannotBePastAndTheCommentIsPlainText(): void
    {
        $buyer = $this->customer();

        $this->assertRefused(QuoteRequestRefusedException::NEEDED_BY_PAST, fn () => $this->request($buyer, null, new QuoteRequestInput(neededBy: new \DateTimeImmutable('2026-11-30'))));

        $quote = $this->request($buyer, null, new QuoteRequestInput(comment: '<b>Soon</b><script>alert(1)</script>', neededBy: new \DateTimeImmutable('2026-12-01')));

        $stored = $this->reread($quote);
        self::assertSame('Soonalert(1)', $stored->getBuyerComment());
        self::assertSame('2026-12-01', $stored->getNeededBy('Y-m-d'));
    }

    public function testTwoRequestsGetTwoConsecutiveReferences(): void
    {
        $buyer = $this->customer();

        $first = $this->request($buyer);
        $second = $this->request($buyer);

        self::assertSame(
            (int) substr((string) $first->getRef(), 3) + 1,
            (int) substr((string) $second->getRef(), 3),
        );
    }

    /**
     * @param callable(): mixed $action
     */
    private function assertRefused(string $reason, callable $action): void
    {
        try {
            $action();
            self::fail('The request was not refused.');
        } catch (QuoteRequestRefusedException $refused) {
            self::assertSame($reason, $refused->reason());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function cartState(int $cartId): array
    {
        CartTableMap::clearInstancePool();
        $cart = CartQuery::create()->findPk($cartId);
        self::assertNotNull($cart);

        return [
            'cart' => $cart->toArray(),
            'lines' => array_map(static fn ($line): array => $line->toArray(), [...CartItemQuery::create()->filterByCartId($cartId)->orderById()->find()]),
        ];
    }

    /**
     * @param array<int, string> $statements filled with every statement the connection runs
     */
    private function listenToStatements(array &$statements): void
    {
        $connection = $this->getPropelConnection();
        $connection->useDebug(true);
        $connection->setLogger(new class($statements) extends AbstractLogger {
            /** @param array<int, string> $statements */
            public function __construct(private array &$statements)
            {
            }

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->statements[] = (string) $message;
            }
        });
    }

    private function stopListeningToStatements(): void
    {
        $this->getPropelConnection()->useDebug(false);
    }
}
