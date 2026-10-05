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

namespace Thelia\Tests\Unit\Scheduler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\RecurringMessage;
use Thelia\Scheduler\TheliaSchedule;

final class TheliaScheduleTest extends TestCase
{
    public function testTheTasksOfTheCoreAreScheduledWithTheirExpression(): void
    {
        $schedule = $this->schedule(saleCheck: '* * * * *', maintenancePurge: '30 3 * * *', failedJobsPurge: '0 4 * * *', currencyRates: '');

        self::assertSame(
            ['sale:check-activation' => '* * * * *', 'maintenance:purge' => '30 3 * * *', 'thelia:messenger:purge-failed' => '0 4 * * *'],
            self::commands($schedule->getRecurringMessages()),
        );
    }

    /**
     * Left out by default: the update overwrites every rate, those a merchant set by
     * hand included.
     */
    public function testTheCurrencyRatesRunOnlyOnceAnExpressionIsGiven(): void
    {
        $schedule = $this->schedule(saleCheck: '', maintenancePurge: '', failedJobsPurge: '', currencyRates: '0 6 * * *');

        self::assertSame(['currency:update-rates' => '0 6 * * *'], self::commands($schedule->getRecurringMessages()));
    }

    public function testAnEmptyExpressionLeavesTheTaskOut(): void
    {
        $schedule = $this->schedule(saleCheck: '  ', maintenancePurge: '', failedJobsPurge: '', currencyRates: '');

        self::assertSame([], $schedule->getRecurringMessages());
    }

    /**
     * Two workers consuming the schedule never run a task twice, and a worker coming
     * back after a stop catches up the last missed run only.
     */
    public function testTheScheduleIsLockedAndRemembersItsLastRun(): void
    {
        $schedule = $this->schedule(saleCheck: '* * * * *', maintenancePurge: '', failedJobsPurge: '', currencyRates: '');

        self::assertNotNull($schedule->getLock());
        self::assertNotNull($schedule->getState());
        self::assertTrue($schedule->shouldProcessOnlyLastMissedRun());
    }

    private function schedule(string $saleCheck, string $maintenancePurge, string $failedJobsPurge, string $currencyRates): \Symfony\Component\Scheduler\Schedule
    {
        return (new TheliaSchedule(new ArrayAdapter(), new LockFactory(new InMemoryStore()), $saleCheck, $maintenancePurge, $failedJobsPurge, $currencyRates))->getSchedule();
    }

    /**
     * @param array<RecurringMessage> $recurringMessages
     *
     * @return array<string, string> command => cron expression
     */
    private static function commands(array $recurringMessages): array
    {
        $commands = [];

        foreach ($recurringMessages as $recurringMessage) {
            foreach ($recurringMessage->getMessages(new \Symfony\Component\Scheduler\Generator\MessageContext('thelia', 'id', $recurringMessage->getTrigger(), new \DateTimeImmutable())) as $message) {
                \assert($message instanceof RunCommandMessage);
                $commands[$message->input] = (string) $recurringMessage->getTrigger();
            }
        }

        return $commands;
    }
}
