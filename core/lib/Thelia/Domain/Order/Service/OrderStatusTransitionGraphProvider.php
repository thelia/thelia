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

namespace Thelia\Domain\Order\Service;

use Thelia\Model\OrderStatusTransitionQuery;

/**
 * Builds the transition graph from the database once per request.
 */
final class OrderStatusTransitionGraphProvider
{
    private ?OrderStatusTransitionGraph $graph = null;

    public function __construct(
        private readonly OrderStatusCatalog $catalog,
    ) {
    }

    public function get(): OrderStatusTransitionGraph
    {
        if (null !== $this->graph) {
            return $this->graph;
        }

        $targetsByFrom = [];

        foreach (OrderStatusTransitionQuery::create()->find() as $transition) {
            $targetsByFrom[$transition->getFromStatusId()][] = $transition->getToStatusId();
        }

        $equivalentIds = [];

        foreach (array_keys($this->catalog->all()) as $statusId) {
            $equivalentIds[$statusId] = $this->catalog->equivalentIds($statusId);
        }

        return $this->graph = new OrderStatusTransitionGraph($targetsByFrom, $equivalentIds);
    }

    public function reset(): void
    {
        $this->graph = null;
        $this->catalog->reset();
    }
}
