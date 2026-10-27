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
use Thelia\Model\SaleCustomerGroup;
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
        // Truncated to the second: MariaDB rounds a fractional DATETIME up,
        // and the stored end date read back would differ from this one.
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
     * An operation reserved for customer groups prices for the members of its
     * groups, for no one else, and never for a guest account of the group.
     */
    public function testAnOperationReservedForAGroupPricesForItsMembersOnly(): void
    {
        $currency = $this->factory->currency();
        $pse = $this->defaultPseFor($this->catalogProduct($currency));
        $group = $this->factory->customerGroup();
        $member = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);
        $guestMember = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);
        $guestMember->setIsGuest(1)->save();
        $sale = $this->runningReservedSale($currency, '10', overrides: ['audienceMode' => Sale::AUDIENCE_MODE_CUSTOMER_GROUPS]);
        $this->factory->saleProduct($sale, $pse->getProduct());
        (new SaleCustomerGroup())->setSaleId($sale->getId())->setCustomerGroupId($group->getId())->save();

        $prices = $this->resolver->resolve([$pse->getId()], $currency, $member);

        self::assertEqualsWithDelta(90.0, $prices[$pse->getId()]->untaxedPromoPrice, 0.000001);
        self::assertSame([], $this->resolver->resolve([$pse->getId()], $currency, $this->newCustomer()));
        self::assertSame([], $this->resolver->resolve([$pse->getId()], $currency, $guestMember));
    }

    /**
     * A group linked to an operation reserved for named customers opens it to no one.
     */
    public function testALinkToAGroupLeftOnAnOperationForNamedCustomersOpensNothing(): void
    {
        $currency = $this->factory->currency();
        $pse = $this->defaultPseFor($this->catalogProduct($currency));
        $group = $this->factory->customerGroup();
        $member = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);
        $sale = $this->runningReservedSale($currency, '10');
        $this->factory->saleProduct($sale, $pse->getProduct());
        (new SaleCustomerGroup())->setSaleId($sale->getId())->setCustomerGroupId($group->getId())->save();

        self::assertSame([], $this->resolver->resolve([$pse->getId()], $currency, $member));
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
        $defaultCurrency = Currency::getDefaultCurrency();
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

        // The first resolution of a test warms what the core memoises for a request
        // (configuration, tax rules): measured cold, it would count those reads too.
        $this->resolver->resolve($onePse, $currency, $customer);

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

    public function testACustomerEntitledToNothingCostsNoProductRead(): void
    {
        [$currency, $pse] = $this->reservedOperationOnOneProduct($this->newCustomer());
        $stranger = $this->newCustomer();

        $statements = $this->recordSqlQueries(function () use ($pse, $currency, $stranger): void {
            self::assertSame([], $this->resolver->resolve([$pse->getId()], $currency, $stranger));
        });

        self::assertSame(0, self::countSqlQueriesSelectingFrom($statements, 'product'));
    }

    public function testTheDateWindowIsReadAtTheInstantAsked(): void
    {
        $customer = $this->newCustomer();
        [$currency, $pse] = $this->reservedOperationOnOneProduct($customer);

        self::assertSame([], $this->resolver->resolve([$pse->getId()], $currency, $customer, new \DateTime('+2 days')));
        self::assertSame([], $this->resolver->resolve([$pse->getId()], $currency, $customer, new \DateTime('-2 days')));
        self::assertArrayHasKey(
            $pse->getId(),
            $this->resolver->resolve([$pse->getId()], $currency, $customer, new \DateTime('+12 hours')),
        );
    }

    /**
     * Only a price that beats the public offer is a reserved price: the same
     * price would credit the reserved operation with a discount it does not give.
     */
    public function testAReservedPriceEqualToTheCurrentPublicOfferIsNotApplied(): void
    {
        $currency = $this->factory->currency();
        $product = $this->catalogProduct($currency);
        $customer = $this->newCustomer();

        $this->publicPromoPriceWrittenFor($product, $currency, '10');

        $sale = $this->runningReservedSale($currency, '10');
        $this->factory->saleProduct($sale, $product);
        $this->factory->saleCustomer($sale, $customer);

        $pse = $this->defaultPseFor($product);

        self::assertSame([], $this->resolver->resolve([$pse->getId()], $currency, $customer));
    }

    public function testEveryProductOfTheBatchGetsItsReservedPrice(): void
    {
        $currency = $this->factory->currency();
        $first = $this->catalogProduct($currency);
        $second = $this->catalogProduct($currency);
        $customer = $this->newCustomer();

        $sale = $this->runningReservedSale($currency, '10');
        $this->factory->saleProduct($sale, $first);
        $this->factory->saleProduct($sale, $second);
        $this->factory->saleCustomer($sale, $customer);

        $firstPse = $this->defaultPseFor($first);
        $secondPse = $this->defaultPseFor($second);
        $resolved = $this->resolver->resolve([$firstPse->getId(), $secondPse->getId()], $currency, $customer);

        self::assertArrayHasKey($firstPse->getId(), $resolved);
        self::assertArrayHasKey($secondPse->getId(), $resolved);
        self::assertSame(90.0, $resolved[$firstPse->getId()]->untaxedPromoPrice);
        self::assertSame(90.0, $resolved[$secondPse->getId()]->untaxedPromoPrice);
    }

    /**
     * A price typed in for the currency is the price of that currency: it is not
     * converted from the default one.
     */
    public function testAPriceTypedInForTheCurrencyIsUsedAsItIs(): void
    {
        $defaultCurrency = Currency::getDefaultCurrency();
        $otherCurrency = $this->factory->currency(['code' => 'XTS', 'rate' => 2.0, 'symbol' => 'X']);
        $product = $this->catalogProduct($defaultCurrency);
        $customer = $this->newCustomer();
        $pse = $this->defaultPseFor($product);

        $this->factory->productPrice($pse, $otherCurrency, ['price' => '150.000000', 'promoPrice' => '150.000000']);

        $sale = $this->runningReservedSale($otherCurrency, '10');
        $this->factory->saleProduct($sale, $product);
        $this->factory->saleCustomer($sale, $customer);

        $resolved = $this->resolver->resolve([$pse->getId()], $otherCurrency, $customer);

        self::assertArrayHasKey($pse->getId(), $resolved);
        self::assertSame(135.0, $resolved[$pse->getId()]->untaxedPromoPrice);
    }

    public function testAPriceFlaggedAsAConversionIsConvertedAgainFromTheDefaultCurrency(): void
    {
        $defaultCurrency = Currency::getDefaultCurrency();
        $otherCurrency = $this->factory->currency(['code' => 'XTS', 'rate' => 2.0, 'symbol' => 'X']);
        $product = $this->catalogProduct($defaultCurrency);
        $customer = $this->newCustomer();
        $pse = $this->defaultPseFor($product);

        $this->factory->productPrice(
            $pse,
            $otherCurrency,
            ['price' => '999.000000', 'promoPrice' => '999.000000', 'fromDefaultCurrency' => true],
        );

        $sale = $this->runningReservedSale($otherCurrency, '10');
        $this->factory->saleProduct($sale, $product);
        $this->factory->saleCustomer($sale, $customer);

        $resolved = $this->resolver->resolve([$pse->getId()], $otherCurrency, $customer);

        // 100 in the default currency at a rate of 2 is 200, minus 10% is 180.
        self::assertArrayHasKey($pse->getId(), $resolved);
        self::assertSame(180.0, $resolved[$pse->getId()]->untaxedPromoPrice);
    }

    /**
     * The rate of the asked currency is relative to the default one, which does not
     * have to be 1: ProductSaleElements::getPricesByCurrency() divides by it too.
     */
    public function testTheConversionIsRelativeToTheRateOfTheDefaultCurrency(): void
    {
        $defaultCurrency = Currency::getDefaultCurrency();
        $defaultCurrency->setRate(0.5)->save();
        $otherCurrency = $this->factory->currency(['code' => 'XTS', 'rate' => 2.0, 'symbol' => 'X']);
        $product = $this->catalogProduct($defaultCurrency);
        $customer = $this->newCustomer();
        $pse = $this->defaultPseFor($product);

        $sale = $this->runningReservedSale($otherCurrency, '10');
        $this->factory->saleProduct($sale, $product);
        $this->factory->saleCustomer($sale, $customer);

        $resolved = $this->resolver->resolve([$pse->getId()], $otherCurrency, $customer);

        // 100 at a rate of 2 against 0.5 is 400, minus 10% is 360.
        self::assertSame(400.0, $pse->getPricesByCurrency($otherCurrency)->getPrice());
        self::assertArrayHasKey($pse->getId(), $resolved);
        self::assertSame(360.0, $resolved[$pse->getId()]->untaxedPromoPrice);
    }

    /**
     * The public offer the reserved price has to beat is converted like the price:
     * a reserved price below it in the asked currency applies.
     */
    public function testThePublicOfferToBeatIsConvertedFromTheDefaultCurrency(): void
    {
        $defaultCurrency = Currency::getDefaultCurrency();
        $otherCurrency = $this->factory->currency(['code' => 'XTS', 'rate' => 2.0, 'symbol' => 'X']);
        $product = $this->catalogProduct($defaultCurrency);
        $customer = $this->newCustomer();
        $pse = $this->defaultPseFor($product);

        $pse->setPromo(1)->save();
        ProductPriceQuery::create()
            ->filterByProductSaleElementsId($pse->getId())
            ->filterByCurrencyId($defaultCurrency->getId())
            ->findOne()
            ->setPromoPrice('90.000000')
            ->save();

        $sale = $this->runningReservedSale($otherCurrency, '20');
        $this->factory->saleProduct($sale, $product);
        $this->factory->saleCustomer($sale, $customer);

        $resolved = $this->resolver->resolve([$pse->getId()], $otherCurrency, $customer);

        // The public offer is 90 at a rate of 2, so 180; 20% off 200 is 160, which beats it.
        self::assertArrayHasKey($pse->getId(), $resolved);
        self::assertSame(160.0, $resolved[$pse->getId()]->untaxedPromoPrice);
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
