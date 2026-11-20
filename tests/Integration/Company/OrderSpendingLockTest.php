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

namespace Thelia\Tests\Integration\Company;

use PHPUnit\Framework\Attributes\Group;
use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Thelia\Domain\Company\Checkout\Service\CompanyPlacementGuard;
use Thelia\Model\Map\OrderTableMap;

/**
 * The row that binds a buyer to their company is held for the whole placement of an order, so
 * that two carts of the same buyer cannot both pass a limit the first one used up.
 *
 * The competing placement is a second session on the same database, asked for the row while
 * this one holds it. The fixtures are committed, and removed by hand.
 */
#[Group('concurrency')]
final class OrderSpendingLockTest extends CompanyDomainTestCase
{
    protected bool $useTransaction = false;

    private ?ConnectionInterface $otherSession = null;

    private string $marker = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->marker = 'lock-test-'.bin2hex(random_bytes(4));
        $this->otherSession = $this->openOtherSession();
        $this->otherSession->exec('SET SESSION innodb_lock_wait_timeout = 1');
    }

    protected function tearDown(): void
    {
        $connection = Propel::getWriteConnection(OrderTableMap::DATABASE_NAME);

        while ($connection->inTransaction()) {
            $connection->rollBack();
        }

        while ($this->otherSession?->inTransaction()) {
            $this->otherSession->rollBack();
        }

        $connection->exec("DELETE FROM `company` WHERE `name` LIKE '".$this->marker."%'");
        $connection->exec("DELETE FROM `customer` WHERE `email` LIKE '".$this->marker."%'");

        parent::tearDown();
    }

    public function testTheRowOfTheBuyerIsHeldUntilThePlacementIsOver(): void
    {
        [$buyer] = $this->aCompanyWithTwoBuyers();
        $guard = $this->guard();
        $hold = $guard->prepare($buyer);
        self::assertNotNull($hold);
        $placement = Propel::getWriteConnection(OrderTableMap::DATABASE_NAME);

        $placement->beginTransaction();

        try {
            $guard->lock($hold, $placement);

            self::assertTrue($this->theOtherSessionIsRefusedTheRow($hold->companyCustomerId), 'A second placement of the same buyer waits for the first.');
        } finally {
            $placement->rollBack();
        }

        self::assertFalse($this->theOtherSessionIsRefusedTheRow($hold->companyCustomerId), 'Once the placement is over the row is free again.');
    }

    public function testAColleagueOfTheSameCompanyIsNotHeld(): void
    {
        [$buyer, $colleague] = $this->aCompanyWithTwoBuyers();
        $guard = $this->guard();
        $hold = $guard->prepare($buyer);
        $colleagueHold = $guard->prepare($colleague);
        self::assertNotNull($hold);
        self::assertNotNull($colleagueHold);
        $placement = Propel::getWriteConnection(OrderTableMap::DATABASE_NAME);

        $placement->beginTransaction();

        try {
            $guard->lock($hold, $placement);

            self::assertFalse($this->theOtherSessionIsRefusedTheRow($colleagueHold->companyCustomerId), 'The limits are the buyer\'s own: a colleague places their order meanwhile.');
        } finally {
            $placement->rollBack();
        }
    }

    public function testACustomerWithoutACompanyHasNothingToLock(): void
    {
        self::assertNull($this->guard()->prepare($this->customer($this->marker.'-alone@test.com')));
    }

    /**
     * @return array{0: \Thelia\Model\Customer, 1: \Thelia\Model\Customer}
     */
    private function aCompanyWithTwoBuyers(): array
    {
        $company = $this->company($this->marker.' company');
        $buyer = $this->buyerOf($company, customer: $this->customer($this->marker.'-a@test.com'), orderLimit: '500', periodLimit: '2000', period: 'month');
        $colleague = $this->buyerOf($company, customer: $this->customer($this->marker.'-b@test.com'));

        return [$buyer, $colleague];
    }

    private function theOtherSessionIsRefusedTheRow(int $companyCustomerId): bool
    {
        \assert($this->otherSession instanceof ConnectionInterface);
        $this->otherSession->beginTransaction();

        try {
            $this->otherSession->query('SELECT `id` FROM `company_customer` WHERE `id` = '.$companyCustomerId.' FOR UPDATE')->fetchColumn();

            return false;
        } catch (\Throwable $timeout) {
            self::assertStringContainsString('Lock wait timeout', $timeout->getMessage());

            return true;
        } finally {
            while ($this->otherSession->inTransaction()) {
                $this->otherSession->rollBack();
            }
        }
    }

    private function guard(): CompanyPlacementGuard
    {
        return $this->getService(CompanyPlacementGuard::class);
    }

    private function openOtherSession(): ConnectionInterface
    {
        $serviceContainer = Propel::getServiceContainer();
        $connectionManager = $serviceContainer->getConnectionManager(OrderTableMap::DATABASE_NAME);

        if (!$connectionManager instanceof ConnectionManagerSingle) {
            self::markTestSkipped('The test database is not served by a single-connection manager.');
        }

        return ConnectionFactory::create(
            $connectionManager->getConfiguration(),
            $serviceContainer->getAdapter(OrderTableMap::DATABASE_NAME),
        );
    }
}
