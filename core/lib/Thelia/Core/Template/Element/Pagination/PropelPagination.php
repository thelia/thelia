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

use Propel\Runtime\Util\PropelModelPager;

class PropelPagination implements PaginationInterface
{
    public function __construct(protected PropelModelPager $pager)
    {
    }

    public function getPropelPager(): PropelModelPager
    {
        return $this->pager;
    }

    public function getPage(): int
    {
        return (int) $this->pager->getPage();
    }

    public function getLastPage(): int
    {
        return (int) $this->pager->getLastPage();
    }

    public function getNbResults(): int
    {
        return (int) $this->pager->getNbResults();
    }
}
