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

namespace Thelia\Tests\Integration\Domain\Ai\Tool;

use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Ai\Log\AiLogPurger;
use Thelia\Model\AiToolApproval;
use Thelia\Model\AiToolApprovalQuery;
use Thelia\Model\AiToolCallLog;
use Thelia\Model\AiToolCallLogQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The maintenance purge of the tool journal: the arguments go after 30 days, the rows with the retention of
 * the log, a proposal nobody answered is marked expired, a decided one is kept as long as the log.
 */
final class AiToolLogPurgerTest extends IntegrationTestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = new \DateTimeImmutable('2026-06-15 12:00:00');
        AiToolCallLogQuery::create()->deleteAll();
        AiToolApprovalQuery::create()->deleteAll();
    }

    protected function tearDown(): void
    {
        ConfigQuery::write('ai_log_retention_days', '400');
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTheArgumentsAreEmptiedWhenTheirDateIsPastAndTheRowStays(): void
    {
        $expired = $this->logRow('2026-05-01 00:00:00', '{"text":"mug"}', '2026-05-31 00:00:00');
        $running = $this->logRow('2026-06-10 00:00:00', '{"text":"cup"}', '2026-07-10 00:00:00');

        $result = (new AiLogPurger())->purge(400, false, $this->now);

        self::assertSame(1, $result->toolArguments);
        self::assertSame(0, $result->toolLogRows);
        self::assertNull(AiToolCallLogQuery::create()->findPk($expired->getId())?->getArguments());
        self::assertSame('succeeded', AiToolCallLogQuery::create()->findPk($expired->getId())?->getStatus());
        self::assertSame('{"text":"cup"}', AiToolCallLogQuery::create()->findPk($running->getId())?->getArguments());
        self::assertSame(0, (new AiLogPurger())->purge(400, false, $this->now)->toolArguments);
    }

    public function testTheRowsOlderThanTheRetentionGo(): void
    {
        $this->logRow('2025-05-01 00:00:00', null, null);
        $recent = $this->logRow('2026-06-01 00:00:00', null, null);

        $result = (new AiLogPurger())->purge(400, false, $this->now);

        self::assertSame(1, $result->toolLogRows);
        self::assertSame([$recent->getId()], AiToolCallLogQuery::create()->select('Id')->find()->toArray());
    }

    public function testAProposalNobodyAnsweredIsMarkedExpiredAndOneStillWaitingStays(): void
    {
        $late = $this->approval('pending', '2026-06-10 00:00:00', '2026-06-14 00:00:00');
        $waiting = $this->approval('pending', '2026-06-10 00:00:00', '2026-06-16 00:00:00');
        $applying = $this->approval('applying', '2026-06-10 00:00:00', '2026-06-14 00:00:00');

        $result = (new AiLogPurger())->purge(400, false, $this->now);

        self::assertSame(1, $result->expiredApprovals);
        self::assertSame('expired', AiToolApprovalQuery::create()->findPk($late->getId())?->getStatus());
        self::assertSame('pending', AiToolApprovalQuery::create()->findPk($waiting->getId())?->getStatus());
        self::assertSame('applying', AiToolApprovalQuery::create()->findPk($applying->getId())?->getStatus());
    }

    public function testADecidedProposalGoesWithTheRetentionOfTheLogButNeverOneThatIsPendingOrApplying(): void
    {
        $old = $this->approval('applied', '2025-05-01 00:00:00', '2025-05-02 00:00:00');
        $oldRejected = $this->approval('rejected', '2025-05-01 00:00:00', '2025-05-02 00:00:00');
        $oldPending = $this->approval('pending', '2025-05-01 00:00:00', '2030-01-01 00:00:00');
        $oldApplying = $this->approval('applying', '2025-05-01 00:00:00', '2025-05-02 00:00:00');
        $recent = $this->approval('applied', '2026-06-01 00:00:00', '2026-06-02 00:00:00');

        $result = (new AiLogPurger())->purge(400, false, $this->now);

        self::assertSame(2, $result->decidedApprovals);
        self::assertNull(AiToolApprovalQuery::create()->findPk($old->getId()));
        self::assertNull(AiToolApprovalQuery::create()->findPk($oldRejected->getId()));
        self::assertNotNull(AiToolApprovalQuery::create()->findPk($oldPending->getId()));
        self::assertNotNull(AiToolApprovalQuery::create()->findPk($oldApplying->getId()));
        self::assertNotNull(AiToolApprovalQuery::create()->findPk($recent->getId()));
    }

    public function testADryRunCountsAndChangesNothing(): void
    {
        $this->logRow('2025-01-01 00:00:00', '{"a":1}', '2025-02-01 00:00:00');
        $this->logRow('2026-06-10 00:00:00', '{"b":2}', '2026-06-14 00:00:00');
        $this->approval('pending', '2026-06-10 00:00:00', '2026-06-14 00:00:00');
        $this->approval('applied', '2025-01-01 00:00:00', '2025-01-02 00:00:00');

        $result = (new AiLogPurger())->purge(400, true, $this->now);

        self::assertSame([1, 1, 1, 1], [$result->toolLogRows, $result->toolArguments, $result->expiredApprovals, $result->decidedApprovals]);
        self::assertSame(2, AiToolCallLogQuery::create()->count());
        self::assertSame(1, AiToolCallLogQuery::create()->filterByArguments(null, \Propel\Runtime\ActiveQuery\Criteria::ISNOTNULL)->filterByCreatedAt('2026-01-01', \Propel\Runtime\ActiveQuery\Criteria::GREATER_THAN)->count());
        self::assertSame(2, AiToolApprovalQuery::create()->count());
        self::assertSame('pending', AiToolApprovalQuery::create()->filterByExpiresAt('2026-06-14 00:00:00')->findOne()?->getStatus());
    }

    public function testTheMaintenancePurgeReportsTheToolJournal(): void
    {
        $this->logRow('2020-01-01 00:00:00', '{"a":1}', '2020-02-01 00:00:00');
        ConfigQuery::write('ai_log_retention_days', '30');

        $event = new MaintenancePurgeEvent(false);
        static::getContainer()->get('event_dispatcher')->dispatch($event, TheliaEvents::MAINTENANCE_PURGE);

        $report = implode("\n", $event->getResults());
        self::assertStringContainsString('AI tool call log (>30 days):</comment> <info>1 deleted', $report);
        self::assertStringContainsString('AI tool call arguments (older than 30 days)', $report);
        self::assertSame(0, AiToolCallLogQuery::create()->count());
    }

    private function logRow(string $createdAt, ?string $arguments, ?string $argumentsExpireAt): AiToolCallLog
    {
        $row = (new AiToolCallLog())
            ->setToolName('shop_read')
            ->setModule('shop')
            ->setEffect('read')
            ->setChannel('gateway')
            ->setStatus('succeeded')
            ->setActorType('visitor')
            ->setActorHash(str_repeat('a', 64))
            ->setArguments($arguments)
            ->setArgumentsExpiresAt($argumentsExpireAt)
            ->setCreatedAt($createdAt);
        $row->save();

        return $row;
    }

    private function approval(string $status, string $createdAt, string $expiresAt): AiToolApproval
    {
        $row = (new AiToolApproval())
            ->setToolName('shop_update')
            ->setModule('shop')
            ->setAclResource('admin.product')
            ->setAclAccess('UPDATE')
            ->setTarget('x:1')
            ->setSummary('x')
            ->setOriginalArguments('{}')
            ->setArguments('{}')
            ->setBeforeState('{}')
            ->setAfterState('{}')
            ->setStatus($status)
            ->setChannel('gateway')
            ->setProposedByType('system')
            ->setProposedByHash(str_repeat('b', 64))
            ->setExpiresAt($expiresAt)
            ->setCreatedAt($createdAt);
        $row->save();

        return $row;
    }
}
