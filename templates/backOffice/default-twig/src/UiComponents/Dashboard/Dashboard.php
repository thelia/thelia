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

namespace BackOfficeDefaultTwigBundle\UiComponents\Dashboard;

use BackOfficeDefaultTwigBundle\DTO\Dashboard\DashboardStats;
use BackOfficeDefaultTwigBundle\Service\Dashboard\DashboardStatsProvider;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'BoDashboard', template: '@BackOfficeDefaultTwig/components/Dashboard/Dashboard.html.twig')]
final class Dashboard
{
    public int $lowStockThreshold = 5;

    public int $recentOrdersLimit = 5;

    public function __construct(
        private readonly DashboardStatsProvider $statsProvider,
    ) {
    }

    public function getStats(): DashboardStats
    {
        return $this->statsProvider->compute($this->lowStockThreshold, $this->recentOrdersLimit);
    }
}
