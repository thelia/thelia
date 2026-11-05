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

namespace Thelia\Tests\Integration\Api;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\AvailabilityFilter;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FilterService;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Catalog\Stock\OutOfStockPolicy;
use Thelia\Domain\Catalog\Stock\StockSettings;
use Thelia\Domain\Catalog\Stock\VisitorCart;
use Thelia\Domain\Checkout\CheckoutFacade;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

/**
 * A combination sold below zero stock is available as long as it can still be bought: the
 * availability facet counts it in stock, and out of stock once its limit is used up.
 *
 *   OPEN       stock 0, limited mode, limit 10             in stock
 *   USED-UP    stock -10, limited mode, limit 10           out
 *   UNLIMITED  stock 0, sells without limit                in stock
 *   REFUSE     stock 0, mode that refuses                  out
 *   REFUSE-LIM stock 0, mode that refuses, limit 10        out
 */
final class PreorderAvailabilityFacetTest extends IntegrationTestCase
{
    private FilterService $filterService;

    /** @var array<string, int> */
    private array $productIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('check-available-stock', '1');
        ConfigQuery::resetCache();
        $this->filterService = static::getContainer()->get(FilterService::class);

        $factory = $this->createFixtureFactory();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();
        $category = $factory->category();

        foreach ([
            'OPEN' => [0, OutOfStockPolicy::Limited, 10.0],
            'USED-UP' => [-10, OutOfStockPolicy::Limited, 10.0],
            'UNLIMITED' => [0, OutOfStockPolicy::Unlimited, null],
            'REFUSE' => [0, OutOfStockPolicy::Refuse, null],
            'REFUSE-LIM' => [0, OutOfStockPolicy::Refuse, 10.0],
        ] as $ref => [$quantity, $policy, $limit]) {
            $product = $factory->product($category, $taxRule, $currency, ['ref' => $ref, 'baseQuantity' => $quantity]);
            $product->getDefaultSaleElements()->setQuantity($quantity)->setOutOfStockPolicy($policy->value)->setPreorderLimit($limit)->save($this->getPropelConnection());
            $this->productIds[$ref] = (int) $product->getId();
        }
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testInStockKeepsWhatCanStillBePreordered(): void
    {
        self::assertSame(['OPEN', 'UNLIMITED'], $this->matching([AvailabilityFilter::IN_STOCK]));
    }

    public function testOutOfStockKeepsWhatCannotBeBoughtAnymore(): void
    {
        self::assertSame(['USED-UP', 'REFUSE', 'REFUSE-LIM'], $this->matching([AvailabilityFilter::OUT_OF_STOCK]));
    }

    public function testTheCartOfTheVisitorIsNotHeldAgainstThemInTheFacet(): void
    {
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '1');
        ConfigQuery::write(StockSettings::RESERVATION_SCOPE, 'checkout');
        ConfigQuery::write(StockSettings::RESERVATION_DURATION, '15');
        ConfigQuery::resetCache();
        $callerTransaction = static::getContainer()->get(TestCallerTransaction::class);
        $callerTransaction->ignoreTheLevelsOpenOn($this->getPropelConnection());
        $visitorCart = static::getContainer()->get(VisitorCart::class);
        $visitorCart->reset();

        try {
            $factory = $this->createFixtureFactory();
            $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['ref' => 'HELD']);
            $product->getDefaultSaleElements()->setQuantity(1)->save($this->getPropelConnection());
            $cart = $factory->cart(null);
            $factory->cartItem($cart, $product, null, ['quantity' => 1.0]);
            static::getContainer()->get(CheckoutFacade::class)->holdStock($cart);
            $this->productIds['HELD'] = (int) $product->getId();

            self::assertNotContains('HELD', $this->matching([AvailabilityFilter::IN_STOCK]), 'The last unit is held: a visitor with no cart finds it gone.');

            static::getContainer()->get('request_stack')->getCurrentRequest()->getSession()->set(Session::SESSION_CART_ID_NAME, $cart->getId());
            $visitorCart->reset();

            self::assertContains('HELD', $this->matching([AvailabilityFilter::IN_STOCK]), 'The visitor holds it themselves: it is still theirs.');
        } finally {
            $callerTransaction->reset();
            $visitorCart->reset();
        }
    }

    /** A negative preorder limit, written behind the back of the core, never takes from the stock itself. */
    public function testANegativePreorderLimitTakesNothingFromTheStock(): void
    {
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '1');
        ConfigQuery::resetCache();

        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['ref' => 'NEGATIVE-LIMIT']);
        $this->productIds['NEGATIVE-LIMIT'] = (int) $product->getId();
        $this->getPropelConnection()->exec(\sprintf(
            'UPDATE product_sale_elements SET quantity = 3, out_of_stock_policy = %d, preorder_limit = -5 WHERE id = %d',
            OutOfStockPolicy::Limited->value,
            (int) $product->getDefaultSaleElements()->getId(),
        ));

        self::assertContains('NEGATIVE-LIMIT', $this->matching([AvailabilityFilter::IN_STOCK]), 'Three units in stock are for sale.');
    }

    public function testTheValueOfAProductSaysWhetherItIsAvailable(): void
    {
        $filter = static::getContainer()->get(AvailabilityFilter::class);
        $open = ProductQuery::create()->findPk($this->productIds['OPEN']);
        $usedUp = ProductQuery::create()->findPk($this->productIds['USED-UP']);

        self::assertSame('In stock', $filter->getValue($open, 'en_US')[0]->getTitle());
        self::assertSame('On order or out of stock', $filter->getValue($usedUp, 'en_US')[0]->getTitle());
    }

    public function testTheCountsAgreeWithTheFilter(): void
    {
        $values = static::getContainer()->get(AvailabilityFilter::class)->getAggregatedValues(array_values($this->productIds), 'en_US');
        $counts = [];

        foreach ($values as $value) {
            $counts[$value->getTitle()] = $value->getCount();
        }

        self::assertSame(['In stock' => 2, 'On order or out of stock' => 3], $counts);
    }

    /**
     * @param list<int> $values
     *
     * @return list<string>
     */
    private function matching(array $values): array
    {
        $query = ProductQuery::create()->filterById(array_values($this->productIds), Criteria::IN);
        $filtered = $this->filterService->filterWithTFilter(tfilters: ['availability' => ['availability' => $values]], resource: 'products', query: $query);

        $matched = array_map(
            static fn (Product $product): int => (int) $product->getId(),
            iterator_to_array($filtered->find($this->getPropelConnection())),
        );

        return array_keys(array_filter($this->productIds, static fn (int $id): bool => \in_array($id, $matched, true)));
    }
}
