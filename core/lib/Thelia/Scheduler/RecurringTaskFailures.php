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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Messenger\ServiceCallMessage;

/**
 * The recurring tasks whose last run failed.
 *
 * A task of the schedule that fails is not set aside with the failed jobs: Messenger
 * only sends there what came from a queue, and a schedule is no queue. Its last
 * failure is kept here, beside the state of the schedule, until a run goes through,
 * or for a month at most: a task taken off the schedule does not stay failed forever.
 * Two workers may finish tasks at the same moment, so the list is changed under a
 * lock.
 */
final readonly class RecurringTaskFailures
{
    private const CACHE_KEY = 'thelia_schedule_failures';

    private const KEPT_FOR_SECONDS = 30 * 86400;

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
        private LockFactory $lockFactory,
        private ClockInterface $clock = new NativeClock(),
    ) {
    }

    /**
     * The name the back office shows for the task carried by $message.
     */
    public static function taskOf(object $message): string
    {
        return match (true) {
            $message instanceof RunCommandMessage => $message->input,
            $message instanceof ServiceCallMessage => $message->getServiceId().'::'.$message->getMethod(),
            default => $message::class,
        };
    }

    public function record(string $task, string $error, ?\DateTimeImmutable $failedAt = null): void
    {
        $failedAt ??= $this->clock->now();

        $this->change(static function (array $failures) use ($task, $error, $failedAt): array {
            $failures[$task] = ['failedAt' => $failedAt->format(\DATE_ATOM), 'error' => $error];

            return $failures;
        });
    }

    public function forget(string $task): void
    {
        if (!isset($this->read()[$task])) {
            return;
        }

        $this->change(static function (array $failures) use ($task): array {
            unset($failures[$task]);

            return $failures;
        });
    }

    /**
     * @param \Closure(array<string, array{failedAt: string, error: string}>): array<string, array{failedAt: string, error: string}> $change
     */
    private function change(\Closure $change): void
    {
        $lock = $this->lockFactory->createLock(self::CACHE_KEY, 10);
        $lock->acquire(true);

        try {
            $this->write($change($this->read()));
        } finally {
            $lock->release();
        }
    }

    /**
     * @return list<RecurringTaskFailure> the newest first
     */
    public function all(): array
    {
        $failures = [];

        foreach ($this->read() as $task => $failure) {
            $failures[] = new RecurringTaskFailure((string) $task, new \DateTimeImmutable($failure['failedAt']), $failure['error']);
        }

        usort($failures, static fn (RecurringTaskFailure $a, RecurringTaskFailure $b): int => $b->failedAt <=> $a->failedAt);

        return $failures;
    }

    /**
     * @return array<string, array{failedAt: string, error: string}>
     */
    private function read(): array
    {
        $value = $this->cache->getItem(self::CACHE_KEY)->get();
        $limit = $this->clock->now()->modify(\sprintf('-%d seconds', self::KEPT_FOR_SECONDS));

        // Each failure keeps for a month from its own date: the entry they share is
        // written again whenever any task fails.
        return array_filter(
            \is_array($value) ? $value : [],
            static fn (array $failure): bool => new \DateTimeImmutable($failure['failedAt']) >= $limit,
        );
    }

    /**
     * @param array<string, array{failedAt: string, error: string}> $failures
     */
    private function write(array $failures): void
    {
        $this->cache->save($this->cache->getItem(self::CACHE_KEY)->set($failures)->expiresAfter(self::KEPT_FOR_SECONDS));
    }
}
