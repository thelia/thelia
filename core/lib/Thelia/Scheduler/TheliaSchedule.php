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

namespace Thelia\Scheduler;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * The recurring tasks of the shop, described with the shop rather than in the crontab
 * of the server.
 *
 * Nothing runs until a worker consumes the schedule (`messenger:consume
 * scheduler_thelia`): a shop that keeps its crontab keeps it, and the two must not
 * both run the same task. Each task of the core reads its cron expression from the
 * environment, and an empty expression leaves it out. The currency rates are left
 * out by default, as the update overwrites every rate a merchant set by hand.
 *
 * A module adds its own task to this schedule with `#[AsCronTask(..., schedule:
 * 'thelia')]` or `#[AsPeriodicTask(..., schedule: 'thelia')]` on a command or a
 * service: the tasks declared that way join these ones.
 *
 * The schedule is stateful and locked: a worker restarted after a missed run catches
 * up the last one only, and two workers consuming it never run a task twice.
 * Both hold across servers only when they are shared: LOCK_DSN must name a store all
 * the workers reach (not the default flock), and the application cache a server they
 * all use (THELIA_CACHE_DSN), or each host runs the tasks on its own and a cache
 * clear forgets the last runs.
 */
#[AsSchedule(self::NAME)]
final readonly class TheliaSchedule implements ScheduleProviderInterface
{
    public const NAME = 'thelia';

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private CacheInterface $state,
        private LockFactory $lockFactory,
        #[Autowire(env: 'THELIA_SCHEDULE_SALE_CHECK')]
        private string $saleCheck,
        #[Autowire(env: 'THELIA_SCHEDULE_MAINTENANCE_PURGE')]
        private string $maintenancePurge,
        #[Autowire(env: 'THELIA_SCHEDULE_FAILED_JOBS_PURGE')]
        private string $failedJobsPurge,
        #[Autowire(env: 'THELIA_SCHEDULE_CURRENCY_RATES')]
        private string $currencyRates,
    ) {
    }

    public function getSchedule(): Schedule
    {
        $schedule = (new Schedule())
            ->stateful($this->state)
            ->lock($this->lockFactory->createLock('thelia_schedule'))
            ->processOnlyLastMissedRun(true);

        foreach ($this->tasks() as [$expression, $command]) {
            if ('' !== trim($expression)) {
                $schedule->add(RecurringMessage::cron(trim($expression), new RunCommandMessage($command)));
            }
        }

        return $schedule;
    }

    /**
     * @return list<array{string, string}> cron expression and command of each task
     */
    private function tasks(): array
    {
        return [
            [$this->saleCheck, 'sale:check-activation'],
            [$this->maintenancePurge, 'maintenance:purge'],
            [$this->failedJobsPurge, 'thelia:messenger:purge-failed'],
            [$this->currencyRates, 'currency:update-rates'],
        ];
    }
}
