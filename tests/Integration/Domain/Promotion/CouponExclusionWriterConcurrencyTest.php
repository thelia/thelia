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

namespace Thelia\Tests\Integration\Domain\Promotion;

use PHPUnit\Framework\Attributes\Group;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Thelia\Domain\Promotion\Coupon\Service\CouponExclusionWriter;
use Thelia\Model\CouponExclusionQuery;
use Thelia\Model\CouponQuery;
use Thelia\Model\Map\CouponTableMap;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * Writing the exclusions of a promotion holds the `coupon` rows of every promotion it touches, the
 * ones it excluded until now included, before anything else: a competing edit of one of them waits
 * for it (and the other way round) instead of crossing it.
 *
 * The competing edit is a second, independent session holding the row, committed for real: the
 * test runs outside the usual test transaction and cleans its fixtures up by hand.
 */
#[Group('concurrency')]
final class CouponExclusionWriterConcurrencyTest extends IntegrationTestCase
{
    protected bool $useTransaction = false;

    private const LOCK_WAIT_TIMEOUT = 1;

    private FixtureFactory $factory;

    private CouponExclusionWriter $writer;

    private ConnectionInterface $otherSession;

    private ConnectionInterface $mainConnection;

    private string $previousLockWaitTimeout = '50';

    private int $lastCouponIdBefore = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lastCouponIdBefore = (int) CouponQuery::create()->orderById(Criteria::DESC)->findOne()?->getId();
        $this->factory = $this->createFixtureFactory();
        $this->writer = $this->getService(CouponExclusionWriter::class);

        $serviceContainer = Propel::getServiceContainer();
        $connectionManager = $serviceContainer->getConnectionManager(CouponTableMap::DATABASE_NAME);

        if (!$connectionManager instanceof ConnectionManagerSingle) {
            self::markTestSkipped('The test database is not served by a single-connection manager.');
        }

        $this->otherSession = ConnectionFactory::create($connectionManager->getConfiguration(), $serviceContainer->getAdapter(CouponTableMap::DATABASE_NAME));
        $this->mainConnection = Propel::getWriteConnection(CouponTableMap::DATABASE_NAME);
        $this->previousLockWaitTimeout = (string) $this->mainConnection->query('SELECT @@SESSION.innodb_lock_wait_timeout')->fetchColumn();
        $this->mainConnection->exec('SET SESSION innodb_lock_wait_timeout = '.self::LOCK_WAIT_TIMEOUT);
    }

    protected function tearDown(): void
    {
        try {
            while ($this->otherSession->inTransaction()) {
                $this->otherSession->rollBack();
            }

            while ($this->mainConnection->inTransaction()) {
                $this->mainConnection->rollBack();
            }

            $this->mainConnection->exec('SET SESSION innodb_lock_wait_timeout = '.(int) $this->previousLockWaitTimeout);
            CouponQuery::create()->filterById($this->lastCouponIdBefore, Criteria::GREATER_THAN)->delete();
        } finally {
            parent::tearDown();
        }
    }

    public function testAnEditWaitsForAPromotionThatTheEditedOneExcludedUntilNow(): void
    {
        $edited = $this->factory->coupon()->getId();
        $formerPartner = $this->factory->coupon()->getId();
        $this->writer->replace($edited, [$formerPartner]);

        // Another admin is saving the former partner: it holds its row.
        $this->otherSession->beginTransaction();
        $this->otherSession->query('SELECT `id` FROM `coupon` WHERE `id` = '.$formerPartner.' FOR UPDATE')->fetchAll();

        try {
            $this->writer->replace($edited, []);
            self::fail('Removing the pair must wait for the row of the former partner, held by the other edit.');
        } catch (\Throwable $throwable) {
            self::assertTrue(self::isLockWaitTimeout($throwable), 'Expected a lock wait timeout (1205), got '.$throwable::class.': '.$throwable->getMessage());
        }

        self::assertSame([$formerPartner], CouponExclusionQuery::create()->excludedCouponIdsFor($edited), 'Nothing was written.');

        $this->otherSession->rollBack();
        $this->writer->replace($edited, []);

        self::assertSame([], CouponExclusionQuery::create()->excludedCouponIdsFor($edited));
        self::assertSame([], CouponExclusionQuery::create()->excludedCouponIdsFor($formerPartner));
    }

    public function testAnEditWaitsForAPromotionItIsAboutToExclude(): void
    {
        $edited = $this->factory->coupon()->getId();
        $newPartner = $this->factory->coupon()->getId();

        $this->otherSession->beginTransaction();
        $this->otherSession->query('SELECT `id` FROM `coupon` WHERE `id` = '.$newPartner.' FOR UPDATE')->fetchAll();

        try {
            $this->writer->replace($edited, [$newPartner]);
            self::fail('Excluding the promotion must wait for its row, held by the other edit.');
        } catch (\Throwable $throwable) {
            self::assertTrue(self::isLockWaitTimeout($throwable), 'Expected a lock wait timeout (1205), got '.$throwable::class.': '.$throwable->getMessage());
        }

        self::assertSame([], CouponExclusionQuery::create()->excludedCouponIdsFor($edited));
    }

    public function testTheRowsToLockAreThePromotionTheNewAndTheFormerPartnersByIncreasingId(): void
    {
        $edited = $this->factory->coupon()->getId();
        $former = $this->factory->coupon()->getId();
        $new = $this->factory->coupon()->getId();
        $this->writer->replace($edited, [$former]);

        self::assertSame([$edited, $former, $new], $this->writer->rowsToLock($edited, [$new]));
        self::assertSame([$new], $this->writer->rowsToLock(null, [$new]));
    }

    private static function isLockWaitTimeout(\Throwable $throwable): bool
    {
        for ($current = $throwable; null !== $current; $current = $current->getPrevious()) {
            if ($current instanceof \PDOException && 1205 === (int) ($current->errorInfo[1] ?? 0)) {
                return true;
            }
        }

        return false;
    }
}
