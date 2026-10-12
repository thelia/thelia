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

namespace Thelia\Tests\Unit\Api\Bridge\Propel\Service;

use ApiPlatform\Validator\ValidatorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Attribute\Column;
use Thelia\Api\Bridge\Propel\Attribute\CompositeIdentifiers;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Bridge\Propel\Service\TypeCasterService;

/**
 * A resource identified by several relations names them in its CompositeIdentifiers attribute.
 * The item provider and the persist processor read them from the transformer to find its row,
 * filtering on each identifier through the query filter its Column attribute names, or else
 * through filterBy<Identifier>Id() or filterBy<Identifier>().
 */
final class ApiResourcePropelTransformerCompositeIdentifiersTest extends TestCase
{
    public function testTheIdentifiersOfAResourceAreTheRelationsItsAttributeNames(): void
    {
        self::assertSame(
            ['product', 'category'],
            $this->transformer()->getResourceCompositeIdentifierValues(new \ReflectionClass(ProductInCategory::class), 'keys'),
        );
    }

    public function testAResourceWithoutTheAttributeHasNoCompositeIdentifiers(): void
    {
        self::assertSame(
            [],
            $this->transformer()->getResourceCompositeIdentifierValues(new \ReflectionClass(\stdClass::class), 'keys'),
        );
    }

    public function testTheColumnAttributesOfTheIdentifiersAreReadOneByOne(): void
    {
        self::assertSame(
            [
                'product' => ['propelQueryFilter' => 'filterByProductRef'],
                'category' => ['propelQueryFilter' => 'filterByCategoryRef'],
            ],
            $this->transformer()->getColumnValues(new \ReflectionClass(ProductInCategory::class), ['product', 'category', 'position']),
        );
    }

    public function testEachIdentifierIsFilteredOnThroughTheFilterItsColumnNamesOrItsConventionalFilter(): void
    {
        $query = new class {
            public array $filters = [];

            public function filterByProductRef(string $ref): self
            {
                $this->filters[] = ['filterByProductRef', $ref];

                return $this;
            }

            public function filterByProductId(int $id): self
            {
                $this->filters[] = ['filterByProductId', $id];

                return $this;
            }

            public function filterByCategoryId(int $id): self
            {
                $this->filters[] = ['filterByCategoryId', $id];

                return $this;
            }

            public function filterByCategory(int $id): self
            {
                $this->filters[] = ['filterByCategory', $id];

                return $this;
            }

            public function filterByPosition(int $position): self
            {
                $this->filters[] = ['filterByPosition', $position];

                return $this;
            }
        };

        $this->transformer()->queryFilterById(
            ['product' => 'PRD-1', 'category' => 8, 'position' => 2, 'unknown' => 3],
            $query,
            ['product' => ['propelQueryFilter' => 'filterByProductRef']],
        );

        self::assertSame(
            [['filterByProductRef', 'PRD-1'], ['filterByCategoryId', 8], ['filterByPosition', 2]],
            $query->filters,
        );
    }

    private function transformer(): ApiResourcePropelTransformerService
    {
        return new ApiResourcePropelTransformerService(
            [],
            $this->createStub(ValidatorInterface::class),
            $this->createStub(EventDispatcherInterface::class),
            $this->createStub(TypeCasterService::class),
        );
    }
}

#[CompositeIdentifiers(['product', 'category'])]
final class ProductInCategory
{
    #[Column(propelQueryFilter: 'filterByProductRef')]
    public ?string $product = null;

    #[Column(propelQueryFilter: 'filterByCategoryRef')]
    public ?int $category = null;

    public int $position = 0;
}
