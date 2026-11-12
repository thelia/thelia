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

namespace Thelia\Tests\Integration\Domain\Pricing;

use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Model\CatalogRestriction;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;
use Thelia\Tests\Integration\Domain\Pricing\PriceList\PriceListFixtures;

/**
 * The shop-wide probe tells in its single statement whether a price list is turned on and
 * whether a catalog restriction exists: the guards of the front ask it before any query of
 * their own.
 */
final class CatalogActivityProbePriceListTest extends ActionIntegrationTestCase
{
    use PriceListFixtures;
    use RecordsSqlQueries;

    protected function tearDown(): void
    {
        $this->forgetRequestMemos();

        parent::tearDown();
    }

    public function testAShopWithoutPriceListNorRestrictionSaysSo(): void
    {
        $activity = $this->probe();

        self::assertFalse($activity->hasPriceLists);
        self::assertFalse($activity->hasCatalogRestrictions);
        self::assertFalse($activity->customersMayBePricedApart());
    }

    public function testOnlyAListThatIsTurnedOnCounts(): void
    {
        $this->priceList(active: false);
        self::assertFalse($this->probe()->hasPriceLists);

        $this->priceList();
        self::assertTrue($this->probe()->hasPriceLists);
        self::assertTrue($this->probe()->customersMayBePricedApart());
    }

    public function testACatalogRestrictionCounts(): void
    {
        $group = $this->factory->customerGroup();
        $product = $this->pricedProduct();
        (new CatalogRestriction())
            ->setCustomerGroupId($group->getId())
            ->setProductId($product->getId())
            ->setMode('deny')
            ->save($this->getPropelConnection());

        self::assertTrue($this->probe()->hasCatalogRestrictions);
        self::assertFalse($this->probe()->hasPriceLists);
    }

    public function testTheTwoAnswersCostNoStatementMoreThanTheProbeAlready(): void
    {
        $this->forgetRequestMemos();
        $probe = $this->getService(CatalogActivityProbe::class);

        $statements = $this->recordSqlQueries(static function () use ($probe): void {
            $probe->read();
            $probe->read()->hasPriceLists;
            $probe->read()->hasCatalogRestrictions;
        });

        self::assertCount(1, $statements);
    }

    private function probe(): \Thelia\Domain\Pricing\CatalogActivity
    {
        $this->forgetRequestMemos();

        return $this->getService(CatalogActivityProbe::class)->read();
    }
}
