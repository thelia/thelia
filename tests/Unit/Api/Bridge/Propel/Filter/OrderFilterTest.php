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
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Thelia\Api\Bridge\Propel\Filter\OrderFilter;

/**
 * The names a client writes in `order[...]` go through the name converter of the API before the
 * filter looks them up in the properties it was configured with.
 *
 * A property configured with a default direction is ordered that way when the client names it
 * without one, and a direction the client does give always wins.
 */
final class OrderFilterTest extends TestCase
{
    public function testAPropertyIsOrderedUnderTheNameTheClientWroteItWith(): void
    {
        $query = $this->query();
        $query->expects(self::once())->method('orderBy')->with('product.created_at', 'DESC');

        $filter = $this->filter(null, ['createdAt' => null], new CamelCaseToSnakeCaseNameConverter());

        $filter->apply($query, OrderedProduct::class, null, [
            'resource_class' => OrderedProduct::class,
            'filters' => ['order' => ['created_at' => 'desc']],
        ]);
    }

    public function testAPropertyTheFilterWasNotConfiguredForIsNotOrdered(): void
    {
        $query = $this->query();
        $query->expects(self::never())->method('orderBy');

        $filter = $this->filter(null, ['createdAt' => null], new CamelCaseToSnakeCaseNameConverter());

        $filter->apply($query, OrderedProduct::class, null, [
            'resource_class' => OrderedProduct::class,
            'filters' => ['order' => ['updated_at' => 'asc']],
        ]);
    }

    public function testAPropertyNamedWithoutADirectionIsOrderedInItsDefaultDirection(): void
    {
        $query = $this->query();
        $query->expects(self::once())->method('orderBy')->with('product_sorted.position', 'DESC');

        $filter = $this->filter(null, ['position' => ['default_direction' => 'desc', 'fieldPath' => 'product_sorted.position']]);

        $filter->apply($query, OrderedProduct::class, null, [
            'resource_class' => OrderedProduct::class,
            'filters' => ['order' => ['position' => '']],
        ]);
    }

    public function testTheDirectionTheClientGivesWinsOverTheDefaultDirection(): void
    {
        $query = $this->query();
        $query->expects(self::once())->method('orderBy')->with('product_sorted.position', 'ASC');

        $filter = $this->filter(null, ['position' => ['default_direction' => 'desc', 'fieldPath' => 'product_sorted.position']]);

        $filter->apply($query, OrderedProduct::class, null, [
            'resource_class' => OrderedProduct::class,
            'filters' => ['order' => ['position' => 'asc']],
        ]);
    }

    public function testAnOrderParameterThatIsNotAListOfPropertiesIsIgnoredWithoutAWarning(): void
    {
        $query = $this->query();
        $query->expects(self::never())->method('orderBy');

        $filter = $this->filter(null, ['createdAt' => null]);

        set_error_handler(static function (int $level, string $message): never {
            throw new \ErrorException($message, 0, $level);
        });

        try {
            $filter->apply($query, OrderedProduct::class, null, [
                'resource_class' => OrderedProduct::class,
                'filters' => ['order' => 'createdAt'],
            ]);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * An order filter whose translated fields resolve without reading the active languages
     * from the database.
     */
    private function filter(?LoggerInterface $logger, array $properties, ?NameConverterInterface $nameConverter = null): OrderFilter
    {
        return new class($logger, $properties, $nameConverter) extends OrderFilter {
            protected function getLocalizedPropertyQueryPath(ModelCriteria $query, string $property, array $context): string
            {
                return 'localized:'.$property;
            }
        };
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
