<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Template\Element\Pagination;

class ArrayPagination implements PaginationInterface
{
    private int $page;

    private int $totalPageCount;

    private int $totalResultCount;

    public function __construct(int $page, int $pageSize, int $totalResultCount)
    {
        $this->page = $page;
        $this->totalPageCount = ceil($totalResultCount / $pageSize);
        $this->totalResultCount = $totalResultCount;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getLastPage(): int
    {
        return $this->totalPageCount;
    }

    public function getNbResults(): int
    {
        return $this->totalResultCount;
    }
}
