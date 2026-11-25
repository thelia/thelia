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

use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\Quote\Acceptance\QuoteSellability;
use Thelia\Domain\Quote\DTO\QuoteDraftInput;
use Thelia\Domain\Quote\DTO\QuoteDraftLine;
use Thelia\Domain\Quote\DTO\QuoteRequestInput;
use Thelia\Domain\Quote\Event\QuoteEvents;
use Thelia\Domain\Quote\QuoteFacade;
use Thelia\Domain\Quote\QuoteTransitions;
use Thelia\Domain\Quote\Service\QuoteAccess;
use Thelia\Domain\Quote\Service\QuoteCatalogPrices;
use Thelia\Domain\Quote\Service\QuoteLineWriter;
use Thelia\Domain\Quote\Service\QuoteNoteSanitizer;
use Thelia\Domain\Quote\Service\QuoteRefAllocator;
use Thelia\Domain\Quote\Service\QuoteRevisionFreezer;
use Thelia\Domain\Quote\Service\QuoteRevisionGuard;
use Thelia\Domain\Quote\Service\QuoteTotalsCalculator;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Customer;
use Thelia\Model\Map\QuoteTableMap;
use Thelia\Model\Product;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItem;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteQuery;
use Thelia\Model\QuoteRevision;
use Thelia\Model\QuoteRevisionQuery;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleCountry;
use Thelia\Tests\Integration\Company\CompanyDomainTestCase;

/**
 * What the tests of the quote domain share: the facade built the way the container builds it, but
 * with a clock the test moves, a shop with 20 % VAT, and the events the facade tells.
 */
abstract class QuoteDomainTestCase extends CompanyDomainTestCase
{
    protected QuoteFacade $quotes;

    protected QuoteAccess $quoteAccess;

    protected MockClock $clock;

    /** @var array<string, list<array{event: object, transactionLevel: int}>> the quote events heard, by name */
    protected array $quoteEvents = [];

    private ?TaxRule $vatRule = null;

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('quote_mode', 'alongside');
        ConfigQuery::write('quote_validity_days', '30');
        ConfigQuery::write('taxrule_id_delivery_module', (string) $this->vatRule()->getId());
        ConfigQuery::resetCache();

        $this->clock = new MockClock('2026-12-01 09:00:00');
        $this->quoteAccess = new QuoteAccess($this->checker, $this->reader);
        $dispatcher = $this->getService(EventDispatcherInterface::class);

        $this->quotes = new QuoteFacade(
            $dispatcher,
            $this->clock,
            new QuoteTransitions(),
            $this->quoteAccess,
            $this->getService(QuoteTotalsCalculator::class),
            $this->getService(QuoteRefAllocator::class),
            $this->getService(QuoteLineWriter::class),
            $this->getService(QuoteCatalogPrices::class),
            new QuoteNoteSanitizer(),
            new QuoteRevisionGuard(),
            $this->getService(QuoteRevisionFreezer::class),
            $this->getService(QuoteSellability::class),
        );

        $this->quoteEvents = [];
        $connection = $this->getPropelConnection();

        foreach ([
            QuoteEvents::REQUESTED,
            QuoteEvents::CREATED,
            QuoteEvents::DRAFT_SAVED,
            QuoteEvents::DUPLICATED,
            QuoteEvents::DELETED,
            QuoteEvents::SENT,
            QuoteEvents::COUNTERED,
            QuoteEvents::ACCEPTED,
            QuoteEvents::REJECTED,
            QuoteEvents::EXTENDED,
            QuoteEvents::REMINDED,
            QuoteEvents::EXPIRED,
            QuoteEvents::ORDERED,
        ] as $name) {
            $dispatcher->addListener($name, function (object $event) use ($name, $connection): void {
                $this->quoteEvents[$name][] = [
                    'event' => $event,
                    'transactionLevel' => $connection instanceof ConnectionWrapper ? $connection->getNestedTransactionCount() : 0,
                ];
            });
        }
    }

    /**
     * 20 % VAT in the default country of the shop, and nowhere else.
     */
    protected function vatRule(): TaxRule
    {
        if (null === $this->vatRule) {
            $this->vatRule = $this->fixtures->taxRule(['isDefault' => false]);
            $tax = $this->fixtures->tax(['requirements' => ['percent' => '20.000000'], 'title' => 'VAT 20']);
            (new TaxRuleCountry())
                ->setTaxRuleId($this->vatRule->getId())
                ->setCountryId(Country::getDefaultCountry()->getId())
                ->setTaxId($tax->getId())
                ->setPosition(1)
                ->save($this->getPropelConnection());
        }

        return $this->vatRule;
    }

    protected function product(string $price, string $title): Product
    {
        return $this->fixtures->product(
            $this->fixtures->category(),
            $this->vatRule(),
            $this->fixtures->currency(),
            ['basePrice' => (float) $price, 'baseQuantity' => 100, 'title' => $title],
        );
    }

    /**
     * @param list<array{Product, float, string}> $lines product, quantity, unit price without taxes
     */
    protected function cartOf(Customer $customer, array $lines): Cart
    {
        $cart = $this->fixtures->cart($customer);

        foreach ($lines as [$product, $quantity, $price]) {
            $this->fixtures->cartItem($cart, $product, null, ['quantity' => $quantity, 'price' => $price, 'promoPrice' => $price]);
        }

        return $cart;
    }

    protected function request(Customer $buyer, ?Cart $cart = null, ?QuoteRequestInput $input = null): Quote
    {
        $cart ??= $this->cartOf($buyer, [[$this->product('100', 'Product A'), 2.0, '100']]);

        return $this->quotes->requestFromCart($cart, $buyer, $input ?? new QuoteRequestInput());
    }

    /**
     * A request: a buyer with 10 of A at 100 and 4 of B at 50, 20 % VAT; C is the
     * product the shop adds, at 20.
     *
     * @return array{buyer: Customer, quote: Quote, a: Product, b: Product, c: Product}
     */
    protected function requestedScenario(): array
    {
        $buyer = $this->customer();
        $a = $this->product('100', 'Product A');
        $b = $this->product('50', 'Product B');
        $c = $this->product('20', 'Product C');
        $quote = $this->quotes->requestFromCart($this->cartOf($buyer, [[$a, 10.0, '100'], [$b, 4.0, '50']]), $buyer, new QuoteRequestInput());

        return ['buyer' => $buyer, 'quote' => $quote, 'a' => $a, 'b' => $b, 'c' => $c];
    }

    /**
     * A sending on top of {@see requestedScenario()}: 12 of A at 90, 4 of B at 45 and
     * 5 of C at 20, 30 off with taxes, 15 of postage without.
     *
     * @param array{buyer: Customer, quote: Quote, a: Product, b: Product, c: Product} $scenario
     */
    protected function draftOfTheShop(array $scenario): QuoteDraftInput
    {
        return $this->draftFrom(
            $this->revisionOf($scenario['quote'], 1),
            [
                $scenario['a']->getRef() => ['quantity' => 12.0, 'price' => '90'],
                $scenario['b']->getRef() => ['price' => '45'],
            ],
            [new QuoteDraftLine(null, $this->saleElementId($scenario['c']), 5.0, '20')],
            discount: '30',
            postage: '15',
        );
    }

    protected function saleElementId(Product $product): int
    {
        return (int) $product->getProductSaleElementss()->getFirst()->getId();
    }

    /**
     * A draft that repeats the lines of a revision with the changes given, the way a screen
     * posts what it holds.
     *
     * @param array<string, array{quantity?: float, price?: string}> $changes by product reference
     * @param list<QuoteDraftLine>                                   $added
     */
    protected function draftFrom(QuoteRevision $revision, array $changes = [], array $added = [], string $discount = '0', string $postage = '0', ?int $deliveryModuleId = null, ?string $merchantNote = null, ?\DateTimeImmutable $validUntil = null): QuoteDraftInput
    {
        $lines = [];

        foreach ($this->linesOf($revision) as $line) {
            $change = $changes[$line->getProductRef()] ?? [];
            $lines[] = new QuoteDraftLine(
                (int) $line->getId(),
                (int) $line->getProductSaleElementsId(),
                $change['quantity'] ?? (float) $line->getQuantity(),
                $change['price'] ?? (string) $line->getPrice(),
            );
        }

        return new QuoteDraftInput([...$lines, ...$added], $discount, $postage, $deliveryModuleId, $merchantNote, $validUntil);
    }

    /**
     * @return list<QuoteItem>
     */
    protected function linesOf(QuoteRevision $revision): array
    {
        return [...QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->orderByPosition()->find()];
    }

    protected function revisionOf(Quote $quote, int $number): QuoteRevision
    {
        return QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->filterByNumber($number)->findOne()
            ?? throw new \LogicException('No revision '.$number);
    }

    protected function lineOf(QuoteRevision $revision, string $productRef): QuoteItem
    {
        foreach ($this->linesOf($revision) as $line) {
            if ($line->getProductRef() === $productRef) {
                return $line;
            }
        }

        throw new \LogicException('No line '.$productRef);
    }

    /**
     * Reads through the pool of instances the way the shop does, from a pool that holds nothing the
     * tests before this one left in it: the pool is switched off while a test runs and switched on
     * again after it, with whatever it was filled with.
     */
    protected function readThroughTheInstancePool(): void
    {
        foreach (Propel::getServiceContainer()->getDatabaseMap(QuoteTableMap::DATABASE_NAME)->getTables() as $table) {
            $table::clearInstancePool();
        }

        Propel::enableInstancePooling();
    }

    protected function reread(Quote $quote): Quote
    {
        QuoteTableMap::clearInstancePool();

        return QuoteQuery::create()->findPk($quote->getId()) ?? throw new \LogicException('The quote is gone.');
    }

    /**
     * @return list<object>
     */
    protected function eventsOf(string $name): array
    {
        return array_map(static fn (array $heard): object => $heard['event'], $this->quoteEvents[$name] ?? []);
    }
}
