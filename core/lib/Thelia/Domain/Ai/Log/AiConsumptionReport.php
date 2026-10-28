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

        $sql = \sprintf(
            'SELECT %s AS label, SUM(`kind` <> :external) AS calls, SUM(`cost`) AS cost FROM `ai_usage_log` WHERE `created_at` >= :from AND `created_at` < :to GROUP BY label ORDER BY %s',
            $expression,
            self::BY_DAY === $dimension ? 'label ASC' : 'cost DESC, label ASC',
        );

        $statement = Propel::getConnection(AiUsageLogTableMap::DATABASE_NAME)->prepare($sql);
        $statement->execute([':external' => AiLogKind::External->value, ':from' => $from->format('Y-m-d 00:00:00'), ':to' => $to->modify('+1 day')->format('Y-m-d 00:00:00')]);

        $rows = [];

        while (false !== ($row = $statement->fetch(\PDO::FETCH_ASSOC))) {
            $rows[] = ['label' => (string) $row['label'], 'calls' => (int) $row['calls'], 'cost' => number_format((float) $row['cost'], 8, '.', '')];
        }

        return $rows;
    }
}
