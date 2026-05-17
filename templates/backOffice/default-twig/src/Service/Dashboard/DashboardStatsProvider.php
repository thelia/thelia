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

namespace BackOfficeDefaultTwigBundle\Service\Dashboard;

use BackOfficeDefaultTwigBundle\DTO\Dashboard\DashboardStats;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Lang;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\ProductSaleElementsQuery;

final readonly class DashboardStatsProvider
{
    private const PENDING_STATUS_CODES = [
        OrderStatus::CODE_NOT_PAID,
        OrderStatus::CODE_PAID,
        OrderStatus::CODE_PROCESSING,
    ];

    public function compute(int $lowStockThreshold, int $recentOrdersLimit): DashboardStats
    {
        $locale = Lang::getDefaultLanguage()->getLocale();

        return new DashboardStats(
            customersCount: $this->countCustomers(),
            pendingOrdersCount: $this->countPendingOrders(),
            recentOrders: $this->findRecentOrders($recentOrdersLimit),
            lowStockProducts: $this->findLowStockProducts($lowStockThreshold, $locale),
            lowStockThreshold: $lowStockThreshold,
            locale: $locale,
        );
    }

    private function countCustomers(): int
    {
        return CustomerQuery::create()->count();
    }

    private function countPendingOrders(): int
    {
        return OrderQuery::create()
            ->useOrderStatusQuery()
                ->filterByCode(self::PENDING_STATUS_CODES, \Propel\Runtime\ActiveQuery\Criteria::IN)
            ->endUse()
            ->count();
    }

    /** @return list<\Thelia\Model\Order> */
    private function findRecentOrders(int $limit): array
    {
        return array_values(iterator_to_array(
            OrderQuery::create()
                ->orderByCreatedAt(\Propel\Runtime\ActiveQuery\Criteria::DESC)
                ->limit($limit)
                ->find(),
        ));
    }

    /** @return list<\Thelia\Model\ProductSaleElements> */
    private function findLowStockProducts(int $threshold, string $locale): array
    {
        $pseList = array_values(iterator_to_array(
            ProductSaleElementsQuery::create()
                ->filterByQuantity($threshold, \Propel\Runtime\ActiveQuery\Criteria::LESS_EQUAL)
                ->orderByQuantity(\Propel\Runtime\ActiveQuery\Criteria::ASC)
                ->limit(5)
                ->find(),
        ));

        foreach ($pseList as $pse) {
            $pse->getProduct()?->setLocale($locale);
        }

        return $pseList;
    }
}
