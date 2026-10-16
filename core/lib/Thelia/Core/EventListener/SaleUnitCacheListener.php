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

namespace Thelia\Core\EventListener;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Event\SaleUnitEvent;
use Thelia\Model\ModuleConfigQuery;

/**
 * Keeps the shared answer to "does the shop sell from sale units" in step with the
 * table: any write or delete of a sale unit, whichever way it comes (back office,
 * admin API, a module), goes through the model and drops the entry.
 *
 * A bulk update or delete issued on the query, or a row changed in SQL, bypasses the
 * model: a cache clear is what puts the entry right after such a change.
 */
readonly class SaleUnitCacheListener
{
    public function __construct(private SaleUnitActivityChecker $activityChecker)
    {
    }

    #[AsEventListener(event: SaleUnitEvent::POST_SAVE)]
    #[AsEventListener(event: SaleUnitEvent::POST_DELETE)]
    public function forgetTheActivity(): void
    {
        $this->activityChecker->forget();
    }

    /**
     * The settings of a unit leave with it through a foreign key, without a model event
     * of their own: what was read for it is dropped here.
     */
    #[AsEventListener(event: SaleUnitEvent::POST_DELETE)]
    public function forgetTheConfigurationOfTheUnit(): void
    {
        ConfigQuery::forgetSaleUnitValues();
        ModuleConfigQuery::resetConfigCache();
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    #[AsEventListener(event: ConsoleEvents::TERMINATE)]
    public function forgetTheActivityAfterTheRequest(): void
    {
        $this->activityChecker->forgetAfterTheRequest();
    }
}
