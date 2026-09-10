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

namespace Thelia\Tests\Integration\Domain\Sale;

use Thelia\Core\Event\Sale\SaleCreateEvent;
use Thelia\Core\Event\Sale\SaleUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Sale\ReservedPrice;
use Thelia\Domain\Sale\ReservedSalePriceResolver;
use Thelia\Model\AttributeCombination;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\Sale;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The price a named customer gets from a reserved operation.
 *
 * Nothing about it is written to the catalog, so it has to be computed on the
 * way out — for a whole batch of sale elements at once, and to the same cent
 * the public path would have written.
 */
final class ReservedSalePriceResolverTest extends ActionIntegrationTestCase
{
    use RecordsSqlQueries;

    private ReservedSalePriceResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = $this->getService(ReservedSalePriceResolver::class);
    }

    /**
     * The parity criterion: the same operation, run publicly, writes exactly the
     * number the resolver hands to the customer it is reserved for.
     */
    public function testTheReservedPriceMatchesWhatThePublicPathWouldHaveWritten(): void
    {
        $currency = $this->factory->currency();
        $publicProduct = $this->catalogProduct($currency);
        $reservedProduct = $this->catalogProduct($currency);
        $customer = $this->newCustomer();

        $publicPromoPrice = $this->publicPromoPriceWrittenFor($publicProduct, $currency, '10');

        $sale = $this->runningReservedSale($currency, '10');
        $this->factory->saleProduct($sale, $reservedProduct);
        $this->factory->saleCustomer($sale, $customer);

        $pse = $this->defaultPseFor($reservedProduct);
        $resolved = $this->resolver->resolve([$pse->getId()], $currency, $customer);

        self::assertArrayHasKey($pse->getId(), $resolved);
        self::assertSame($publicPromoPrice, $resolved[$pse->getId()]->untaxedPromoPrice);
    }

    public function testAnAmountOffsetIsResolvedToo(): void
    {
        $currency = $this->factory->currency();
        $publicProduct = $this->catalogProduct($currency);
        $reservedProduct = $this->catalogProduct($currency);
        $customer = $this->newCustomer();

        $publicPromoPrice = $this->publicPromoPriceWrittenFor(
            $publicProduct,
            $currency,
            '25',
            Sale::OFFSET_TYPE_AMOUNT,
        );

        $sale = $this->runningReservedSale($currency, '25', Sale::OFFSET_TYPE_AMOUNT);
        $this->factory->saleProduct($sale, $reservedProduct);
        $this->factory->saleCustomer($sale, $customer);

        $pse = $this->defaultPseFor($reservedProduct);
        $resolved = $this->resolver->resolve([$pse->getId()], $currency, $customer);

        // `product_price.promo_price` is a DECIMAL(16,6), so the public reference is
        // the resolved price rounded to the sixth decimal — parity to the cent.
        self::assertEqualsWithDelta(
            $publicPromoPrice,
            $resolved[$pse->getId()]->untaxedPromoPrice,
            0.000001,
        );
    }

    public function testTheResolvedPriceCarriesTheOperationItComesFrom(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);
        $customer = $this->newCustomer();
        // Seconds only: MySQL rounds a fractional second up when it writes a
        // DATETIME column and MariaDB truncates it, so a date carrying microseconds
        // reads back one second later on one engine and unchanged on the other.
        $endDate = new \DateTime((new \DateTime('+3 days'))->format('Y-m-d H:i:s'));

        $sale = $this->runningReservedSale($currency, '10', Sale::OFFSET_TYPE_PERCENTAGE, ['endDate' => $endDate]);
        $this->factory->saleProduct($sale, $product);
        $this->factory->saleCustomer($sale, $customer);

        $pse = $this->defaultPseFor($product);
        $reserved = $this->resolver->resolve([$pse->getId()], $currency, $customer)[$pse->getId()];

        self::assertInstanceOf(ReservedPrice::class, $reserved);
        self::assertSame($pse->getId(), $reserved->productSaleElementsId);
        self::assertSame($sale->getId(), $reserved->saleId);
        self::assertSame($endDate->format('Y-m-d H:i:s'), $reserved->saleEndDate->format('Y-m-d H:i:s'));
        self::assertTrue($reserved->displayInitialPrice);
    }

    public function testAVisitorGetsNoReservedPrice(): void
    {
        [$currency, $pse] = $this->reservedOperationOnOneProduct($this->newCustomer());

        self::assertSame([], $this->resolver->resolve([$pse->getId()], $currency, null));
    }

    public function testACustomerTheOperationDoesNotNameGetsNoReservedPrice(): void
    {
        [$currency, $pse] = $this->reservedOperationOnOneProduct($this->newCustomer());

        self::assertSame([], $this->resolver->resolve([$pse->getId()], $currency, $this->newCustomer()));
    }

    /**
     * The active flag is only as fresh as the last run of the scheduled command,
     * so the dates are what decides — evaluated in SQL, at the instant asked.
     */
    public function testAnOperationWhoseEndDateHasPassedGivesNoPriceEvenWhileStillFlaggedActive(): void
    {
        $customer = $this->newCustomer();
        [$currency, $pse, $sale] = $this->reservedOperationOnOneProduct($customer);

        $sale->setEndDate(new \DateTime('-1 minute'))->save();

        self::assertSame(1, (int) $sale->getActive(), 'the flag is deliberately left on');
        self::assertSame([], $this->resolver->resolve([$pse->getId()], $currency, $customer));
    }

    public function testAnOperationThatHasNotStartedYetGivesNoPrice(): void
    {
        $customer = $this->newCustomer();
        [$currency, $pse, $sale] = $this->reservedOperationOnOneProduct($customer);

        $sale->setStartDate(new \DateTime('+1 hour'))->save();

        self::assertSame([], $this->resolver->resolve([$pse->getId()], $currency, $customer));
    }

    /**
     * Two reserved operations on the same sale element: the customer gets the
     * better of the two, not the one that happens to be read last.
     */
    public function testTheLowestOfTwoReservedOperationsWins(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);
        $customer = $this->newCustomer();

        $mild = $this->runningReservedSale($currency, '10');
        $this->factory->saleProduct($mild, $product);
        $this->factory->saleCustomer($mild, $customer);

        $generous = $this->runningReservedSale($currency, '40');
        $this->factory->saleProduct($generous, $product);
        $this->factory->saleCustomer($generous, $customer);

        $pse = $this->defaultPseFor($product);
        $reserved = $this->resolver->resolve([$pse->getId()], $currency, $customer)[$pse->getId()];

        self::assertSame(60.0, $reserved->untaxedPromoPrice);
        self::assertSame($generous->getId(), $reserved->saleId);
    }

    /**
     * The customer always sees the best price available: a reserved operation that
     * is dearer than the public special offer already running is not applied at all.
     */
    public function testAReservedPriceAboveTheCurrentEffectivePriceIsNotApplied(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);
        $customer = $this->newCustomer();

        // The public operation takes 50% off, the reserved one only 10%.
        $this->publicPromoPriceWrittenFor($product, $currency, '50');

        $sale = $this->runningReservedSale($currency, '10');
        $this->factory->saleProduct($sale, $product);
        $this->factory->saleCustomer($sale, $customer);

        $pse = $this->defaultPseFor($product);

        self::assertSame(1, (int) $pse->getPromo(), 'the public offer is running');
        self::assertSame([], $this->resolver->resolve([$pse->getId()], $currency, $customer));
    }

    public function testAReservedPriceBelowTheCurrentPublicOfferIsApplied(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);
        $customer = $this->newCustomer();

        $this->publicPromoPriceWrittenFor($product, $currency, '10');

        $sale = $this->runningReservedSale($currency, '60');
        $this->factory->saleProduct($sale, $product);
        $this->factory->saleCustomer($sale, $customer);

        $pse = $this->defaultPseFor($product);
        $resolved = $this->resolver->resolve([$pse->getId()], $currency, $customer);

        self::assertSame(40.0, $resolved[$pse->getId()]->untaxedPromoPrice);
    }

    /**
     * An operation selecting one attribute value discounts only the sale elements
     * carrying it — the same selection Action\Sale makes for a public operation.
     */
    public function testOnlyTheSaleElementsCarryingTheSelectedAttributeValueAreResolved(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);
        $customer = $this->newCustomer();

        $attribute = $this->factory->attribute();
        $selected = $this->factory->attributeAv($attribute);
        $notSelected = $this->factory->attributeAv($attribute);

        $selectedPse = $this->extraPseWithPrice($product, $currency, $attribute->getId(), $selected->getId());
        $otherPse = $this->extraPseWithPrice($product, $currency, $attribute->getId(), $notSelected->getId());

        $sale = $this->runningReservedSale($currency, '10');
        $this->factory->saleProduct($sale, $product, $selected);
        $this->factory->saleCustomer($sale, $customer);

        $resolved = $this->resolver->resolve(
            [$selectedPse->getId(), $otherPse->getId(), $this->defaultPseFor($product)->getId()],
            $currency,
            $customer,
        );

        self::assertSame([$selectedPse->getId()], array_keys($resolved));
    }

    /**
     * A price the currency has no row of its own for is converted from the default
     * currency, exactly as ProductSaleElements::getPricesByCurrency() does.
     */
    public function testAPriceIsConvertedFromTheDefaultCurrency(): void
    {
        $defaultCurrency = $this->factory->currency();
        $otherCurrency = $this->factory->currency(['code' => 'XTS', 'rate' => 2.0, 'symbol' => 'X']);
        $product = $this->catalogProduct($defaultCurrency);
        $customer = $this->newCustomer();

        $sale = $this->runningReservedSale($otherCurrency, '10');
        $this->factory->saleProduct($sale, $product);
        $this->factory->saleCustomer($sale, $customer);

        $pse = $this->defaultPseFor($product);
        $resolved = $this->resolver->resolve([$pse->getId()], $otherCurrency, $customer);

        // 100 in the default currency at a rate of 2 is 200, minus 10% is 180.
        self::assertSame(180.0, $resolved[$pse->getId()]->untaxedPromoPrice);
    }

    /**
     * The whole point of the batch entry point: a catalog page with fifty sale
     * elements must not cost fifty queries.
     */
    public function testResolvingAWholeBatchCostsNoQueryPerSaleElement(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);
        $customer = $this->newCustomer();

        $sale = $this->runningReservedSale($currency, '10');
        $this->factory->saleProduct($sale, $product);
        $this->factory->saleCustomer($sale, $customer);

        $onePse = [$this->defaultPseFor($product)->getId()];
        $manyPses = $onePse;
        for ($i = 0; $i < 6; ++$i) {
            $manyPses[] = $this->extraPseWithPrice($product, $currency)->getId();
        }

        $forOne = $this->recordSqlQueries(function () use ($onePse, $currency, $customer): void {
            self::assertCount(1, $this->resolver->resolve($onePse, $currency, $customer));
        });

        $forMany = $this->recordSqlQueries(function () use ($manyPses, $currency, $customer): void {
            self::assertCount(7, $this->resolver->resolve($manyPses, $currency, $customer));
        });

        self::assertSame(
            \count($forOne),
            \count($forMany),
            \sprintf(
                "resolving 7 sale elements ran %d statements against %d for a single one:\n%s",
                \count($forMany),
                \count($forOne),
                implode("\n", $forMany),
            ),
        );
    }

    public function testAnEmptyBatchAsksTheDatabaseNothing(): void
    {
        $currency = $this->factory->currency();
        $customer = $this->newCustomer();

        $statements = $this->recordSqlQueries(function () use ($currency, $customer): void {
            self::assertSame([], $this->resolver->resolve([], $currency, $customer));
        });

        self::assertSame([], $statements);
    }

    /**
     * @return array{0: Currency, 1: ProductSaleElements, 2: Sale}
     */
    private function reservedOperationOnOneProduct(Customer $customer): array
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);

        $sale = $this->runningReservedSale($currency, '10');
        $this->factory->saleProduct($sale, $product);
        $this->factory->saleCustomer($sale, $customer);

        return [$currency, $this->defaultPseFor($product), $sale];
    }

    private function runningReservedSale(
        Currency $currency,
        string $offset,
        int $offsetType = Sale::OFFSET_TYPE_PERCENTAGE,
        array $overrides = [],
    ): Sale {
        $sale = $this->factory->sale($overrides + [
            'active' => true,
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'priceOffsetType' => $offsetType,
            'startDate' => new \DateTime('-1 day'),
            'endDate' => new \DateTime('+1 day'),
        ]);

        $this->factory->saleOffsetCurrency($sale, $currency, (float) $offset);

        return $sale;
    }

    /**
     * Runs a public operation through Action\Sale and returns the promo price it
     * wrote in the catalog — the reference the reserved path has to match.
     */
    private function publicPromoPriceWrittenFor(
        Product $product,
        Currency $currency,
        string $offset,
        int $offsetType = Sale::OFFSET_TYPE_PERCENTAGE,
    ): float {
        $sale = $this->dispatch(
            (new SaleCreateEvent())->setLocale('en_US')->setTitle('Public')->setSaleLabel('PUB'),
            TheliaEvents::SALE_CREATE,
        )->getSale();

        $event = new SaleUpdateEvent($sale->getId());
        $event
            ->setLocale('en_US')
            ->setTitle('Public')
            ->setSaleLabel('PUB')
            ->setActive(true)
            ->setStartDate(new \DateTime('-1 day'))
            ->setEndDate(new \DateTime('+1 day'))
            ->setPriceOffsetType($offsetType)
            ->setDisplayInitialPrice(true)
            ->setChapo('')
            ->setDescription('')
            ->setPostscriptum('')
            ->setPriceOffsets([$currency->getId() => $offset])
            ->setProducts([$product->getId()])
            ->setProductAttributes([]);

        $this->dispatch($event, TheliaEvents::SALE_UPDATE);

        return (float) ProductPriceQuery::create()
            ->filterByProductSaleElementsId($this->defaultPseFor($product)->getId())
            ->filterByCurrencyId($currency->getId())
            ->findOne()
            ->getPromoPrice();
    }

    private function catalogProduct(Currency $currency): Product
    {
        return $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $currency,
            ['baseQuantity' => 100, 'basePrice' => 100.0],
        );
    }

    private function extraPseWithPrice(
        Product $product,
        Currency $currency,
        ?int $attributeId = null,
        ?int $attributeAvId = null,
    ): ProductSaleElements {
        $pse = $this->factory->productSaleElement($product);
        $this->factory->productPrice($pse, $currency, ['price' => '100.000000', 'promoPrice' => '100.000000']);

        if (null !== $attributeId && null !== $attributeAvId) {
            (new AttributeCombination())
                ->setProductSaleElementsId($pse->getId())
                ->setAttributeId($attributeId)
                ->setAttributeAvId($attributeAvId)
                ->save();
        }

        return $pse;
    }

    private function defaultPseFor(Product $product): ProductSaleElements
    {
        return ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByIsDefault(true)
            ->findOne();
    }

    private function newCustomer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }
}
