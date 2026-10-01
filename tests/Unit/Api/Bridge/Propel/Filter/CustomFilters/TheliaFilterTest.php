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

namespace Thelia\Tests\Unit\Api\Bridge\Propel\Filter\CustomFilters;

use PHPUnit\Framework\TestCase;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\FilterService;
use Thelia\Api\Bridge\Propel\Filter\CustomFilters\TheliaFilter;

/**
 * `AbstractFilter::apply()` calls `filterProperty()` once per entry of the request, and the
 * selection (`tfilters`) is the same whichever the entry: it must reach the query once.
 */
final class TheliaFilterTest extends TestCase
{
    private const PARAMETERS = ['visible' => 'true', 'itemsPerPage' => '30', 'page' => '1', 'category_depth' => '5'];

    public function testTheSelectionOfTheContextIsAppliedOncePerQuery(): void
    {
        $filterService = $this->createMock(FilterService::class);
        $filterService->expects(self::once())->method('filterTFilterWithContext');
        $filterService->expects(self::never())->method('filterTFilterWithRequest');

        $query = $this->createMock(ModelCriteria::class);
        $this->filter($filterService, new Request())->apply($query, 'Product', null, ['filters' => ['tfilters' => ['category' => [111]]] + self::PARAMETERS]);
    }

    public function testTheSelectionOfTheRequestIsAppliedOncePerQuery(): void
    {
        $request = new Request(['tfilters' => ['category' => [111]]] + self::PARAMETERS, ['isApiRoute' => true]);
        $filterService = $this->createMock(FilterService::class);
        $filterService->expects(self::once())->method('filterTFilterWithRequest');
        $filterService->expects(self::never())->method('filterTFilterWithContext');

        // The parameters of the request are in the context, `tfilters` itself is not an entry of it.
        $this->filter($filterService, $request)->apply($this->createMock(ModelCriteria::class), 'Product', null, ['filters' => self::PARAMETERS]);
    }

    public function testEachQueryGetsTheSelection(): void
    {
        $request = new Request(['tfilters' => ['category' => [111]]], ['isApiRoute' => true]);
        $filterService = $this->createMock(FilterService::class);
        $filterService->expects(self::exactly(2))->method('filterTFilterWithRequest');
        $filter = $this->filter($filterService, $request);

        $filter->apply($this->createMock(ModelCriteria::class), 'Product', null, ['filters' => self::PARAMETERS]);
        $filter->apply($this->createMock(ModelCriteria::class), 'Product', null, ['filters' => self::PARAMETERS]);
    }

    public function testNoSelectionIsNothingToApply(): void
    {
        $filterService = $this->createMock(FilterService::class);
        $filterService->expects(self::never())->method('filterTFilterWithContext');
        $filterService->expects(self::never())->method('filterTFilterWithRequest');

        $this->filter($filterService, new Request())->apply($this->createMock(ModelCriteria::class), 'Product', null, ['filters' => self::PARAMETERS]);
    }

    private function filter(FilterService $filterService, Request $request): TheliaFilter
    {
        $requests = new RequestStack();
        $requests->push($request);

        return new TheliaFilter($filterService, $requests);
    }
}
