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

namespace Thelia\Core\Event\Administrator;

use Thelia\Core\Event\ActionEvent;

/**
 * Replaces the sale units an administrator is restricted to.
 *
 * An empty list lifts the restriction: the administrator reaches every sale unit
 * again, and the national orders.
 */
class AdministratorSaleUnitsEvent extends ActionEvent
{
    /** @var list<int> */
    private array $saleUnitIds;

    /**
     * @param list<int> $saleUnitIds
     */
    public function __construct(private readonly int $adminId, array $saleUnitIds = [])
    {
        $this->setSaleUnitIds($saleUnitIds);
    }

    public function getAdminId(): int
    {
        return $this->adminId;
    }

    /**
     * @return list<int>
     */
    public function getSaleUnitIds(): array
    {
        return $this->saleUnitIds;
    }

    /**
     * @param list<int> $saleUnitIds
     */
    public function setSaleUnitIds(array $saleUnitIds): static
    {
        $this->saleUnitIds = array_values(array_unique(array_map('intval', $saleUnitIds)));

        return $this;
    }
}
