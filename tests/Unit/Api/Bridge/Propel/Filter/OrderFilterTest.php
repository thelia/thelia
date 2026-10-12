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

namespace Thelia\Tests\Unit\Api\Bridge\Propel\Filter;

use PHPUnit\Framework\TestCase;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;

/**
 * The names a client writes in `order[...]` go through the name converter of the API before the
 * filter looks them up in the properties it was configured with.
 */
final class OrderFilterTest extends TestCase
{
    public function testAPropertyIsOrderedUnderTheNameTheClientWroteItWith(): void
    {
        $query = $this->query();
        $query->expects(self::once())->method('orderBy')->with('product.created_at', 'DESC');

        $filter = new OrderFilter(null, ['createdAt' => null], new CamelCaseToSnakeCaseNameConverter());

        $filter->apply($query, OrderedProduct::class, null, [
            'resource_class' => OrderedProduct::class,
            'filters' => ['order' => ['created_at' => 'desc']],
        ]);
    }

    public function testAPropertyTheFilterWasNotConfiguredForIsNotOrdered(): void
    {
        $query = $this->query();
        $query->expects(self::never())->method('orderBy');

        $filter = new OrderFilter(null, ['createdAt' => null], new CamelCaseToSnakeCaseNameConverter());

        $filter->apply($query, OrderedProduct::class, null, [
            'resource_class' => OrderedProduct::class,
            'filters' => ['order' => ['updated_at' => 'asc']],
        ]);
    }

    private function query(): ModelCriteria
    {
        $tableMap = $this->createStub(TableMap::class);
        $tableMap->method('getName')->willReturn('product');

        $query = $this->createMock(ModelCriteria::class);
        $query->method('getTableMap')->willReturn($tableMap);

        return $query;
    }
}

final class OrderedProduct
{
    public ?\DateTimeInterface $createdAt = null;

    public ?\DateTimeInterface $updatedAt = null;
}
