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

namespace Thelia\Domain\Report;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\StatementInterface;
use Propel\Runtime\Propel;
use Thelia\Domain\Order\Sql\OrderAmountSql;
use Thelia\Domain\Report\Sales\SalesReportCriteria;
use Thelia\Domain\Report\Sales\StatusScope;
use Thelia\Model\OrderStatusQuery;

/**
 * Runs the raw SQL of the reports: the orders of a report criteria (their status scope and sale
 * units), its dates bound as `:from` and `:to`, and the rounding pivots of the order totals.
 */
final readonly class ReportQuery
{
    private const string SQL_DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ?ConnectionInterface $connection = null)
    {
    }

    /**
     * @param array<string, scalar> $parameters
     *
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, ?SalesReportCriteria $criteria, array $parameters = []): array
    {
        $statement = ($this->connection ?? Propel::getConnection())->prepare($sql);

        if (!$statement instanceof StatementInterface) {
            throw new \RuntimeException('The report statement could not be prepared.');
        }

        if ($criteria instanceof SalesReportCriteria) {
            $statement->bindValue('from', self::sqlDate($criteria->from));
            $statement->bindValue('to', self::sqlDate($criteria->to));

            OrderAmountSql::bindRoundingPivots($statement, $sql);
        }

        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value);
        }

        $statement->execute();

        $rows = [];

        while (false !== ($row = $statement->fetch(\PDO::FETCH_ASSOC))) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * The status scope and the sale units of the criteria, on the alias `o`, without the dates.
     * The ids are written in the statement as integers.
     */
    public static function scopeCondition(SalesReportCriteria $criteria): string
    {
        $condition = '';

        if (StatusScope::Paid === $criteria->scope) {
            $condition .= ' AND '.self::inList('o.status_id', OrderStatusQuery::getPaidStatusIdList());
        }

        if (null !== $criteria->saleUnitIds) {
            $condition .= ' AND '.self::inList('o.sale_unit_id', $criteria->saleUnitIds);
        }

        return $condition;
    }

    /**
     * @param array<mixed> $ids
     */
    public static function inList(string $column, array $ids): string
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        return [] === $ids ? '1 = 0' : $column.' IN ('.implode(', ', $ids).')';
    }

    public static function share(float|int $part, float|int $whole): float
    {
        return 0 == $whole ? 0.0 : $part / $whole;
    }

    public static function sqlDate(\DateTimeInterface $date): string
    {
        return $date->format(self::SQL_DATE_FORMAT);
    }
}
