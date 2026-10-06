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

namespace Thelia\Tests\Integration\Domain\Shipping;

use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Thelia\Domain\Checkout\Exception\DeliverySlotFullException;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliverySlotBooker;
use Thelia\Model\DeliverySlot;
use Thelia\Model\DeliverySlotBookingQuery;
use Thelia\Model\Map\DeliverySlotTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * Two buyers taking the last place of a slot at the same time: one gets it, the other is
 * refused.
 *
 * Real parallelism is out of reach of a single PHPUnit process, so the interleaving is
 * staged on two genuinely distinct connections, each in its own transaction: the first books
 * the place and has not committed yet when the second asks for it. A booking that counted the
 * orders first and wrote afterwards would let the second through; the conditional update
 * makes it wait on the first, and once the first commits it finds the slot full.
 *
 * The rows have to be committed for the second connection to see them, so this test runs
 * outside the transaction wrapper and deletes its slot afterwards.
 */
final class DeliverySlotBookingConcurrencyTest extends IntegrationTestCase
{
    protected bool $useTransaction = false;

    private ?DeliverySlot $slot = null;

    /** @var list<ConnectionInterface> */
    private array $buyers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $module = ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed — run bin/test-prepare.');

        $this->slot = (new DeliverySlot())
            ->setModuleId($module->getId())
            ->setStartTime('09:00:00')
            ->setEndTime('11:00:00')
            ->setCapacity(1);
        $this->slot->save();
    }

    protected function tearDown(): void
    {
        foreach ($this->buyers as $buyer) {
            if ($buyer->inTransaction()) {
                $buyer->rollBack();
            }
        }
        $this->buyers = [];

        // The bookings go with the slot (ON DELETE CASCADE).
        $this->slot?->delete();
        $this->slot = null;

        parent::tearDown();
    }

    public function testTwoBuyersRacingForTheLastPlaceCannotBothGetIt(): void
    {
        $booker = $this->getService(DeliverySlotBooker::class);
        $slotId = (int) $this->slot?->getId();
        $date = (new \DateTimeImmutable('+3 days'))->format('Y-m-d');

        $first = $this->buyer();
        $second = $this->buyer();
        $second->exec('SET SESSION innodb_lock_wait_timeout = 1');

        $first->beginTransaction();
        $booker->book($slotId, $date, $first);

        // The first order holds the place but has not committed: the second cannot read past
        // it to a count that still says "one left".
        $second->beginTransaction();

        try {
            $booker->book($slotId, $date, $second);
            self::fail('The second buyer must not get a place the first one is holding.');
        } catch (\PDOException $waited) {
            self::assertStringContainsString('Lock wait timeout', $waited->getMessage(), 'The second buyer waits on the first instead of reading past it.');
        }

        $second->rollBack();
        $first->commit();

        // The first order is written; the second, asking again, finds the slot full.
        $second->beginTransaction();

        try {
            $booker->book($slotId, $date, $second);
            self::fail('The second buyer must be refused once the first committed.');
        } catch (DeliverySlotFullException) {
        } finally {
            $second->rollBack();
        }

        self::assertSame(1, (int) DeliverySlotBookingQuery::create()->filterByDeliverySlotId($slotId)->filterByDeliveryDate($date)->findOne()?->getBooked());
    }

    /**
     * A connection of its own, opened from the configuration Propel already holds: Propel
     * hands out one connection per datasource, and two bookings on it would share a
     * transaction.
     */
    private function buyer(): ConnectionInterface
    {
        $serviceContainer = Propel::getServiceContainer();
        $connectionManager = $serviceContainer->getConnectionManager(DeliverySlotTableMap::DATABASE_NAME);

        if (!$connectionManager instanceof ConnectionManagerSingle) {
            self::markTestSkipped('The test database is not served by a single-connection manager.');
        }

        return $this->buyers[] = ConnectionFactory::create(
            $connectionManager->getConfiguration(),
            $serviceContainer->getAdapter(DeliverySlotTableMap::DATABASE_NAME),
        );
    }
}
