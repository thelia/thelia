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
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Scheduler\Messenger\ServiceCallMessage;

/**
 * The recurring tasks whose last run failed.
 *
 * A task of the schedule that fails is not set aside with the failed jobs: Messenger
 * only sends there what came from a queue, and a schedule is no queue. Its last
 * failure is kept here, beside the state of the schedule, until a run goes through.
 */
final readonly class RecurringTaskFailures
{
    private const CACHE_KEY = 'thelia_schedule_failures';

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
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

    public function record(string $task, string $error, \DateTimeImmutable $failedAt = new \DateTimeImmutable()): void
    {
        $failures = $this->read();
        $failures[$task] = ['failedAt' => $failedAt->format(\DATE_ATOM), 'error' => $error];

        $this->write($failures);
    }

    public function forget(string $task): void
    {
        $failures = $this->read();

        if (!isset($failures[$task])) {
            return;
        }

        unset($failures[$task]);
        $this->write($failures);
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

        return \is_array($value) ? $value : [];
    }

    /**
     * @param array<string, array{failedAt: string, error: string}> $failures
     */
    private function write(array $failures): void
    {
        $this->cache->save($this->cache->getItem(self::CACHE_KEY)->set($failures));
    }
}
