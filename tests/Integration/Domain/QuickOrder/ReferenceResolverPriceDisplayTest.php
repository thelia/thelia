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

namespace Thelia\Tests\Integration\Domain\QuickOrder;

use Thelia\Domain\Catalog\DTO\ReferenceQuantity;
use Thelia\Domain\Catalog\DTO\ReferenceQuantityLines;
use Thelia\Domain\Pricing\PriceVisibility\PriceDisplayMode;
use Thelia\Domain\QuickOrder\DTO\QuickOrderLine;
use Thelia\Domain\QuickOrder\Enum\LineStatus;
use Thelia\Domain\QuickOrder\Service\ReferenceResolver;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\ForgetsPooledModels;
use Thelia\Test\Trait\RecordsSqlQueries;
use Thelia\Tests\Integration\Domain\Pricing\PriceList\PriceListFixtures;
use Thelia\Tests\Integration\Domain\Pricing\PriceVisibility\PriceDisplayFixtures;

/**
 * The quick order sells only what its customer is shown the price of: a sale element whose
 * price the mode hides, or that an exclusive price list does not price, comes back without a
 * price and cannot be added, whoever the customer is. A signed-in customer in a shop that
 * shows its prices to identified customers only is the one the mode lets through.
 */
final class ReferenceResolverPriceDisplayTest extends IntegrationTestCase
{
    use ForgetsPooledModels;
    use PriceDisplayFixtures;
    use PriceListFixtures;
    use RecordsSqlQueries;

    private FixtureFactory $factory;

    private ReferenceResolver $resolver;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->resolver = $this->getService(ReferenceResolver::class);
        $this->customer = $this->factory->customer($this->factory->customerTitle());
        $this->forgetPriceDisplayMemos();
    }

    protected function tearDown(): void
    {
        $this->forgetPriceDisplayMemos();

        parent::tearDown();
    }

    public function testAShopThatShowsEveryPriceResolvesTheLine(): void
    {
        $line = $this->resolveOne($this->pricedProduct(40.0)->getDefaultSaleElements());

        self::assertSame(LineStatus::Resolved, $line->status);
        self::assertEqualsWithDelta(40.0, $line->untaxedUnitPrice, 0.0001);
    }

    public function testAnIdentifiedCustomerIsShownThePriceWhenTheShopShowsItToIdentifiedCustomers(): void
    {
        $this->shopShows(PriceDisplayMode::LoggedIn);

        $line = $this->resolveOne($this->pricedProduct(40.0)->getDefaultSaleElements());

        self::assertSame(LineStatus::Resolved, $line->status);
        self::assertEqualsWithDelta(40.0, $line->untaxedUnitPrice, 0.0001);
    }

    public function testAPriceOnRequestIsNotShownToACustomerAndTheLineCannotBeAdded(): void
    {
        $this->shopShows(PriceDisplayMode::OnRequest);

        $line = $this->resolveOne($this->pricedProduct(40.0)->getDefaultSaleElements());

        self::assertSame(LineStatus::Unavailable, $line->status);
        self::assertNull($line->untaxedUnitPrice);
        self::assertNull($line->taxedUnitPrice);
    }

    public function testAProductThatStatesItsOwnModeIsHiddenAloneAndTheOthersStayOnSale(): void
    {
        $hidden = $this->pricedProduct(40.0);
        $shown = $this->pricedProduct(25.0);
        $this->productShows($hidden, PriceDisplayMode::OnRequest);

        $table = $this->resolver->resolve($this->customer, new ReferenceQuantityLines([
            new ReferenceQuantity((string) $hidden->getDefaultSaleElements()->getRef(), 1),
            new ReferenceQuantity((string) $shown->getDefaultSaleElements()->getRef(), 1),
        ]), Currency::getDefaultCurrency());

        self::assertSame(LineStatus::Unavailable, $table->lines[0]->status);
        self::assertNull($table->lines[0]->untaxedUnitPrice);
        self::assertSame(LineStatus::Resolved, $table->lines[1]->status);
        self::assertEqualsWithDelta(25.0, $table->lines[1]->untaxedUnitPrice, 0.0001);
    }

    public function testASaleElementAnExclusiveListDoesNotPriceCannotBeAdded(): void
    {
        $product = $this->pricedProduct(100.0);
        $priced = $product->getDefaultSaleElements();
        $closed = $this->factory->productSaleElement($product, ['ref' => 'CLOSED-'.uniqid()]);
        $this->factory->productPrice($closed, Currency::getDefaultCurrency());
        $list = $this->priceList('EXCL', exclusive: true);
        $this->assignToCustomer($list, $this->customer);
        $this->priceListItem($list, $priced, '50');
        $this->forgetPriceDisplayMemos();

        $kept = $this->resolveOne($priced);
        self::assertSame(LineStatus::Resolved, $kept->status);
        self::assertEqualsWithDelta(50.0, $kept->untaxedUnitPrice, 0.0001);

        $refused = $this->resolveOne($closed);
        self::assertSame(LineStatus::Unavailable, $refused->status);
        self::assertNull($refused->untaxedUnitPrice);
    }

    public function testTheNumberOfStatementsDoesNotGrowWithTheNumberOfLinesWhilePricesAreHidden(): void
    {
        $this->shopShows(PriceDisplayMode::LoggedIn);
        $references = [];

        for ($i = 0; $i < 10; ++$i) {
            $references[] = (string) $this->pricedProduct()->getDefaultSaleElements()->getRef();
        }

        // The first resolution loads what the process keeps (languages, tax rules): not a cost per line.
        $this->countStatementsResolving($references);

        $few = $this->countStatementsResolving(\array_slice($references, 0, 3));
        $many = $this->countStatementsResolving($references);

        self::assertSame($few, $many, 'Resolving more lines ran more statements.');
    }

    /**
     * @param list<string> $references
     */
    private function countStatementsResolving(array $references): int
    {
        $this->forgetPriceDisplayMemos();
        $lines = new ReferenceQuantityLines(array_map(static fn (string $reference): ReferenceQuantity => new ReferenceQuantity($reference, 1), $references));

        return \count($this->recordSqlQueriesWithoutPooledModels(fn () => $this->resolver->resolve($this->customer, $lines, Currency::getDefaultCurrency())));
    }

    private function resolveOne(ProductSaleElements $saleElements): QuickOrderLine
    {
        return $this->resolver->resolve(
            $this->customer,
            new ReferenceQuantityLines([new ReferenceQuantity((string) $saleElements->getRef(), 1, (int) $saleElements->getId())]),
            Currency::getDefaultCurrency(),
        )->lines[0];
    }
}
