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

namespace BackOfficeDefaultTwigBundle\DTO\Dashboard;

use Thelia\Model\Order;
use Thelia\Model\ProductSaleElements;

final readonly class DashboardStats
{
    /** @param list<Order> $recentOrders @param list<ProductSaleElements> $lowStockProducts */
    public function __construct(
        public int $customersCount,
        public int $pendingOrdersCount,
        public array $recentOrders,
        public array $lowStockProducts,
        public int $lowStockThreshold,
        public string $locale,
    ) {
    }
}
