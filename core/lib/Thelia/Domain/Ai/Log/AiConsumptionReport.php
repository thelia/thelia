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

namespace Thelia\Domain\Ai\Log;

use Propel\Runtime\Propel;
use Thelia\Domain\Ai\AiUsageLogStatus;
use Thelia\Domain\Ai\Internal\AiDecimal;
use Thelia\Model\Map\AiUsageLogTableMap;

/**
 * What the AI spent over a period, summed from the usage log and grouped by usage, module, model or day. Amounts
 * are decimal strings in US dollars, as the log keeps them; calls count the model calls (a cost declared for a third-party API adds to the cost only). The period is made of whole days, both included; the
 * grouping is chosen among a fixed list and the dates are bound parameters.
 *
 * @internal
 */
final readonly class AiConsumptionReport
{
    public const string BY_USAGE = 'usage';
    public const string BY_MODULE = 'module';
    public const string BY_MODEL = 'model';
    public const string BY_DAY = 'day';

    public const array DIMENSIONS = [self::BY_USAGE, self::BY_MODULE, self::BY_MODEL, self::BY_DAY];

    private const array EXPRESSIONS = [
        self::BY_USAGE => '`usage_id`',
        self::BY_MODULE => '`module`',
        self::BY_MODEL => "COALESCE(NULLIF(`model`, ''), '-')",
        self::BY_DAY => "DATE_FORMAT(`created_at`, '%Y-%m-%d')",
    ];

    /**
     * @param value-of<self::DIMENSIONS> $dimension
     *
     * @return list<array{label: string, calls: int, cost: string}> the biggest spender first, the days oldest first
     */
    public function by(string $dimension, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $expression = self::EXPRESSIONS[$dimension] ?? throw new \InvalidArgumentException(\sprintf('Group by %s.', implode(', ', self::DIMENSIONS)));

        $rows = $this->aggregate(
            ['label' => $expression],
            '`created_at` >= :from AND `created_at` < :to',
            [':from' => $from->format('Y-m-d 00:00:00'), ':to' => $to->modify('+1 day')->format('Y-m-d 00:00:00')],
            self::BY_DAY === $dimension ? 'label ASC' : 'cost DESC, label ASC',
        );

        return array_map(static fn (array $row): array => ['label' => (string) $row['label'], 'calls' => $row['calls'], 'cost' => $row['cost']], $rows);
    }

    /**
     * The figures of each usage in each calendar month, the newest month first. Every filter is optional.
     *
     * @param string|null $month     one month, written YYYY-MM
     * @param string|null $fromMonth first month included, written YYYY-MM
     * @param string|null $toMonth   last month included, written YYYY-MM
     *
     * @return list<array{month: string, usage_id: string, module: string, calls: int, failures: int, external_calls: int, input_tokens: int, output_tokens: int, cost: string}>
     */
    public function byMonthAndUsage(?string $month = null, ?string $fromMonth = null, ?string $toMonth = null, ?string $usageId = null, ?string $module = null): array
    {
        $monthOfRow = "DATE_FORMAT(`created_at`, '%Y-%m')";
        $conditions = [];
        $parameters = [];

        $filters = [
            [$monthOfRow, '=', $month],
            [$monthOfRow, '>=', $fromMonth],
            [$monthOfRow, '<=', $toMonth],
            ['`usage_id`', '=', $usageId],
            ['`module`', '=', $module],
        ];

        foreach ($filters as $position => [$expression, $operator, $value]) {
            if (null !== $value && '' !== $value) {
                $conditions[] = \sprintf('%s %s :filter%d', $expression, $operator, $position);
                $parameters[':filter'.$position] = $value;
            }
        }

        $rows = $this->aggregate(
            ['month' => $monthOfRow, 'usage_id' => '`usage_id`', 'module' => '`module`'],
            [] === $conditions ? '1 = 1' : implode(' AND ', $conditions),
            $parameters,
            'month DESC, `usage_id` ASC',
        );

        return array_map(static fn (array $row): array => ['month' => (string) $row['month'], 'usage_id' => (string) $row['usage_id'], 'module' => (string) $row['module']] + array_diff_key($row, ['month' => 0, 'usage_id' => 0, 'module' => 0]), $rows);
    }

    /**
     * The one aggregation of the log: the figures of every group, summed by the database. The cost is read back as
     * the decimal string the database sums, never through a float.
     *
     * @param array<string, string> $groups     alias => SQL expression, fixed by the callers, never user input
     * @param array<string, string> $parameters bound parameters of $where
     *
     * @return list<array<string, mixed>> the group aliases as selected, then calls, failures, external_calls, input_tokens, output_tokens, cost
     */
    private function aggregate(array $groups, string $where, array $parameters, string $order): array
    {
        $selected = [];

        foreach ($groups as $alias => $expression) {
            $selected[] = \sprintf('%s AS %s', $expression, $alias);
        }

        $sql = \sprintf(
            'SELECT %s, SUM(`kind` <> :external_calls) AS calls, SUM(`kind` <> :external_failures AND `status` <> :succeeded) AS failures, SUM(`kind` = :external_kind) AS external_calls, SUM(`input_tokens`) AS input_tokens, SUM(`output_tokens`) AS output_tokens, SUM(`cost`) AS cost FROM `ai_usage_log` WHERE %s GROUP BY %s ORDER BY %s',
            implode(', ', $selected),
            $where,
            implode(', ', array_keys($groups)),
            $order,
        );

        $statement = Propel::getConnection(AiUsageLogTableMap::DATABASE_NAME)->prepare($sql);
        $statement->execute([':external_calls' => AiLogKind::External->value, ':external_failures' => AiLogKind::External->value, ':external_kind' => AiLogKind::External->value, ':succeeded' => AiUsageLogStatus::Succeeded->value] + $parameters);

        $rows = [];

        while (false !== ($row = $statement->fetch(\PDO::FETCH_ASSOC))) {
            $rows[] = array_intersect_key($row, $groups) + [
                'calls' => (int) $row['calls'],
                'failures' => (int) $row['failures'],
                'external_calls' => (int) $row['external_calls'],
                'input_tokens' => (int) $row['input_tokens'],
                'output_tokens' => (int) $row['output_tokens'],
                'cost' => AiDecimal::format(AiDecimal::parse((string) ($row['cost'] ?? '0'))),
            ];
        }

        return $rows;
    }
}
