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

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Ai\Log\AiLogPurger;
use Thelia\Domain\Ai\Service\AiSettings;
use Thelia\Model\AiBudgetReservation;
use Thelia\Model\AiBudgetReservationQuery;
use Thelia\Model\AiUsageLog;
use Thelia\Model\AiUsageLogQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The maintenance purge of the AI layer: old log rows, expired prompts, abandoned reservations.
 */
final class AiLogPurgerTest extends IntegrationTestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = new \DateTimeImmutable('2026-06-15 12:00:00');
        AiUsageLogQuery::create()->deleteAll();
        AiBudgetReservationQuery::create()->deleteAll();
    }

    protected function tearDown(): void
    {
        ConfigQuery::write('ai_log_retention_days', '400');
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testOnlyTheRowsOlderThanTheRetentionGo(): void
    {
        $this->row('2025-05-01 00:00:00');
        $this->row('2025-05-10 00:00:00');
        $recent = $this->row('2026-06-01 00:00:00');

        $result = (new AiLogPurger())->purge(400, false, $this->now);

        self::assertSame(2, $result->logRows);
        self::assertSame([$recent->getId()], AiUsageLogQuery::create()->select('Id')->find()->toArray());
    }

    public function testARowAtTheEdgeOfTheRetentionStays(): void
    {
        $edge = $this->row('2026-05-16 12:00:00');
        $this->row('2026-05-16 11:59:59');

        $result = (new AiLogPurger())->purge(30, false, $this->now);

        self::assertSame(1, $result->logRows);
        self::assertSame([$edge->getId()], AiUsageLogQuery::create()->select('Id')->find()->toArray());
    }

    public function testAnExpiredPromptIsEmptiedAndTheRowStays(): void
    {
        $expired = $this->row('2026-06-10 00:00:00', 'what the visitor typed', '2026-06-14 00:00:00');
        $running = $this->row('2026-06-10 00:00:00', 'still kept', '2026-06-20 00:00:00');
        $noExpiry = $this->row('2026-06-10 00:00:00', 'no date', null);

        $result = (new AiLogPurger())->purge(400, false, $this->now);

        self::assertSame(1, $result->storedPrompts);
        self::assertSame(0, $result->logRows);
        self::assertNull(AiUsageLogQuery::create()->findPk($expired->getId())?->getPrompt());
        self::assertSame('still kept', AiUsageLogQuery::create()->findPk($running->getId())?->getPrompt());
        self::assertSame('no date', AiUsageLogQuery::create()->findPk($noExpiry->getId())?->getPrompt());
    }

    public function testAnEmptiedPromptIsNotCountedAgain(): void
    {
        $this->row('2026-06-10 00:00:00', 'what the visitor typed', '2026-06-14 00:00:00');
        $purger = new AiLogPurger();

        $purger->purge(400, false, $this->now);

        self::assertSame(0, $purger->purge(400, false, $this->now)->storedPrompts);
        self::assertSame(0, $purger->purge(400, true, $this->now)->storedPrompts);
    }

    public function testOnlyTheExpiredReservationsGo(): void
    {
        $this->reservation('2026-06-15 11:00:00');
        $live = $this->reservation('2026-06-15 12:10:00');

        $result = (new AiLogPurger())->purge(400, false, $this->now);

        self::assertSame(1, $result->expiredReservations);
        self::assertSame([$live->getId()], AiBudgetReservationQuery::create()->select('Id')->find()->toArray());
    }

    public function testADryRunCountsAndChangesNothing(): void
    {
        $this->row('2025-01-01 00:00:00', 'a prompt', '2025-02-01 00:00:00');
        $this->row('2026-06-10 00:00:00', 'another', '2026-06-14 00:00:00');
        $this->reservation('2026-06-15 11:00:00');

        $result = (new AiLogPurger())->purge(400, true, $this->now);

        self::assertSame([1, 1, 1], [$result->logRows, $result->storedPrompts, $result->expiredReservations]);
        self::assertSame(2, AiUsageLogQuery::create()->count());
        self::assertSame(1, AiUsageLogQuery::create()->filterByPrompt(null, Criteria::ISNOTNULL)->filterByCreatedAt('2026-01-01', Criteria::GREATER_THAN)->count());
        self::assertSame(1, AiBudgetReservationQuery::create()->count());
    }

    public function testMoreRowsThanOneBatchAllGo(): void
    {
        $this->row('2025-01-01 00:00:00');
        $connection = Propel::getConnection('TheliaMain');
        $columns = '`usage_id`, `module`, `kind`, `cost`, `currency`, `actor_type`, `actor_hash`, `status`, `created_at`, `updated_at`';

        for ($doubling = 0; $doubling < 11; ++$doubling) {
            $connection->exec(\sprintf('INSERT INTO `ai_usage_log` (%s) SELECT %s FROM `ai_usage_log`', $columns, $columns));
        }

        self::assertSame(2048, AiUsageLogQuery::create()->count());

        $result = (new AiLogPurger())->purge(400, false, $this->now);

        self::assertSame(2048, $result->logRows);
        self::assertSame(0, AiUsageLogQuery::create()->count());
    }

    public function testTheMaintenancePurgeAppliesTheRetentionOfTheShop(): void
    {
        $recent = $this->row('2026-01-01 00:00:00');
        $this->row('2020-01-01 00:00:00');
        $this->reservation('2020-01-01 00:00:00');

        ConfigQuery::write('ai_log_retention_days', '36500');
        $event = new MaintenancePurgeEvent(true);
        static::getContainer()->get('event_dispatcher')->dispatch($event, TheliaEvents::MAINTENANCE_PURGE);

        $lines = array_values(array_filter($event->getResults(), static fn (string $line): bool => str_contains($line, 'AI ')));
        self::assertCount(7, $lines);
        self::assertStringContainsString('AI usage log (>36500 days):</comment> <info>0 to delete', $lines[0]);
        self::assertStringContainsString('1 to delete', $lines[2]);

        ConfigQuery::write('ai_log_retention_days', '30');
        $event = new MaintenancePurgeEvent(false);
        static::getContainer()->get('event_dispatcher')->dispatch($event, TheliaEvents::MAINTENANCE_PURGE);

        self::assertSame(0, AiUsageLogQuery::create()->count());
        self::assertSame(0, AiBudgetReservationQuery::create()->count());
        self::assertNull(AiUsageLogQuery::create()->findPk($recent->getId()));
        self::assertStringContainsString('AI usage log (>30 days):</comment> <info>2 deleted', implode("\n", $event->getResults()));
    }

    public function testTheRetentionDefaultsToFourHundredDaysWhenTheSettingIsNotAPositiveNumber(): void
    {
        $settings = new AiSettings();

        foreach (['400' => 400, '30' => 30, '0' => 400, '-5' => 400, 'a year' => 400, '' => 400] as $stored => $expected) {
            ConfigQuery::write('ai_log_retention_days', (string) $stored);
            self::assertSame($expected, $settings->logRetentionDays(), 'stored: '.$stored);
        }
    }

    private function row(string $createdAt, ?string $prompt = null, ?string $promptExpiresAt = null): AiUsageLog
    {
        $row = (new AiUsageLog())
            ->setUsageId('aiprobe.pages')->setModule('aiprobe')->setKind('completion')->setCost('0.10000000')
            ->setActorType('system')->setActorHash(str_repeat('a', 64))->setStatus('succeeded')
            ->setPrompt($prompt)
            ->setPromptExpiresAt(null === $promptExpiresAt ? null : new \DateTimeImmutable($promptExpiresAt))
            ->setCreatedAt(new \DateTimeImmutable($createdAt));
        $row->save();

        return $row;
    }

    private function reservation(string $expiresAt): AiBudgetReservation
    {
        $reservation = (new AiBudgetReservation())->setUsageId('aiprobe.pages')->setAmount('0.5')->setExpiresAt(new \DateTimeImmutable($expiresAt));
        $reservation->save();

        return $reservation;
    }
}
