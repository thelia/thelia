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

namespace Thelia\Tests\Integration\Domain\SaleUnit;

use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * Whether the shop sells from sale units is asked of the database once and then
 * kept in the shared pool, so a single shop stops asking; writing or deleting a sale
 * unit drops the answer at once, in the request that did it and in the next ones.
 */
final class SaleUnitActivityCacheTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    private SaleUnitActivityChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = $this->getService(SaleUnitActivityChecker::class);
    }

    public function testASingleShopAsksOnceThenReadsTheSharedAnswer(): void
    {
        $first = $this->recordSqlQueries(fn () => self::assertFalse($this->checker->isMultiSaleUnitMode()));
        self::assertSame(1, self::countSqlQueriesSelectingFrom($first, 'sale_unit'), 'The first request asks the database.');

        $this->checker->reset();

        $later = $this->recordSqlQueries(fn () => self::assertFalse($this->checker->isMultiSaleUnitMode()));
        self::assertSame(0, self::countSqlQueriesSelectingFrom($later, 'sale_unit'), 'A later request reads the shared answer.');
    }

    public function testCreatingAnActiveUnitTurnsTheModeOnAtOnce(): void
    {
        self::assertFalse($this->checker->isMultiSaleUnitMode());

        $this->createFixtureFactory()->saleUnit();

        self::assertTrue($this->checker->isMultiSaleUnitMode(), 'The request that created the unit sees it.');

        $this->checker->forgetAfterTheRequest();
        $this->checker->reset();

        self::assertTrue($this->checker->isMultiSaleUnitMode(), 'So does the next one.');
    }

    public function testTurningTheLastUnitOffOrDeletingItTurnsTheModeOff(): void
    {
        $saleUnit = $this->createFixtureFactory()->saleUnit();
        $this->settle();
        self::assertTrue($this->checker->isMultiSaleUnitMode());

        $saleUnit->setActive(false)->save($this->getPropelConnection());
        self::assertFalse($this->checker->isMultiSaleUnitMode());

        $saleUnit->setActive(true)->save($this->getPropelConnection());
        $this->settle();
        self::assertTrue($this->checker->isMultiSaleUnitMode());

        $saleUnit->delete($this->getPropelConnection());
        self::assertFalse($this->checker->isMultiSaleUnitMode());
    }

    public function testARequestThatChangedAUnitPublishesNothingUntilItEnds(): void
    {
        $this->createFixtureFactory()->saleUnit();
        self::assertTrue($this->checker->isMultiSaleUnitMode());

        // Another process asking while the change is not committed yet must not be
        // handed the value of a transaction that may still roll back.
        $pool = $this->getService('thelia.cache.config.adapter');
        self::assertFalse($pool->getItem(SaleUnitActivityChecker::CACHE_KEY)->isHit());
    }

    /**
     * The request that wrote ends: the next one starts from the shared pool.
     */
    private function settle(): void
    {
        $this->checker->forgetAfterTheRequest();
        $this->checker->reset();
    }
}
