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

namespace Thelia\Test\Volume\Measure;

/**
 * What a campaign measured, page by page and job by job, and how two campaigns differ.
 *
 * The count of a page is its fewest queries over the calls: a cache that expires while the page
 * is measured (a minute-long one adds a few queries once a minute) costs one call more, not the
 * steady state of the page. Every call stays in the report, and a page whose calls differ is
 * flagged. Two campaigns on the same dataset must give the same count: a difference is a finding.
 */
final class MeasureReport
{
    /** @var list<array{name: string, area: string, path: string, session: string, status: int, queries: list<int>, steady: int, stable: bool, firstByteMedian: float, totalMedian: float, bytes: int}> */
    private array $pages = [];

    /** @var list<array{name: string, command: string, seconds: float, exitCode: int}> */
    private array $jobs = [];

    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        private readonly array $meta,
    ) {
    }

    /**
     * @param array{name: string, area: string, path: string, session: string} $page
     * @param list<PageMeasure>                                                $runs
     */
    public function addPage(array $page, array $runs): void
    {
        if ([] === $runs) {
            throw new \InvalidArgumentException('A page is measured at least once.');
        }

        $queries = array_map(static fn (PageMeasure $run): int => $run->queries, $runs);
        $this->pages[] = $page + [
            'status' => end($runs)->status,
            'queries' => $queries,
            'steady' => min($queries),
            'stable' => 1 === \count(array_unique($queries)),
            'firstByteMedian' => round(self::median(array_map(static fn (PageMeasure $run): float => $run->firstByteSeconds, $runs)), 4),
            'totalMedian' => round(self::median(array_map(static fn (PageMeasure $run): float => $run->totalSeconds, $runs)), 4),
            'bytes' => end($runs)->bytes,
        ];
    }

    public function addJob(string $name, string $command, float $seconds, int $exitCode): void
    {
        $this->jobs[] = ['name' => $name, 'command' => $command, 'seconds' => round($seconds, 3), 'exitCode' => $exitCode];
    }

    /**
     * @return list<array{name: string, area: string, path: string, session: string, status: int, queries: list<int>, steady: int, stable: bool, firstByteMedian: float, totalMedian: float, bytes: int}>
     */
    public function pages(): array
    {
        return $this->pages;
    }

    /**
     * @return list<array{name: string, command: string, seconds: float, exitCode: int}>
     */
    public function jobs(): array
    {
        return $this->jobs;
    }

    public function toJson(): string
    {
        return json_encode(['meta' => $this->meta, 'pages' => $this->pages, 'jobs' => $this->jobs], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)."\n";
    }

    /**
     * The pages whose query count differs from a previous campaign, or that a campaign measured
     * and the other did not.
     *
     * @param array{pages?: list<array{name: string, steady: int}>} $previous
     *
     * @return list<string>
     */
    public function differencesWith(array $previous): array
    {
        $before = [];
        foreach ($previous['pages'] ?? [] as $page) {
            $before[$page['name']] = $page['steady'];
        }

        $differences = [];
        foreach ($this->pages as $page) {
            if (!isset($before[$page['name']])) {
                $differences[] = \sprintf('%s: not measured by the previous campaign', $page['name']);

                continue;
            }
            if ($before[$page['name']] !== $page['steady']) {
                $differences[] = \sprintf('%s: %d queries before, %d now', $page['name'], $before[$page['name']], $page['steady']);
            }
            unset($before[$page['name']]);
        }
        foreach (array_keys($before) as $name) {
            $differences[] = \sprintf('%s: not measured by this campaign', $name);
        }

        return $differences;
    }

    /**
     * @param list<float> $values
     */
    public static function median(array $values): float
    {
        if ([] === $values) {
            return 0.0;
        }
        sort($values);
        $middle = intdiv(\count($values), 2);

        return 0 === \count($values) % 2 ? ($values[$middle - 1] + $values[$middle]) / 2 : $values[$middle];
    }
}
