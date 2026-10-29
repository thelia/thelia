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

namespace Thelia\Tests\Integration\Domain\Ai;

use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Domain\Ai\Budget\AiBudgetLedger;
use Thelia\Domain\Ai\Exception\AiBudgetExceededException;
use Thelia\Model\AiBudgetReservation;
use Thelia\Model\AiBudgetReservationQuery;
use Thelia\Model\AiUsageLog;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

final class AiBudgetLedgerTest extends IntegrationTestCase
{
    private AiBudgetLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = new AiBudgetLedger();
    }

    public function testAReservationHoldsItsAmountUntilTheLifetimeOfAHold(): void
    {
        $hold = $this->ledger->reserve('aiprobe.writing', '0.25000000', '10', '0');

        $row = AiBudgetReservationQuery::create()->findPk($hold->id);

        self::assertNotNull($row);
        self::assertSame('aiprobe.writing', $row->getUsageId());
        self::assertSame('0.25000000', $row->getAmount());
        self::assertSame('USD', $row->getCurrency());
        self::assertEqualsWithDelta(time() + AiBudgetLedger::HOLD_LIFETIME_SECONDS, $row->getExpiresAt()?->getTimestamp(), 5);
    }

    public function testAReservationCarriesTheCurrencyItIsGiven(): void
    {
        $hold = $this->ledger->reserve('aiprobe.writing', '0.25000000', '10', '0', 'EUR');

        self::assertSame('EUR', AiBudgetReservationQuery::create()->findPk($hold->id)?->getCurrency());
    }

    public function testTheSecondOfTwoReservationsThatDoNotFitTogetherIsRefused(): void
    {
        $first = $this->ledger->reserve('aiprobe.writing', '0.60000000', '1', '0');

        try {
            $this->ledger->reserve('aiprobe.writing', '0.60000000', '1', '0');
            self::fail('The two reservations are above the budget of 1.');
        } catch (AiBudgetExceededException $refusal) {
            self::assertFalse($refusal->globalCap);
            self::assertSame('aiprobe.writing', $refusal->usageId);
        }

        self::assertSame(1, $this->holds('aiprobe.writing'));

        $this->ledger->release($first);

        $this->ledger->reserve('aiprobe.writing', '0.60000000', '1', '0');
        self::assertSame(1, $this->holds('aiprobe.writing'));
    }

    public function testWhatTheLogHoldsForTheMonthCountsAndLastMonthDoesNot(): void
    {
        $this->logRow('aiprobe.writing', '0.70000000', new \DateTimeImmutable('first day of this month 00:00:01'));
        $this->logRow('aiprobe.writing', '5.00000000', new \DateTimeImmutable('last day of last month 23:59:59'));
        $this->logRow('aiprobe.chat', '5.00000000', new \DateTimeImmutable('first day of this month 00:00:01'));

        $this->ledger->reserve('aiprobe.writing', '0.30000000', '1', '0');

        $this->expectException(AiBudgetExceededException::class);

        $this->ledger->reserve('aiprobe.writing', '0.00000001', '1', '0');
    }

    public function testTheGlobalCapIsASumOverAllTheUsagesAndSaysSo(): void
    {
        $this->logRow('aiprobe.chat', '1.50000000', new \DateTimeImmutable('first day of this month 00:00:01'));
        $this->ledger->reserve('aiprobe.writing', '0.40000000', null, '2');

        try {
            $this->ledger->reserve('aiprobe.other', '0.20000000', null, '2');
            self::fail('1.50 spent + 0.40 held + 0.20 is above the cap of 2.');
        } catch (AiBudgetExceededException $refusal) {
            self::assertTrue($refusal->globalCap);
        }
    }

    public function testTheBudgetOfTheUsageIsCheckedBeforeTheGlobalCap(): void
    {
        $this->logRow('aiprobe.writing', '1.00000000', new \DateTimeImmutable('first day of this month 00:00:01'));

        try {
            $this->ledger->reserve('aiprobe.writing', '0.10000000', '1', '1');
            self::fail('Both limits are reached.');
        } catch (AiBudgetExceededException $refusal) {
            self::assertFalse($refusal->globalCap);
        }
    }

    public function testWithoutBudgetNorCapNothingIsRefused(): void
    {
        $this->logRow('aiprobe.writing', '99999.00000000', new \DateTimeImmutable('first day of this month 00:00:01'));

        $this->ledger->reserve('aiprobe.writing', '500.00000000', null, '0');
        $this->ledger->reserve('aiprobe.writing', '500.00000000', '0.000000', '0');

        self::assertSame(2, $this->holds('aiprobe.writing'));
    }

    public function testAReservationOfNothingOnlyChecksThatTheBudgetIsNotReached(): void
    {
        $this->ledger->reserve('aiprobe.writing', '0', '1', '0');
        $this->logRow('aiprobe.writing', '1.00000000', new \DateTimeImmutable('first day of this month 00:00:01'));

        $this->expectException(AiBudgetExceededException::class);

        $this->ledger->reserve('aiprobe.writing', '0', '1', '0');
    }

    public function testAnExpiredHoldNoLongerCountsAndIsCleanedUp(): void
    {
        $expired = (new AiBudgetReservation())->setUsageId('aiprobe.writing')->setAmount('0.90000000')->setCurrency('USD')->setExpiresAt(new \DateTimeImmutable('-1 second'));
        $expired->save();
        $alive = (new AiBudgetReservation())->setUsageId('aiprobe.other')->setAmount('0.10000000')->setCurrency('USD')->setExpiresAt(new \DateTimeImmutable('+5 minutes'));
        $alive->save();

        $this->ledger->reserve('aiprobe.writing', '0.50000000', '1', '0');

        self::assertNull(AiBudgetReservationQuery::create()->findPk($expired->getId()));
        self::assertNotNull(AiBudgetReservationQuery::create()->findPk($alive->getId()));
    }

    public function testReleasingTwiceIsHarmless(): void
    {
        $hold = $this->ledger->reserve('aiprobe.writing', '0.50000000', '1', '0');

        $this->ledger->release($hold);
        $this->ledger->release($hold);

        self::assertTrue($hold->isReleased());
        self::assertNull(AiBudgetReservationQuery::create()->findPk($hold->id));
    }

    public function testAReservationLocksAConfigurationRowThatNoOtherConnectionCanLock(): void
    {
        $this->ledger->reserve('aiprobe.writing', '0.10000000', '1', '0');

        $manager = Propel::getServiceContainer()->getConnectionManager(DatabaseConfiguration::THELIA_CONNECTION_NAME);
        self::assertInstanceOf(ConnectionManagerSingle::class, $manager);
        $configuration = $manager->getConfiguration();
        $other = new \PDO($configuration['dsn'], $configuration['user'] ?? null, $configuration['password'] ?? null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $other->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $other->beginTransaction();

        try {
            $other->query(\sprintf("SELECT `id` FROM `config` WHERE `name` = '%s' FOR UPDATE", AiBudgetLedger::LOCK_CONFIG_NAME));
            self::fail('A second server reserved while the first one held the lock: the two sums would race.');
        } catch (\PDOException $exception) {
            self::assertStringContainsString('Lock wait timeout exceeded', $exception->getMessage());
        } finally {
            $other->rollBack();
        }
    }

    public function testTheLockRowIsCreatedWhenAShopDeletedIt(): void
    {
        ConfigQuery::create()->filterByName(AiBudgetLedger::LOCK_CONFIG_NAME)->delete();

        $this->ledger->reserve('aiprobe.writing', '0.10000000', '1', '0');

        self::assertSame('0', ConfigQuery::create()->findOneByName(AiBudgetLedger::LOCK_CONFIG_NAME)?->getValue());
    }

    /**
     * @phpstan-impure
     */
    private function holds(string $usageId): int
    {
        return AiBudgetReservationQuery::create()->filterByUsageId($usageId)->count();
    }

    private function logRow(string $usageId, string $cost, \DateTimeImmutable $createdAt): void
    {
        (new AiUsageLog())
            ->setUsageId($usageId)->setModule(explode('.', $usageId)[0])->setKind('completion')
            ->setCost($cost)->setActorType('system')->setActorHash(str_repeat('a', 64))->setStatus('succeeded')
            ->setCreatedAt($createdAt)
            ->save();
    }
}
