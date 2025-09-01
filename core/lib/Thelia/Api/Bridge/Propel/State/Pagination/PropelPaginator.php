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

namespace Thelia\Api\Bridge\Propel\State\Pagination;

use ApiPlatform\State\Pagination\PaginatorInterface;
use Propel\Runtime\Util\PropelModelPager;

class PropelPaginator extends \ArrayIterator implements PaginatorInterface
{
    public function __construct(private readonly PropelModelPager $pager, array $resources)
    {
        parent::__construct($resources);
    }

    public function getLastPage(): float
    {
        return (float) $this->pager->getLastPage();
    }

    public function getTotalItems(): float
    {
        return (float) $this->pager->getNbResults();
    }

    public function getCurrentPage(): float
    {
        return (float) $this->pager->getPage();
    }

    public function getItemsPerPage(): float
    {
        return (float) $this->pager->getMaxPerPage();
    }
}
