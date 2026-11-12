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

namespace Thelia\Tests\Integration\Domain\Pricing\PriceVisibility;

use Thelia\Domain\Pricing\CatalogActivity;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Domain\Pricing\PriceVisibility\PriceDisplayMode;
use Thelia\Model\CatalogRestriction;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;
use Thelia\Tests\Integration\Domain\Pricing\PriceList\PriceListFixtures;

/**
 * The single statement of the probe also says whether the shop or a product hides a price, and
 * whether a price list or a restriction is assigned to the default group, the group of every
 * visitor: the answers the shared cache and the cart ask without a statement of their own.
 */
final class CatalogActivityProbePriceDisplayTest extends ActionIntegrationTestCase
{
    use PriceDisplayFixtures;
    use PriceListFixtures;
    use RecordsSqlQueries;

    protected function tearDown(): void
    {
        $this->forgetPriceDisplayMemos();

        parent::tearDown();
    }

    public function testAShopThatShowsEveryPriceSaysNothingIsHidden(): void
    {
        $activity = $this->probe();

        self::assertFalse($activity->hasNonVisiblePriceDisplay);
        self::assertFalse($activity->hasDefaultGroupPriceLists);
        self::assertFalse($activity->hasDefaultGroupCatalogRestrictions);
        self::assertFalse($activity->visitorsMayBeShownApart());
    }

    public function testAShopModeOtherThanVisibleCounts(): void
    {
        $this->shopShows(PriceDisplayMode::LoggedIn);
        self::assertTrue($this->probe()->hasNonVisiblePriceDisplay);

        $this->shopShows(PriceDisplayMode::OnRequest);
        self::assertTrue($this->probe()->hasNonVisiblePriceDisplay);

        $this->shopShows(PriceDisplayMode::Visible);
        self::assertFalse($this->probe()->hasNonVisiblePriceDisplay);
    }

    public function testAProductThatStatesAModeOtherThanVisibleCounts(): void
    {
        $product = $this->pricedProduct();

        $this->productShows($product, PriceDisplayMode::Visible);
        self::assertFalse($this->probe()->hasNonVisiblePriceDisplay, 'a product that states visible in a shop that shows everything hides nothing');

        $this->productShows($product, PriceDisplayMode::OnRequest);
        self::assertTrue($this->probe()->hasNonVisiblePriceDisplay);

        $this->productShows($product, PriceDisplayMode::LoggedIn);
        self::assertTrue($this->probe()->hasNonVisiblePriceDisplay);

        $this->productShows($product, null);
        self::assertFalse($this->probe()->hasNonVisiblePriceDisplay);
    }

    public function testAnUnknownValueIsNotAMode(): void
    {
        $this->pricedProduct()->setPriceDisplayMode('sometimes')->save($this->getPropelConnection());
        \Thelia\Model\ConfigQuery::write(PriceDisplayMode::CONFIG_KEY, 'sometimes', false);
        $this->forgetPriceDisplayMemos();

        self::assertFalse($this->probe()->hasNonVisiblePriceDisplay);
    }

    public function testAPriceListOfTheDefaultGroupCountsAndOneOfAnotherGroupOrCustomerDoesNot(): void
    {
        $other = $this->factory->customerGroup();
        $list = $this->priceList();
        $this->assignToGroup($list, $other);
        $this->assignToCustomer($list, $this->memberOf($other));
        self::assertFalse($this->probe()->hasDefaultGroupPriceLists);

        $default = CustomerGroupQuery::findDefault();
        $inactive = $this->priceList(active: false);
        $this->assignToGroup($inactive, $default);
        self::assertFalse($this->probe()->hasDefaultGroupPriceLists, 'a list that is turned off is no list');

        $this->assignToGroup($list, $default);
        self::assertTrue($this->probe()->hasDefaultGroupPriceLists);
        self::assertTrue($this->probe()->visitorsMayBeShownApart());
    }

    public function testARestrictionOfTheDefaultGroupCountsAndOneOfAnotherGroupDoesNot(): void
    {
        $product = $this->pricedProduct();
        $other = $this->factory->customerGroup();
        (new CatalogRestriction())->setCustomerGroupId($other->getId())->setProductId($product->getId())->setMode('deny')->save($this->getPropelConnection());
        self::assertFalse($this->probe()->hasDefaultGroupCatalogRestrictions);

        (new CatalogRestriction())->setCustomerGroupId(CustomerGroupQuery::findDefault()->getId())->setProductId($product->getId())->setMode('deny')->save($this->getPropelConnection());
        self::assertTrue($this->probe()->hasDefaultGroupCatalogRestrictions);
        self::assertTrue($this->probe()->visitorsMayBeShownApart());
    }

    public function testTheNewAnswersCostNoStatementMoreThanTheProbeAlready(): void
    {
        $this->forgetPriceDisplayMemos();
        $probe = $this->getService(CatalogActivityProbe::class);

        $statements = $this->recordSqlQueries(static function () use ($probe): void {
            $probe->read();
            $probe->read()->hasNonVisiblePriceDisplay;
            $probe->read()->hasDefaultGroupPriceLists;
            $probe->read()->hasDefaultGroupCatalogRestrictions;
        });

        self::assertCount(1, $statements);
    }

    private function probe(): CatalogActivity
    {
        $this->forgetPriceDisplayMemos();

        return $this->getService(CatalogActivityProbe::class)->read();
    }
}
